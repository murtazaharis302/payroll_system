<?php

namespace Tests\Feature;

use App\Models\AttendanceDevice;
use App\Models\AttendanceDevicePendingLog;
use App\Models\AttendanceDeviceUserMapping;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliAttendanceDeviceMappingTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $superAdmin;
    protected User $companyAdminA;
    protected EmployeeMaster $employeeA1;
    protected EmployeeMaster $employeeA2;
    protected EmployeeMaster $employeeB1;
    protected AttendanceDevice $deviceA;
    protected Department $departmentA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\MenusTableSeeder::class);

        $this->superAdmin = User::create([
            'name' => 'Super Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password'),
            'role_id' => Role::SUPER_ADMIN, 'role' => 'admin', 'status' => 1,
        ]);

        $this->companyA = Company::create(['name' => 'Company Alpha', 'email' => 'alpha@test.com', 'status' => '1', 'user_id' => $this->superAdmin->id, 'company_code' => 'ALPHA']);
        $this->companyB = Company::create(['name' => 'Company Beta', 'email' => 'beta@test.com', 'status' => '1', 'user_id' => $this->superAdmin->id, 'company_code' => 'BETA']);

        $this->companyAdminA = User::create([
            'name' => 'Admin A', 'email' => 'admina@test.com', 'password' => bcrypt('password'),
            'role_id' => Role::COMPANY_ADMIN, 'role' => 'company', 'company_id' => $this->companyA->id, 'status' => 1,
        ]);

        $this->departmentA = Department::create([
            'company_id' => $this->companyA->id,
            'code' => 'ENG',
            'name' => 'Engineering Department',
            'status' => '1',
        ]);

        $this->employeeA1 = EmployeeMaster::create([
            'company_id' => $this->companyA->id, 'dept_id' => $this->departmentA->id, 'employee_code' => 'EMP-A101', 'first_name' => 'John', 'last_name' => 'Santos',
            'biometric_id' => '101', 'status' => 'active', 'date_of_birth' => '1990-01-01', 'date_hired' => '2020-01-01',
        ]);

        $this->employeeA2 = EmployeeMaster::create([
            'company_id' => $this->companyA->id, 'dept_id' => $this->departmentA->id, 'employee_code' => 'EMP-A102', 'first_name' => 'Maria', 'last_name' => 'Cruz',
            'biometric_id' => null, 'status' => 'active', 'date_of_birth' => '1992-02-02', 'date_hired' => '2020-01-01',
        ]);

        $this->employeeB1 = EmployeeMaster::create([
            'company_id' => $this->companyB->id, 'employee_code' => 'EMP-B101', 'first_name' => 'Bob', 'last_name' => 'Beta',
            'biometric_id' => '101', 'status' => 'active', 'date_of_birth' => '1988-08-08', 'date_hired' => '2020-01-01',
        ]);

        $this->deviceA = AttendanceDevice::create([
            'company_id' => $this->companyA->id,
            'name' => 'Alpha Main Biometric',
            'is_active' => true,
        ]);
    }

    public function test_server_address_normalization_and_url_generation(): void
    {
        // 1. Railway HTTPS URL
        $this->deviceA->server_address = AttendanceDevice::normalizeServerAddress('https://payrollsystem-production-5676.up.railway.app/');
        $this->deviceA->save();
        $this->assertStringStartsWith('https://payrollsystem-production-5676.up.railway.app/api/deli/attendance/', $this->deviceA->upload_url);

        // 2. LAN HTTP URL with explicit port
        $this->deviceA->server_address = AttendanceDevice::normalizeServerAddress('http://192.168.1.50:8000///');
        $this->deviceA->save();
        $this->assertStringStartsWith('http://192.168.1.50:8000/api/deli/attendance/', $this->deviceA->upload_url);

        // 3. Custom domain
        $this->deviceA->server_address = AttendanceDevice::normalizeServerAddress('https://payroll.company.local');
        $this->deviceA->save();
        $this->assertStringStartsWith('https://payroll.company.local/api/deli/attendance/', $this->deviceA->upload_url);
    }

    public function test_server_address_rejects_paths_queries_and_localhost(): void
    {
        // Path rejection
        $this->expectException(\InvalidArgumentException::class);
        AttendanceDevice::normalizeServerAddress('https://server.com/payroll');
    }

    public function test_server_address_rejects_query_parameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AttendanceDevice::normalizeServerAddress('https://server.com?test=1');
    }

    public function test_server_address_rejects_localhost(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AttendanceDevice::normalizeServerAddress('http://127.0.0.1:8000');
    }

    public function test_default_server_address_returns_null_when_local_or_unset(): void
    {
        config(['app.url' => 'http://localhost']);
        $this->assertNull(AttendanceDevice::getDefaultServerAddress());
    }

    public function test_open_employee_mapping_page_renders_with_dept_and_pending_punches(): void
    {
        // Add one active mapping
        AttendanceDeviceUserMapping::create([
            'attendance_device_id' => $this->deviceA->id,
            'device_user_id' => '101',
            'employee_id' => $this->employeeA1->id,
        ]);

        // Add one pending unmapped log
        AttendanceDevicePendingLog::create([
            'attendance_device_id' => $this->deviceA->id,
            'device_user_id' => '205',
            'logged_at' => Carbon::parse('2026-08-14 08:30:00'),
            'mode' => 'fp',
            'status' => 'pending',
        ]);

        // Super Admin opens mapping view
        $responseAdmin = $this->actingAs($this->superAdmin)->get(route('admin.attendance.devices.mappings', $this->deviceA->id));
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertSee('Employee Mapping: Alpha Main Biometric');
        $responseAdmin->assertSee('Engineering Department');
        $responseAdmin->assertSee('John Santos');
        $responseAdmin->assertSee('205');
        $responseAdmin->assertSee('1 punch');

        // Company Admin opens mapping view
        $responseComp = $this->actingAs($this->companyAdminA)->get(route('company.attendance.devices.mappings', $this->deviceA->id));
        $responseComp->assertStatus(200);
        $responseComp->assertSee('Engineering Department');
        $responseComp->assertSee('John Santos');
    }

    public function test_unique_biometric_id_match_automatically_creates_explicit_device_mapping(): void
    {
        $this->assertDatabaseMissing('attendance_device_user_mappings', [
            'attendance_device_id' => $this->deviceA->id,
            'device_user_id' => '101',
        ]);

        $url = route('api.deli.attendance', [
            'publicId' => $this->deviceA->public_id,
            'token' => $this->deviceA->upload_token,
        ]);

        $response = $this->postJson($url, [
            'start_pos' => 0,
            'next_pos' => 10,
            'logs' => [
                ['user_id' => '101', 'time' => '2026-08-14T08:00:00', 'mode' => 'face'],
            ],
        ]);

        $response->assertStatus(200);

        // Explicit mapping auto-created
        $this->assertDatabaseHas('attendance_device_user_mappings', [
            'attendance_device_id' => $this->deviceA->id,
            'device_user_id' => '101',
            'employee_id' => $this->employeeA1->id,
        ]);

        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employeeA1->id,
            'log_time' => '08:00:00',
            'log_type' => 'in',
        ]);
    }

    public function test_explicit_mapping_overrides_biometric_id_fallback(): void
    {
        // Explicitly map DELI user 101 to employee A2 (Maria) instead of A1 (John who has biometric_id 101)
        AttendanceDeviceUserMapping::create([
            'attendance_device_id' => $this->deviceA->id,
            'device_user_id' => '101',
            'employee_id' => $this->employeeA2->id,
        ]);

        $url = route('api.deli.attendance', [
            'publicId' => $this->deviceA->public_id,
            'token' => $this->deviceA->upload_token,
        ]);

        $response = $this->postJson($url, [
            'start_pos' => 0,
            'next_pos' => 10,
            'logs' => [
                ['user_id' => '101', 'time' => '2026-08-14T08:00:00', 'mode' => 'face'],
            ],
        ]);

        $response->assertStatus(200);

        // Log must be created for Maria (employeeA2), NOT John (employeeA1)
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employeeA2->id,
            'log_time' => '08:00:00',
        ]);
        $this->assertDatabaseMissing('attendance_logs', [
            'employee_id' => $this->employeeA1->id,
        ]);
    }

    public function test_cross_company_employee_mapping_is_rejected(): void
    {
        // Try mapping device A (Company A) to employee B1 (Company B)
        $response = $this->actingAs($this->companyAdminA)->post(route('company.attendance.devices.store-mapping', $this->deviceA->id), [
            'device_user_id' => '999',
            'employee_id' => $this->employeeB1->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_unknown_user_punches_are_preserved_as_pending_and_recovered_chronologically_when_mapped(): void
    {
        $url = route('api.deli.attendance', [
            'publicId' => $this->deviceA->public_id,
            'token' => $this->deviceA->upload_token,
        ]);

        // Upload 2 punches for unmapped DELI user 117 (08:00 and 17:00)
        $response = $this->postJson($url, [
            'start_pos' => 0,
            'next_pos' => 20,
            'logs' => [
                ['user_id' => '117', 'time' => '2026-08-14T08:00:00', 'mode' => 'face'],
                ['user_id' => '117', 'time' => '2026-08-14T17:00:00', 'mode' => 'face'],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertExactJson(['next_upload_pos' => 20]);

        // Pointer advanced, but no AttendanceLogs yet
        $this->assertEquals(20, $this->deviceA->fresh()->next_upload_pos);
        $this->assertDatabaseCount('attendance_logs', 0);

        // 2 pending logs created with status 'pending'
        $this->assertDatabaseCount('attendance_device_pending_logs', 2);
        $this->assertDatabaseHas('attendance_device_pending_logs', [
            'attendance_device_id' => $this->deviceA->id,
            'device_user_id' => '117',
            'status' => 'pending',
            'processed_at' => null,
        ]);

        // Administrator opens Mapping UI and maps DELI User 117 -> Maria Cruz (employeeA2)
        $responseMap = $this->actingAs($this->companyAdminA)->post(route('company.attendance.devices.store-mapping', $this->deviceA->id), [
            'device_user_id' => '117',
            'employee_id' => $this->employeeA2->id,
        ]);

        $responseMap->assertStatus(302);
        $responseMap->assertSessionHas('statusSuccess');

        // Both punches recovered into AttendanceLog chronologically as in (08:00) and out (17:00)
        $this->assertDatabaseCount('attendance_logs', 2);

        $logIn = AttendanceLog::where('employee_id', $this->employeeA2->id)
            ->where('log_time', '08:00:00')
            ->first();
        $this->assertNotNull($logIn);
        $this->assertEquals('in', $logIn->log_type);

        $logOut = AttendanceLog::where('employee_id', $this->employeeA2->id)
            ->where('log_time', '17:00:00')
            ->first();
        $this->assertNotNull($logOut);
        $this->assertEquals('out', $logOut->log_type);

        // Pending logs are marked 'processed' with processed_at timestamp
        $pendingLogs = AttendanceDevicePendingLog::where('device_user_id', '117')->get();
        $this->assertCount(2, $pendingLogs);
        foreach ($pendingLogs as $pl) {
            $this->assertEquals('processed', $pl->status);
            $this->assertNotNull($pl->processed_at);
        }

        // Future uploads for user 117 automatically use the saved mapping without creating pending logs
        $responseFuture = $this->postJson($url, [
            'start_pos' => 20,
            'next_pos' => 30,
            'logs' => [
                ['user_id' => '117', 'time' => '2026-08-15T08:05:00', 'mode' => 'face'],
            ],
        ]);

        $responseFuture->assertStatus(200);
        $this->assertDatabaseCount('attendance_logs', 3);
        $this->assertDatabaseCount('attendance_device_pending_logs', 2); // No new pending rows
    }

    public function test_registration_requires_server_address_when_no_default_exists_and_never_generates_localhost_url(): void
    {
        config(['app.url' => 'http://localhost']);

        // 1. Attempt registering device without server_address in local environment => validation fails
        $response = $this->actingAs($this->companyAdminA)->post(route('company.attendance.devices.store'), [
            'name' => 'Local Device Unit',
            'server_address' => '',
        ]);

        $response->assertSessionHasErrors('server_address');
        $this->assertDatabaseMissing('attendance_devices', ['name' => 'Local Device Unit']);

        // 2. An existing device with null server_address in local environment returns null upload_url (never localhost)
        $orphanDevice = AttendanceDevice::create([
            'company_id' => $this->companyA->id,
            'name' => 'Orphan Unit',
            'server_address' => null,
            'is_active' => true,
        ]);

        $this->assertNull($orphanDevice->upload_url);

        // 3. Registering with valid reachable server address succeeds
        $responseSuccess = $this->actingAs($this->companyAdminA)->post(route('company.attendance.devices.store'), [
            'name' => 'LAN Device Unit',
            'server_address' => 'http://192.168.1.120:8000',
        ]);

        $responseSuccess->assertStatus(302);
        $responseSuccess->assertSessionHas('statusSuccess');

        $createdDevice = AttendanceDevice::where('name', 'LAN Device Unit')->first();
        $this->assertNotNull($createdDevice);
        $this->assertEquals('http://192.168.1.120:8000', $createdDevice->server_address);
        $this->assertStringStartsWith('http://192.168.1.120:8000/api/deli/attendance/', $createdDevice->upload_url);
    }
}
