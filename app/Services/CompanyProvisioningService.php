<?php

namespace App\Services;

use App\Mail\CompanyCreated;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompanyProvisioningService
{
    /**
     * Atomically provision a User and dependent Company record.
     *
     * @param array $data Validated input data
     * @return array Array containing created 'user' and 'company'
     * @throws ValidationException
     */
    public function provision(array $data): array
    {
        try {
            $result = DB::transaction(function () use ($data) {
                $user = new User();
                $user->name = $data['name'];
                $user->email = $data['email'];
                $user->password = !empty($data['password'])
                    ? Hash::make($data['password'])
                    : Hash::make(Str::random(32));
                $user->role = 'company';
                $user->status = 0; // Pending approval
                $user->save();

                $company = new Company();
                $company->user_id = $user->id;
                $company->name = $data['companyName'];
                if (!empty($data['company_code'])) {
                    $company->company_code = strtoupper(trim($data['company_code']));
                } else {
                    $company->company_code = Company::generateCompanyCode();
                }
                $company->email = $data['companyEmail'];
                $company->phone = $data['phone'];
                $company->website = $data['website'];
                $company->address = $data['address'] ?? null;
                $company->status = 0; // Pending approval
                $company->save();

                return ['user' => $user, 'company' => $company];
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                $messages = [];
                $errMsg = $e->getMessage();

                if (str_contains($errMsg, 'company_code')) {
                    $messages['company_code'] = ['The company code has already been taken.'];
                } elseif (str_contains($errMsg, 'companies.name') || str_contains($errMsg, 'companies_name')) {
                    $messages['companyName'] = ['The company name has already been taken.'];
                } elseif (str_contains($errMsg, 'companies.email') || str_contains($errMsg, 'companyEmail')) {
                    $messages['companyEmail'] = ['The company email has already been taken.'];
                } else {
                    $messages['email'] = ['The email address has already been taken.'];
                }

                throw ValidationException::withMessages($messages);
            }
            throw $e;
        }

        $user = $result['user'];
        $company = $result['company'];

        if (config('mail.notifications_enabled', true)) {
            DB::afterCommit(function () use ($user, $company) {
                try {
                    Mail::to($user->email)->queue(new CompanyCreated($company, $user));
                } catch (\Throwable $e) {
                    Log::error('Company creation email queuing failed: ' . $e->getMessage());
                }

                try {
                    $adminId = User::whereHas('role', function ($q) {
                        $q->where('name', 'SuperAdmin');
                    })->value('id');

                    if ($adminId) {
                        $notificationType = 4;
                        $message = 'A new Company (' . $company->name . ' - Code: ' . $company->company_code . ') has registered and is awaiting your approval.';
                        saveNotification($notificationType, $user->id, $adminId, $adminId, $message);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Company registration notification failed: ' . $e->getMessage());
                }
            });
        }

        return $result;
    }
}
