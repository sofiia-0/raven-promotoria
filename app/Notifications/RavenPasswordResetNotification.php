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
            ->subject('Raven | Configure o recupere su acceso')
            ->greeting('Hola, '.$notifiable->name)
            ->line('Este correo contiene un enlace seguro para configurar o restablecer su contraseña de Raven.')
            ->action('Configurar contraseña', $url)
            ->line('Por seguridad, el enlace caduca en '.$minutes.' minutos.')
            ->line('Si no esperaba este mensaje, puede ignorarlo. No comparta el enlace con otras personas.');
    }
}
