<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiredMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Company $company, public string $previousPlan) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre abonnement TangaFlow a expiré');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.subscription.expired',
            with: [
                'company' => $this->company,
                'previousPlan' => $this->previousPlan,
            ],
        );
    }
}
