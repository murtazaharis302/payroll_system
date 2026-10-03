<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddPayrollDraftEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payroll_period_id' => ['required', 'integer', 'exists:payroll_periods,id'],
            'employee_id' => ['required', 'integer', 'exists:employees_master,id'],
        ];
    }
}
