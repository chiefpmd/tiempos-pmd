<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proyecto extends Model
{
    protected $table = 'proyectos';
    protected $fillable = ['nombre', 'abreviacion', 'cliente', 'fecha_inicio', 'semanas', 'status'];
    protected $casts = ['fecha_inicio' => 'date'];

    public function muebles()
    {
        return $this->hasMany(Mueble::class, 'proyecto_id');
    }

    public function materiales()
    {
        return $this->hasMany(ProyectoMaterial::class, 'proyecto_id');
    }

    public function ganttAnual()
    {
        return $this->hasOne(GanttAnual::class, 'proyecto_id');
    }

    public function getFechaFinAttribute(): \Carbon\Carbon
    {
        return $this->fecha_inicio->copy()->addWeeks($this->semanas)->subDay();
    }
}
