<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->enum('tipo', ['natural', 'juridica']);
            $table->string('nombre');
            $table->text('direccion')->nullable();
            $table->string('departamento', 50)->nullable();
            $table->string('municipio', 100)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('celular', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('dui', 12)->nullable()->unique();
            $table->string('nit', 20)->nullable();
            $table->string('nrc', 20)->nullable();
            $table->enum('estado_civil', ['soltero', 'casado', 'divorciado', 'viudo', 'union_libre'])->nullable();
            $table->string('lugar_trabajo')->nullable();
            $table->decimal('ingresos', 14, 2)->default(0);
            $table->decimal('egresos', 14, 2)->default(0);
            $table->enum('clasificacion_cobro', ['A', 'B', 'C', 'D'])->default('A');
            $table->foreignId('zona_id')->nullable()->constrained('zonas')->nullOnDelete();
            $table->foreignId('cartera_id')->nullable()->constrained('carteras')->nullOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('vendedores')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tipo');
            $table->index('clasificacion_cobro');
            $table->index('zona_id');
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
