<?php

$service = app(\App\Services\ActivoFijoService::class);
try {
    $activo = $service->registrarActivo([
        'unidad_id' => 1,
        'categoria_id' => 2, // Maquinaria 20%
        'nombre' => 'Test Maquina',
        'descripcion' => 'Maquina de prueba',
        'fecha_adquisicion' => now()->subMonths(2)->toDateString(),
        'valor_adquisicion' => 10000,
        'valor_residual' => 1000,
        'es_usado' => true,
        'anios_uso_previo' => 2, // 60%
        'maquinaria_importada_exenta' => false
    ]);
    echo 'Activo Registrado: ' . $activo->codigo_inventario . "\n";
    
    $deps = $service->calcularDepreciacionMasiva(now()->subMonth()->toDateString());
    echo 'Depreciados mes pasado: ' . $deps . "\n";
    
    $deps2 = $service->calcularDepreciacionMasiva(now()->toDateString());
    echo 'Depreciados este mes: ' . $deps2 . "\n";
    
    $activo->refresh();
    echo 'Depreciacion Acumulada: ' . $activo->depreciacion_acumulada . "\n";
} catch (\Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
