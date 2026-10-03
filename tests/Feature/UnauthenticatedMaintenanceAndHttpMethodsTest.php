<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnauthenticatedMaintenanceAndHttpMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $companyAdmin;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@system.test',
            'password' => bcrypt('password'),
            'role_id' => Role::SUPER_ADMIN,
            'role' => 'admin',
        ]);

        $this->companyAdmin = User::create([
            'name' => 'Company Admin',
            'email' => 'companyadmin@system.test',
            'password' => bcrypt('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'role' => 'company',
        ]);

        $this->company = Company::create([
            'user_id' => $this->companyAdmin->id,
            'name' => 'Test Company',
            'company_code' => 'TST-001',
            'email' => 'test@company.com',
            'status' => 1,
        ]);

        $this->companyAdmin->update(['company_id' => $this->company->id]);
    }

    /** @test */
    public function unauthenticated_users_cannot_access_storage_link_or_clear_cache(): void
    {
        $responseLink = $this->get('/create-storage-link');
        $this->assertEquals(404, $responseLink->status());

        $responseCache = $this->get('/clear-cache');
        $this->assertEquals(404, $responseCache->status());
    }

    /** @test */
    public function unauthenticated_users_cannot_access_debug_test_routes(): void
    {
        $responsePayslip = $this->get('/test-payslip');
        $this->assertEquals(404, $responsePayslip->status());

        $responseSimple = $this->get('/test-reports-simple');
        $this->assertEquals(404, $responseSimple->status());
    }

    /** @test */
    public function get_requests_to_state_mutating_endpoints_are_rejected(): void
    {
        $this->actingAs($this->superAdmin);

        // GET request to user deletion route must return 405 Method Not Allowed
        $responseUserDelete = $this->get(route('admin.deleteUser', $this->companyAdmin->id));
        $this->assertEquals(405, $responseUserDelete->status());

        // User must NOT be deleted by GET request
        $this->assertDatabaseHas('users', ['id' => $this->companyAdmin->id]);

        // GET request to company deactivation route must return 405 Method Not Allowed
        $responseDeactivate = $this->get(route('admin.deactivateCompany', $this->company->id));
        $this->assertEquals(405, $responseDeactivate->status());

        // Company status must remain unchanged (active)
        $this->assertEquals(1, $this->company->fresh()->status);
    }

    /** @test */
    public function authenticated_mutations_require_correct_http_verbs_and_authorization(): void
    {
        $this->actingAs($this->superAdmin);

        // POST request to company deactivation
        $response = $this->post(route('admin.deactivateCompany', $this->company->id));
        $response->assertRedirect();

        // Company should now be deactivated (status = 2)
        $this->assertEquals(2, (int) $this->company->fresh()->status);

        // POST request to company activation
        $responseActivate = $this->post(route('admin.activateCompany', $this->company->id));
        $responseActivate->assertRedirect();

        // Company should be reactivated (status = 1)
        $this->assertEquals(1, $this->company->fresh()->status);
    }
}
