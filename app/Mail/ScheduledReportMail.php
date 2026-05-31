<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $reportTitle,
        public string $periodLabel,
        public string $attachmentName,
        public string $attachmentContent,
        public string $attachmentMime,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->reportTitle.' — '.$this->periodLabel);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.scheduled-report');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->attachmentContent, $this->attachmentName)
                ->withMime($this->attachmentMime),
        ];
    }
}
