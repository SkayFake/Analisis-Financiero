<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_dte', function (Blueprint $t) {
            foreach (['api_key', 'password_api', 'password_firmador'] as $campo) {
                $t->text($campo)->nullable()->change();
            }
        });
        DB::table('configuracion_dte')->orderBy('id')->chunkById(100, function ($filas) {
            foreach ($filas as $fila) {
                $datos = [];
                foreach (['api_key', 'password_api', 'password_firmador'] as $campo) {
                    if ($fila->$campo !== null) {
                        $datos[$campo] = Crypt::encryptString($fila->$campo);
                    }
                }
                if ($datos) {
                    DB::table('configuracion_dte')->where('id', $fila->id)->update($datos);
                }
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('No se descifran credenciales automáticamente. Conserve APP_KEY y un respaldo protegido.');
    }
};
