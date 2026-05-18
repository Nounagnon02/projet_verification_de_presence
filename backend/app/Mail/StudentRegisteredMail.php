<?php

namespace App\Mail;

use App\Models\Etudiant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentRegisteredMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $etudiant;

    /**
     * Create a new message instance.
     */
    public function __construct(Etudiant $etudiant)
    {
        $this->etudiant = $etudiant;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre Identifiant Unique - UAC Presence',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.students.registered',
            with: [
                'name' => $this->etudiant->prenom,
                'idUnique' => $this->etudiant->identifiant_unique,
            ],
        );
    }
}
