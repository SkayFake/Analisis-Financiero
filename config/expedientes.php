<?php

// Política interna inicial. No constituye una lista universal de obligaciones legales.
return [
    'exigir_creditos_nuevos' => true,
    'max_kb' => 2048,
    'max_documentos_por_cliente' => 500,
    'categorias' => [
        'dui_completo' => 'DUI: ambos lados en un archivo',
        'dui_frente' => 'DUI: frente',
        'dui_reverso' => 'DUI: reverso',
        'nit' => 'NIT / constancia tributaria',
        'nrc' => 'NRC (cuando corresponda)',
        'domicilio' => 'Comprobante de domicilio',
        'constancia_salario' => 'Constancia de salario / pensión',
        'boletas_pago' => 'Boletas de pago',
        'estado_cuenta' => 'Estado de cuenta / evidencia de ingresos',
        'declaracion_renta' => 'Declaración de renta',
        'estados_financieros' => 'Estados financieros del negocio',
        'constitucion' => 'Escritura de constitución / personería',
        'representacion' => 'Credencial vigente / poder de representación',
        'solicitud' => 'Solicitud de crédito firmada',
        'contrato' => 'Contrato de crédito comercial firmado',
        'fianza' => 'Documento de fianza firmado para este crédito',
        'referencias' => 'Referencias comerciales o personales',
        'autorizacion_consulta' => 'Autorización de consulta crediticia, si aplica',
        'consentimiento' => 'Constancia de información / consentimiento, si aplica',
        'otro' => 'Otro comprobante pertinente',
    ],
    // Cada grupo admite alternativas; cada alternativa exige todas sus categorías.
    'grupos' => [
        'identidad' => ['nombre' => 'DUI del titular / representante (ambos lados)', 'alternativas' => [['dui_completo'], ['dui_frente', 'dui_reverso']]],
        'domicilio' => ['nombre' => 'Domicilio', 'alternativas' => [['domicilio']]],
        'ingresos' => ['nombre' => 'Capacidad de pago / ingresos', 'alternativas' => [['constancia_salario'], ['boletas_pago'], ['estado_cuenta'], ['declaracion_renta'], ['estados_financieros']]],
        'nit' => ['nombre' => 'Identificación tributaria de la sociedad', 'alternativas' => [['nit']]],
        'personeria' => ['nombre' => 'Constitución / personería de la sociedad', 'alternativas' => [['constitucion']]],
        'representacion' => ['nombre' => 'Facultades del representante legal', 'alternativas' => [['representacion']]],
        'financieros' => ['nombre' => 'Estados financieros del negocio', 'alternativas' => [['estados_financieros']]],
        'solicitud' => ['nombre' => 'Solicitud firmada de este crédito', 'alternativas' => [['solicitud']]],
        'contrato' => ['nombre' => 'Contrato firmado de este crédito', 'alternativas' => [['contrato']]],
        'fianza' => ['nombre' => 'Fianza firmada para este crédito', 'alternativas' => [['fianza']]],
    ],
    'requisitos' => [
        'natural' => ['identidad', 'domicilio', 'ingresos'],
        'juridica' => ['identidad', 'nit', 'personeria', 'representacion', 'domicilio', 'financieros'],
        'credito' => ['solicitud', 'contrato'],
        'fiador' => ['identidad', 'domicilio', 'ingresos', 'fianza'],
    ],
];
