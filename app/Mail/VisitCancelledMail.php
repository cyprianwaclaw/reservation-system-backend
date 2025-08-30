<?php

namespace App\Mail;

use App\Models\Visit;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VisitCancelledMail extends Mailable
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
        return $this->subject('Twoja wizyta została anulowana')
                    // ->markdown('emails.visits.reminder');
                    ->view('emails.visits.cancelled');

    }
}
