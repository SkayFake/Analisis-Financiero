<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creditos', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('producto_credito_id')->constrained('productos_credito')->restrictOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('vendedores')->nullOnDelete();
            $table->foreignId('cartera_id')->nullable()->constrained('carteras')->nullOnDelete();
            $table->foreignId('politica_cobro_id')->nullable()->constrained('politicas_cobro')->nullOnDelete();
            $table->decimal('monto_original', 14, 2);
            $table->decimal('saldo_actual', 14, 2);
            $table->decimal('tasa_interes', 8, 4);
            $table->decimal('comision', 8, 4)->default(0);
            $table->integer('plazo_dias');
            $table->integer('numero_cuotas');
            $table->date('fecha_solicitud');
            $table->date('fecha_aprobacion')->nullable();
            $table->date('fecha_desembolso')->nullable();
            $table->date('fecha_vencimiento');
            $table->date('fecha_cancelacion')->nullable();
            $table->enum('estado', [
                'solicitado', 'aprobado', 'vigente', 'vencido',
                'incobrable', 'refinanciado', 'embargado', 'cancelado'
            ])->default('solicitado');
            $table->enum('tipo_venta', ['contado', 'credito'])->default('credito');
            $table->decimal('interes_moratorio', 8, 4)->default(0);
            $table->integer('dias_mora')->default(0);
            $table->text('observaciones')->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('cliente_id');
            $table->index('estado');
            $table->index('fecha_vencimiento');
            $table->index('vendedor_id');
            $table->index('cartera_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creditos');
    }
};
