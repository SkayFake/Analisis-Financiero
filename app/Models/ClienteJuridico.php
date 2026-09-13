<?php

namespace App\Models;

use App\Services\RatioFinancieroService;
use Illuminate\Database\Eloquent\Model;

class ClienteJuridico extends Model
{
    protected $table = 'clientes_juridicos';

    protected $fillable = [
        'cliente_id', 'nombre_comercial', 'giro', 'representante_legal',
        'dui_representante', 'balance_general', 'estado_resultados',
        'ratios_financieros', 'fecha_balance', 'numero_empleados',
        'fecha_constitucion',
    ];

    protected $casts = [
        'balance_general' => 'array',
        'estado_resultados' => 'array',
        'ratios_financieros' => 'array',
        'fecha_balance' => 'date',
        'fecha_constitucion' => 'date',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Recalcula automáticamente los ratios financieros a partir del
     * Balance General y Estado de Resultados almacenados.
     */
    public function recalcularRatios(): array
    {
        $ratios = app(RatioFinancieroService::class)->calcular(
            $this->balance_general ?? [],
            $this->estado_resultados ?? []
        );

        $this->update(['ratios_financieros' => $ratios]);

        return $ratios;
    }

    /**
     * Estructura esperada del Balance General.
     */
    public static function estructuraBalanceGeneral(): array
    {
        return [
            'activo_corriente' => [
                'efectivo' => 0,
                'cuentas_por_cobrar' => 0,
                'inventarios' => 0,
                'otros_activos_corrientes' => 0,
            ],
            'activo_no_corriente' => [
                'propiedad_planta_equipo' => 0,
                'depreciacion_acumulada' => 0,
                'otros_activos' => 0,
            ],
            'pasivo_corriente' => [
                'cuentas_por_pagar' => 0,
                'prestamos_corto_plazo' => 0,
                'otros_pasivos_corrientes' => 0,
            ],
            'pasivo_no_corriente' => [
                'prestamos_largo_plazo' => 0,
                'otros_pasivos' => 0,
            ],
            'patrimonio' => [
                'capital_social' => 0,
                'reserva_legal' => 0,
                'utilidades_retenidas' => 0,
            ],
        ];
    }

    /**
     * Estructura esperada del Estado de Resultados.
     */
    public static function estructuraEstadoResultados(): array
    {
        return [
            'ingresos_operacionales' => 0,
            'costo_ventas' => 0,
            'gastos_operacionales' => 0,
            'gastos_administrativos' => 0,
            'gastos_financieros' => 0,
            'otros_ingresos' => 0,
            'otros_gastos' => 0,
            'impuesto_renta' => 0,
        ];
    }
}
