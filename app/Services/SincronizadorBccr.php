<?php

namespace App\Services;

use App\Models\Indicador;
use App\Models\Observacion;
use App\Models\Sincronizacion;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class SincronizadorBccr
{
    public function __construct(private BccrService $bccr = new BccrService()) {}

    /**
     * Sincroniza todos los cuadros del catálogo.
     * Devuelve [cuadro => ['registros' => n, 'error' => ?string]].
     */
    public function sincronizar(?string $desde = null, ?string $hasta = null): array
    {
        $desde ??= config('bccr.desde');
        $hasta ??= date('Y-m-d');
        $ids     = Indicador::idsPorCodigo();
        $resumen = [];

        $porCuadro = collect(config('bccr.indicadores'))
            ->filter(fn ($i) => ($i['fuente'] ?? 'BCCR') === 'BCCR')
            ->groupBy('cuadro');

        foreach ($porCuadro as $cuadro => $indicadores) {
            $codigos = $indicadores->pluck('codigo')->all();
            $diario  = ($indicadores->first()['frecuencia'] ?? 'mensual') === 'diaria';

            try {
                $series = $this->bccr->fetchCuadro((int) $cuadro, $desde, $hasta, $diario);
                $filas  = [];

                foreach ($codigos as $codigo) {
                    if (!isset($ids[$codigo])) {
                        throw new RuntimeException("El indicador {$codigo} no está sembrado. Ejecute db:seed.");
                    }
                    foreach ($series[$codigo] ?? [] as $fecha => $valor) {
                        $filas[] = ['indicador_id' => $ids[$codigo], 'fecha' => $fecha, 'valor' => $valor];
                    }
                }

                $n = Observacion::guardar($filas);
                Sincronizacion::registrar('BCCR', "Cuadro {$cuadro}", $n);
                $resumen[$cuadro] = ['registros' => $n, 'error' => null];
            } catch (RuntimeException $e) {
                Sincronizacion::registrar('BCCR', "Cuadro {$cuadro}", 0, false, mb_substr($e->getMessage(), 0, 255));
                $resumen[$cuadro] = ['registros' => 0, 'error' => $e->getMessage()];
            }
        }

        InflacionService::limpiarCache();

        return $resumen;
    }
}
