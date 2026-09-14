<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recrea tablas dependientes de creditos con estructura limpia.
 * Incluye: cuotas, pagos, fiadores, refinanciamientos, embargos,
 * historial_clasificaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop tables in dependency order
        Schema::dropIfExists('historial_clasificaciones');
        Schema::dropIfExists('embargos');
        Schema::dropIfExists('refinanciamientos');
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('cuotas');
        Schema::dropIfExists('fiadores');

        // ─── Cuotas ──────────────────────────────────────────────────────────
        Schema::create('cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_id')->constrained('creditos')->cascadeOnDelete();
            $table->integer('numero_cuota');
            $table->date('fecha_vencimiento');

            // En crédito comercial sin interés: capital = total.
            // Con interés: capital + interés = total.
            $table->decimal('capital', 14, 2)->comment('Porción de la mercadería en esta cuota.');
            $table->decimal('interes', 14, 2)->default(0)->comment('Interés comercial (si tasa > 0).');
            $table->decimal('total', 14, 2)->comment('Total a pagar en esta cuota = capital + interés.');
            $table->decimal('mora', 14, 2)->default(0)->comment('Mora acumulada sobre esta cuota vencida.');
            $table->decimal('saldo_pendiente', 14, 2)->comment('Saldo del crédito después de pagar esta cuota.');
            $table->decimal('monto_pagado', 14, 2)->default(0);

            $table->enum('estado', ['pendiente', 'pagada', 'vencida', 'parcial'])->default('pendiente');
            $table->date('fecha_pago')->nullable();
            $table->integer('dias_mora')->default(0);

            $table->timestamps();

            $table->index(['credito_id', 'numero_cuota']);
            $table->index('estado');
            $table->index('fecha_vencimiento');
        });

        // ─── Pagos ───────────────────────────────────────────────────────────
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_id')->constrained('creditos')->cascadeOnDelete();
            $table->foreignId('cuota_id')->nullable()->constrained('cuotas')->nullOnDelete();

            $table->string('numero_recibo', 30)->unique();
            $table->date('fecha_pago');

            $table->decimal('monto_total', 14, 2);
            $table->decimal('abono_capital', 14, 2)->default(0);
            $table->decimal('pago_interes', 14, 2)->default(0);
            $table->decimal('pago_mora', 14, 2)->default(0);
            $table->decimal('saldo_despues', 14, 2);

            $table->enum('forma_pago', ['efectivo', 'transferencia', 'cheque', 'tarjeta'])
                ->default('efectivo');
            $table->string('referencia', 100)->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('recibido_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('credito_id');
            $table->index('fecha_pago');
        });

        // ─── Fiadores ────────────────────────────────────────────────────────
        Schema::create('fiadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_id')->constrained('creditos')->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->string('dui', 20)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('direccion', 250)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        // ─── Refinanciamientos ───────────────────────────────────────────────
        Schema::create('refinanciamientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_original_id')->constrained('creditos')->restrictOnDelete();
            $table->foreignId('credito_nuevo_id')->constrained('creditos')->restrictOnDelete();
            $table->decimal('saldo_anterior', 14, 2);
            $table->decimal('nuevo_monto', 14, 2);
            $table->decimal('intereses_pendientes', 14, 2)->default(0);
            $table->text('motivo')->nullable();
            $table->date('fecha');
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ─── Embargos ────────────────────────────────────────────────────────
        Schema::create('embargos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_id')->constrained('creditos')->cascadeOnDelete();
            $table->date('fecha_embargo');
            $table->text('descripcion_bienes');
            $table->decimal('valor_estimado', 14, 2)->default(0);
            $table->enum('estado', ['activo', 'ejecutado', 'liberado'])->default('activo');
            $table->text('observaciones')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ─── Historial Clasificaciones ───────────────────────────────────────
        Schema::create('historial_clasificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_id')->constrained('creditos')->cascadeOnDelete();
            $table->string('clasificacion_anterior', 30)->nullable();
            $table->string('clasificacion_nueva', 30);
            $table->integer('dias_mora')->default(0);
            $table->decimal('saldo_momento', 14, 2)->default(0);
            $table->date('fecha');
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_clasificaciones');
        Schema::dropIfExists('embargos');
        Schema::dropIfExists('refinanciamientos');
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('cuotas');
        Schema::dropIfExists('fiadores');
    }
};
