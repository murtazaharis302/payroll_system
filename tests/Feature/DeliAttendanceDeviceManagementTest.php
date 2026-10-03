<?php

namespace Tests\Feature;

use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliAttendanceDeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $superAdmin;
    protected User $companyAdminA;
    protected User $companyAdminB;
    protected User $hrUserA;
    protected User $employeeUserA;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://payrollsystem-production-5676.up.railway.app']);

        $this->seed(\Database\Seeders\MenusTableSeeder::class);

        $this->superAdmin = User::create([
            'name'     => 'Super Admin',
            'email'    => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role_id'  => Role::SUPER_ADMIN,
            'role'     => 'admin',
            'status'   => 1,
        ]);

        $this->companyA = Company::create([
            'name'         => 'Company A',
            'email'        => 'compa@test.com',
            'status'       => '1',
            'user_id'      => $this->superAdmin->id,
            'company_code' => 'COMPA',
        ]);

        $this->companyB = Company::create([
            'name'         => 'Company B',
            'email'        => 'compb@test.com',
            'status'       => '1',
            'user_id'      => $this->superAdmin->id,
            'company_code' => 'COMPB',
        ]);

        $this->superAdmin->update(['company_id' => $this->companyA->id]);

        $this->companyAdminA = User::create([
            'name'       => 'Admin A',
            'email'      => 'admina@test.com',
            'password'   => bcrypt('password'),
            'role_id'    => Role::COMPANY_ADMIN,
            'role'       => 'company',
            'company_id' => $this->companyA->id,
            'status'     => 1,
        ]);

        $this->companyAdminB = User::create([
            'name'       => 'Admin B',
            'email'      => 'adminb@test.com',
            'password'   => bcrypt('password'),
            'role_id'    => Role::COMPANY_ADMIN,
            'role'       => 'company',
            'company_id' => $this->companyB->id,
            'status'     => 1,
        ]);

        $this->hrUserA = User::create([
            'name'       => 'HR User A',
            'email'      => 'hra@test.com',
            'password'   => bcrypt('password'),
            'role_id'    => Role::HR,
            'role'       => 'hr',
            'company_id' => $this->companyA->id,
            'status'     => 1,
        ]);

        $this->employeeUserA = User::create([
            'name'       => 'Employee A',
            'email'      => 'employeea@test.com',
            'password'   => bcrypt('password'),
            'role_id'    => Role::EMPLOYEE,
            'role'       => 'employee',
            'company_id' => $this->companyA->id,
            'status'     => 1,
        ]);
    }

    public function test_super_admin_can_open_device_management(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.attendance.devices.index'));
        $response->assertStatus(200);
        $response->assertSee('DELI Biometric Device Integration');
        $response->assertSee(route('admin.attendance.devices.index'));
    }

    public function test_super_admin_all_companies_mode_hides_readiness_data(): void
    {
        AttendanceDevice::create([
            'company_id' => $this->companyA->id,
            'name'       => 'Device Alpha',
            'is_active'  => true,
        ]);

        AttendanceDevice::create([
            'company_id' => $this->companyB->id,
            'name'       => 'Device Beta',
            'is_active'  => true,
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('admin.attendance.devices.index', ['company_id' => 'all']));
        $response->assertStatus(200);
        $response->assertSee('Device Alpha');
        $response->assertSee('Device Beta');
        $response->assertSee('Select a company to view biometric mapping readiness');
    }

    public function test_company_admin_can_manage_own_devices(): void
    {
        $response = $this->actingAs($this->companyAdminA)->get(route('company.attendance.devices.index'));
        $response->assertStatus(200);
        $response->assertSee('DELI Biometric Device Integration');
        $response->assertSee(route('company.attendance.devices.index'));
    }

    public function test_disabling_attendance_permission_blocks_company_admin(): void
    {
        $attendanceMenu = \App\Models\Menu::where('module', 'attendance')->first();
        if ($attendanceMenu) {
            \App\Models\MenuRoleAccess::where('company_id', $this->companyA->id)
                ->where('role_id', Role::COMPANY_ADMIN)
                ->where('menu_id', $attendanceMenu->id)
                ->delete();

            \App\Models\MenuRoleAccess::create([
                'company_id' => $this->companyA->id,
                'role_id'    => Role::COMPANY_ADMIN,
                'menu_id'    => $attendanceMenu->id,
                'is_enabled' => false,
            ]);

            $response = $this->actingAs($this->companyAdminA)->get(route('company.attendance.devices.index'));
            $response->assertStatus(403);
        }
    }

    public function test_hr_user_cannot_manage_devices(): void
    {
        $response = $this->actingAs($this->hrUserA)->get(route('company.attendance.devices.index'));
        $response->assertStatus(403);
    }

    public function test_employee_user_cannot_manage_devices(): void
    {
        $response = $this->actingAs($this->employeeUserA)->get(route('company.attendance.devices.index'));
        $response->assertStatus(403);
    }

    public function test_super_admin_can_register_device_for_any_company(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('admin.attendance.devices.store'), [
            'company_id' => $this->companyB->id,
            'name'       => 'Super Registered Device',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendance_devices', [
            'company_id' => $this->companyB->id,
            'name'       => 'Super Registered Device',
            'is_active'  => 1,
        ]);
    }

    public function test_company_admin_registers_device_forced_to_own_company(): void
    {
        $response = $this->actingAs($this->companyAdminA)->post(route('company.attendance.devices.store'), [
            'company_id' => $this->companyB->id, // Attempt to tamper
            'name'       => 'Company A Device',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendance_devices', [
            'company_id' => $this->companyA->id,
            'name'       => 'Company A Device',
        ]);
        $this->assertDatabaseMissing('attendance_devices', [
            'company_id' => $this->companyB->id,
            'name'       => 'Company A Device',
        ]);
    }

    public function test_device_receives_unique_public_id_and_token_and_upload_url(): void
    {
        $device = AttendanceDevice::create([
            'company_id' => $this->companyA->id,
            'name'       => 'Main Entrance DELI',
            'is_active'  => true,
        ]);

        $this->assertNotEmpty($device->public_id);
        $this->assertNotEmpty($device->upload_token);
        $this->assertStringContainsString($device->public_id, $device->upload_url);
        $this->assertStringContainsString($device->upload_token, $device->upload_url);
    }

    public function test_company_admin_cannot_access_or_modify_other_company_device(): void
    {
        $deviceB = AttendanceDevice::create([
            'company_id' => $this->companyB->id,
            'name'       => 'Company B Secret Device',
            'is_active'  => true,
        ]);

        // Toggle attempt by Company Admin A
        $responseToggle = $this->actingAs($this->companyAdminA)->post(route('company.attendance.devices.toggle', $deviceB->id));
        $responseToggle->assertStatus(403);

        // Rotate token attempt by Company Admin A
        $responseRotate = $this->actingAs($this->companyAdminA)->post(route('company.attendance.devices.rotate-token', $deviceB->id));
        $responseRotate->assertStatus(403);

        // Reset position attempt by Company Admin A
        $responseReset = $this->actingAs($this->companyAdminA)->post(route('company.attendance.devices.reset-position', $deviceB->id), [
            'position' => 10,
        ]);
        $responseReset->assertStatus(403);

        // View logs attempt by Company Admin A
        $responseLogs = $this->actingAs($this->companyAdminA)->get(route('company.attendance.devices.logs', $deviceB->id));
        $responseLogs->assertStatus(403);
    }

    public function test_device_status_toggle_action(): void
    {
        $device = AttendanceDevice::create([
            'company_id' => $this->companyA->id,
            'name'       => 'Toggle Device',
            'is_active'  => true,
        ]);

        $this->actingAs($this->companyAdminA)->post(route('company.attendance.devices.toggle', $device->id));
        $this->assertFalse($device->fresh()->is_active);

        $this->actingAs($this->companyAdminA)->post(route('company.attendance.devices.toggle', $device->id));
        $this->assertTrue($device->fresh()->is_active);
    }

    public function test_token_rotation_invalidates_previous_token(): void
    {
        $device = AttendanceDevice::create([
            'company_id' => $this->companyA->id,
            'name'       => 'Rotate Device',
            'is_active'  => true,
        ]);

        $oldToken = $device->upload_token;

        $this->actingAs($this->companyAdminA)->post(route('company.attendance.devices.rotate-token', $device->id));

        $newToken = $device->fresh()->upload_token;
        $this->assertNotEquals($oldToken, $newToken);
    }

    public function test_reset_position_updates_pointer_value(): void
    {
        $device = AttendanceDevice::create([
            'company_id'      => $this->companyA->id,
            'name'            => 'Pointer Device',
            'is_active'       => true,
            'next_upload_pos' => 150,
        ]);

        // Reset to null
        $this->actingAs($this->companyAdminA)->post(route('company.attendance.devices.reset-position', $device->id), [
            'position' => 'null',
        ]);
        $this->assertNull($device->fresh()->next_upload_pos);

        // Reset to 0
        $this->actingAs($this->companyAdminA)->post(route('company.attendance.devices.reset-position', $device->id), [
            'position' => '0',
        ]);
        $this->assertEquals(0, $device->fresh()->next_upload_pos);

        // Reset to 42
        $this->actingAs($this->companyAdminA)->post(route('company.attendance.devices.reset-position', $device->id), [
            'position' => '42',
        ]);
        $this->assertEquals(42, $device->fresh()->next_upload_pos);

        // Invalid position (string "abc") must fail validation and not change pointer
        $response = $this->actingAs($this->companyAdminA)->post(route('company.attendance.devices.reset-position', $device->id), [
            'position' => 'abc',
        ]);
        $response->assertSessionHasErrors('position');
        $this->assertEquals(42, $device->fresh()->next_upload_pos);
    }

    public function test_readiness_duplicate_check_detects_inactive_employee_duplicates(): void
    {
        // Active employee with biometric_id 555
        EmployeeMaster::create([
            'company_id'    => $this->companyA->id,
            'employee_code' => 'EMP-ACT555',
            'first_name'    => 'Active',
            'last_name'     => 'User',
            'biometric_id'  => '555',
            'status'        => 'active',
            'date_of_birth' => '1990-01-01',
            'date_hired'    => '2020-01-01',
        ]);

        // Inactive employee with SAME biometric_id 555
        EmployeeMaster::create([
            'company_id'    => $this->companyA->id,
            'employee_code' => 'EMP-INA555',
            'first_name'    => 'Inactive',
            'last_name'     => 'User',
            'biometric_id'  => '555',
            'status'        => 'inactive',
            'date_of_birth' => '1985-01-01',
            'date_hired'    => '2019-01-01',
        ]);

        $response = $this->actingAs($this->companyAdminA)->get(route('company.attendance.devices.index'));
        $response->assertStatus(200);
        // The duplicate ID 555 must be surfaced in readiness card
        $response->assertSee('555');
        $response->assertSee('Ambiguity Warning');
    }

    public function test_safe_test_handshake_simulation_does_not_mutate_last_seen_at(): void
    {
        $device = AttendanceDevice::create([
            'company_id'   => $this->companyA->id,
            'name'         => 'Sim Device',
            'is_active'    => true,
            'last_seen_at' => null,
        ]);

        $response = $this->actingAs($this->companyAdminA)->post(route('company.attendance.devices.test-handshake', $device->id));
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status'  => 200,
        ]);

        $this->assertNull($device->fresh()->last_seen_at, 'Simulated test handshake must NOT update last_seen_at');
    }

    public function test_viewing_device_logs(): void
    {
        $emp = EmployeeMaster::create([
            'company_id'    => $this->companyA->id,
            'employee_code' => 'EMP-999',
            'first_name'    => 'John',
            'last_name'     => 'Doe',
            'biometric_id'  => '999',
            'status'        => 'active',
            'date_of_birth' => '1990-01-01',
            'date_hired'    => '2020-01-01',
        ]);

        $device = AttendanceDevice::create([
            'company_id' => $this->companyA->id,
            'name'       => 'Logs View Device',
            'is_active'  => true,
        ]);

        AttendanceLog::create([
            'employee_id'   => $emp->id,
            'company_id'    => $this->companyA->id,
            'biometric_id'  => '999',
            'log_date'      => '2026-08-14',
            'log_time'      => '08:30:00',
            'log_datetime'  => '2026-08-14 08:30:00',
            'log_type'      => 'in',
            'device_name'   => $device->name,
            'device_serial' => 'DELI:' . $device->public_id,
            'import_status' => 'processed',
        ]);

        $response = $this->actingAs($this->companyAdminA)->get(route('company.attendance.devices.logs', $device->id));
        $response->assertStatus(200);
        $response->assertSee('John Doe');
        $response->assertSee('DELI:' . $device->public_id);
    }

    public function test_existing_manual_attendance_import_behavior_still_works(): void
    {
        $emp = EmployeeMaster::create([
            'company_id'    => $this->companyA->id,
            'employee_code' => 'EMP-777',
            'first_name'    => 'Manual',
            'last_name'     => 'ImportUser',
            'biometric_id'  => '777',
            'status'        => 'active',
            'date_of_birth' => '1990-01-01',
            'date_hired'    => '2020-01-01',
        ]);

        $fileContent = "777,2026-08-14 08:30:00\n777,2026-08-14 17:30:00\n";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('attendance.csv', $fileContent);

        $response = $this->actingAs($this->companyAdminA)->post(route('company.attendance.process-import'), [
            'company_id'  => $this->companyA->id,
            'import_file' => $file,
        ]);

        $response->assertRedirect(route('attendance.index'));

        $this->assertDatabaseHas('attendance_logs', [
            'employee_id'  => $emp->id,
            'company_id'   => $this->companyA->id,
            'biometric_id' => '777',
            'log_time'     => '08:30:00',
            'log_type'     => 'in',
        ]);

        $this->assertDatabaseHas('attendance_logs', [
            'employee_id'  => $emp->id,
            'company_id'   => $this->companyA->id,
            'biometric_id' => '777',
            'log_time'     => '17:30:00',
            'log_type'     => 'out',
        ]);
    }
}
