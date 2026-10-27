<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class RavenPasswordResetNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        // La ruta password.reset ya la registra Fortify.
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Raven | Configura o recupera tu acceso')
            ->greeting('Hola, '.$notifiable->name)
            ->line('Este correo contiene un enlace seguro para configurar o restablecer tu contraseña de Raven.')
            ->action('Configurar mi contraseña', $url)
            ->line('Por seguridad, el enlace caduca en '.$minutes.' minutos.')
            ->line('Si no esperabas este mensaje, podés ignorarlo. No compartás el enlace con otras personas.');
    }
}
