<?php

namespace Tests\Feature;

use App\Models\Indicador;
use App\Models\Observacion;
use App\Services\InflacionService;
use Database\Seeders\IndicadorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InflacionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IndicadorSeeder::class);
        InflacionService::limpiarCache();
    }

    /** Crea una serie mensual a partir de 2024-01 con los valores dados. */
    private function serie(string $codigo, array $valores, string $inicio = '2024-01'): void
    {
        $id  = Indicador::where('codigo', $codigo)->value('id');
        $mes = $inicio;
        foreach ($valores as $v) {
            Observacion::create(['indicador_id' => $id, 'fecha' => date('Y-m-t', strtotime("{$mes}-01")), 'valor' => $v]);
            $mes = InflacionService::mesSiguiente($mes);
        }
    }

    public function test_mes_menos_cruza_anios(): void
    {
        $this->assertSame('2025-12', InflacionService::mesMenos('2026-01', 1));
        $this->assertSame('2025-09', InflacionService::mesMenos('2026-09', 12));
        $this->assertSame('2024-11', InflacionService::mesMenos('2026-02', 15));
    }

    public function test_variaciones_desde_niveles(): void
    {
        $s = ['2025-09' => 100.0, '2025-12' => 104.0, '2026-08' => 109.0, '2026-09' => 110.0];

        $this->assertSame(10.0, InflacionService::variacion($s, '2026-09', 12));
        $this->assertSame(0.92, InflacionService::variacion($s, '2026-09', 1));
        $this->assertSame(5.77, InflacionService::acumulada($s, '2026-09'));
        $this->assertNull(InflacionService::variacion($s, '2026-08', 12));
    }

    public function test_contribuciones_de_subgrupos_suman_la_variacion_de_la_cba(): void
    {
        // CBA = A + B; A sube de 60 a 66 y B baja de 40 a 38 → CBA 100 → 104 (+4 %)
        $a = array_merge([60], array_fill(0, 11, 60), [66]);
        $b = array_merge([40], array_fill(0, 11, 40), [38]);
        $cba = array_map(fn ($x, $y) => $x + $y, $a, $b);

        $this->serie('CBA_NAC', $cba);
        foreach (['SG_A' => $a, 'SG_B' => $b] as $codigo => $valores) {
            Indicador::create(['codigo' => $codigo, 'fuente' => 'INEC', 'grupo' => 'subgrupo', 'nombre' => $codigo, 'nombre_corto' => $codigo, 'unidad' => '₡']);
            $this->serie($codigo, $valores);
        }

        $r = (new InflacionService())->subgrupos();

        $this->assertSame('2025-01', $r['mes']);
        $this->assertSame(4.0, $r['cba']['interanual']);
        $this->assertSame(6.0, $r['filas'][0]['contribucion']);
        $this->assertSame(-2.0, $r['filas'][1]['contribucion']);
        $this->assertEqualsWithDelta($r['cba']['interanual'], array_sum(array_column($r['filas'], 'contribucion')), 0.01);
    }

    public function test_poder_adquisitivo(): void
    {
        $this->serie('89635', [100, 101, 102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 125]);

        $r = (new InflacionService())->poderAdquisitivo(100000, '2024-01');

        $this->assertSame('2025-01', $r['hasta']);
        $this->assertSame(125000.0, $r['equivalente']);
        $this->assertSame(25.0, $r['variacion']);
        $this->assertSame(80000.0, $r['poder']);
    }

    public function test_api_resumen_sin_datos_responde_404(): void
    {
        $this->getJson('/api/resumen')->assertNotFound()->assertJson(['success' => false]);
    }

    public function test_api_poder_valida_parametros(): void
    {
        $this->getJson('/api/poder?monto=-5&desde=2024-01')->assertStatus(422);
        $this->getJson('/api/poder?monto=100&desde=2024-13')->assertStatus(422);
    }

    public function test_admin_requiere_sesion(): void
    {
        $this->postJson('/api/admin/sincronizar')->assertUnauthorized();
    }
}
