<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Observacion extends Model
{
    protected $table      = 'observaciones';
    public    $timestamps = false;
    protected $fillable   = ['indicador_id', 'fecha', 'valor'];
    protected $casts      = ['valor' => 'float'];

    public function indicador(): BelongsTo
    {
        return $this->belongsTo(Indicador::class);
    }

    /** Guarda filas [indicador_id, fecha, valor] actualizando las existentes. */
    public static function guardar(array $filas): int
    {
        foreach (array_chunk($filas, 1000) as $lote) {
            static::upsert($lote, ['indicador_id', 'fecha'], ['valor']);
        }
        return count($filas);
    }
}
