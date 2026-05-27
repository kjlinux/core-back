<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);
        $appName = config('app.name');
        $expireMinutes = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe — ' . $appName)
            ->greeting('Bonjour,')
            ->line('Vous recevez cet email car nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.')
            ->action('Réinitialiser le mot de passe', $url)
            ->line('Ce lien expirera dans ' . $expireMinutes . ' minutes.')
            ->line('Si vous n\'êtes pas à l\'origine de cette demande, aucune action n\'est requise.')
            ->salutation('Cordialement, l\'équipe ' . $appName);
    }
}
