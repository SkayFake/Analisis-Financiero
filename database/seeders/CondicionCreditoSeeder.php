<?php

namespace Database\Seeders;

use App\Models\CondicionCredito;
use Illuminate\Database\Seeder;

/**
 * Condiciones de crédito comercial predefinidas para El Salvador.
 *
 * Marco legal aplicable:
 * - Ley de Protección al Consumidor, D.776 (Art. 18-A, 18-B): transparencia en tasas.
 * - Ley de Usura, D.720: tasa no debe superar 2x tasa activa promedio BCR.
 *   Tasa activa promedio BCR 2025-2026: ~18-22% anual → máximo legal ~36-44% anual.
 *   Para crédito comercial responsable se recomienda ≤ 24% anual.
 * - Práctica comercial El Salvador: mora 3-5%/mes sobre saldo vencido, 3-5 días de gracia.
 */
class CondicionCreditoSeeder extends Seeder
{
    public function run(): void
    {
        $condiciones = [
            [
                'nombre'                              => 'Crédito 30 días sin interés',
                'descripcion'                         => 'Pago único al vencimiento, 30 días plazo. Sin interés. Mora del 3%/mes después de 5 días de gracia. Ideal para clientes de confianza.',
                'activa'                              => true,
                'tasa_interes_anual'                  => 0.0000, // Sin interés
                'tasa_mora_mensual'                   => 3.0000, // 3%/mes sobre saldo vencido
                'dias_gracia'                         => 5,
                'frecuencia_pago'                     => 'mensual',
                'plazo_maximo_cuotas'                 => 1,
                'monto_auto_aprobado'                 => 500.00,
                'compras_minimas_cliente_frecuente'   => 3,
                'requiere_fiador'                     => false,
                'requiere_dui'                        => true,
            ],
            [
                'nombre'                              => 'Crédito cuotas mensuales sin interés',
                'descripcion'                         => 'Hasta 12 cuotas mensuales iguales. Sin interés sobre el saldo. Mora del 3%/mes. Estándar para clientes frecuentes.',
                'activa'                              => true,
                'tasa_interes_anual'                  => 0.0000,
                'tasa_mora_mensual'                   => 3.0000,
                'dias_gracia'                         => 3,
                'frecuencia_pago'                     => 'mensual',
                'plazo_maximo_cuotas'                 => 12,
                'monto_auto_aprobado'                 => 1000.00,
                'compras_minimas_cliente_frecuente'   => 3,
                'requiere_fiador'                     => false,
                'requiere_dui'                        => true,
            ],
            [
                'nombre'                              => 'Crédito con interés 18% anual',
                'descripcion'                         => 'Crédito comercial con interés del 18% anual (≤ máximo Ley de Usura D.720 SV). Cuotas mensuales, hasta 12 meses. Mora 4%/mes.',
                'activa'                              => true,
                'tasa_interes_anual'                  => 18.0000, // Dentro del límite legal El Salvador
                'tasa_mora_mensual'                   => 4.0000,
                'dias_gracia'                         => 3,
                'frecuencia_pago'                     => 'mensual',
                'plazo_maximo_cuotas'                 => 12,
                'monto_auto_aprobado'                 => 300.00,  // Menor límite por ser crédito con interés
                'compras_minimas_cliente_frecuente'   => 5,
                'requiere_fiador'                     => false,
                'requiere_dui'                        => true,
            ],
            [
                'nombre'                              => 'Crédito quincenal sin interés',
                'descripcion'                         => 'Cuotas quincenales iguales, hasta 6 cuotas (3 meses). Sin interés. Mora 3.5%/mes. Para montos medianos.',
                'activa'                              => true,
                'tasa_interes_anual'                  => 0.0000,
                'tasa_mora_mensual'                   => 3.5000,
                'dias_gracia'                         => 3,
                'frecuencia_pago'                     => 'quincenal',
                'plazo_maximo_cuotas'                 => 6,
                'monto_auto_aprobado'                 => 750.00,
                'compras_minimas_cliente_frecuente'   => 3,
                'requiere_fiador'                     => false,
                'requiere_dui'                        => true,
            ],
            [
                'nombre'                              => 'Crédito especial con fiador',
                'descripcion'                         => 'Para clientes nuevos con montos mayores. Requiere fiador y documentación completa. Aprobación manual obligatoria. 0% interés, mora 3%/mes.',
                'activa'                              => true,
                'tasa_interes_anual'                  => 0.0000,
                'tasa_mora_mensual'                   => 3.0000,
                'dias_gracia'                         => 5,
                'frecuencia_pago'                     => 'mensual',
                'plazo_maximo_cuotas'                 => 6,
                'monto_auto_aprobado'                 => 0.00,    // Siempre requiere aprobación manual
                'compras_minimas_cliente_frecuente'   => 10,      // Muy difícil auto-aprobar
                'requiere_fiador'                     => true,
                'requiere_dui'                        => true,
            ],
        ];

        foreach ($condiciones as $condicion) {
            CondicionCredito::firstOrCreate(
                ['nombre' => $condicion['nombre']],
                $condicion
            );
        }

        $this->command->info('✓ Condiciones de crédito comercial creadas (marco legal El Salvador).');
    }
}
