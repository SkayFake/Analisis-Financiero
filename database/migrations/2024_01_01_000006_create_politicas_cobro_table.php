<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('politicas_cobro', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->integer('dias_gracia')->default(0);
            $table->integer('dias_cobro_30')->default(30);
            $table->integer('dias_cobro_60')->default(60);
            $table->decimal('interes_moratorio', 8, 4)->default(12.0000);
            $table->integer('dias_incobrable')->default(180);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('politicas_cobro');
    }
};
