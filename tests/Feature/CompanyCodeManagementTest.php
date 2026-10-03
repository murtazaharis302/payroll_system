<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CompanyCodeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Super Admin user
        $this->adminUser = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@payroll.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => '1',
        ]);
    }

    public function test_super_admin_can_create_company_with_custom_company_code(): void
    {
        $response = $this->actingAs($this->adminUser)->post(panelRoute('storeCompany'), [
            'name' => 'John Owner',
            'companyName' => 'Acme Corporation',
            'company_code' => 'ACME-001',
            'email' => 'owner@acme.test',
            'companyEmail' => 'info@acme.test',
            'password' => 'password123',
            'phone' => '123456789',
            'website' => 'https://acme.test',
        ]);

        $response->assertRedirect(route('companies'));
        $response->assertSessionHas('status');

        $company = Company::where('name', 'Acme Corporation')->first();
        $this->assertNotNull($company);
        $this->assertEquals('ACME-001', $company->company_code);
    }

    public function test_company_code_auto_assigned_and_notified_when_left_empty(): void
    {
        $response = $this->actingAs($this->adminUser)->post(panelRoute('storeCompany'), [
            'name' => 'Jane Owner',
            'companyName' => 'Beta Logistics',
            'company_code' => '', // Left empty
            'email' => 'owner@beta.test',
            'companyEmail' => 'info@beta.test',
            'password' => 'password123',
            'phone' => '987654321',
            'website' => 'https://beta.test',
        ]);

        $response->assertRedirect(route('companies'));
        $company = Company::where('name', 'Beta Logistics')->first();
        $this->assertNotNull($company);
        $this->assertNotEmpty($company->company_code);
        $this->assertStringStartsWith('COMP-', $company->company_code);

        // Verify status message notifies user of assigned code
        $response->assertSessionHas('status', 'Company Added Successfully with Company Code: ' . $company->company_code);
    }

    public function test_duplicate_company_code_fails_validation(): void
    {
        // Existing company with code 'EX-001'
        $existingUser = User::create([
            'name' => 'Existing Owner',
            'email' => 'ex@company.test',
            'password' => bcrypt('password'),
            'role' => 'company',
            'status' => '1',
        ]);

        Company::create([
            'user_id' => $existingUser->id,
            'company_code' => 'EX-001',
            'name' => 'Existing Company',
            'email' => 'info@ex.test',
            'phone' => '00000',
            'website' => 'https://ex.test',
            'status' => '1',
        ]);

        // Attempt creating another company with duplicate code 'EX-001'
        $response = $this->actingAs($this->adminUser)->post(panelRoute('storeCompany'), [
            'name' => 'New Owner',
            'companyName' => 'New Company',
            'company_code' => 'EX-001',
            'email' => 'new@company.test',
            'companyEmail' => 'info@new.test',
            'password' => 'password123',
            'phone' => '11111',
            'website' => 'https://new.test',
        ]);

        $response->assertSessionHasErrors(['company_code']);
    }

    public function test_editing_company_code(): void
    {
        $owner = User::create([
            'name' => 'Delta Owner',
            'email' => 'owner@delta.test',
            'password' => bcrypt('password'),
            'role' => 'company',
            'status' => '1',
        ]);

        $company = Company::create([
            'user_id' => $owner->id,
            'company_code' => 'DELTA-OLD',
            'name' => 'Delta Enterprise',
            'email' => 'info@delta.test',
            'phone' => '55555',
            'website' => 'https://delta.test',
            'status' => '1',
        ]);

        $response = $this->actingAs($this->adminUser)->put(panelRoute('updateCompany', $company->id), [
            'name' => 'Delta Enterprise Updated',
            'company_code' => 'DELTA-NEW',
            'email' => 'info@delta.test',
            'phone' => '55555',
            'website' => 'https://delta.test',
        ]);

        $response->assertRedirect(route('companies'));
        $company->refresh();
        $this->assertEquals('DELTA-NEW', $company->company_code);
    }

    public function test_company_admin_can_access_edit_profile_page_without_errors(): void
    {
        $companyUser = User::create([
            'name' => 'Company Admin User',
            'email' => 'companyadmin@test.com',
            'password' => bcrypt('password'),
            'role' => 'company',
            'status' => '1',
        ]);

        Company::create([
            'user_id' => $companyUser->id,
            'company_code' => 'COMP-MYACC',
            'name' => 'My Account Co',
            'email' => 'info@myaccount.test',
            'phone' => '12345',
            'website' => 'https://myaccount.test',
            'status' => '1',
        ]);

        $response = $this->actingAs($companyUser)->get(panelRoute('edit_profile', $companyUser->id));

        $response->assertStatus(200);
        $response->assertSee('COMP-MYACC');
        $response->assertSee('My Account Co');
    }
}
