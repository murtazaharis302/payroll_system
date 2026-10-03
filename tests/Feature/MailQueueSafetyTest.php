<?php

namespace Tests\Feature;

use App\Mail\CompanyApprovedMail;
use App\Mail\CompanyCreated;
use App\Mail\CompanyStatusMail;
use App\Mail\CompanyVerificationMail;
use App\Mail\EmployeeConformation;
use App\Mail\UserActivatedEmail;
use App\Mail\UserRegister;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailQueueSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);
        Role::firstOrCreate(['id' => 4, 'name' => 'Employee']);
    }

    public function test_employee_creation_succeeds_and_no_mail_queued_when_mail_disabled(): void
    {
        config(['mail.notifications_enabled' => false]);
        Mail::fake();

        $companyUser = User::factory()->create([
            'role_id' => 2,
            'role' => 'company',
        ]);
        $company = Company::create([
            'user_id' => $companyUser->id,
            'name' => 'Safety Corp',
            'company_code' => 'SAFE-0001',
            'email' => 'safe@test.com',
            'status' => 1,
        ]);
        $companyUser->update(['company_id' => $company->id]);

        $this->actingAs($companyUser);

        $response = $this->post(route('storeEmployee'), [
            'name' => 'No Mail Employee',
            'email' => 'nomail@safe.com',
            'phone' => '1234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'employee',
            'address' => '123 Safe St',
            'age' => '28',
        ]);

        $response->assertRedirect(route('employee'));

        $user = User::withoutGlobalScopes()->where('email', 'nomail@safe.com')->first();
        $this->assertNotNull($user);

        $employee = Employee::where('e_user_id', $user->id)->first();
        $this->assertNotNull($employee);

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }

    public function test_employee_creation_queues_mail_on_mail_queue_when_mail_enabled(): void
    {
        config(['auth.employee_password_provisioning' => 'invitation']);
        config(['mail.notifications_enabled' => true]);
        Mail::fake();

        $companyUser = User::factory()->create([
            'role_id' => 2,
            'role' => 'company',
        ]);
        $company = Company::create([
            'user_id' => $companyUser->id,
            'name' => 'Queued Corp',
            'company_code' => 'QUEUE-001',
            'email' => 'queue@test.com',
            'status' => 1,
        ]);
        $companyUser->update(['company_id' => $company->id]);

        $this->actingAs($companyUser);

        $response = $this->post(route('storeEmployee'), [
            'name' => 'Queued Employee',
            'email' => 'queued@corp.com',
            'phone' => '1234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'employee',
            'address' => '456 Queue St',
            'age' => '30',
        ]);

        $response->assertRedirect(route('employee'));

        Mail::assertQueued(EmployeeConformation::class, function ($mail) {
            return $mail->queue === 'mail' && $mail->hasTo('queued@corp.com');
        });
    }

    public function test_company_approval_uses_neutral_message_and_queues_mail(): void
    {
        config(['mail.notifications_enabled' => true]);
        Mail::fake();

        $admin = User::factory()->create([
            'role_id' => 1,
            'role' => 'admin',
        ]);

        $owner = User::factory()->create([
            'role_id' => 2,
            'role' => 'company',
            'email' => 'owner@pendingcorp.com',
        ]);

        $company = Company::create([
            'user_id' => $owner->id,
            'name' => 'Pending Corp',
            'company_code' => 'PEND-0001',
            'email' => 'info@pendingcorp.com',
            'status' => 0,
        ]);

        $this->actingAs($admin);

        $response = $this->post(route('aprovePendingCompany', $company->id));

        $response->assertRedirect(route('pending'));
        $response->assertSessionHas('status', 'Company Pending Corp has been approved.');

        Mail::assertQueued(CompanyApprovedMail::class, function ($mail) {
            return $mail->queue === 'mail' && $mail->hasTo('owner@pendingcorp.com');
        });
    }

    public function test_mailables_implement_should_queue_and_have_correct_queue_settings(): void
    {
        $mailables = [
            new EmployeeConformation(new User, 'secret'),
            new CompanyCreated(new Company, new User),
            new CompanyStatusMail(new Company, 'active'),
            new CompanyApprovedMail(new Company),
            new CompanyVerificationMail(new User),
            new UserRegister(new User),
            new UserActivatedEmail(new User),
        ];

        foreach ($mailables as $mailable) {
            $this->assertInstanceOf(ShouldQueue::class, $mailable);
            $this->assertEquals('mail', $mailable->queue);
            $this->assertEquals(3, $mailable->tries);
            $this->assertEquals(30, $mailable->timeout);
            $this->assertEquals([10, 30, 60], $mailable->backoff);
        }
    }

    public function test_mailables_handle_disabled_notifications_gracefully_in_build(): void
    {
        config(['mail.notifications_enabled' => false]);

        $user = new User(['email' => 'test@example.com']);
        $mailable = new EmployeeConformation($user, 'secret');

        $result = $mailable->build();
        $this->assertSame($mailable, $result);
    }
}
