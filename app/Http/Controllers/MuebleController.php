<?php

namespace App\Http\Controllers;

use App\Models\Mueble;
use App\Models\Proyecto;
use Illuminate\Http\Request;

class MuebleController extends Controller
{
    public function store(Request $request, Proyecto $proyecto)
    {
        $data = $request->validate([
            'numero' => 'required|string|max:50',
            'descripcion' => 'required|string|max:255',
        ]);
        $proyecto->muebles()->create($data);
        return back()->with('success', 'Mueble agregado.');
    }

    public function destroy(Mueble $mueble)
    {
        $mueble->delete();
        return back()->with('success', 'Mueble eliminado.');
    }
}
