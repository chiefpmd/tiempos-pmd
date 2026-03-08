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
            'equipo' => 'required|in:Carpintería,Barniz,Instalación,Vidrio,Eléctrico,Mantenimiento,Herrero,Armado,Tapicero',
            'color_hex' => 'required|string|max:7',
            'activo' => 'boolean',
            'es_lider' => 'boolean',
            'lider_id' => 'nullable|exists:personal,id,es_lider,1',
            'clave_empleado' => 'nullable|string|max:50',
            'nomina_bruta_semanal' => 'nullable|numeric|min:0',
            'dias_semana' => 'nullable|integer|min:1|max:7',
            'factor_he' => 'nullable|numeric|min:0',
        ]);
        $data['activo'] = $request->boolean('activo');
        $data['es_lider'] = $request->boolean('es_lider');
        if ($data['es_lider']) $data['lider_id'] = null;
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
            'equipo' => 'required|in:Carpintería,Barniz,Instalación,Vidrio,Eléctrico,Mantenimiento,Herrero,Armado,Tapicero',
            'color_hex' => 'required|string|max:7',
            'activo' => 'boolean',
            'es_lider' => 'boolean',
            'lider_id' => 'nullable|exists:personal,id,es_lider,1',
            'clave_empleado' => 'nullable|string|max:50',
            'nomina_bruta_semanal' => 'nullable|numeric|min:0',
            'dias_semana' => 'nullable|integer|min:1|max:7',
            'factor_he' => 'nullable|numeric|min:0',
        ]);
        $data['activo'] = $request->boolean('activo');
        $data['es_lider'] = $request->boolean('es_lider');
        if ($data['es_lider']) $data['lider_id'] = null;
        $personal->update($data);
        return redirect()->route('personal.index')->with('success', 'Personal actualizado.');
    }

    public function destroy(Personal $personal)
    {
        $personal->delete();
        return redirect()->route('personal.index')->with('success', 'Personal eliminado.');
    }
}
