<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportPrintMarginAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $adminUser;
    protected PayrollPeriod $period;
    protected EmployeeMaster $emp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'name' => 'Report Admin',
            'email' => 'report_audit_admin@test.com',
            'password' => Hash::make('password'),
            'role_id' => Role::COMPANY_ADMIN,
            'status' => 1,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Print Audit Corp',
            'company_code' => 'AUDIT',
            'email' => 'audit_corp@test.com',
            'status' => 1,
        ]);

        $this->adminUser->update(['company_id' => $this->company->id]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Audit Period 2026-08',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'status' => 'completed',
        ]);

        $this->emp = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-AUDIT',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'monthly_rate' => 25000.00,
            'status' => 'active',
        ]);
    }

    /**
     * Audit all printable Blade report views for physical @page margins and no wildcard margins.
     */
    public function test_all_printable_report_views_have_physical_margins_and_no_wildcard_margins(): void
    {
        $reportViews = [
            'voucher.blade.php',
            'payslip.blade.php',
            'batch-payslips.blade.php',
            'deduction-ledger.blade.php',
            '13th-month-computation.blade.php',
            'alpha-list.blade.php',
            'deduction-history.blade.php',
            'deduction-payroll-history.blade.php',
            'income-history.blade.php',
            'income-payroll-history.blade.php',
            'single-deduction-matrix.blade.php',
            'payroll-register.blade.php',
            'loan-ledger.blade.php',
            'bank-report.blade.php',
            'payroll-summary-1.blade.php',
            'payroll-summary-2.blade.php',
            'pdf/thirteenth_month_history.blade.php',
        ];

        $baseDir = resource_path('views/admin/reports/');

        foreach ($reportViews as $relPath) {
            $fullPath = $baseDir . $relPath;
            $this->assertFileExists($fullPath, "Report view {$relPath} must exist.");

            $content = file_get_contents($fullPath);

            // 1. Must contain @page directive
            $this->assertMatchesRegularExpression('/@page\s*\{/i', $content, "{$relPath} must define an @page rule.");

            // 2. @page must not use px units for margins
            if (preg_match('/@page\s*\{([^}]+)\}/is', $content, $pageMatch)) {
                $pageBlock = $pageMatch[1];
                $this->assertDoesNotMatchRegularExpression(
                    '/margin[^;]*\d+px/i',
                    $pageBlock,
                    "{$relPath} @page declaration must use physical units (in, mm), not px."
                );
            }

            // 3. Must not contain wildcard * margin or padding declarations
            if (preg_match_all('/(\*\s*\{[^}]+\})/is', $content, $starMatches)) {
                foreach ($starMatches[1] as $starBlock) {
                    $this->assertDoesNotMatchRegularExpression(
                        '/margin\s*:[^;]+;/i',
                        $starBlock,
                        "{$relPath} must not apply margin to all elements via wildcard * selector."
                    );
                    $this->assertDoesNotMatchRegularExpression(
                        '/padding\s*:[^;]+;/i',
                        $starBlock,
                        "{$relPath} must not apply padding to all elements via wildcard * selector."
                    );
                }
            }
        }
    }

    /**
     * Test deduction ledger view renders with legal landscape and clean physical margins.
     */
    public function test_deduction_ledger_view_renders_with_clean_margins(): void
    {
        $reportData = [
            [
                'employee' => $this->emp,
                'employee_code' => 'EMP-AUDIT',
                'name' => 'Doe, John',
                'department' => 'Operations',
                'rows' => [
                    [
                        'period' => $this->period,
                        'period_name' => $this->period->period_name,
                        'period_range' => '08/01/2026 to 08/15/2026',
                        'amount' => 500.00,
                    ],
                ],
                'employee_total' => 500.00,
            ],
        ];

        $view = view('admin.reports.deduction-ledger', [
            'company' => $this->company,
            'fromPeriod' => $this->period,
            'toPeriod' => $this->period,
            'periods' => collect([$this->period]),
            'deductionLabel' => 'SSS Contribution',
            'deductionTypeId' => 'sss_contribution',
            'reportData' => $reportData,
            'grandTotalLedger' => 500.00,
        ])->render();

        $this->assertStringContainsString('margin: 8mm 8mm;', $view);
        $this->assertStringContainsString('legal landscape', $view);
        $this->assertStringContainsString('DEDUCTION LEDGER REPORT', $view);
        $this->assertStringContainsString('Doe, John', $view);
        $this->assertStringContainsString('500.00', $view);
    }

    /**
     * Test 13th month computation view renders with clean 8mm landscape margins.
     */
    public function test_thirteenth_month_computation_view_renders_cleanly(): void
    {
        $view = view('admin.reports.13th-month-computation', [
            'company' => $this->company,
            'fromPeriod' => $this->period,
            'toPeriod' => $this->period,
            'periods' => collect([$this->period]),
            'periodCount' => 1,
            'reportData' => [],
            'grandTotal' => [
                'total_basic_pay' => 0,
                'total_overtime' => 0,
                'total_gross' => 0,
                'thirteenth_month' => 0,
                'periods_worked' => 0,
            ],
        ])->render();

        $this->assertStringContainsString('margin: 8mm 8mm;', $view);
        $this->assertStringContainsString('13TH MONTH PAY COMPUTATION MATRIX', $view);
    }

    /**
     * Test alpha list view renders with portrait safe margins.
     */
    public function test_alpha_list_view_renders_with_safe_portrait_margins(): void
    {
        $view = view('admin.reports.alpha-list', [
            'company' => $this->company,
            'period' => $this->period,
            'payrolls' => collect(),
            'totals' => [
                'gross_income' => 0,
                'total_deductions' => 0,
                'net_pay' => 0,
            ],
        ])->render();

        $this->assertStringContainsString('margin: 10mm 12mm;', $view);
        $this->assertStringContainsString('Alpha List', $view);
    }
}
