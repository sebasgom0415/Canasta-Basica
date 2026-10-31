<?php

namespace App\Http\Controllers;

use App\Models\Indicador;
use App\Models\Sincronizacion;
use App\Services\CanastaImportService;
use App\Services\SincronizadorBccr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class AdminController extends Controller
{
    public function index(): View
    {
        $authenticated = session('admin_auth', false);
        return view('admin', compact('authenticated'));
    }

    public function login(Request $request)
    {
        $key = (string) $request->input('key', '');

        if (config('admin.key') && hash_equals((string) config('admin.key'), $key)) {
            $request->session()->regenerate();
            session(['admin_auth' => true]);
            return redirect()->route('admin.index');
        }

        sleep(1);
        return back()->withErrors(['key' => 'Contraseña incorrecta.']);
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.index');
    }

    /** Estado de cada indicador: cantidad de datos y último mes. */
    public function estado(): JsonResponse
    {
        $indicadores = Indicador::query()
            ->leftJoin('observaciones', 'observaciones.indicador_id', '=', 'indicadores.id')
            ->groupBy('indicadores.id', 'indicadores.codigo', 'indicadores.cuadro', 'indicadores.fuente', 'indicadores.nombre', 'indicadores.grupo', 'indicadores.orden')
            ->orderBy('indicadores.orden')
            ->get([
                'indicadores.codigo', 'indicadores.cuadro', 'indicadores.fuente', 'indicadores.nombre', 'indicadores.grupo',
                DB::raw('COUNT(observaciones.id) as total'),
                DB::raw('MIN(observaciones.fecha) as desde'),
                DB::raw('MAX(observaciones.fecha) as hasta'),
            ]);

        return response()->json([
            'success'        => true,
            'indicadores'    => $indicadores,
            'sincronizaciones' => Sincronizacion::orderByDesc('id')->limit(15)->get(),
        ]);
    }

    public function sincronizar(Request $request): JsonResponse
    {
        $key = 'sync:' . session()->getId();
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json(['success' => false, 'error' => 'Espere unos segundos antes de volver a sincronizar.'], 429);
        }
        RateLimiter::hit($key, 20);

        $completo = $request->boolean('completo');
        $desde    = $completo ? null : now()->subMonths(18)->startOfMonth()->toDateString();

        try {
            $resumen = (new SincronizadorBccr())->sincronizar($desde);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }

        $total   = array_sum(array_column($resumen, 'registros'));
        $errores = array_filter(array_column($resumen, 'error', null));

        return response()->json([
            'success' => true,
            'message' => "Se guardaron {$total} observaciones" . ($errores ? ' (' . count($errores) . ' cuadros con error).' : '.'),
            'detalle' => $resumen,
        ]);
    }

    public function importarCanasta(Request $request, CanastaImportService $import): JsonResponse
    {
        $request->validate(['archivo' => 'required|file|mimes:xlsx|max:5120'], [
            'archivo.required' => 'Seleccione el archivo Excel del INEC.',
            'archivo.mimes'    => 'El archivo debe ser un Excel (.xlsx).',
            'archivo.max'      => 'El archivo no puede pesar más de 5 MB.',
        ]);

        $archivo = $request->file('archivo');
        $ruta    = $archivo->storeAs('inec', $archivo->getClientOriginalName() ?: 'cba.xlsx');

        try {
            $res = $import->importar(Storage::disk('local')->path($ruta));
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Se importaron {$res['registros']} registros del INEC.",
            'detalle' => $res['detalle'],
        ]);
    }
}
