<?php

namespace App\Http\Controllers;

use App\Models\Personal;
use Illuminate\Http\Request;

class PersonalController extends Controller
{
    public function index()
    {
        $personal = Personal::orderBy('equipo')->orderBy('nombre')->get();
        return view('personal.index', compact('personal'));
    }

    public function create()
    {
        return view('personal.form', ['persona' => new Personal()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'equipo' => 'required|in:Carpintería,Barniz,Instalación',
            'color_hex' => 'required|string|max:7',
            'activo' => 'boolean',
        ]);
        $data['activo'] = $request->boolean('activo');
        Personal::create($data);
        return redirect()->route('personal.index')->with('success', 'Personal creado.');
    }

    public function edit(Personal $personal)
    {
        return view('personal.form', ['persona' => $personal]);
    }

    public function update(Request $request, Personal $personal)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'equipo' => 'required|in:Carpintería,Barniz,Instalación',
            'color_hex' => 'required|string|max:7',
            'activo' => 'boolean',
        ]);
        $data['activo'] = $request->boolean('activo');
        $personal->update($data);
        return redirect()->route('personal.index')->with('success', 'Personal actualizado.');
    }

    public function destroy(Personal $personal)
    {
        $personal->delete();
        return redirect()->route('personal.index')->with('success', 'Personal eliminado.');
    }
}
