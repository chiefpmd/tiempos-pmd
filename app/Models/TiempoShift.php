<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TiempoShift extends Model
{
    protected $fillable = ['proyecto_id', 'dias_habiles', 'snapshot', 'user_id', 'reverted'];
    protected $casts = ['snapshot' => 'array', 'reverted' => 'boolean'];

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class);
    }
}
