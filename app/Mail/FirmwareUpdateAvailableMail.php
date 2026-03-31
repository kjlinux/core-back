<?php

namespace App\Mail;

use App\Models\FirmwareVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FirmwareUpdateAvailableMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly FirmwareVersion $firmware,
    ) {}

    public function envelope(): Envelope
    {
        $kind = $this->firmware->device_kind === 'rfid' ? 'RFID' : 'Biometrique';
        return new Envelope(
            subject: "Mise a jour firmware disponible — v{$this->firmware->version} ({$kind})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.firmware-update-available',
        );
    }
}
