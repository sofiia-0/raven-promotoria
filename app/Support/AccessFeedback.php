<?php

namespace App\Support;

/** Traduce únicamente la presentación de estas vistas; no altera errores, reglas o respuestas de Fortify. */
class AccessFeedback
{
    public static function message(string $message): string
    {
        $keys = [
            'auth.failed' => 'El correo o la contraseña no son correctos.',
            'auth.password' => 'La contraseña no es correcta.',
            'passwords.sent' => 'Le hemos enviado un enlace para restablecer su contraseña.',
            'passwords.reset' => 'Su contraseña se ha actualizado correctamente.',
            'passwords.token' => 'El enlace no es válido o ha expirado. Solicite uno nuevo.',
            'passwords.user' => 'No se encontró una cuenta con ese correo electrónico.',
            'passwords.throttled' => 'Espere un momento antes de solicitar otro enlace.',
        ];
        foreach ($keys as $key => $text) {
            if ($message === $key || $message === __($key)) {
                return $text;
            }
        }
        if ($message === __('The provided password was incorrect.')) {
            return 'La contraseña no es correcta.';
        }
        if (preg_match('/Too many login attempts.*?(\d+) seconds/i', $message, $match)) {
            return 'Ha realizado demasiados intentos. Inténtelo de nuevo en '.$match[1].' segundos.';
        }
        if (preg_match('/must be at least (\d+) characters/i', $message, $match)) {
            return 'Utilice al menos '.$match[1].' caracteres.';
        }
        if (str_contains($message, 'confirmation does not match')) {
            return 'Las contraseñas no coinciden.';
        }
        if (str_contains($message, 'must be a valid email')) {
            return 'Ingrese un correo electrónico válido.';
        }
        if (str_contains($message, 'field is required')) {
            return 'Complete este campo.';
        }

        return $message;
    }
}
