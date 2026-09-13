<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_dte', function (Blueprint $table) {
            $table->id();
            $table->string('nit', 20)->nullable();
            $table->string('nrc', 20)->nullable();
            $table->string('nombre_comercial')->nullable();
            $table->string('actividad_economica')->nullable();
            
            // Credenciales MH
            $table->string('api_key')->nullable();
            $table->string('password_api')->nullable();
            
            // Credenciales Firmador
            $table->string('url_firmador')->default('http://localhost:8113');
            $table->string('password_firmador')->nullable();
            
            // Entorno
            $table->enum('ambiente', ['00', '01'])->default('00')->comment('00: Pruebas, 01: Producción');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_dte');
    }
};
