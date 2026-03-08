<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaNomina extends Model
{
    protected $table = 'categorias_nomina';
    protected $fillable = ['nombre', 'activa'];
    protected $casts = ['activa' => 'boolean'];

    public function nominasDiarias()
    {
        return $this->hasMany(NominaDiaria::class, 'categoria_id');
    }
}
