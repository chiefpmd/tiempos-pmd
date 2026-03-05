<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tiempo extends Model
{
    protected $table = 'tiempos';
    protected $fillable = ['mueble_id', 'proceso', 'personal_id', 'fecha', 'horas'];
    protected $casts = ['fecha' => 'date', 'horas' => 'decimal:1'];

    public function mueble()
    {
        return $this->belongsTo(Mueble::class, 'mueble_id');
    }

    public function personal()
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }
}
