<?php

namespace App\Http\Controllers;

use App\Services\InflacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private InflacionService $inflacion) {}

    public function index(): View
    {
        return view('index');
    }

    public function resumen(): JsonResponse
    {
        $data = $this->inflacion->resumen();
        if (!$data['disponible']) {
            return response()->json(['success' => false, 'message' => 'Aún no hay datos. Sincronice el BCCR desde el panel de administración.'], 404);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function ipc(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->inflacion->ipc()]);
    }

    public function divisiones(Request $request): JsonResponse
    {
        $mes = $this->mesValido($request->query('mes'));

        return response()->json(['success' => true, 'data' => $this->inflacion->divisiones($mes)]);
    }

    public function evolucion(Request $request): JsonResponse
    {
        $codigos = array_slice(array_filter(explode(',', (string) $request->query('codigos', '')), 'ctype_digit'), 0, 4);
        $modo    = $request->query('modo') === 'interanual' ? 'interanual' : 'indice';

        return response()->json([
            'success' => true,
            'data'    => $this->inflacion->evolucion($codigos, $modo, $this->mesValido($request->query('base'))),
        ]);
    }

    public function componentes(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->inflacion->componentes()]);
    }

    public function canasta(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->inflacion->canasta()]);
    }

    public function subgrupos(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->inflacion->subgrupos($this->mesValido($request->query('mes')))]);
    }

    public function salario(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->inflacion->salario()]);
    }

    public function poderAdquisitivo(Request $request): JsonResponse
    {
        $monto = (float) $request->query('monto', 0);
        $desde = $this->mesValido($request->query('desde'));

        if ($monto <= 0 || $monto > 1e12 || !$desde) {
            return response()->json(['success' => false, 'error' => 'Ingrese un monto y un mes válidos.'], 422);
        }

        $res = $this->inflacion->poderAdquisitivo($monto, $desde, $this->mesValido($request->query('hasta')));
        if (!$res) {
            return response()->json(['success' => false, 'error' => 'No hay IPC para el mes seleccionado.'], 404);
        }

        return response()->json(['success' => true, 'data' => $res]);
    }

    private function mesValido(mixed $mes): ?string
    {
        return is_string($mes) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) ? $mes : null;
    }
}
