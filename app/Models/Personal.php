<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Personal extends Model
{
    use SoftDeletes;

    protected $table = 'personal';
    protected $fillable = ['nombre', 'equipo', 'color_hex', 'activo', 'es_lider', 'lider_id', 'clave_empleado', 'nomina_bruta_semanal', 'dias_semana', 'factor_he'];
    protected $casts = [
        'activo' => 'boolean',
        'es_lider' => 'boolean',
        'nomina_bruta_semanal' => 'decimal:2',
        'factor_he' => 'decimal:2',
    ];

    public function tiempos()
    {
        return $this->hasMany(Tiempo::class, 'personal_id');
    }

    public function nominasDiarias()
    {
        return $this->hasMany(NominaDiaria::class, 'personal_id');
    }

    public function lider()
    {
        return $this->belongsTo(Personal::class, 'lider_id');
    }

    public function miembros()
    {
        return $this->hasMany(Personal::class, 'lider_id');
    }

    public function equipoDiario()
    {
        return $this->hasMany(EquipoDiario::class, 'personal_id');
    }

    public function equipoComoLider()
    {
        return $this->hasMany(EquipoDiario::class, 'lider_id');
    }

    public function getSalarioDiarioAttribute(): float
    {
        if (!$this->nomina_bruta_semanal || !$this->dias_semana) return 0;
        return $this->nomina_bruta_semanal / $this->dias_semana;
    }

    public function getSalarioHeAttribute(): float
    {
        return $this->salario_diario * ($this->factor_he ?? 0.20);
    }
}
