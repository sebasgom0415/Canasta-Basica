<?php

namespace App\Services;

use App\Models\Indicador;
use App\Models\Observacion;
use Illuminate\Support\Facades\Cache;

/**
 * Cálculos sobre las series guardadas. Todas las variaciones se derivan de
 * los niveles (índices o montos) para que sean consistentes entre fuentes:
 *   mensual    = nivel(t) / nivel(t-1)  - 1
 *   interanual = nivel(t) / nivel(t-12) - 1
 *   acumulada  = nivel(t) / nivel(diciembre anterior) - 1
 * Los meses se manejan como claves 'Y-m'.
 */
class InflacionService
{
    private const CACHE_VERSION = 'canasta:version';

    private array $series = [];

    public static function limpiarCache(): void
    {
        Cache::forever(self::CACHE_VERSION, (string) microtime(true));
    }

    // ─── Endpoints ──────────────────────────────────────────────────────

    public function resumen(): array
    {
        return $this->cache('resumen', function () {
            $ipc = $this->serie('89635');
            if (!$ipc) return ['disponible' => false];

            $mes  = array_key_last($ipc);
            $prev = self::mesMenos($mes, 1);
            $divs = $this->divisiones();

            return [
                'disponible'  => true,
                'mes'         => $mes,
                'meta'        => config('bccr.meta'),
                'ipc'         => [
                    'nivel'           => $ipc[$mes],
                    'mensual'         => self::variacion($ipc, $mes, 1),
                    'interanual'      => self::variacion($ipc, $mes, 12),
                    'interanual_prev' => self::variacion($ipc, $prev, 12),
                    'acumulada'       => self::acumulada($ipc, $mes),
                ],
                'subyacente'  => $this->ultimoInteranual('90267'),
                'salario'     => $this->ultimoInteranual('90930'),
                'expectativa' => $this->ultimoValor('84990'),
                'tipo_cambio' => $this->ultimoValor('318'),
                'canasta'     => $this->resumenCanasta(),
                'divisiones'  => [
                    'mes'   => $divs['mes'],
                    'alza'  => $divs['filas'][0] ?? null,
                    'baja'  => $divs['filas'][count($divs['filas']) - 1] ?? null,
                ],
            ];
        });
    }

    /** Serie histórica del IPC general frente a subyacente, expectativas y meta. */
    public function ipc(): array
    {
        return $this->cache('ipc', function () {
            $ipc   = $this->serie('89635');
            $sub   = $this->serie('90267');
            $exp   = $this->serie('84990');
            $meses = array_values(array_filter(array_keys($ipc), fn ($m) => isset($ipc[self::mesMenos($m, 12)])));

            return [
                'meses'       => $meses,
                'interanual'  => array_map(fn ($m) => self::variacion($ipc, $m, 12), $meses),
                'mensual'     => array_map(fn ($m) => self::variacion($ipc, $m, 1), $meses),
                'subyacente'  => array_map(fn ($m) => self::variacion($sub, $m, 12), $meses),
                'expectativa' => array_map(fn ($m) => isset($exp[$m]) ? round($exp[$m], 2) : null, $meses),
                'nivel'       => array_map(fn ($m) => round($ipc[$m], 3), $meses),
                'meta'        => config('bccr.meta'),
            ];
        });
    }

    /**
     * Variación por división para un mes, ordenada de mayor a menor alza
     * interanual, más un mapa de calor de los 12 meses previos.
     */
    public function divisiones(?string $mes = null): array
    {
        return $this->cache('divisiones:' . ($mes ?? 'ultimo'), function () use ($mes) {
            $indicadores = $this->indicadores('division');
            $disponibles = $this->mesesComunes($indicadores->pluck('codigo')->all());
            if (!$disponibles) return ['mes' => null, 'meses' => [], 'filas' => [], 'calor' => null];

            $mes = in_array($mes, $disponibles, true) ? $mes : end($disponibles);
            $ipc = $this->serie('89635');

            $filas = $indicadores->map(function (Indicador $i) use ($mes) {
                $s = $this->serie($i->codigo);
                return [
                    'codigo'     => $i->codigo,
                    'nombre'     => $i->nombre,
                    'corto'      => $i->nombre_corto,
                    'icono'      => $i->icono,
                    'nivel'      => round($s[$mes], 3),
                    'mensual'    => self::variacion($s, $mes, 1),
                    'interanual' => self::variacion($s, $mes, 12),
                    'acumulada'  => self::acumulada($s, $mes),
                ];
            })->sortByDesc('interanual')->values()->all();

            $mesesCalor = array_map(fn ($k) => self::mesMenos($mes, $k), range(11, 0));
            $calor = [
                'meses' => $mesesCalor,
                'filas' => array_map(function ($f) use ($mesesCalor) {
                    $s = $this->serie($f['codigo']);
                    return ['codigo' => $f['codigo'], 'corto' => $f['corto'], 'valores' => array_map(fn ($m) => self::variacion($s, $m, 12), $mesesCalor)];
                }, $filas),
                'general' => array_map(fn ($m) => self::variacion($ipc, $m, 12), $mesesCalor),
            ];

            return [
                'mes'     => $mes,
                'meses'   => array_reverse($disponibles),
                'general' => [
                    'mensual'    => self::variacion($ipc, $mes, 1),
                    'interanual' => self::variacion($ipc, $mes, 12),
                    'acumulada'  => self::acumulada($ipc, $mes),
                ],
                'filas'   => $filas,
                'calor'   => $calor,
            ];
        });
    }

    /**
     * Evolución de divisiones seleccionadas.
     * modo 'indice': nivel reexpresado a base 100 en el mes $base.
     * modo 'interanual': variación interanual (%).
     */
    public function evolucion(array $codigos, string $modo, ?string $base): array
    {
        $validos = $this->indicadores('division')->whereIn('codigo', $codigos)->keyBy('codigo');
        $codigos = array_values(array_filter($codigos, fn ($c) => $validos->has($c)));

        return $this->cache('evolucion:' . implode(',', $codigos) . ":{$modo}:{$base}", function () use ($codigos, $validos, $modo, $base) {
            $todos = array_merge(['89635'], $codigos);
            $meses = $this->mesesComunes($todos);
            if (!$meses) return ['modo' => $modo, 'base' => null, 'meses' => [], 'series' => []];
            if ($modo === 'interanual') {
                $meses = array_values(array_filter($meses, fn ($m) => in_array(self::mesMenos($m, 12), $meses, true)));
            } else {
                $base  = in_array($base, $meses, true) ? $base : ($meses[0] ?? null);
                $meses = array_values(array_filter($meses, fn ($m) => $m >= $base));
            }

            $calc = function (array $s) use ($meses, $modo, $base) {
                return array_map(fn ($m) => $modo === 'interanual'
                    ? self::variacion($s, $m, 12)
                    : round($s[$m] / $s[$base] * 100, 2), $meses);
            };

            $series = [['codigo' => '89635', 'corto' => 'IPC general', 'valores' => $calc($this->serie('89635'))]];
            foreach ($codigos as $c) {
                $series[] = ['codigo' => $c, 'corto' => $validos[$c]->nombre_corto, 'valores' => $calc($this->serie($c))];
            }

            return ['modo' => $modo, 'base' => $modo === 'indice' ? $base : null, 'meses' => $meses, 'series' => $series];
        });
    }

    /** Variación interanual de regulados / no regulados / bienes / servicios. */
    public function componentes(): array
    {
        return $this->cache('componentes', function () {
            $ind   = $this->indicadores('componente');
            $meses = $this->mesesComunes($ind->pluck('codigo')->all());
            $meses = array_values(array_filter($meses, fn ($m) => in_array(self::mesMenos($m, 12), $meses, true)));

            return [
                'meses'  => $meses,
                'series' => $ind->map(fn (Indicador $i) => [
                    'codigo'  => $i->codigo,
                    'corto'   => $i->nombre_corto,
                    'valores' => array_map(fn ($m) => self::variacion($this->serie($i->codigo), $m, 12), $meses),
                ])->values()->all(),
            ];
        });
    }

    /**
     * Canasta Básica Alimentaria (INEC) cruzada con el tipo de cambio (BCCR)
     * y con el IPC de alimentos (BCCR).
     */
    public function canasta(): array
    {
        return $this->cache('canasta', function () {
            $nac = $this->serie('CBA_NAC');
            $urb = $this->serie('CBA_URB');
            $rur = $this->serie('CBA_RUR');
            $lp  = $this->serie('LP_NAC');
            $tc  = $this->serie('318');
            $ali = $this->serie('89668');

            $meses = array_keys($nac);
            if (!$meses) return ['disponible' => false];

            $valores = fn (array $s) => array_map(fn ($m) => isset($s[$m]) ? round($s[$m], 2) : null, $meses);
            $usd     = fn (array $s) => array_map(fn ($m) => isset($s[$m], $tc[$m]) ? round($s[$m] / $tc[$m], 2) : null, $meses);

            return [
                'disponible'           => true,
                'meses'                => $meses,
                'nacional'             => $valores($nac),
                'urbana'               => $valores($urb),
                'rural'                => $valores($rur),
                'linea_pobreza'        => $valores($lp),
                'nacional_usd'         => $usd($nac),
                'urbana_usd'           => $usd($urb),
                'rural_usd'            => $usd($rur),
                'linea_pobreza_usd'    => $usd($lp),
                'interanual_cba'       => array_map(fn ($m) => self::variacion($nac, $m, 12), $meses),
                'interanual_alimentos' => array_map(fn ($m) => self::variacion($ali, $m, 12), $meses),
            ];
        });
    }

    /**
     * Subgrupos de la CBA en un mes: costo, peso dentro de la canasta,
     * variación interanual y contribución (en p.p.) a la variación de la CBA:
     *   contribución = (costo_sub(t) - costo_sub(t-12)) / CBA(t-12) × 100
     * La suma de contribuciones es igual a la variación interanual de la CBA.
     */
    public function subgrupos(?string $mes = null): array
    {
        return $this->cache('subgrupos:' . ($mes ?? 'ultimo'), function () use ($mes) {
            $cba   = $this->serie('CBA_NAC');
            $meses = array_values(array_filter(array_keys($cba), fn ($m) => isset($cba[self::mesMenos($m, 12)])));
            if (!$meses) return ['disponible' => false];

            $mes   = in_array($mes, $meses, true) ? $mes : end($meses);
            $hace  = self::mesMenos($mes, 12);

            $filas = $this->indicadores('subgrupo')->map(function (Indicador $i) use ($mes, $hace, $cba) {
                $s = $this->serie($i->codigo);
                if (!isset($s[$mes])) return null;

                return [
                    'codigo'       => $i->codigo,
                    'nombre'       => $i->nombre,
                    'icono'        => $i->icono,
                    'costo'        => round($s[$mes], 2),
                    'peso'         => round($s[$mes] / $cba[$mes] * 100, 2),
                    'interanual'   => self::variacion($s, $mes, 12),
                    'mensual'      => self::variacion($s, $mes, 1),
                    'contribucion' => isset($s[$hace]) ? round(($s[$mes] - $s[$hace]) / $cba[$hace] * 100, 2) : null,
                ];
            })->filter()->sortByDesc('contribucion')->values()->all();

            return [
                'disponible' => true,
                'mes'        => $mes,
                'meses'      => array_reverse($meses),
                'cba'        => [
                    'costo'      => round($cba[$mes], 2),
                    'interanual' => self::variacion($cba, $mes, 12),
                    'mensual'    => self::variacion($cba, $mes, 1),
                ],
                'filas'      => $filas,
            ];
        });
    }

    /** Índice de salario mínimo real y su variación interanual. */
    public function salario(): array
    {
        return $this->cache('salario', function () {
            $s     = $this->serie('90930');
            $meses = array_keys($s);

            return [
                'meses'      => $meses,
                'nivel'      => array_map(fn ($m) => round($s[$m], 2), $meses),
                'interanual' => array_map(fn ($m) => self::variacion($s, $m, 12), $meses),
            ];
        });
    }

    /** ¿Cuánto vale hoy un monto de un mes pasado? (ajuste por IPC). */
    public function poderAdquisitivo(float $monto, string $desde, ?string $hasta = null): ?array
    {
        $ipc   = $this->serie('89635');
        $hasta = $hasta && isset($ipc[$hasta]) ? $hasta : array_key_last($ipc);
        if (!isset($ipc[$desde], $ipc[$hasta])) return null;

        $factor = $ipc[$hasta] / $ipc[$desde];

        return [
            'monto'       => $monto,
            'desde'       => $desde,
            'hasta'       => $hasta,
            'equivalente' => round($monto * $factor, 2),
            'variacion'   => round(($factor - 1) * 100, 2),
            'poder'       => round($monto / $factor, 2),
        ];
    }

    public function mesesIpc(): array
    {
        return array_keys($this->serie('89635'));
    }

    // ─── Cálculo ────────────────────────────────────────────────────────

    public static function variacion(array $serie, string $mes, int $rezago): ?float
    {
        $anterior = self::mesMenos($mes, $rezago);
        if (!isset($serie[$mes], $serie[$anterior]) || (float) $serie[$anterior] == 0.0) return null;

        return round(($serie[$mes] / $serie[$anterior] - 1) * 100, 2);
    }

    public static function acumulada(array $serie, string $mes): ?float
    {
        $diciembre = ((int) substr($mes, 0, 4) - 1) . '-12';
        if (!isset($serie[$mes], $serie[$diciembre])) return null;

        return round(($serie[$mes] / $serie[$diciembre] - 1) * 100, 2);
    }

    public static function mesSiguiente(string $mes): string
    {
        return self::mesMenos($mes, -1);
    }

    public static function mesMenos(string $mes, int $n): string
    {
        [$y, $m] = array_map('intval', explode('-', $mes));
        $total   = $y * 12 + ($m - 1) - $n;

        return sprintf('%04d-%02d', intdiv($total, 12), $total % 12 + 1);
    }

    // ─── Acceso a datos ────────────────────────────────────────────────

    /** Serie mensual [Y-m => valor]. Las series diarias se promedian por mes. */
    private function serie(string $codigo): array
    {
        if (isset($this->series[$codigo])) return $this->series[$codigo];

        $rows = Observacion::query()
            ->join('indicadores', 'indicadores.id', '=', 'observaciones.indicador_id')
            ->where('indicadores.codigo', $codigo)
            ->orderBy('fecha')
            ->get(['fecha', 'valor']);

        $serie = [];
        $conteo = [];
        foreach ($rows as $r) {
            $mes = substr((string) $r->fecha, 0, 7);
            $serie[$mes]  = ($serie[$mes] ?? 0) + (float) $r->valor;
            $conteo[$mes] = ($conteo[$mes] ?? 0) + 1;
        }
        foreach ($serie as $mes => $suma) {
            $serie[$mes] = $suma / $conteo[$mes];
        }

        return $this->series[$codigo] = $serie;
    }

    private function indicadores(string $grupo)
    {
        return Indicador::where('grupo', $grupo)->orderBy('orden')->get();
    }

    /** Meses presentes en todas las series indicadas, ascendente. */
    private function mesesComunes(array $codigos): array
    {
        $comunes = null;
        foreach ($codigos as $c) {
            $meses   = array_keys($this->serie($c));
            $comunes = $comunes === null ? $meses : array_values(array_intersect($comunes, $meses));
        }
        $comunes ??= [];
        sort($comunes);

        return $comunes;
    }

    private function ultimoInteranual(string $codigo): ?array
    {
        $s = $this->serie($codigo);
        if (!$s) return null;
        $mes = array_key_last($s);

        return ['mes' => $mes, 'interanual' => self::variacion($s, $mes, 12), 'nivel' => round($s[$mes], 2)];
    }

    private function ultimoValor(string $codigo): ?array
    {
        $s = $this->serie($codigo);
        if (!$s) return null;
        $mes = array_key_last($s);

        return ['mes' => $mes, 'valor' => round($s[$mes], 2)];
    }

    private function resumenCanasta(): ?array
    {
        $nac = $this->serie('CBA_NAC');
        if (!$nac) return null;
        $mes = array_key_last($nac);
        $tc  = $this->serie('318');
        $lp  = $this->serie('LP_NAC');

        return [
            'mes'           => $mes,
            'costo'         => round($nac[$mes], 2),
            'interanual'    => self::variacion($nac, $mes, 12),
            'usd'           => isset($tc[$mes]) ? round($nac[$mes] / $tc[$mes], 2) : null,
            'linea_pobreza' => isset($lp[$mes]) ? round($lp[$mes], 2) : null,
        ];
    }

    private function cache(string $clave, \Closure $fn): array
    {
        $version = Cache::get(self::CACHE_VERSION, '0');

        return Cache::remember("canasta:{$version}:{$clave}", now()->addDay(), $fn);
    }
}
