<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHrAdjustmentRequest extends FormRequest
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
            'employee_id' => 'required|exists:employees_master,id',
            'amount' => 'required_if:type,income,deduction|nullable|numeric|min:0',
            'type' => 'required|in:income,deduction,attendance',
            'description' => 'required|string|max:255',
            'effective_date' => 'required|date',
            'income_type_id' => 'required_if:type,income|nullable|exists:income_types,id',
            'deduction_type_id' => 'required_if:type,deduction|nullable|exists:deduction_types,id',
            'hours_worked' => 'required_if:type,attendance|nullable|numeric|min:0',
            'overtime_hours' => 'required_if:type,attendance|nullable|numeric|min:0',
        ];
    }
}
