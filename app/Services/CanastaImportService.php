<?php

namespace App\Services;

use App\Models\Indicador;
use App\Models\Observacion;
use App\Models\Sincronizacion;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

/**
 * Importa el Excel oficial del INEC "Cuadros de series históricas de la
 * Canasta Básica Alimentaria (CBA) 2011" (seEconomCBAmmaaaa-NN.xlsx).
 *
 * El sitio del INEC bloquea descargas automáticas, así que el archivo se
 * descarga a mano y se sube desde el panel o con `canasta:importar-cba`.
 *
 *   Cuadro 1 → líneas de pobreza (total, urbana, rural)
 *   Cuadro 2 → costo per cápita mensual de la CBA (total, urbana, rural)
 *   Cuadro 3 → costo per cápita mensual nacional por subgrupo de alimentos
 */
class CanastaImportService
{
    private const MESES = [
        'ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'ago' => 8, 'set' => 9, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12,
    ];

    private array $ids = [];

    public function importar(string $ruta): array
    {
        try {
            $libro = IOFactory::load($ruta);
        } catch (Throwable $e) {
            throw new RuntimeException('No se pudo leer el archivo Excel: ' . $e->getMessage());
        }

        $this->ids = Indicador::idsPorCodigo();
        $filas     = [];
        $resumen   = [];

        foreach (['Cuadro 1' => 'lineasPobreza', 'Cuadro 2' => 'costoPorZona', 'Cuadro 3' => 'subgrupos'] as $hoja => $metodo) {
            $ws = $libro->getSheetByName($hoja);
            if (!$ws) {
                throw new RuntimeException("El archivo no tiene la hoja «{$hoja}». ¿Es el Excel de series históricas de la CBA?");
            }
            $nuevas           = $this->{$metodo}($ws);
            $resumen[$hoja]   = count($nuevas);
            $filas            = array_merge($filas, $nuevas);
        }

        if (!$filas) {
            throw new RuntimeException('No se encontraron datos en el archivo.');
        }

        $n = Observacion::guardar($filas);
        Sincronizacion::registrar('INEC', 'CBA ' . basename($ruta), $n);
        InflacionService::limpiarCache();

        return ['registros' => $n, 'detalle' => $resumen];
    }

    /** Cuadro 1: filas Zona | Línea | ene-04 | feb-04 ... */
    private function lineasPobreza(Worksheet $ws): array
    {
        $datos  = $ws->toArray(null, true, false);
        $meses  = $this->encabezadoMeses($datos, 2);
        $codigo = ['total país' => 'LP_NAC', 'urbana' => 'LP_URB', 'rural' => 'LP_RUR'];
        $filas  = [];

        foreach ($datos as $r) {
            $zona  = mb_strtolower(trim((string) ($r[0] ?? '')));
            $linea = mb_strtolower((string) ($r[1] ?? ''));
            if (!isset($codigo[$zona]) || !str_starts_with($linea, 'línea de pobreza')) continue;

            foreach ($meses as $col => $fecha) {
                if (is_numeric($r[$col] ?? null)) {
                    $filas[] = $this->fila($codigo[$zona], $fecha, $r[$col]);
                }
            }
        }

        return $filas;
    }

    /** Cuadro 2: filas Año | Mes | total | urbana | rural | (diarios) */
    private function costoPorZona(Worksheet $ws): array
    {
        $filas = [];
        $anio  = null;

        foreach ($ws->toArray(null, true, false) as $r) {
            if (is_numeric($r[0] ?? null)) $anio = (int) $r[0];
            $mes = self::MESES[mb_substr(mb_strtolower(trim((string) ($r[1] ?? ''))), 0, 3)] ?? null;
            if (!$anio || !$mes || !is_numeric($r[2] ?? null)) continue;

            $fecha = date('Y-m-t', mktime(0, 0, 0, $mes, 1, $anio));
            foreach ([2 => 'CBA_NAC', 3 => 'CBA_URB', 4 => 'CBA_RUR'] as $col => $codigo) {
                if (is_numeric($r[$col] ?? null)) {
                    $filas[] = $this->fila($codigo, $fecha, $r[$col]);
                }
            }
        }

        return $filas;
    }

    /** Cuadro 3: filas Subgrupo | ene-11 | feb-11 ... (la primera es la CBA total) */
    private function subgrupos(Worksheet $ws): array
    {
        $datos = $ws->toArray(null, true, false);
        $meses = $this->encabezadoMeses($datos, 1);
        $filas = [];
        $orden = 100;

        foreach ($datos as $r) {
            $nombre = trim((string) ($r[0] ?? ''));
            $valores = array_filter(array_intersect_key($r, $meses), 'is_numeric');
            if ($nombre === '' || !$valores || str_starts_with(mb_strtolower($nombre), 'canasta')) continue;

            $codigo = $this->subgrupo($nombre, $orden++);
            foreach ($valores as $col => $valor) {
                $filas[] = $this->fila($codigo, $meses[$col], $valor);
            }
        }

        return $filas;
    }

    /** Busca la fila de encabezado con meses tipo «ene-11» y devuelve [columna => Y-m-t]. */
    private function encabezadoMeses(array $datos, int $desdeCol): array
    {
        foreach ($datos as $r) {
            $meses = [];
            foreach (array_slice($r, $desdeCol, null, true) as $col => $celda) {
                if (preg_match('/^([a-z]{3})\w*-(\d{2})$/u', mb_strtolower(trim((string) $celda)), $m) && isset(self::MESES[$m[1]])) {
                    $y = 2000 + (int) $m[2];
                    $meses[$col] = date('Y-m-t', mktime(0, 0, 0, self::MESES[$m[1]], 1, $y));
                }
            }
            if (count($meses) > 1) return $meses;
        }

        throw new RuntimeException('No se encontró la fila de meses en el cuadro.');
    }

    /** Crea (si hace falta) el indicador de un subgrupo de alimentos y devuelve su código. */
    private function subgrupo(string $nombre, int $orden): string
    {
        $slug   = Str::slug($nombre, '_');
        $codigo = 'SG_' . mb_strtoupper(mb_substr($slug, 0, 17));

        $icono = collect(config('bccr.iconos_subgrupo'))->first(fn ($i, $clave) => str_contains($slug, $clave), 'basket');
        $ind   = Indicador::updateOrCreate(['codigo' => $codigo], [
            'fuente' => 'INEC', 'grupo' => 'subgrupo', 'nombre' => $nombre,
            'nombre_corto' => mb_substr($nombre, 0, 40), 'icono' => $icono, 'unidad' => '₡', 'orden' => $orden,
        ]);
        $this->ids[$codigo] = $ind->id;

        return $codigo;
    }

    private function fila(string $codigo, string $fecha, mixed $valor): array
    {
        if (!isset($this->ids[$codigo])) {
            throw new RuntimeException("El indicador {$codigo} no está sembrado. Ejecute db:seed.");
        }

        return ['indicador_id' => $this->ids[$codigo], 'fecha' => $fecha, 'valor' => round((float) $valor, 6)];
    }
}
