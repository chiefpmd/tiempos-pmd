<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NominaDiaria extends Model
{
    protected $table = 'nomina_diaria';

    protected $fillable = [
        'personal_id', 'fecha', 'semana',
        'proyecto_id', 'mueble_id', 'categoria_id',
        'horas_extra', 'proyecto_he_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'horas_extra' => 'decimal:1',
    ];

    public function personal()
    {
        return $this->belongsTo(Personal::class);
    }

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function categoria()
    {
        return $this->belongsTo(CategoriaNomina::class, 'categoria_id');
    }

    public function mueble()
    {
        return $this->belongsTo(Mueble::class);
    }

    public function proyectoHe()
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_he_id');
    }

    public function getAsignacionAttribute(): string
    {
        return $this->proyecto?->nombre ?? $this->categoria?->nombre ?? 'Sin asignar';
    }

    public function getCostoDiaAttribute(): float
    {
        $p = $this->personal;
        if (!$p || !$p->nomina_bruta_semanal || !$p->dias_semana) {
            return 0;
        }
        return $p->nomina_bruta_semanal / $p->dias_semana;
    }

    public function getCostoHeAttribute(): float
    {
        $p = $this->personal;
        if (!$p || $this->horas_extra <= 0) {
            return 0;
        }
        $salarioDiario = $p->nomina_bruta_semanal / $p->dias_semana;
        return $this->horas_extra * $salarioDiario * $p->factor_he;
    }

    public function getCostoTotalAttribute(): float
    {
        return $this->costo_dia + $this->costo_he;
    }
}
