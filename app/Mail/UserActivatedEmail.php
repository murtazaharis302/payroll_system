<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\Log;

class UserActivatedEmail extends Mailable implements ShouldQueue
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
            Log::info('Mail notifications disabled. Skipping UserActivatedEmail email.');
            return $this->html('');
        }

        return $this->subject('User Activated')
                    ->view('website.email.user_activated')
                    ->with([
                        'user' => $this->user,
                    ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('UserActivatedEmail mail failed: ' . $exception->getMessage());
    }
    // public function envelope(): Envelope
    // {
    //     return new Envelope(
    //         subject: 'User Activated Email',
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
