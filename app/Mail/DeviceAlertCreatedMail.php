<?php

namespace App\Mail;

use App\Models\DeviceAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeviceAlertCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DeviceAlert $alert) {}

    public function envelope(): Envelope
    {
        $severity = strtoupper($this->alert->severity);
        return new Envelope(
            subject: "[Support IT][{$severity}] " . $this->alert->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.device-alert',
            with: [
                'alert' => $this->alert,
            ],
        );
    }
}
