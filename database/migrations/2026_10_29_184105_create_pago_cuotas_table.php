<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pago_cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $table->foreignId('cuota_id')->constrained('cuotas')->cascadeOnDelete();
            $table->decimal('monto_total', 14, 2);
            $table->decimal('abono_capital', 14, 2)->default(0);
            $table->decimal('pago_interes', 14, 2)->default(0);
            $table->decimal('pago_mora', 14, 2)->default(0);
            $table->timestamps();

            $table->index(['pago_id', 'cuota_id']);
            $table->index('cuota_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pago_cuotas');
    }
};
