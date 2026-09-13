<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_id')->constrained('creditos')->restrictOnDelete();
            $table->foreignId('cuota_id')->nullable()->constrained('cuotas')->nullOnDelete();
            $table->string('numero_recibo', 30)->unique();
            $table->date('fecha_pago');
            $table->decimal('monto_total', 14, 2);
            $table->decimal('abono_capital', 14, 2)->default(0);
            $table->decimal('pago_interes', 14, 2)->default(0);
            $table->decimal('pago_comision', 14, 2)->default(0);
            $table->decimal('pago_mora', 14, 2)->default(0);
            $table->decimal('saldo_despues', 14, 2);
            $table->enum('forma_pago', ['efectivo', 'transferencia', 'cheque', 'tarjeta'])->default('efectivo');
            $table->string('referencia')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('recibido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('credito_id');
            $table->index('fecha_pago');
            $table->index('numero_recibo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
