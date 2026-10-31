<?php

use App\Services\CanastaImportService;
use App\Services\SincronizadorBccr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('canasta:sync {--desde= : Fecha inicial Y-m-d (por defecto, todo el historial)} {--recientes : Solo los últimos 18 meses}', function () {
    $desde = $this->option('recientes')
        ? now()->subMonths(18)->startOfMonth()->toDateString()
        : $this->option('desde');

    $this->info('Sincronizando con el BCCR desde ' . ($desde ?? config('bccr.desde')) . '...');
    $resumen = (new SincronizadorBccr())->sincronizar($desde);

    $this->table(['Cuadro', 'Registros', 'Error'], collect($resumen)->map(
        fn ($r, $cuadro) => [$cuadro, $r['registros'], $r['error'] ?? '—']
    )->values()->all());

    return collect($resumen)->contains(fn ($r) => $r['error']) ? 1 : 0;
})->purpose('Descarga del BCCR los indicadores de inflación configurados en config/bccr.php');

Artisan::command('canasta:importar-cba {archivo : Excel de series históricas de la CBA (seEconomCBA...xlsx)}', function (CanastaImportService $import) {
    $res = $import->importar($this->argument('archivo'));
    $this->info("Importados {$res['registros']} registros.");
    $this->table(['Hoja', 'Registros'], collect($res['detalle'])->map(fn ($n, $hoja) => [$hoja, $n])->values()->all());
})->purpose('Importa la Canasta Básica Alimentaria desde el Excel del INEC');

// El BCCR publica el IPC los primeros días de cada mes
Schedule::command('canasta:sync --recientes')->dailyAt('07:00');
