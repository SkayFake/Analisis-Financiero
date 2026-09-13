<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones')->cascadeOnDelete();
            $table->string('codigo', 4)->comment('Código de la unidad, ej: 5676');
            $table->string('nombre');
            $table->timestamps();
            
            $table->unique(['institucion_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades');
    }
};
