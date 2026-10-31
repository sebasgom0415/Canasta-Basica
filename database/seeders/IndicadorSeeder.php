<?php

namespace Database\Seeders;

use App\Models\Indicador;
use Illuminate\Database\Seeder;

class IndicadorSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('bccr.indicadores') as $orden => $i) {
            Indicador::updateOrCreate(['codigo' => $i['codigo']], [
                'cuadro'       => $i['cuadro'],
                'fuente'       => $i['fuente'] ?? 'BCCR',
                'grupo'        => $i['grupo'],
                'nombre'       => $i['nombre'],
                'nombre_corto' => $i['corto'],
                'icono'        => $i['icono'] ?? null,
                'unidad'       => $i['unidad'] ?? 'índice',
                'frecuencia'   => $i['frecuencia'] ?? 'mensual',
                'orden'        => $orden,
            ]);
        }
    }
}
