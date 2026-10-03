<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarPermissionsTest extends TestCase
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

    public function test_standard_employee_cannot_see_admins_or_employees_tab_in_sidebar(): void
    {
        $companyUser = User::factory()->create([
            'role_id' => 2,
            'role' => 'company',
        ]);
        $company = Company::create([
            'user_id' => $companyUser->id,
            'name' => 'Acme Corporation',
            'company_code' => 'COMP-0001',
            'email' => 'acme@test.com',
            'status' => 1,
        ]);

        $employeeUser = User::factory()->create([
            'role_id' => 4,
            'role' => 'employee',
            'company_id' => $company->id,
        ]);

        $this->actingAs($employeeUser);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);

        // Standard employee must NOT see Admins, Core Applications, or Employee Master Directory in DOM
        $response->assertDontSee('Admins');
        $response->assertDontSee('Core Applications');
        $response->assertDontSee('Tenant Control');
        $response->assertDontSee('Reporting & Audit');
    }

    public function test_super_admin_can_see_admins_tab(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => 1,
            'role' => 'admin',
        ]);

        $this->actingAs($superAdmin);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Admins');
    }

    public function test_company_admin_cannot_see_companies_tab(): void
    {
        $companyAdmin = User::factory()->create([
            'role_id' => 2,
            'role' => 'company',
        ]);

        $this->actingAs($companyAdmin);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertDontSee('Tenant Control');
    }

    public function test_super_admin_can_see_companies_tab(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => 1,
            'role' => 'admin',
        ]);

        $this->actingAs($superAdmin);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Tenant Control');
    }
}
