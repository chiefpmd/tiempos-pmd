<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use App\Models\Personal;
use App\Models\Tiempo;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProyectoExport;

class ExportController extends Controller
{
    public function exportarProyecto(Proyecto $proyecto)
    {
        $filename = str_replace(' ', '_', $proyecto->nombre) . '_' . now()->format('Ymd') . '.xlsx';
        return Excel::download(new ProyectoExport($proyecto), $filename);
    }

    public function exportarGeneral()
    {
        return Excel::download(new \App\Exports\GeneralExport(), 'Vista_General_' . now()->format('Ymd') . '.xlsx');
    }
}
