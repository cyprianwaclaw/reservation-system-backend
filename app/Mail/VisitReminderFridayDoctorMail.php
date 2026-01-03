<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Visit;

class VisitReminderFridayDoctorMail extends Mailable
{
    use Queueable, SerializesModels;

    public $visit;

    /**
     * Create a new message instance.
     */
    public function __construct(Visit $visit)
    {
        $this->visit = $visit;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Przypomnienie o jutrzejszej wizycie')
            // ->markdown('emails.visits.reminder');
            ->view('emails.visits.reminder-friday-doctor');
    }
}