<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GanttAnual extends Model
{
    protected $table = 'gantt_anual';
    protected $fillable = ['proyecto_id', 'fecha_inicio', 'fecha_fin'];
    protected $casts = ['fecha_inicio' => 'date', 'fecha_fin' => 'date'];

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }
}
