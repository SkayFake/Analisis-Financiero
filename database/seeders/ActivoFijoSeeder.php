<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Institucion;
use App\Models\Unidad;
use App\Models\CategoriaActivo;

class ActivoFijoSeeder extends Seeder
{
    public function run()
    {
        // 1. Institucion
        $inst = Institucion::create([
            'codigo' => '2322',
            'nombre' => 'Ministerio de Hacienda / Empresa Central S.A.'
        ]);

        // 2. Unidad
        Unidad::create([
            'institucion_id' => $inst->id,
            'codigo' => '5676',
            'nombre' => 'Dirección Administrativa'
        ]);

        // 3. Categorías LISR (Art. 30)
        CategoriaActivo::insert([
            ['codigo' => '8871', 'nombre' => 'Edificaciones', 'porcentaje_depreciacion' => 5.00],
            ['codigo' => '8872', 'nombre' => 'Maquinaria', 'porcentaje_depreciacion' => 20.00],
            ['codigo' => '8873', 'nombre' => 'Vehículos', 'porcentaje_depreciacion' => 25.00],
            ['codigo' => '8874', 'nombre' => 'Otros Bienes Muebles', 'porcentaje_depreciacion' => 50.00],
        ]);
    }
}
