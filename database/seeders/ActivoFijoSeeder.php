<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Institucion;
use App\Models\Unidad;
use App\Models\CategoriaActivo;

class ActivoFijoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear institución por defecto si no existe
        $institucion = Institucion::firstOrCreate(
            ['codigo' => '2322'],
            ['nombre' => 'Sede Central']
        );

        // 2. Crear unidades organizacionales (departamentos) básicas
        Unidad::firstOrCreate(
            ['codigo' => '5676', 'institucion_id' => $institucion->id],
            ['nombre' => 'Administración y Finanzas']
        );

        Unidad::firstOrCreate(
            ['codigo' => '5677', 'institucion_id' => $institucion->id],
            ['nombre' => 'Operaciones y Logística']
        );

        // 3. Crear Categorías de Activos (Según Ley de Impuesto Sobre la Renta El Salvador - Art 30)
        $categorias = [
            [
                'codigo' => 'EDIF',
                'nombre' => 'Edificaciones',
                'porcentaje_depreciacion' => 5.00
            ],
            [
                'codigo' => 'MAQU',
                'nombre' => 'Maquinaria',
                'porcentaje_depreciacion' => 20.00
            ],
            [
                'codigo' => 'VEHI',
                'nombre' => 'Vehículos',
                'porcentaje_depreciacion' => 25.00
            ],
            [
                'codigo' => 'OTRO',
                'nombre' => 'Otros Bienes Muebles (Mobiliario, Equipo, Software)',
                'porcentaje_depreciacion' => 50.00
            ]
        ];

        foreach ($categorias as $categoria) {
            CategoriaActivo::updateOrCreate(
                ['codigo' => $categoria['codigo']],
                [
                    'nombre' => $categoria['nombre'],
                    'porcentaje_depreciacion' => $categoria['porcentaje_depreciacion']
                ]
            );
        }
    }
}
