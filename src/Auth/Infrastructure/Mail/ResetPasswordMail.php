<?php

declare(strict_types=1);

namespace Urbania\Auth\Infrastructure\Mail;

use Illuminate\Mail\Mailable;

class ResetPasswordMail extends Mailable
{
    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly string $token,
        public readonly string $email,
        public readonly string $webUrl,
    ) {}

    /**
     * Build the message.
     */
    public function build(): self
    {
        $resetLink = $this->webUrl.'/reset-password?token='.urlencode($this->token).'&email='.urlencode($this->email);

        return $this
            ->subject('Recuperación de contraseña — Urbania')
            ->html($this->buildHtml($resetLink));
    }

    private function buildHtml(string $resetLink): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperación de contraseña</title>
</head>
<body style="font-family: system-ui, sans-serif; background: #f4f4f5; padding: 20px; margin: 0;">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="padding: 40px 40px 20px; text-align: center;">
                            <h1 style="color: #18181b; font-size: 24px; margin: 0;">Urbania</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 40px;">
                            <p style="color: #3f3f46; font-size: 16px; line-height: 1.5;">
                                Recibiste este correo porque solicitaste restablecer tu contraseña en Urbania.
                            </p>
                            <p style="color: #3f3f46; font-size: 16px; line-height: 1.5;">
                                Hacé clic en el botón de abajo para crear una nueva contraseña. Este enlace expira en <strong>60 minutos</strong>.
                            </p>
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="{$resetLink}" style="display: inline-block; background: #18181b; color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-size: 16px; font-weight: 600;">
                                    Restablecer contraseña
                                </a>
                            </div>
                            <p style="color: #71717a; font-size: 14px; line-height: 1.5;">
                                Si no solicitaste este cambio, podés ignorar este correo — tu contraseña actual seguirá funcionando.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 40px 40px; border-top: 1px solid #e4e4e7;">
                            <p style="color: #a1a1aa; font-size: 12px; text-align: center;">
                                Urbania — Gestión de condominios
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
