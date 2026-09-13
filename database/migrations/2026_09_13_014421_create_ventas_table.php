<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes');
            $table->foreignId('vendedor_id')->nullable()->constrained('vendedores');
            
            // Tipo Documento MH
            // 01 = FCF, 03 = CCF, 05 = NC, 06 = ND, 11 = Ticket (No fiscal estricto o comprobante interno)
            $table->string('tipo_documento', 2)->default('01'); 
            $table->string('codigo_generacion', 36)->unique()->comment('UUID del DTE');
            $table->string('numero_control', 40)->unique();
            
            // Tiempos
            $table->date('fecha_emision');
            $table->time('hora_emision');
            
            // Financiero
            $table->enum('condicion_operacion', ['1', '2', '3'])->default('1')->comment('1: Contado, 2: Crédito, 3: Otro');
            $table->decimal('total_nosujeto', 14, 4)->default(0);
            $table->decimal('total_exento', 14, 4)->default(0);
            $table->decimal('total_gravado', 14, 4)->default(0);
            $table->decimal('iva_retenido', 14, 4)->default(0);
            $table->decimal('iva_percibido', 14, 4)->default(0);
            $table->decimal('total_iva', 14, 4)->default(0);
            $table->decimal('monto_total_operacion', 14, 4)->default(0);
            $table->decimal('total_pagar', 14, 4)->default(0);
            
            // Tracking DTE
            $table->enum('estado_dte', ['pendiente', 'procesado', 'rechazado', 'contingencia', 'invalidado'])->default('pendiente');
            $table->string('sello_recepcion', 40)->nullable()->comment('Sello devuelto por MH');
            $table->json('json_firmado')->nullable();
            $table->text('mensaje_mh')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
