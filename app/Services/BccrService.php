<?php

namespace App\Services;

use RuntimeException;

class BccrService
{
    private string $base;
    private string $token;

    public function __construct()
    {
        $this->base  = rtrim((string) config('bccr.base'), '/');
        $this->token = (string) config('bccr.token');

        if ($this->token === '') {
            throw new RuntimeException('Falta BCCR_API_TOKEN en el archivo .env.');
        }
    }

    /**
     * Descarga un cuadro completo del SDDE.
     * Devuelve [codigoIndicador => [Y-m-d => valor]].
     * Los cuadros diarios se piden año por año para no saturar el API.
     */
    public function fetchCuadro(int $cuadro, string $desde, string $hasta, bool $diario = false): array
    {
        if (!$diario) {
            return $this->request($cuadro, $desde, $hasta);
        }

        $resultado = [];
        $inicio    = (int) substr($desde, 0, 4);
        $fin       = (int) substr($hasta, 0, 4);

        for ($y = $inicio; $y <= $fin; $y++) {
            $d = max($desde, "{$y}-01-01");
            $h = min($hasta, "{$y}-12-31");
            foreach ($this->request($cuadro, $d, $h) as $codigo => $serie) {
                $resultado[$codigo] = ($resultado[$codigo] ?? []) + $serie;
            }
        }

        return $resultado;
    }

    private function request(int $cuadro, string $desde, string $hasta): array
    {
        $url = sprintf(
            '%s/cuadro/%d/series?idioma=ES&fechaInicio=%s&fechaFin=%s',
            $this->base, $cuadro, str_replace('-', '/', $desde), str_replace('-', '/', $hasta)
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->token,
                'Accept: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($body === false || $code !== 200) {
            throw new RuntimeException("Error al consultar el cuadro {$cuadro} del BCCR (HTTP {$code}).");
        }

        $raw = json_decode($body, true);
        if (!is_array($raw)) {
            throw new RuntimeException("El BCCR devolvió una respuesta inválida para el cuadro {$cuadro}.");
        }
        if (($raw['estado'] ?? false) !== true) {
            throw new RuntimeException("Cuadro {$cuadro}: " . ($raw['mensaje'] ?? 'error desconocido'));
        }

        return $this->parse($raw);
    }

    private function parse(array $raw): array
    {
        $series = [];

        foreach ($raw['datos'][0]['indicadores'] ?? [] as $indicador) {
            $codigo = (string) $indicador['codigoIndicador'];
            foreach ($indicador['series'] as $punto) {
                if ($punto['valorDatoPorPeriodo'] === null) continue;
                $series[$codigo][substr($punto['fecha'], 0, 10)] = (float) $punto['valorDatoPorPeriodo'];
            }
        }

        return $series;
    }
}
