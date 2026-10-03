<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\Log;

class CompanyVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;

    public $tries = 3;
    public $timeout = 30;
    public $backoff = [10, 30, 60];

    /**
     * Create a new message instance.
     */
    public function __construct($user)
    {
        $this->user = $user;
        $this->onQueue('mail');
    }

    public function build()
    {
        if (!config('mail.notifications_enabled', true)) {
            Log::info('Mail notifications disabled. Skipping CompanyVerificationMail email.');
            return $this->html('');
        }

        return $this->subject('Please Verify Your Company Registration')
            ->view('website.email.emailtemplate')
            ->with([
                'logo' => $this->embed(public_path('company-assets/icons/select-and-rent-logo-3.png')),
                'facebook' => $this->embed(public_path('company-assets/icons/socials(3).png')),
                'twitter' => $this->embed(public_path('company-assets/icons/socials.png')),
                'instagram' => $this->embed(public_path('company-assets/icons/socials(2).png')),
                'linkedin' => $this->embed(public_path('company-assets/icons/socials(1).png')),
            ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('CompanyVerificationMail mail failed: ' . $exception->getMessage());
    }
    /**
     * Get the message envelope.
     */
    // public function envelope(): Envelope
    // {
    //     return new Envelope(
    //         subject: 'Company Verification Mail',
    //     );
    // }

    /**
     * Get the message content definition.
     */
    // public function content(): Content
    // {
    //     return new Content(
    //         view: 'view.name',
    //     );
    // }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    // public function attachments(): array
    // {
    //     return [];
    // }
}
