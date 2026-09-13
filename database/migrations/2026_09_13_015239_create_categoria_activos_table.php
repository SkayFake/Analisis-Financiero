<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categoria_activos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 4)->unique()->comment('Código del tipo, ej: 8871');
            $table->string('nombre');
            $table->decimal('porcentaje_depreciacion', 5, 2)->comment('Ej: 5.00, 20.00, 25.00, 50.00');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_activos');
    }
};
