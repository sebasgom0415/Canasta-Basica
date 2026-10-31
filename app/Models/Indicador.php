<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Indicador extends Model
{
    protected $table    = 'indicadores';
    protected $fillable = ['codigo', 'cuadro', 'fuente', 'grupo', 'nombre', 'nombre_corto', 'icono', 'unidad', 'frecuencia', 'orden'];

    public function observaciones(): HasMany
    {
        return $this->hasMany(Observacion::class);
    }

    /** Mapa codigo => id, útil para upserts masivos. */
    public static function idsPorCodigo(): array
    {
        return static::pluck('id', 'codigo')->all();
    }
}
