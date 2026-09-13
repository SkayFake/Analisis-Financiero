<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_invalidacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas');
            $table->string('codigo_generacion', 36)->comment('UUID de la factura original');
            $table->string('sello_recepcion', 40)->comment('Sello de recepción de la factura original');
            $table->integer('tipo_documento_anulado');
            $table->integer('motivo_invalidacion');
            $table->string('responsable_nit', 14)->nullable();
            
            $table->enum('estado', ['pendiente', 'procesado', 'rechazado'])->default('pendiente');
            $table->string('sello_invalidacion', 40)->nullable()->comment('Sello de la anulación emitido por MH');
            $table->json('json_firmado')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_invalidacion');
    }
};
