<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->estados(['local', 'pendiente', 'procesado', 'rechazado', 'contingencia', 'invalidado']);
        // Old demo responses were never fiscal authorizations.
        DB::table('ventas')->where('sello_recepcion', 'SELLO_DUMMY_TESTING')->update([
            'estado_dte' => 'local', 'sello_recepcion' => null, 'json_firmado' => null,
            'mensaje_mh' => 'Documento local. Registro de demostración sin autorización fiscal.',
        ]);
    }

    public function down(): void
    {
        if (DB::table('ventas')->where('estado_dte', 'local')->exists()) {
            throw new RuntimeException('Conserve los documentos locales antes de revertir esta migración.');
        }
        $this->estados(['pendiente', 'procesado', 'rechazado', 'contingencia', 'invalidado']);
    }

    private function estados(array $estados): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ventas DROP CONSTRAINT ventas_estado_dte_check');
            $lista = implode(', ', array_map(fn ($estado) => "'{$estado}'", $estados));
            DB::statement("ALTER TABLE ventas ADD CONSTRAINT ventas_estado_dte_check CHECK (estado_dte IN ({$lista}))");
        } else {
            Schema::table('ventas', fn (Blueprint $table) => $table->enum('estado_dte', $estados)->default('pendiente')->change());
        }
    }
};
