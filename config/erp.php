<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Configuración General del ERP
    |--------------------------------------------------------------------------
    */

    'currency' => env('ERP_CURRENCY', 'USD'),
    'country' => env('ERP_COUNTRY', 'SV'),
    'currency_symbol' => '$',
    'decimal_places' => 2,
    'tax_rate' => 0.13, // IVA El Salvador 13%

    /*
    |--------------------------------------------------------------------------
    | Módulo 1: Cuentas por Cobrar
    |--------------------------------------------------------------------------
    */

    'cobros' => [
        // Clasificación de incobrables por días de mora
        'clasificacion' => [
            'A' => ['min_dias' => 0, 'max_dias' => 30, 'label' => 'Normal', 'color' => '#10b981'],
            'B' => ['min_dias' => 31, 'max_dias' => 60, 'label' => 'Subnormal', 'color' => '#f59e0b'],
            'C' => ['min_dias' => 61, 'max_dias' => 90, 'label' => 'Deficiente', 'color' => '#f97316'],
            'D' => ['min_dias' => 91, 'max_dias' => null, 'label' => 'Incobrable', 'color' => '#ef4444'],
        ],

        // Plazos de cobro configurables
        'plazos_cobro' => [30, 60, 90],

        // Interés moratorio por defecto (% anual)
        'interes_moratorio_default' => 12.0,

        // Días para declarar incobrable
        'dias_incobrable' => 180,
    ],

    /*
    |--------------------------------------------------------------------------
    | Módulo 2: Inventarios (Art. 143 Código Tributario)
    |--------------------------------------------------------------------------
    */

    'inventario' => [
        'metodos_costeo' => [
            'ultima_compra' => 'Costo según última compra',
            'promedio_aligacion' => 'Costo promedio por aligación directa',
            'promedio' => 'Costo promedio',
            'peps' => 'PEPS (Primeras Entradas, Primeras Salidas)',
        ],
        'stock_minimo_default' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Módulo 3: Facturación Electrónica (DTE)
    |--------------------------------------------------------------------------
    */

    'dte' => [
        'api_url' => env('DTE_API_URL', 'https://apidte.dfrn.gob.sv'),
        'firmador_url' => env('DTE_FIRMADOR_URL', 'http://localhost:8113'),
        'nit_emisor' => env('DTE_NIT_EMISOR', ''),
        'nrc_emisor' => env('DTE_NRC_EMISOR', ''),
        'codigo_actividad' => env('DTE_CODIGO_ACTIVIDAD', ''),
        'nombre_comercial' => env('DTE_NOMBRE_COMERCIAL', ''),
        'ambiente' => env('DTE_AMBIENTE', '00'), // 00 = pruebas, 01 = producción
        'version_json' => 1,

        'tipos_documento' => [
            '01' => 'Factura',
            '03' => 'Comprobante de Crédito Fiscal',
            '05' => 'Nota de Crédito',
            '06' => 'Nota de Débito',
            '11' => 'Factura de Exportación',
            '14' => 'Factura de Sujeto Excluido',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Módulo 4: Activo Fijo (Art. 30 LISR)
    |--------------------------------------------------------------------------
    */

    'activo_fijo' => [
        // Porcentajes máximos de depreciación anual
        'depreciacion' => [
            'edificaciones' => 5,
            'maquinaria' => 20,
            'vehiculos' => 25,
            'otros_muebles' => 50,
        ],

        // Ajuste bienes usados (% del valor de bien nuevo)
        'ajuste_usados' => [
            1 => 80, // 1 año de uso previo
            2 => 60, // 2 años
            3 => 40, // 3 años
            4 => 20, // 4 o más años
        ],
    ],

];
