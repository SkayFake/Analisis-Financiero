<?php

namespace App\Mail;

use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Pago;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificacionCobroMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Cliente $cliente,
        public string $asuntoMensaje,
        public string $cuerpoMensaje,
        public string $tipoNotificacion,
        public ?Credito $credito = null,
        public ?Pago $pago = null,
        public ?string $etapaSecuencia = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->asuntoMensaje . ' — El Tablazo',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notificacion_cobro',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
