<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\Log;

class CompanyApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $company;

    public $tries = 3;
    public $timeout = 30;
    public $backoff = [10, 30, 60];

    /**
     * Create a new message instance.
     */
    public function __construct($company)
    {
        $this->company = $company;
        $this->onQueue('mail');
    }

    /**
     * Build the message.
     */
    public function build()
    {
        if (!config('mail.notifications_enabled', true)) {
            Log::info('Mail notifications disabled. Skipping CompanyApprovedMail email.');
            return $this->html('');
        }

        return $this->subject('Your Company Has Been Approved')
                    ->view('admin.emails.company_approved');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('CompanyApprovedMail mail failed: ' . $exception->getMessage());
    }

    /**
     * Get the message envelope.
     */
    // public function envelope(): Envelope
    // {
    //     return new Envelope(
    //         subject: 'Company Approved Mail',
    //     );
    // }

    // /**
    //  * Get the message content definition.
    //  */
    // public function content(): Content
    // {
    //     return new Content(
    //         view: 'view.name',
    //     );
    // }

    // /**
    //  * Get the attachments for the message.
    //  *
    //  * @return array<int, \Illuminate\Mail\Mailables\Attachment>
    //  */
    // public function attachments(): array
    // {
    //     return [];
    // }
}
