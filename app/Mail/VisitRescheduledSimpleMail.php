<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Visit;

class VisitRescheduledSimpleMail extends Mailable
{
    use Queueable, SerializesModels;

    public $oldVisit;
    public $newVisit;

    public function __construct(Visit $oldVisit, Visit $newVisit)
    {
        $this->oldVisit = $oldVisit;
        $this->newVisit = $newVisit;
    }

    public function build()
    {
        return $this->subject('Twoja wizyta została przeniesiona')
            ->view('emails.visits.rescheduled_simple');
    }
}
