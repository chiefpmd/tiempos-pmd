<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mueble extends Model
{
    protected $table = 'muebles';
    protected $fillable = ['proyecto_id', 'numero', 'descripcion', 'costo_mueble'];

    protected $casts = [
        'costo_mueble' => 'decimal:2',
    ];

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function nominaDiaria()
    {
        return $this->hasMany(NominaDiaria::class);
    }

    public function tiempos()
    {
        return $this->hasMany(Tiempo::class, 'mueble_id');
    }
}
