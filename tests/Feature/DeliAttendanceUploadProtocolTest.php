<?php

namespace Tests\Feature;

use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliAttendanceUploadProtocolTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected EmployeeMaster $employeeA1;
    protected EmployeeMaster $employeeA2;
    protected EmployeeMaster $employeeB1;
    protected AttendanceDevice $deviceA;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::create([
            'name' => 'Super Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password'),
            'role_id' => Role::SUPER_ADMIN, 'role' => 'admin', 'status' => 1,
        ]);

        $this->companyA = Company::create(['name' => 'Company Alpha', 'email' => 'alpha@test.com', 'status' => '1', 'user_id' => $admin->id, 'company_code' => 'ALPHA']);
        $this->companyB = Company::create(['name' => 'Company Beta', 'email' => 'beta@test.com', 'status' => '1', 'user_id' => $admin->id, 'company_code' => 'BETA']);

        $this->employeeA1 = EmployeeMaster::create([
            'company_id' => $this->companyA->id, 'employee_code' => 'EMP-A101', 'first_name' => 'Alice', 'last_name' => 'Alpha',
            'biometric_id' => '101', 'status' => 'active', 'date_of_birth' => '1990-01-01', 'date_hired' => '2020-01-01',
        ]);

        $this->employeeA2 = EmployeeMaster::create([
            'company_id' => $this->companyA->id, 'employee_code' => 'EMP-A102', 'first_name' => 'Arthur', 'last_name' => 'Alpha',
            'biometric_id' => '102', 'status' => 'active', 'date_of_birth' => '1992-02-02', 'date_hired' => '2020-01-01',
        ]);

        $this->employeeB1 = EmployeeMaster::create([
            'company_id' => $this->companyB->id, 'employee_code' => 'EMP-B101', 'first_name' => 'Bob', 'last_name' => 'Beta',
            'biometric_id' => '101', 'status' => 'active', 'date_of_birth' => '1988-08-08', 'date_hired' => '2020-01-01',
        ]);

        $this->deviceA = AttendanceDevice::create([
            'company_id' => $this->companyA->id, 'name' => 'Alpha Main Biometric', 'is_active' => true,
        ]);
    }

    public function test_invalid_token_or_public_id_is_rejected(): void
    {
        $urlInvalidToken = route('api.deli.attendance', [
            'publicId' => $this->deviceA->public_id,
            'token'    => 'invalid_token_value_123',
        ]);

        $response = $this->call('POST', $urlInvalidToken, [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        $response->assertStatus(404);

        $urlInvalidDevice = route('api.deli.attendance', [
            'publicId' => 'non-existent-device-uuid',
            'token'    => $this->deviceA->upload_token,
        ]);

        $response2 = $this->call('POST', $urlInvalidDevice, [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        $response2->assertStatus(404);
    }

    public function test_inactive_device_is_rejected(): void
    {
        $this->deviceA->update(['is_active' => false]);

        $url = route('api.deli.attendance', [
            'publicId' => $this->deviceA->public_id,
            'token'    => $this->deviceA->upload_token,
        ]);

        $response = $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        $response->assertStatus(403);
    }

    public function test_new_device_handshake_returns_empty_json_and_updates_last_seen(): void
    {
        $this->assertNull($this->deviceA->next_upload_pos);
        $this->assertNull($this->deviceA->last_seen_at);

        $url = route('api.deli.attendance', [
            'publicId' => $this->deviceA->public_id,
            'token'    => $this->deviceA->upload_token,
        ]);

        // Exact raw {} empty JSON object
        $response = $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        $response->assertStatus(200);
        $response->assertExactJson([]);

        $this->assertNotNull($this->deviceA->fresh()->last_seen_at);
    }

    public function test_empty_body_and_json_array_are_rejected_with_422(): void
    {
        $url = route('api.deli.attendance', [
            'publicId' => $this->deviceA->public_id,
            'token'    => $this->deviceA->upload_token,
        ]);

        // Empty body ""
        $responseEmpty = $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/json'], '');
        $responseEmpty->assertStatus(422);

        // JSON array "[]"
        $responseArray = $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/json'], '[]');
        $responseArray->assertStatus(422);

        // Scalar JSON string
        $responseScalar = $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/json'], '"just_a_string"');
        $responseScalar->assertStatus(422);
    }

    public function test_synchronized_device_handshake_returns_stored_position(): void
    {
        $this->deviceA->update(['next_upload_pos' => 50]);

        $url = route('api.deli.attendance', [
            'publicId' => $this->deviceA->public_id,
            'token'    => $this->deviceA->upload_token,
        ]);

        // Exact raw {}
        $response = $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        $response->assertStatus(200);
        $response->assertExactJson(['next_upload_pos' => 50]);
    }

    public function test_batch_upload_creates_attendance_logs_with_exact_protocol_shape(): void
    {
        $url = route('api.deli.attendance', [
            'publicId' => $this->deviceA->public_id,
            'token'    => $this->deviceA->upload_token,
        ]);

        $payload = [
            'start_pos' => 50,
            'next_pos'  => 100,
            'logs'      => [
                ['user_id' => '101', 'time' => '2026-08-14T08:10:05', 'mode' => 'face'],
                ['user_id' => '101', 'time' => '2026-08-14T17:15:10', 'mode' => 'fp'],
            ],
        ];

        $response = $this->postJson($url, $payload);
        $response->assertStatus(200);
        $response->assertExactJson(['next_upload_pos' => 100]);

        // Verify two records created
        $this->assertDatabaseCount('attendance_logs', 2);

        $logIn = AttendanceLog::where('employee_id', $this->employeeA1->id)
            ->where('log_time', '08:10:05')
            ->first();

        $this->assertNotNull($logIn);
        $this->assertEquals('in', $logIn->log_type);
        $this->assertEquals('2026-08-14', $logIn->log_date->toDateString());
        $this->assertEquals($this->companyA->id, $logIn->company_id);
        $this->assertEquals($this->deviceA->name, $logIn->device_name);
        $this->assertEquals('DELI:' . $this->deviceA->public_id, $logIn->device_serial);
        $this->assertEquals('processed', $logIn->import_status);
        $this->assertNull($logIn->imported_by);

        $logOut = AttendanceLog::where('employee_id', $this->employeeA1->id)
            ->where('log_time', '17:15:10')
            ->first();

        $this->assertNotNull($logOut);
        $this->assertEquals('out', $logOut->log_type);

        // Verify device state updated
        $freshDevice = $this->deviceA->fresh();
        $this->assertEquals(100, $freshDevice->next_upload_pos);
        $this->assertEquals(50, $freshDevice->last_start_pos);
        $this->assertEquals(100, $freshDevice->last_next_pos);
        $this->assertNotNull($freshDevice->last_upload_at);
        $this->assertEquals(2, $freshDevice->last_result['imported']);
    }

    public function test_matching_is_strictly_restricted_to_device_company(): void
    {
        // Device is in Company A, employee with biometric_id '101' exists in Company A (Alice) and Company B (Bob)
        $url = route('api.deli.attendance', [
            'publicId' => $this->deviceA->public_id,
            'token'    => $this->deviceA->upload_token,
        ]);

        $payload = [
            'start_pos' => 0,
            'next_pos'  => 10,
            'logs'      => [
                [
                    'user_id' => '101',
                    'time'    => '2026-08-14T09:00:00',
                    'mode'    => 'fp',
                ],
            ],
        ];

        $response = $this->postJson($url, $payload);
        $response->assertStatus(200);

        // Record must belong to Alice in Company A, NEVER Bob in Company B
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employeeA1->id,
            'company_id'  => $this->companyA->id,
        ]);
        $this->assertDatabaseMissing('attendance_logs', [
            'employee_id' => $this->employeeB1->id,
        ]);
    }

    public function test_ambiguous_duplicate_biometric_ids_in_same_company_are_skipped(): void
    {
        // Create duplicate biometric ID in Company A
        EmployeeMaster::create([
            'company_id'    => $this->companyA->id,
            'employee_code' => 'EMP-DUP101',
            'first_name'    => 'Duplicate',
            'last_name'     => 'Alice',
            'biometric_id'  => '101', // duplicate in Company A!
            'status'        => 'active',
            'date_of_birth' => '1995-05-05',
            'date_hired'    => '2021-01-01',
        ]);

        $url = route('api.deli.attendance', [
            'publicId' => $this->deviceA->public_id,
            'token'    => $this->deviceA->upload_token,
        ]);

        $payload = [
            'start_pos' => 0,
            'next_pos'  => 10,
            'logs'      => [
                [
                    'user_id' => '101',
                    'time'    => '2026-08-14T09:00:00',
                ],
                [
                    'user_id' => '102', // Arthur (unique)
                    'time'    => '2026-08-14T09:05:00',
                ],
            ],
        ];

        $response = $this->postJson($url, $payload);
        $response->assertStatus(200);

        // 101 must NOT be created because it's ambiguous
        $this->assertDatabaseMissing('attendance_logs', [
            'biometric_id' => '101',
        ]);

        // 102 must be created normally
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id'  => $this->employeeA2->id,
            'biometric_id' => '102',
        ]);

        $deviceResult = $this->deviceA->fresh()->last_result;
        $this->assertEquals(1, $deviceResult['imported']);
        $this->assertEquals(1, $deviceResult['ambiguous']);
    }

    public function test_duplicate_employee_date_time_logs_are_skipped_and_replays_are_idempotent(): void
    {
        $url = route('api.deli.attendance', [
            'publicId' => $this->deviceA->public_id,
            'token'    => $this->deviceA->upload_token,
        ]);

        $payload = [
            'start_pos' => 0,
            'next_pos'  => 10,
            'logs'      => [
                [
                    'user_id' => '101',
                    'time'    => '2026-08-14T08:00:00',
                ],
            ],
        ];

        // First upload
        $res1 = $this->postJson($url, $payload);
        $res1->assertStatus(200);
        $this->assertDatabaseCount('attendance_logs', 1);

        // Replay exact same payload
        $res2 = $this->postJson($url, $payload);
        $res2->assertStatus(200);
        $this->assertDatabaseCount('attendance_logs', 1); // Still 1

        $deviceResult = $this->deviceA->fresh()->last_result;
        $this->assertEquals(1, $deviceResult['duplicates']);
        $this->assertEquals(0, $deviceResult['imported']);
    }

    public function test_replay_cannot_move_upload_pointer_backwards(): void
    {
        $this->deviceA->update(['next_upload_pos' => 200]);

        $url = route('api.deli.attendance', [
            'publicId' => $this->deviceA->public_id,
            'token'    => $this->deviceA->upload_token,
        ]);

        // Replay old chunk next_pos = 100 (< 200)
        $payload = [
            'start_pos' => 50,
            'next_pos'  => 100,
            'logs'      => [
                [
                    'user_id' => '101',
                    'time'    => '2026-08-14T08:00:00',
                ],
            ],
        ];

        $response = $this->postJson($url, $payload);
        $response->assertStatus(200);
        $response->assertExactJson(['next_upload_pos' => 200]); // Must return stored pointer max(200, 100)

        $this->assertEquals(200, $this->deviceA->fresh()->next_upload_pos);
    }

    public function test_invalid_individual_log_does_not_crash_valid_batch(): void
    {
        $url = route('api.deli.attendance', [
            'publicId' => $this->deviceA->public_id,
            'token'    => $this->deviceA->upload_token,
        ]);

        $payload = [
            'start_pos' => 0,
            'next_pos'  => 10,
            'logs'      => [
                [
                    'user_id' => '101',
                    'time'    => 'NOT_A_VALID_DATE',
                ],
                [
                    'user_id' => '', // Missing user_id
                    'time'    => '2026-08-14T08:00:00',
                ],
                [
                    'user_id' => '99999', // Unmatched biometric ID
                    'time'    => '2026-08-14T08:00:00',
                ],
                [
                    'user_id' => '102', // Valid Arthur
                    'time'    => '2026-08-14T08:30:00',
                ],
            ],
        ];

        $response = $this->postJson($url, $payload);
        $response->assertStatus(200);
        $response->assertExactJson(['next_upload_pos' => 10]);

        $this->assertDatabaseCount('attendance_logs', 1);
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employeeA2->id,
            'log_time'    => '08:30:00',
        ]);

        $result = $this->deviceA->fresh()->last_result;
        $this->assertEquals(4, $result['received']);
        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(2, $result['invalid']);
        $this->assertEquals(1, $result['unmatched']);
        $this->assertContains('99999', $result['unmatched_user_ids']);
    }

    public function test_malformed_top_level_payload_returns_422(): void
    {
        $url = route('api.deli.attendance', [
            'publicId' => $this->deviceA->public_id,
            'token'    => $this->deviceA->upload_token,
        ]);

        // Missing logs array
        $response = $this->postJson($url, [
            'start_pos' => 10,
            'next_pos'  => 20,
        ]);
        $response->assertStatus(422);

        // Negative start_pos
        $response2 = $this->postJson($url, [
            'start_pos' => -1,
            'next_pos'  => 10,
            'logs'      => [],
        ]);
        $response2->assertStatus(422);

        // next_pos < start_pos
        $response3 = $this->postJson($url, [
            'start_pos' => 50,
            'next_pos'  => 20,
            'logs'      => [],
        ]);
        $response3->assertStatus(422);

        // Non-empty non-batch payload (e.g. {"abc": 1}) must NOT be accepted as handshake
        $response4 = $this->postJson($url, [
            'abc' => 1,
        ]);
        $response4->assertStatus(422);
    }
}
