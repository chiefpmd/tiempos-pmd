<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use App\Models\ProyectoMaterial;
use Illuminate\Http\Request;

class ProyectoMaterialController extends Controller
{
    public function guardar(Request $request, Proyecto $proyecto)
    {
        $data = $request->validate([
            'tipo' => 'required|in:pedido,entrega',
            'fecha' => 'nullable|date',
        ]);

        if (empty($data['fecha'])) {
            ProyectoMaterial::where('proyecto_id', $proyecto->id)
                ->where('tipo', $data['tipo'])
                ->delete();
            return response()->json(['ok' => true, 'deleted' => true]);
        }

        ProyectoMaterial::updateOrCreate(
            ['proyecto_id' => $proyecto->id, 'tipo' => $data['tipo']],
            ['fecha' => $data['fecha']]
        );

        return response()->json(['ok' => true]);
    }
}
