<?php

namespace App\Services;

use App\Mail\EmployeeConformation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeProvisioningService
{
    /**
     * Atomically provision a User and dependent Employee record.
     *
     * @param  array  $data  Validated input data
     * @param  int  $ownerUserId  Owner user ID
     * @param  int  $companyId  Company ID
     * @return array Array containing created 'user' and 'employee'
     *
     * @throws ValidationException
     */
    public function provision(array $data, int $ownerUserId, int $companyId): array
    {
        $provisioningMethod = config('auth.employee_password_provisioning', 'manual');

        if ($provisioningMethod !== 'invitation' && empty($data['password'])) {
            throw ValidationException::withMessages([
                'password' => ['An initial password is required.'],
            ]);
        }

        try {
            $result = DB::transaction(function () use ($data, $ownerUserId, $companyId, $provisioningMethod) {
                $user = new User;
                $user->name = $data['name'];
                $user->email = $data['email'];
                $user->password = $provisioningMethod === 'invitation'
                    ? Hash::make(Str::random(32))
                    : Hash::make($data['password']);
                $user->role = $data['role'];
                $user->phone = $data['phone'];
                $user->company_id = $companyId;
                $user->status = 1;
                $user->save();

                $employee = new Employee;
                $employee->owner_user_id = $ownerUserId;
                $employee->designation = ucfirst($data['role']);
                $employee->e_user_id = $user->id;
                $employee->id_number = Employee::generateIdNumber();
                $employee->age = $data['age'] ?? null;
                $employee->address = $data['address'];
                $employee->save();

                return ['user' => $user, 'employee' => $employee];
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                throw ValidationException::withMessages([
                    'email' => ['An account with this email address already exists.'],
                ]);
            }
            throw $e;
        }

        $user = $result['user'];

        if ($provisioningMethod === 'invitation' && config('mail.notifications_enabled', true)) {
            DB::afterCommit(function () use ($user) {
                try {
                    $token = Password::broker()->createToken($user);
                    $setupUrl = route('password.reset', ['token' => $token, 'email' => $user->email]);

                    Mail::to($user->email)->queue(new EmployeeConformation($user, $setupUrl));
                } catch (\Throwable $e) {
                    Log::warning('Employee setup email queue failed: '.$e->getMessage());
                }
            });
        }

        return $result;
    }
}
