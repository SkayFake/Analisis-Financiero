<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_id')->constrained('creditos')->cascadeOnDelete();
            $table->integer('numero_cuota');
            $table->date('fecha_vencimiento');
            $table->decimal('capital', 14, 2);
            $table->decimal('interes', 14, 2);
            $table->decimal('comision', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->decimal('saldo_pendiente', 14, 2);
            $table->decimal('monto_pagado', 14, 2)->default(0);
            $table->enum('estado', ['pendiente', 'pagada', 'vencida', 'parcial'])->default('pendiente');
            $table->date('fecha_pago')->nullable();
            $table->integer('dias_mora')->default(0);
            $table->timestamps();

            $table->index(['credito_id', 'numero_cuota']);
            $table->index('estado');
            $table->index('fecha_vencimiento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuotas');
    }
};
