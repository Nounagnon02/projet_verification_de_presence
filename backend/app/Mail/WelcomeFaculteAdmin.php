<?php

namespace App\Mail;

use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeFaculteAdmin extends Mailable
{
    use Queueable, SerializesModels;

    public User $admin;
    public string $password;
    public Etablissement $etablissement;

    /**
     * Create a new message instance.
     */
    public function __construct(User $admin, string $password, Etablissement $etablissement)
    {
        $this->admin = $admin;
        $this->password = $password;
        $this->etablissement = $etablissement;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Bienvenue à l'UAC — Vos identifiants d'accès pour {$this->etablissement->nom}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        return new Content(
            html: <<<HTML
            <!DOCTYPE html>
            <html>
            <head><meta charset="utf-8"></head>
            <body style="font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0;">
                <table width="100%" cellpadding="0" cellspacing="0" style="background: #f5f5f5; padding: 40px 0;">
                    <tr>
                        <td align="center">
                            <table width="600" cellpadding="0" cellspacing="0" style="background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.1);">
                                <tr>
                                    <td style="background: linear-gradient(135deg, #1a237e 0%, #283593 100%); padding: 40px 30px; text-align: center;">
                                        <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Université d'Abomey-Calavi</h1>
                                        <p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 16px;">Système de Gestion des Présences</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 40px 30px;">
                                        <h2 style="color: #1a237e; margin: 0 0 20px; font-size: 20px;">Bienvenue, {$this->etablissement->nom} !</h2>

                                        <p style="color: #333; line-height: 1.6; margin: 0 0 20px;">
                                            Votre compte administrateur pour la faculté <strong>{$this->etablissement->nom}</strong> a été créé avec succès.
                                            Vous trouverez ci-dessous vos identifiants de connexion.
                                        </p>

                                        <table width="100%" cellpadding="0" cellspacing="0" style="background: #f8f9ff; border-radius: 6px; padding: 20px; margin-bottom: 24px;">
                                            <tr>
                                                <td style="padding: 6px 0;">
                                                    <strong style="color: #555;">Établissement :</strong>
                                                    <span style="color: #1a237e; margin-left: 8px;">{$this->etablissement->nom} ({$this->etablissement->code})</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0;">
                                                    <strong style="color: #555;">Email :</strong>
                                                    <span style="color: #1a237e; margin-left: 8px;">{$this->admin->email}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0;">
                                                    <strong style="color: #555;">Mot de passe :</strong>
                                                    <span style="font-family: monospace; font-size: 16px; color: #c62828; margin-left: 8px; background: #fff; padding: 4px 8px; border-radius: 4px; border: 1px solid #e0e0e0;">{$this->password}</span>
                                                </td>
                                            </tr>
                                        </table>

                                        <p style="color: #e65100; font-size: 14px; margin: 0 0 24px;">
                                            ⚠️ Pour des raisons de sécurité, veuillez changer ce mot de passe lors de votre première connexion.
                                        </p>

                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td align="center">
                                                    <a href="{$frontendUrl}/login"
                                                       style="display: inline-block; background: linear-gradient(135deg, #1a237e 0%, #283593 100%); color: #ffffff; text-decoration: none; padding: 12px 32px; border-radius: 6px; font-size: 16px; font-weight: 600;">
                                                        Se connecter
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>

                                        <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 32px 0;">

                                        <p style="color: #888; font-size: 13px; line-height: 1.5; margin: 0;">
                                            Si vous n'avez pas demandé la création de ce compte, veuillez ignorer cet email.
                                            Pour toute assistance, contactez le support UAC.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="background: #fafafa; padding: 20px 30px; text-align: center; border-top: 1px solid #e0e0e0;">
                                        <p style="color: #aaa; font-size: 12px; margin: 0;">
                                            © {$this->etablissement->nom} — Université d'Abomey-Calavi<br>
                                            Ce message est automatique, merci de ne pas y répondre.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            HTML,
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
