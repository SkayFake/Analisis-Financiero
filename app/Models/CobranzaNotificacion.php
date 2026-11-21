<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CobranzaNotificacion extends Model
{
    protected $table = 'cobranza_notificaciones';

    protected $fillable = [
        'cliente_id',
        'credito_id',
        'pago_id',
        'tipo_notificacion',
        'canal',
        'etapa_secuencia',
        'destinatario_contacto',
        'asunto',
        'mensaje',
        'estado_envio',
        'fecha_envio',
        'usuario_id',
        'usuario_nombre',
        'estado_respuesta',
        'fecha_respuesta',
        'notas_respuesta',
        'fecha_compromiso',
        'monto_compromiso',
    ];

    protected $casts = [
        'fecha_envio' => 'datetime',
        'fecha_respuesta' => 'datetime',
        'fecha_compromiso' => 'date',
        'monto_compromiso' => 'decimal:2',
    ];

    public const ESTADOS_RESPUESTA = [
        'pendiente_respuesta' => ['label' => 'Pendiente de Respuesta', 'badge' => 'warning'],
        'contesto_llamada' => ['label' => 'Contestó Llamada', 'badge' => 'info'],
        'respondio_correo' => ['label' => 'Respondió Correo', 'badge' => 'info'],
        'promesa_pago' => ['label' => 'Promesa de Pago', 'badge' => 'success'],
        'solicita_prorroga' => ['label' => 'Solicita Prórroga', 'badge' => 'primary'],
        'no_contesto' => ['label' => 'No Contestó / Buzón', 'badge' => 'neutral'],
        'se_niega_pagar' => ['label' => 'Se Niega a Pagar', 'badge' => 'danger'],
        'contacto_invalido' => ['label' => 'Contacto Inválido / Rebotado', 'badge' => 'danger'],
    ];

    public const CANALES = [
        'correo' => ['label' => 'Correo Electrónico', 'badge' => 'primary'],
        'llamada' => ['label' => 'Llamada Telefónica', 'badge' => 'warning'],
        'notificacion_escrita' => ['label' => 'Notificación Escrita', 'badge' => 'info'],
        'visita' => ['label' => 'Visita Presencial', 'badge' => 'success'],
    ];

    public const TIPOS = [
        'cobro_pendiente' => ['label' => 'Recordatorio Preventivo', 'badge' => 'info'],
        'cobro_vencido' => ['label' => 'Cobro Vencido (Mora)', 'badge' => 'warning'],
        'pago_realizado' => ['label' => 'Confirmación de Pago', 'badge' => 'success'],
        'aviso_secuencia' => ['label' => 'Aviso Formal de Secuencia', 'badge' => 'danger'],
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function credito()
    {
        return $this->belongsTo(Credito::class)->withTrashed();
    }

    public function pago()
    {
        return $this->belongsTo(Pago::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }

    public function getEstadoRespuestaLabelAttribute(): string
    {
        return self::ESTADOS_RESPUESTA[$this->estado_respuesta]['label'] ?? $this->estado_respuesta;
    }

    public function getEstadoRespuestaBadgeAttribute(): string
    {
        return self::ESTADOS_RESPUESTA[$this->estado_respuesta]['badge'] ?? 'neutral';
    }

    public function getCanalLabelAttribute(): string
    {
        return self::CANALES[$this->canal]['label'] ?? $this->canal;
    }

    public function getCanalBadgeAttribute(): string
    {
        return self::CANALES[$this->canal]['badge'] ?? 'neutral';
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo_notificacion]['label'] ?? $this->tipo_notificacion;
    }

    public function getTipoBadgeAttribute(): string
    {
        return self::TIPOS[$this->tipo_notificacion]['badge'] ?? 'neutral';
    }
}
