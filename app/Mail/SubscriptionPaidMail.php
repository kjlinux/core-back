<?php

namespace App\Mail;

use App\Models\SubscriptionPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionPaidMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public SubscriptionPayment $payment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirmation de paiement TangaFlow');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.subscription.paid',
            with: ['payment' => $this->payment->load('company')],
        );
    }
}
