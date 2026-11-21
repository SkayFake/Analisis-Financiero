<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CobranzaSecuencia extends Model
{
    protected $table = 'cobranza_secuencias';

    protected $fillable = [
        'credito_id',
        'cliente_id',
        'etapa_actual',
        'fecha_ingreso_etapa',
        'dias_mora_al_escalar',
        'motivo_escalamiento',
        'escalado_por',
        'agencia_nombre',
        'expediente_legal_referencia',
        'estado',
        'notas',
    ];

    protected $casts = [
        'fecha_ingreso_etapa' => 'date',
        'dias_mora_al_escalar' => 'integer',
    ];

    public const ETAPAS = [
        'notificacion_escrita' => [
            'orden' => 1,
            'nombre' => 'Notificación Escrita',
            'descripcion' => 'Envío de cartas de cobro, estados de cuenta y notificaciones escritas preventivas o de mora.',
            'dias_mora_sugeridos' => '1 - 15 días',
            'badge' => 'info',
        ],
        'llamada' => [
            'orden' => 2,
            'nombre' => 'Llamada Telefónica',
            'descripcion' => 'Contacto telefónico directo con el cliente o fiadores para indagar motivos y acordar fecha de pago.',
            'dias_mora_sugeridos' => '16 - 30 días',
            'badge' => 'warning',
        ],
        'visita' => [
            'orden' => 3,
            'nombre' => 'Visita Presencial',
            'descripcion' => 'Gestión en el domicilio o lugar de trabajo del deudor para verificación de arraigo y entrega formal.',
            'dias_mora_sugeridos' => '31 - 60 días',
            'badge' => 'primary',
        ],
        'agencia_cobranza' => [
            'orden' => 4,
            'nombre' => 'Agencia de Cobranza',
            'descripcion' => 'Traslado de la cuenta a despacho o agencia de cobranza extrajudicial especializada.',
            'dias_mora_sugeridos' => '61 - 90 días',
            'badge' => 'warning',
        ],
        'recurso_legal' => [
            'orden' => 5,
            'nombre' => 'Recurso Legal',
            'descripcion' => 'Inicio de proceso judicial ejecutivo, embargo de bienes o recuperación vía abogados.',
            'dias_mora_sugeridos' => 'Más de 90 días',
            'badge' => 'danger',
        ],
    ];

    public function credito()
    {
        return $this->belongsTo(Credito::class)->withTrashed();
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function usuarioEscalado()
    {
        return $this->belongsTo(User::class, 'escalado_por');
    }

    public function getEtapaInfoAttribute(): array
    {
        return self::ETAPAS[$this->etapa_actual] ?? [
            'orden' => 0,
            'nombre' => ucfirst($this->etapa_actual),
            'descripcion' => '',
            'dias_mora_sugeridos' => '',
            'badge' => 'neutral',
        ];
    }

    public function getEtapaNombreAttribute(): string
    {
        return $this->etapa_info['nombre'] ?? $this->etapa_actual;
    }

    public function getEtapaBadgeAttribute(): string
    {
        return $this->etapa_info['badge'] ?? 'neutral';
    }

    public static function siguienteEtapa(string $etapaActual): ?string
    {
        $orden = [
            'notificacion_escrita' => 'llamada',
            'llamada' => 'visita',
            'visita' => 'agencia_cobranza',
            'agencia_cobranza' => 'recurso_legal',
            'recurso_legal' => null,
        ];

        return $orden[$etapaActual] ?? null;
    }

    public static function etapaSugeridaPorMora(int $diasMora): string
    {
        if ($diasMora <= 15) {
            return 'notificacion_escrita';
        }
        if ($diasMora <= 30) {
            return 'llamada';
        }
        if ($diasMora <= 60) {
            return 'visita';
        }
        if ($diasMora <= 90) {
            return 'agencia_cobranza';
        }

        return 'recurso_legal';
    }
}
