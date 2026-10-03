<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollDraftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payroll_period_id' => 'required|exists:payroll_periods,id',
            'dept_id' => 'nullable|integer|exists:departments,id',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'exists:employees_master,id',
            'use_fixed_amount' => 'nullable|boolean',
            'deductions' => 'nullable|array',
            'deductions.*.deduction_type' => 'required_with:deductions|string',
            'deductions.*.display' => 'nullable|boolean',
            'deductions.*.amount' => 'nullable|numeric|min:0',
            'use_fixed_income_amount' => 'nullable|boolean',
            'incomes' => 'nullable|array',
            'incomes.*.income_type' => 'required_with:incomes|string',
            'incomes.*.display' => 'nullable|boolean',
            'incomes.*.number_input' => 'nullable|numeric|min:0',
            'incomes.*.amount' => 'nullable|numeric|min:0',
            'payroll_inputs' => 'nullable|array',
            'payroll_inputs.*.days_worked' => 'nullable|numeric|min:0',
            'payroll_inputs.*.number_input' => 'nullable|numeric|min:0',
            'payroll_inputs.*.overtime_hours' => 'nullable|numeric|min:0',
            'employee_inputs' => 'nullable|array',
            'employee_inputs.*.days_worked' => 'nullable|numeric|min:0',
            'employee_inputs.*.number_input' => 'nullable|numeric|min:0',
            'employee_inputs.*.overtime_hours' => 'nullable|numeric|min:0',
            'days_worked' => 'nullable',
        ];
    }
}
