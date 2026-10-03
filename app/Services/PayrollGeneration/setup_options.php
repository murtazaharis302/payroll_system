<?php

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmpDeduction;
use App\Models\EmpIncome;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\GovernmentContribution;
use App\Models\HRAdjustment;
use App\Models\IncomeType;
use App\Models\LoanPayment;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\PayrollPolicy;
use App\Services\PayrollLineCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


        // Merge company-wide deduction defaults if options are omitted or empty
        $company = $period->company ?? ($companyId ? Company::find($companyId) : null);
        if (!$company) {
            throw new \InvalidArgumentException("Payroll period [{$period->id}] has no valid associated company.");
        }

        $companyDefaults = $company->deduction_defaults ?? [];
        $companyIncomeDefaults = $company->income_defaults ?? [];

        if (empty($options['deductions']) && empty($options['deduction_types'])) {
            if (!isset($options['use_fixed_amount']) && isset($companyDefaults['use_fixed_amount'])) {
                $options['use_fixed_amount'] = $companyDefaults['use_fixed_amount'];
            }
            if (!empty($companyDefaults['deductions'])) {
                $options['deductions'] = $companyDefaults['deductions'];
            }
        }

        if (empty($options['incomes']) && empty($options['income_types'])) {
            if (!isset($options['use_fixed_income_amount']) && isset($companyIncomeDefaults['use_fixed_income_amount'])) {
                $options['use_fixed_income_amount'] = $companyIncomeDefaults['use_fixed_income_amount'];
            }
            if (!empty($companyIncomeDefaults['incomes'])) {
                $options['incomes'] = $companyIncomeDefaults['incomes'];
            }
        }

        // Parse options for selective deduction inclusion & manual/fixed entry
        $useFixedAmount = isset($options['use_fixed_amount']) ? (bool) $options['use_fixed_amount'] : true;
        $rawDeductionOptions = $options['deductions'] ?? [];

        $rawIncomeOptions = $options['incomes'] ?? $options['income_types'] ?? [];

        if (isset($options['use_fixed_income_amount'])) {
            $useFixedIncome = (bool) $options['use_fixed_income_amount'];
        } elseif (isset($options['use_fixed_amount'])) {
            $useFixedIncome = (bool) $options['use_fixed_amount'];
        } else {
            $useFixedIncome = true;
        }

        $normalizeKey = function(?string $str): string {
            if (!$str) return '';
            return strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $str));
        };

        $incomeConfigMap = [];
        if (!empty($rawIncomeOptions) && is_array($rawIncomeOptions)) {
            foreach ($rawIncomeOptions as $k => $item) {
                if (!is_array($item)) continue;
                $typeId = isset($item['income_type_id']) && is_numeric($item['income_type_id']) ? (int) $item['income_type_id'] : null;
                if ($typeId === null && isset($item['income_type']) && is_numeric($item['income_type'])) {
                    $typeId = (int) $item['income_type'];
                }
                if ($typeId === null && is_numeric($k)) {
                    $typeId = (int) $k;
                }

                $typeStr = (string) ($item['income_type'] ?? '');
                $configEntry = [
                    'income_type_id' => $typeId,
                    'income_type' => $typeStr,
                    'mode' => $item['mode'] ?? ($useFixedIncome ? 'fixed' : 'automated'),
                    'display' => isset($item['display']) ? (bool) $item['display'] : true,
                    'number_input' => (isset($item['number_input']) && is_numeric($item['number_input']) && $item['number_input'] !== '') ? (float) $item['number_input'] : null,
                    'amount' => (isset($item['amount']) && is_numeric($item['amount']) && $item['amount'] !== '') ? (float) $item['amount'] : null,
                    'is_legacy_default' => !array_key_exists('number_input', $item) && isset($item['amount']),
                ];

                if ($typeId !== null) {
                    $incomeConfigMap[(string) $typeId] = $configEntry;
                }

                $key = $normalizeKey($typeStr);
                if ($key !== '') {
                    $incomeConfigMap[$key] = $configEntry;
                }
            }
        }

        $getIncomeOption = function(?string $systemKey = null, ?string $code = null, ?string $name = null, ?int $id = null) use ($incomeConfigMap, $normalizeKey): array {
            if (empty($incomeConfigMap)) {
                return ['has_config' => false, 'display' => true, 'number_input' => null, 'amount' => null, 'is_legacy_default' => false];
            }

            if ($id !== null && array_key_exists((string) $id, $incomeConfigMap)) {
                return array_merge(['has_config' => true], $incomeConfigMap[(string) $id]);
            }

            $keysToTry = array_filter([
                $id ? (string) $id : null,
                $systemKey ? $normalizeKey($systemKey) : null,
                $code ? $normalizeKey($code) : null,
            ]);

            foreach (array_unique($keysToTry) as $key) {
                if (array_key_exists($key, $incomeConfigMap)) {
                    return array_merge(['has_config' => true], $incomeConfigMap[$key]);
                }
            }

            return ['has_config' => false, 'display' => true, 'number_input' => null, 'amount' => null, 'is_legacy_default' => false];
        };

        $deductionConfigMap = [];
        if (!empty($rawDeductionOptions) && is_array($rawDeductionOptions)) {
            foreach ($rawDeductionOptions as $item) {
                if (!is_array($item)) continue;
                $typeStr = (string) ($item['deduction_type'] ?? '');
                $key = $normalizeKey($typeStr);
                if ($key === '') continue;

                $deductionConfigMap[$key] = [
                    'display' => isset($item['display']) ? (bool) $item['display'] : true,
                    'amount' => isset($item['amount']) ? (float) $item['amount'] : 0.00,
                ];
            }
        }

        $getDeductionOption = function(?string $systemKey = null, ?string $code = null, ?string $name = null, ?int $id = null) use ($deductionConfigMap, $normalizeKey): array {
            if (empty($deductionConfigMap)) {
                return ['has_config' => false, 'display' => true, 'amount' => 0.00];
            }

            $keysToTry = array_filter([
                $id ? (string)$id : null,
                $systemKey ? $normalizeKey($systemKey) : null,
                $code ? $normalizeKey($code) : null,
                $name ? $normalizeKey($name) : null,
            ]);

            $sysNorm = $systemKey ? $normalizeKey($systemKey) : '';
            $codeNorm = $code ? $normalizeKey($code) : '';
            $nameNorm = $name ? $normalizeKey($name) : '';

            if ($sysNorm === 'sss' || $codeNorm === 'sss' || str_contains($nameNorm, 'sss')) {
                $keysToTry[] = 'sss';
                $keysToTry[] = 'ssscontribution';
            }
            if ($sysNorm === 'philhealth' || $codeNorm === 'philhealth' || str_contains($nameNorm, 'philhealth')) {
                $keysToTry[] = 'philhealth';
                $keysToTry[] = 'philhealthcontribution';
            }
            if ($sysNorm === 'pagibig' || $codeNorm === 'pagibig' || str_contains($nameNorm, 'pagibig')) {
                $keysToTry[] = 'pagibig';
                $keysToTry[] = 'pagibigcontribution';
            }
            if ($sysNorm === 'tax' || $codeNorm === 'tax' || str_contains($nameNorm, 'tax')) {
                $keysToTry[] = 'tax';
                $keysToTry[] = 'withholdingtax';
            }
            if ($codeNorm === 'sssloan' || str_contains($nameNorm, 'sssloan')) {
                $keysToTry[] = 'sssloan';
                $keysToTry[] = 'sssloandeduction';
            }
            if ($codeNorm === 'pagibigloan' || str_contains($nameNorm, 'pagibigloan')) {
                $keysToTry[] = 'pagibigloan';
                $keysToTry[] = 'pagibigloandeduction';
            }
            if ($codeNorm === 'vale' || str_contains($nameNorm, 'vale') || str_contains($nameNorm, 'cashadvance')) {
                $keysToTry[] = 'vale';
                $keysToTry[] = 'valeloan';
                $keysToTry[] = 'valecashadvance';
                $keysToTry[] = 'valecashadvancededuction';
            }
            if ($codeNorm === 'goods' || str_contains($nameNorm, 'goods')) {
                $keysToTry[] = 'goods';
                $keysToTry[] = 'goodspurchase';
                $keysToTry[] = 'goodspurchasededuction';
            }
            if ($sysNorm === 'tardiness' || $codeNorm === 'tardiness' || str_contains($nameNorm, 'tardiness') || str_contains($nameNorm, 'late')) {
                $keysToTry[] = 'tardiness';
                $keysToTry[] = 'latetardiness';
                $keysToTry[] = 'late';
                $keysToTry[] = 'latetardinessdeduction';
            }
            if ($sysNorm === 'undertime' || $codeNorm === 'undertime' || str_contains($nameNorm, 'undertime')) {
                $keysToTry[] = 'undertime';
                $keysToTry[] = 'undertimededuction';
            }
            if ($codeNorm === 'absences' || str_contains($nameNorm, 'absence') || str_contains($nameNorm, 'absent')) {
                $keysToTry[] = 'absences';
                $keysToTry[] = 'absence';
                $keysToTry[] = 'daysabsent';
            }
            if ($codeNorm === 'other' || str_contains($nameNorm, 'other')) {
                $keysToTry[] = 'other';
                $keysToTry[] = 'otherdeductions';
            }

            foreach (array_unique($keysToTry) as $key) {
                if (array_key_exists($key, $deductionConfigMap)) {
                    return array_merge(['has_config' => true], $deductionConfigMap[$key]);
                }
            }

            return ['has_config' => false, 'display' => true, 'amount' => 0.00];
        };
