<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use Illuminate\Http\Request;

class ProyectoController extends Controller
{
    public function index()
    {
        $proyectos = Proyecto::withCount('muebles')->orderBy('fecha_inicio', 'desc')->get();
        return view('proyectos.index', compact('proyectos'));
    }

    public function create()
    {
        return view('proyectos.form', ['proyecto' => new Proyecto()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'cliente' => 'required|string|max:255',
            'fecha_inicio' => 'required|date',
            'semanas' => 'required|integer|min:1|max:52',
            'status' => 'required|in:activo,completado,pausado',
        ]);
        Proyecto::create($data);
        return redirect()->route('proyectos.index')->with('success', 'Proyecto creado.');
    }

    public function edit(Proyecto $proyecto)
    {
        return view('proyectos.form', compact('proyecto'));
    }

    public function update(Request $request, Proyecto $proyecto)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'cliente' => 'required|string|max:255',
            'fecha_inicio' => 'required|date',
            'semanas' => 'required|integer|min:1|max:52',
            'status' => 'required|in:activo,completado,pausado',
        ]);
        $proyecto->update($data);
        return redirect()->route('proyectos.index')->with('success', 'Proyecto actualizado.');
    }

    public function destroy(Proyecto $proyecto)
    {
        $proyecto->delete();
        return redirect()->route('proyectos.index')->with('success', 'Proyecto eliminado.');
    }
}
