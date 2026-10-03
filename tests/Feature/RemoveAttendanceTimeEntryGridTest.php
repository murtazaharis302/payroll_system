<?php

namespace Tests\Feature;

use Tests\TestCase;

class RemoveAttendanceTimeEntryGridTest extends TestCase
{
    public function test_attendance_grid_removal_partial_hides_grid_and_syncs_system_income_quantities(): void
    {
        $employee = (object) [
            'daily_rate' => 750.00,
            'monthly_rate' => 0.00,
        ];

        $basicPayIn = (object) [
            'id' => 101,
            'incomeType' => (object) ['system_key' => 'basic'],
        ];

        $overtimePayIn = (object) [
            'id' => 202,
            'incomeType' => (object) ['system_key' => 'overtime'],
        ];

        $html = view('admin.payroll-drafts._remove_attendance_grid', [
            'employee' => $employee,
            'payIns' => collect([$basicPayIn, $overtimePayIn]),
        ])->render();

        $this->assertStringContainsString('#payrollForm .card:has(#days_worked)', $html);
        $this->assertStringContainsString('attendanceCard.remove()', $html);
        $this->assertStringContainsString('data-attendance-preserved', $html);
        $this->assertStringContainsString('pay_in_num_101', $html);
        $this->assertStringContainsString('pay_in_num_202', $html);
        $this->assertStringContainsString("ensureHiddenInput('days_worked'", $html);
        $this->assertStringContainsString("ensureHiddenInput('overtime_hours'", $html);
    }

    public function test_edit_view_loads_attendance_grid_removal_partial(): void
    {
        $editView = file_get_contents(resource_path('views/admin/payroll-drafts/edit.blade.php'));

        $this->assertStringContainsString(
            "@include('admin.payroll-drafts._remove_attendance_grid')",
            $editView
        );
    }
}
