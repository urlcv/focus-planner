<?php

declare(strict_types=1);

namespace URLCV\FocusPlanner\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FocusPlannerRecovery extends Mailable
{
    use Queueable, SerializesModels;

    public string $plannerUrl;

    public function __construct(string $token)
    {
        $this->plannerUrl = url('/tools/focus-planner?token=' . $token);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Focus Planner link',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'focus-planner::emails.recovery',
        );
    }
}
