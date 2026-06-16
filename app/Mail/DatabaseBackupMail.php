<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DatabaseBackupMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $filePath,
        public string $fileName,
        public string $generatedAt,
        public string $humanSize,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Sauvegarde base de données '.config('app.name').' - '.$this->generatedAt,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Bonjour,</p>'
                .'<p>Veuillez trouver ci-joint la sauvegarde quotidienne de la base de données <strong>'.e(config('app.name')).'</strong>.</p>'
                .'<ul>'
                .'<li>Fichier : '.e($this->fileName).'</li>'
                .'<li>Taille : '.e($this->humanSize).'</li>'
                .'<li>Généré le : '.e($this->generatedAt).'</li>'
                .'</ul>'
                .'<p>Restauration : <code>gunzip -c '.e($this->fileName).' | psql -U &lt;utilisateur&gt; -d &lt;base&gt;</code></p>'
                .'<p>Message automatique, ne pas répondre.</p>',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->filePath)
                ->as($this->fileName)
                ->withMime('application/gzip'),
        ];
    }
}
