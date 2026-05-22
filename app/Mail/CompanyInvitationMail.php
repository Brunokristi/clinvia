<?php

namespace App\Mail;

use App\Models\CompanyInvitation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

class CompanyInvitationMail extends Mailable
{
    public function __construct(
        public CompanyInvitation $invitation,
        public string $plainToken,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pozvánka do Clinvia'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.company-invitation',
            with: [
                'companyName' => $this->invitation->company->legal_name,
                'acceptUrl' => URL::route('company-invitations.accept', ['token' => $this->plainToken]),
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}
