<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProyectoMaterial extends Model
{
    protected $table = 'proyecto_materiales';
    protected $fillable = ['proyecto_id', 'tipo', 'fecha'];
    protected $casts = ['fecha' => 'date'];

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class);
    }
}
