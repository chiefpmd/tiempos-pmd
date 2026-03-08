<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipoDiario extends Model
{
    protected $table = 'equipo_diario';
    protected $fillable = ['personal_id', 'lider_id', 'fecha'];
    protected $casts = ['fecha' => 'date'];

    public function personal()
    {
        return $this->belongsTo(Personal::class);
    }

    public function lider()
    {
        return $this->belongsTo(Personal::class, 'lider_id');
    }
}
