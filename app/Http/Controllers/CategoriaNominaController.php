<?php

namespace App\Http\Controllers;

use App\Models\CategoriaNomina;
use Illuminate\Http\Request;

class CategoriaNominaController extends Controller
{
    public function index()
    {
        $categorias = CategoriaNomina::orderBy('nombre')->get();
        return view('nomina.categorias', compact('categorias'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:categorias_nomina,nombre',
        ]);

        CategoriaNomina::create($request->only('nombre'));

        return redirect()->route('nomina.categorias')->with('success', 'Categoría creada.');
    }

    public function update(Request $request, CategoriaNomina $categoria)
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:categorias_nomina,nombre,' . $categoria->id,
            'activa' => 'boolean',
        ]);

        $categoria->update([
            'nombre' => $request->nombre,
            'activa' => $request->boolean('activa', true),
        ]);

        return redirect()->route('nomina.categorias')->with('success', 'Categoría actualizada.');
    }

    public function destroy(CategoriaNomina $categoria)
    {
        $categoria->delete();
        return redirect()->route('nomina.categorias')->with('success', 'Categoría eliminada.');
    }
}
