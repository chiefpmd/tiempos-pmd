<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiaFestivo extends Model
{
    protected $table = 'dias_festivos';
    protected $fillable = ['fecha', 'nombre'];
    protected $casts = ['fecha' => 'date'];

    public static function fechas(): array
    {
        return static::pluck('fecha')->map(fn($f) => $f->format('Y-m-d'))->toArray();
    }

    public static function esDiaLaborable(\Carbon\Carbon $dia): bool
    {
        static $festivos = null;
        if ($festivos === null) {
            $festivos = static::fechas();
        }
        return $dia->isWeekday() && !in_array($dia->format('Y-m-d'), $festivos);
    }
}
