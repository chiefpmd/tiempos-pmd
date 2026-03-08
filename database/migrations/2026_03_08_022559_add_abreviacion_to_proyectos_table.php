<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            $table->string('abreviacion', 10)->nullable()->after('nombre');
        });

        // Populate existing projects
        $abreviaciones = [
            'Juan Cano Casa 1' => 'JC1',
            'Juan Cano Casa 3' => 'JC3',
            'Panerai Artz' => 'P.Artz',
            'Panerai Puebla' => 'P.Pue',
            'Panerai Veracruz' => 'P.Ver',
            'Legacy' => 'LEG',
            'Zenith St Maarten' => 'Z.SMT',
        ];

        foreach ($abreviaciones as $nombre => $abrev) {
            DB::table('proyectos')->where('nombre', $nombre)->update(['abreviacion' => $abrev]);
        }
    }

    public function down(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            $table->dropColumn('abreviacion');
        });
    }
};
