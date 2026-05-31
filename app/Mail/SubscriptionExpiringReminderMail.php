<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiringReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Company $company, public int $daysLeft) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Votre abonnement TangaFlow expire dans {$this->daysLeft} jour(s)",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.subscription.expiring-reminder',
            with: [
                'company' => $this->company,
                'daysLeft' => $this->daysLeft,
                'plan' => $this->company->subscription,
                'expiresAt' => $this->company->subscription_expires_at,
            ],
        );
    }
}
