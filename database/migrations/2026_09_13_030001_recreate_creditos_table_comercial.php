<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reestructura la tabla creditos para crédito comercial (mercadería a crédito).
 * El crédito nace de una Venta (factura) y no de un desembolso de dinero.
 *
 * Se puede ejecutar migrate:fresh ya que no hay datos de producción.
 */
return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL requiere eliminar tablas dependientes antes de la tabla principal.
        // Eliminamos en orden inverso de dependencia.
        Schema::dropIfExists('historial_clasificaciones');
        Schema::dropIfExists('embargos');
        Schema::dropIfExists('refinanciamientos');
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('cuotas');
        Schema::dropIfExists('fiadores');
        Schema::dropIfExists('creditos');

        Schema::create('creditos', function (Blueprint $table) {
            $table->id();

            // ─── Identificación ──────────────────────────────────────────────
            $table->string('numero', 30)->unique()
                ->comment('Número de crédito generado. Formato: CC-YYYY-NNNNNN');

            // ─── Relaciones principales ──────────────────────────────────────
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('vendedores')->nullOnDelete();
            $table->foreignId('cartera_id')->nullable()->constrained('carteras')->nullOnDelete();
            $table->foreignId('politica_cobro_id')->nullable()->constrained('politicas_cobro')->nullOnDelete();

            // ─── Vínculo con la factura (origen del crédito) ─────────────────
            // Un crédito comercial SIEMPRE nace de una factura/venta.
            // nullable para créditos manuales excepcionales.
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete()
                ->comment('Factura de origen. El crédito comercial nace de la venta.');

            // ─── Condiciones del crédito ─────────────────────────────────────
            $table->foreignId('condicion_credito_id')->nullable()
                ->constrained('condiciones_credito')->nullOnDelete()
                ->comment('Condiciones configuradas por la institución (tasa, mora, plazo).');

            // ─── Montos ──────────────────────────────────────────────────────
            $table->decimal('monto_original', 14, 2)
                ->comment('Valor de la mercadería a crédito (= total_pagar de la factura).');
            $table->decimal('saldo_actual', 14, 2)
                ->comment('Saldo pendiente de cobrar.');
            $table->decimal('interes_acumulado', 14, 2)->default(0)
                ->comment('Interés comercial acumulado sobre el saldo (si la condición tiene tasa > 0).');
            $table->decimal('mora_acumulada', 14, 2)->default(0)
                ->comment('Recargo por mora acumulado.');

            // ─── Tasas (copiadas de condicion_credito al crear para audit trail) ──
            $table->decimal('tasa_interes_anual', 8, 4)->default(0)
                ->comment('Tasa anual copiada de condicion_credito al momento de crear el crédito.');
            $table->decimal('tasa_mora_mensual', 8, 4)->default(3.0000)
                ->comment('Tasa de mora mensual copiada de condicion_credito.');
            $table->integer('dias_gracia')->default(3);

            // ─── Plan de pago ────────────────────────────────────────────────
            $table->integer('numero_cuotas');
            $table->enum('frecuencia_pago', ['semanal', 'quincenal', 'mensual'])->default('mensual');
            $table->integer('plazo_dias')
                ->comment('Plazo total en días calculado según frecuencia y número de cuotas.');

            // ─── Fechas ──────────────────────────────────────────────────────
            $table->date('fecha_solicitud');
            $table->date('fecha_aprobacion')->nullable();
            $table->date('fecha_primera_cuota')
                ->comment('Fecha de vencimiento de la primera cuota.');
            $table->date('fecha_vencimiento')
                ->comment('Fecha de vencimiento de la última cuota.');
            $table->date('fecha_cancelacion')->nullable();

            // ─── Estado y aprobación ─────────────────────────────────────────
            $table->enum('estado', [
                'pendiente_aprobacion',   // Cliente nuevo o monto sobre límite auto-aprobado
                'aprobado',               // Listo para activarse (crédito aprobado)
                'vigente',                // Crédito activo con cuotas al día
                'vencido',                // Tiene cuotas vencidas
                'incobrable',             // > dias_incobrable en mora
                'refinanciado',
                'cancelado',              // Pagado en su totalidad
                'rechazado',              // No aprobado
            ])->default('pendiente_aprobacion');

            $table->integer('dias_mora')->default(0);
            $table->text('observaciones')->nullable();

            // ─── Auditoria aprobación ────────────────────────────────────────
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo_aprobacion')->nullable()
                ->comment('Razón de aprobación/rechazo. Para clientes nuevos con expediente.');

            // ─── Documentación cliente nuevo (requerida por Ley AML/LPC SV) ─
            $table->boolean('dui_verificado')->default(false);
            $table->boolean('referencia_verificada')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // ─── Índices ─────────────────────────────────────────────────────
            $table->index('cliente_id');
            $table->index('venta_id');
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
