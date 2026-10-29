<?php

namespace Tests\Unit;

use App\Models\Cuota;
use PHPUnit\Framework\TestCase;

class SaldoCuotaTest extends TestCase
{
    public function test_pendiente_incluye_mora_y_descuenta_abonos_sin_cambiar_el_plan(): void
    {
        $cuota = new Cuota(['total' => 188.33, 'mora' => 10, 'monto_pagado' => 111.67, 'saldo_pendiente' => 753.32]);
        $this->assertSame(86.66, $cuota->saldo_por_pagar);
        $this->assertSame('188.33', $cuota->total);
        $this->assertSame('753.32', $cuota->saldo_pendiente);

        $cuota->monto_pagado = 198.33;
        $this->assertSame(0.0, $cuota->saldo_por_pagar);
        $cuota->monto_pagado = 200;
        $this->assertSame(0.0, $cuota->saldo_por_pagar);
    }
}
