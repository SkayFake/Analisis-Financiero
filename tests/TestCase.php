<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['erp.modo_academico' => false]); // La mayoría de pruebas verifica controles fuera del simulador.
        // Topes sintéticos solo para pruebas de flujos. Nunca se despliegan como límites legales.
        config(['erp.creditos.limites_legales' => array_map(fn ($id) => [
            'condicion_credito_id' => $id, 'desde' => '2000-01-01', 'hasta' => '2100-12-31',
            'monto_minimo' => 0, 'monto_maximo' => 999999999999, 'tasa_maxima_efectiva' => 10000,
            'fuente' => 'https://example.test/limite-sintetico',
        ], range(1, 10000))]);
    }
}
