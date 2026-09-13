<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendedores', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nombre');
            $table->string('telefono', 20)->nullable();
            $table->string('email')->nullable();
            $table->foreignId('zona_id')->nullable()->constrained('zonas')->nullOnDelete();
            $table->decimal('meta_mensual', 14, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('zona_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendedores');
    }
};
