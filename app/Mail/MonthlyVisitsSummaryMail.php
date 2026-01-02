<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MonthlyVisitsSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $report;
    public string $month;

    public function __construct(array $report, string $month)
    {
        $this->report = $report;
        $this->month = $month;
    }

    public function build()
    {
        return $this->subject('Podsumowanie wizyt – ' . $this->month)
            ->view('emails.visits.monthly-summary');
    }
}
