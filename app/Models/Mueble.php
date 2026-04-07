<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mueble extends Model
{
    protected $table = 'muebles';
    protected $fillable = ['proyecto_id', 'numero', 'descripcion', 'costo_mueble', 'presupuesto_nomina', 'jornales_presupuesto', 'fecha_entrega'];

    protected $casts = [
        'costo_mueble' => 'decimal:2',
        'presupuesto_nomina' => 'decimal:2',
        'jornales_presupuesto' => 'decimal:1',
        'fecha_entrega' => 'date',
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
