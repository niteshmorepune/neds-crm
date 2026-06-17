<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MonthlyReportReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
        public Carbon $date,
        public Collection $customers,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Monthly reports due — '.$this->date->format('F Y').' | NEDS CRM',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.monthly-report-reminder');
    }
}
