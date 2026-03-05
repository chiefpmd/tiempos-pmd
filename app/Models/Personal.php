<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Personal extends Model
{
    protected $table = 'personal';
    protected $fillable = ['nombre', 'equipo', 'color_hex', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function tiempos()
    {
        return $this->hasMany(Tiempo::class, 'personal_id');
    }
}
