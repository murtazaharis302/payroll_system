<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CompanyStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $company;
    public $status; // 'active' or 'inactive'

    public $tries = 3;
    public $timeout = 30;
    public $backoff = [10, 30, 60];

    public function __construct($company, $status)
    {
        $this->company = $company;
        $this->status = $status;
        $this->onQueue('mail');
    }

    public function build()
    {
        if (!config('mail.notifications_enabled', true)) {
            Log::info('Mail notifications disabled. Skipping CompanyStatusMail email.');
            return $this->html('');
        }

        $view = $this->status === 'active'
            ? 'admin.emails.companyActivated'
            : 'admin.emails.companyDeactivated';

        return $this->subject("Company Status Updated: " . ucfirst($this->status))
                    ->view($view)
                    ->with([
                        'company' => $this->company,
                    ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('CompanyStatusMail mail failed: ' . $exception->getMessage());
    }
}


