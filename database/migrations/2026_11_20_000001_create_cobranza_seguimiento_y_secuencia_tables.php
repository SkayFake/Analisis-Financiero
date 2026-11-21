<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Añadir términos de pronto pago (ej: 2/10, neto 30) a condiciones_credito
        Schema::table('condiciones_credito', function (Blueprint $table) {
            $table->integer('dias_pronto_pago')->nullable()->after('dias_gracia')
                ->comment('Días para aprovechar el descuento por pronto pago (ej: 10 días)');
            $table->decimal('descuento_pronto_pago', 5, 2)->nullable()->after('dias_pronto_pago')
                ->comment('Porcentaje de descuento por pronto pago (ej: 2.00%)');
            $table->string('termino_pago', 50)->default('neto 30')->after('descuento_pronto_pago')
                ->comment('Nomenclatura comercial del término (ej: 2/10, neto 30)');
        });

        // 2. Añadir términos de pronto pago a creditos
        Schema::table('creditos', function (Blueprint $table) {
            $table->integer('dias_pronto_pago')->nullable()->after('dias_gracia');
            $table->decimal('descuento_pronto_pago', 5, 2)->nullable()->after('dias_pronto_pago');
            $table->date('fecha_limite_pronto_pago')->nullable()->after('descuento_pronto_pago');
            $table->string('termino_pago', 50)->default('neto 30')->after('fecha_limite_pronto_pago');
        });

        // 3. Secuencia de cobranza de la clase
        // Notificación escrita -> llamada -> visita -> agencia de cobranza -> recurso legal
        Schema::create('cobranza_secuencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_id')->unique()->constrained('creditos')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('etapa_actual', 50)->default('notificacion_escrita')
                ->comment('Etapa actual: notificacion_escrita, llamada, visita, agencia_cobranza, recurso_legal');
            $table->date('fecha_ingreso_etapa');
            $table->integer('dias_mora_al_escalar')->default(0);
            $table->text('motivo_escalamiento')->nullable();
            $table->foreignId('escalado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('agencia_nombre', 150)->nullable();
            $table->string('expediente_legal_referencia', 150)->nullable();
            $table->string('estado', 50)->default('en_gestion')
                ->comment('en_gestion, acuerdo_alcanzado, escalado_externo, en_demanda, recuperado, incobrable');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['etapa_actual', 'estado']);
        });

        // 4. Seguimiento de notificaciones y control de respuesta del cliente
        Schema::create('cobranza_notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('credito_id')->nullable()->constrained('creditos')->cascadeOnDelete();
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->nullOnDelete();
            $table->string('tipo_notificacion', 50)
                ->comment('cobro_pendiente, cobro_vencido, pago_realizado, aviso_secuencia');
            $table->string('canal', 30)
                ->comment('correo, llamada, notificacion_escrita, visita');
            $table->string('etapa_secuencia', 50)->nullable()
                ->comment('notificacion_escrita, llamada, visita, agencia_cobranza, recurso_legal');
            $table->string('destinatario_contacto', 150);
            $table->string('asunto', 200);
            $table->text('mensaje');
            $table->string('estado_envio', 30)->default('enviado');
            $table->timestamp('fecha_envio')->useCurrent();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre', 150);
            $table->string('estado_respuesta', 50)->default('pendiente_respuesta')
                ->comment('pendiente_respuesta, contesto_llamada, respondio_correo, no_contesto, promesa_pago, solicita_prorroga, se_niega_pagar, contacto_invalido');
            $table->timestamp('fecha_respuesta')->nullable();
            $table->text('notas_respuesta')->nullable();
            $table->date('fecha_compromiso')->nullable();
            $table->decimal('monto_compromiso', 14, 2)->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'estado_respuesta']);
            $table->index(['credito_id', 'canal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranza_notificaciones');
        Schema::dropIfExists('cobranza_secuencias');

        Schema::table('creditos', function (Blueprint $table) {
            $table->dropColumn([
                'dias_pronto_pago',
                'descuento_pronto_pago',
                'fecha_limite_pronto_pago',
                'termino_pago',
            ]);
        });

        Schema::table('condiciones_credito', function (Blueprint $table) {
            $table->dropColumn([
                'dias_pronto_pago',
                'descuento_pronto_pago',
                'termino_pago',
            ]);
        });
    }
};
