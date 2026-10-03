<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\Log;

class UserRegister extends Mailable implements ShouldQueue
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
            Log::info('Mail notifications disabled. Skipping UserRegister email.');
            return $this->html('');
        }

        $confirmationLink = route('confirm.email', ['token' => $this->user->confirmation_token]);

        return $this->subject('Confirm Your Email Address')
                    ->view('website.email.user_confirm_email')
                    ->with([
                        'user' => $this->user,
                        'confirmationLink' => $confirmationLink,
                    ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('UserRegister mail failed: ' . $exception->getMessage());
    }

   

    /**
     * Get the message envelope.
     */
    // public function envelope(): Envelope
    // {
    //     return new Envelope(
    //         subject: 'User Register',
    //     );
    // }

    // /**
    //  * Get the message content definition.
    //  */
    // public function content(): Content
    // {
    //     return new Content(
    //         view: 'admin.emails.company_approved',
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
