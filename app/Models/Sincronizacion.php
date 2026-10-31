<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sincronizacion extends Model
{
    protected $table      = 'sincronizaciones';
    public    $timestamps = false;
    protected $fillable   = ['fuente', 'detalle', 'registros', 'exito', 'mensaje'];
    protected $casts      = ['exito' => 'boolean', 'created_at' => 'datetime'];

    public static function registrar(string $fuente, string $detalle, int $registros, bool $exito = true, ?string $mensaje = null): void
    {
        static::create(compact('fuente', 'detalle', 'registros', 'exito', 'mensaje'));
    }
}
