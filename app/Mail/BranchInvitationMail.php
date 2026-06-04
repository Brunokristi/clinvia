<?php

namespace App\Mail;

use App\Models\BranchInvitation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BranchInvitationMail extends Mailable
{
    public function __construct(
        public BranchInvitation $invitation,
        public string $plainToken,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pozvánka do pobočky Clinvia'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invitations.branch',
            with: [
                'branchName' => $this->invitation->branch->name,
                'acceptUrl' => url('/branch-invite/'.$this->plainToken),
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}
