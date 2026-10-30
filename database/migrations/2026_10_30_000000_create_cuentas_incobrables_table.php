<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_incobrables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_id')->constrained('creditos')->restrictOnDelete();
            // true mientras está en cartera; null permite conservar varios ciclos cerrados.
            $table->boolean('abierta')->nullable()->default(true);
            $table->unique(['credito_id', 'abierta']);
            $table->string('estado_anterior', 30)->nullable();
            $table->decimal('saldo_capital', 14, 2);
            $table->decimal('saldo_total', 14, 2);
            $table->unsignedInteger('dias_mora')->default(0);
            $table->text('motivo');
            $table->timestamp('declarado_at')->nullable();
            $table->foreignId('declarado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reactivado_at')->nullable();
            $table->foreignId('reactivado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo_reactivacion')->nullable();
            $table->timestamps();
        });

        // Incorporar estados antiguos sin inventar responsables ni fechas de declaración.
        DB::table('creditos')->where('estado', 'incobrable')->orderBy('id')->chunkById(200, function ($creditos) {
            foreach ($creditos as $credito) {
                $pendiente = DB::table('cuotas')->where('credito_id', $credito->id)
                    ->selectRaw('COALESCE(SUM(GREATEST(total + mora - monto_pagado, 0)), 0) AS total')->value('total');
                DB::table('cuentas_incobrables')->insert([
                    'credito_id' => $credito->id, 'abierta' => true,
                    'saldo_capital' => $credito->saldo_actual, 'saldo_total' => max($credito->saldo_actual, $pendiente),
                    'dias_mora' => max(0, $credito->dias_mora),
                    'motivo' => 'Registro anterior a la cartera de incobrables. Fecha, responsable y motivo originales no disponibles.',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        if (DB::table('cuentas_incobrables')->exists()) {
            throw new RuntimeException('La cartera contiene registros históricos. Respalde y conserve el historial antes de revertir esta migración.');
        }
        Schema::dropIfExists('cuentas_incobrables');
    }
};
