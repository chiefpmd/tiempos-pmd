<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MuebleAvanceMensual extends Model
{
    protected $table = 'mueble_avance_mensual';

    protected $fillable = ['mueble_id', 'anio', 'mes', 'avance_carpinteria', 'avance_barniz'];

    protected $casts = [
        'avance_carpinteria' => 'decimal:1',
        'avance_barniz' => 'decimal:1',
    ];

    public function mueble()
    {
        return $this->belongsTo(Mueble::class);
    }
}
