<?php

namespace App\Http\Controllers;

use App\Models\DiaFestivo;
use Illuminate\Http\Request;

class DiaFestivoController extends Controller
{
    public function index()
    {
        $festivos = DiaFestivo::orderBy('fecha')->get();
        return view('festivos.index', compact('festivos'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'fecha' => 'required|date|unique:dias_festivos,fecha',
            'nombre' => 'required|string|max:100',
        ]);

        DiaFestivo::create($data);

        return redirect()->route('festivos.index')->with('success', 'Día festivo agregado.');
    }

    public function destroy(DiaFestivo $festivo)
    {
        $festivo->delete();
        return redirect()->route('festivos.index')->with('success', 'Día festivo eliminado.');
    }
}
