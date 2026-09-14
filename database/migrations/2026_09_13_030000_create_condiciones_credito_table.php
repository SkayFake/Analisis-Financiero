<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de condiciones de crédito comercial configurables por la institución.
 * Reemplaza a productos_credito (concepto bancario).
 * Marco legal: Ley de Protección al Consumidor (Decreto 776) y Ley de Usura (Decreto 720) de El Salvador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condiciones_credito', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);                             // Ej: "30 días", "Cuotas estándar"
            $table->text('descripcion')->nullable();
            $table->boolean('activa')->default(true);

            // ─── Interés sobre el saldo (crédito comercial) ─────────────────
            // En El Salvador la Ley de Usura (D.720) limita la tasa al doble
            // de la tasa activa promedio bancaria publicada por el BCR.
            // Tasa activa promedio BCR ~18-22% anual (2024-2026).
            // Valor 0 = sin interés (precio de lista, crédito a plazo simple).
            $table->decimal('tasa_interes_anual', 8, 4)->default(0.0000)
                ->comment('% anual sobre saldo. 0 = sin interés. Máx. legal ~2x tasa BCR.');

            // ─── Mora ────────────────────────────────────────────────────────
            // Ley Protección Consumidor Art. 18-B: el cargo por mora debe ser
            // proporcional al atraso y estar pactado en el contrato.
            // Práctica comercial El Salvador: 1% - 5% mensual sobre cuota vencida.
            $table->decimal('tasa_mora_mensual', 8, 4)->default(3.0000)
                ->comment('% mensual sobre el saldo vencido. Ley LPC limita abusividad.');
            $table->integer('dias_gracia')->default(3)
                ->comment('Días de gracia antes de aplicar mora. Común 3-5 días en comercio.');

            // ─── Plan de pago ────────────────────────────────────────────────
            $table->enum('frecuencia_pago', ['semanal', 'quincenal', 'mensual'])
                ->default('mensual');
            $table->integer('plazo_maximo_cuotas')->default(12)
                ->comment('Máximo de cuotas permitidas bajo esta condición.');

            // ─── Requisitos de aprobación ────────────────────────────────────
            $table->decimal('monto_auto_aprobado', 14, 2)->default(500.00)
                ->comment('Montos ≤ este valor se aprueban automáticamente para clientes frecuentes.');
            $table->integer('compras_minimas_cliente_frecuente')->default(3)
                ->comment('Mínimo de compras anteriores para ser considerado cliente frecuente.');
            $table->boolean('requiere_fiador')->default(false);
            $table->boolean('requiere_dui')->default(true)
                ->comment('DUI requerido según Ley Antilavado y LPC.');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condiciones_credito');
    }
};
