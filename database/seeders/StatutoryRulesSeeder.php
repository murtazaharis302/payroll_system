<?php

namespace Database\Seeders;

use App\Models\StatutoryRule;
use Illuminate\Database\Seeder;

class StatutoryRulesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. SSS 2025 Schedule (Effective Jan 1, 2025)
        $sss2025Brackets = [];

        // Salary range steps: 5,000 to 35,000 MSC
        // Below 5,250 -> MSC 5,000
        $sss2025Brackets[] = [
            'range_from' => 0.00,
            'range_to' => 5249.99,
            'msc' => 5000.00,
            'regular_ee' => 250.00,
            'regular_er' => 500.00,
            'wisp_ee' => 0.00,
            'wisp_er' => 0.00,
            'ec' => 10.00,
            'total_ee' => 250.00,
            'total_er' => 510.00,
        ];

        for ($msc = 5500; $msc <= 35000; $msc += 500) {
            $rangeFrom = $msc - 250.00;
            $rangeTo = ($msc === 35000) ? null : ($msc + 249.99);

            $regularMsc = min($msc, 20000.00);
            $wispMsc = max(0.00, $msc - 20000.00);

            $regularEe = round($regularMsc * 0.05, 2);
            $regularEr = round($regularMsc * 0.10, 2);

            $wispEe = round($wispMsc * 0.05, 2);
            $wispEr = round($wispMsc * 0.10, 2);

            $ec = ($msc <= 14750) ? 10.00 : 30.00;

            $totalEe = $regularEe + $wispEe;
            $totalEr = $regularEr + $wispEr + $ec;

            $sss2025Brackets[] = [
                'range_from' => $rangeFrom,
                'range_to' => $rangeTo,
                'msc' => (float) $msc,
                'regular_ee' => $regularEe,
                'regular_er' => $regularEr,
                'wisp_ee' => $wispEe,
                'wisp_er' => $wispEr,
                'ec' => $ec,
                'total_ee' => $totalEe,
                'total_er' => $totalEr,
            ];
        }

        StatutoryRule::updateOrCreate(
            ['version_code' => 'SSS_2025_01'],
            [
                'statutory_type' => 'sss',
                'source_document' => 'SSS Circular No. 2024-006 (Social Security Act of 2018 - Jan 2025 Schedule)',
                'effective_from' => '2025-01-01',
                'effective_to' => null,
                'salary_floor' => 5000.00,
                'salary_ceiling' => 35000.00,
                'ee_share_rate' => 0.0500,
                'er_share_rate' => 0.1000,
                'brackets' => $sss2025Brackets,
                'rounding_decimals' => 2,
                'rounding_mode' => 'half_up',
                'is_active' => true,
            ]
        );

        // 2. SSS 2024 Schedule (Effective Jan 1, 2023 to Dec 31, 2024)
        $sss2024Brackets = [];
        $sss2024Brackets[] = [
            'range_from' => 0.00,
            'range_to' => 4249.99,
            'msc' => 4000.00,
            'regular_ee' => 180.00,
            'regular_er' => 380.00,
            'wisp_ee' => 0.00,
            'wisp_er' => 0.00,
            'ec' => 10.00,
            'total_ee' => 180.00,
            'total_er' => 390.00,
        ];

        for ($msc = 4500; $msc <= 30000; $msc += 500) {
            $rangeFrom = $msc - 250.00;
            $rangeTo = ($msc === 30000) ? null : ($msc + 249.99);

            $regularMsc = min($msc, 20000.00);
            $wispMsc = max(0.00, $msc - 20000.00);

            $regularEe = round($regularMsc * 0.045, 2);
            $regularEr = round($regularMsc * 0.095, 2);

            $wispEe = round($wispMsc * 0.045, 2);
            $wispEr = round($wispMsc * 0.095, 2);

            $ec = ($msc <= 14750) ? 10.00 : 30.00;

            $totalEe = $regularEe + $wispEe;
            $totalEr = $regularEr + $wispEr + $ec;

            $sss2024Brackets[] = [
                'range_from' => $rangeFrom,
                'range_to' => $rangeTo,
                'msc' => (float) $msc,
                'regular_ee' => $regularEe,
                'regular_er' => $regularEr,
                'wisp_ee' => $wispEe,
                'wisp_er' => $wispEr,
                'ec' => $ec,
                'total_ee' => $totalEe,
                'total_er' => $totalEr,
            ];
        }

        StatutoryRule::updateOrCreate(
            ['version_code' => 'SSS_2024_01'],
            [
                'statutory_type' => 'sss',
                'source_document' => 'SSS Circular No. 2022-033 (2024 Contribution Schedule)',
                'effective_from' => '2023-01-01',
                'effective_to' => '2024-12-31',
                'salary_floor' => 4000.00,
                'salary_ceiling' => 30000.00,
                'ee_share_rate' => 0.0450,
                'er_share_rate' => 0.0950,
                'brackets' => $sss2024Brackets,
                'rounding_decimals' => 2,
                'rounding_mode' => 'half_up',
                'is_active' => true,
            ]
        );

        // 3. PhilHealth 2024-2025 Schedule
        StatutoryRule::updateOrCreate(
            ['version_code' => 'PHILHEALTH_2024_2025'],
            [
                'statutory_type' => 'philhealth',
                'source_document' => 'PhilHealth Advisory No. 2024-0001 (Universal Health Care Act - 5% Premium Schedule)',
                'effective_from' => '2024-01-01',
                'effective_to' => null,
                'salary_floor' => 10000.00,
                'salary_ceiling' => 100000.00,
                'ee_share_rate' => 0.0250,
                'er_share_rate' => 0.0250,
                'brackets' => null,
                'rounding_decimals' => 2,
                'rounding_mode' => 'half_up',
                'is_active' => true,
            ]
        );

        // 4. Pag-IBIG 2024 Schedule (Circular 460 - Max Fund Salary 10,000)
        StatutoryRule::updateOrCreate(
            ['version_code' => 'PAGIBIG_2024_02'],
            [
                'statutory_type' => 'pagibig',
                'source_document' => 'Pag-IBIG Circular No. 460 (Increase in Maximum Fund Salary to PHP 10,000)',
                'effective_from' => '2024-02-01',
                'effective_to' => null,
                'salary_floor' => 1000.00,
                'salary_ceiling' => 10000.00,
                'ee_share_rate' => 0.0200,
                'er_share_rate' => 0.0200,
                'brackets' => [
                    [
                        'min' => 0.00,
                        'max' => 1500.00,
                        'ee_rate' => 0.0100,
                        'er_rate' => 0.0200,
                        'cap_salary' => false,
                    ],
                    [
                        'min' => 1500.01,
                        'max' => null,
                        'ee_rate' => 0.0200,
                        'er_rate' => 0.0200,
                        'cap_salary' => true,
                    ],
                ],
                'rounding_decimals' => 2,
                'rounding_mode' => 'half_up',
                'is_active' => true,
            ]
        );

        // 5. Withholding Tax (TRAIN Law Schedule)
        StatutoryRule::updateOrCreate(
            ['version_code' => 'TAX_TRAIN_2023'],
            [
                'statutory_type' => 'tax',
                'source_document' => 'BIR Revenue Regulations No. 13-2018 / TRAIN Act (Revised Withholding Tax Table)',
                'effective_from' => '2023-01-01',
                'effective_to' => null,
                'salary_floor' => 0.00,
                'salary_ceiling' => null,
                'ee_share_rate' => null,
                'er_share_rate' => null,
                'brackets' => [
                    [
                        'min' => 0.00,
                        'max' => 250000.00,
                        'base_tax' => 0.00,
                        'percentage' => 0.0000,
                        'excess_over' => 0.00,
                    ],
                    [
                        'min' => 250000.01,
                        'max' => 400000.00,
                        'base_tax' => 0.00,
                        'percentage' => 0.1500,
                        'excess_over' => 250000.00,
                    ],
                    [
                        'min' => 400000.01,
                        'max' => 800000.00,
                        'base_tax' => 22500.00,
                        'percentage' => 0.2000,
                        'excess_over' => 400000.00,
                    ],
                    [
                        'min' => 800000.01,
                        'max' => 2000000.00,
                        'base_tax' => 102500.00,
                        'percentage' => 0.2500,
                        'excess_over' => 800000.00,
                    ],
                    [
                        'min' => 2000000.01,
                        'max' => 8000000.00,
                        'base_tax' => 402500.00,
                        'percentage' => 0.3000,
                        'excess_over' => 2000000.00,
                    ],
                    [
                        'min' => 8000000.01,
                        'max' => null,
                        'base_tax' => 2202500.00,
                        'percentage' => 0.3500,
                        'excess_over' => 8000000.00,
                    ],
                ],
                'rounding_decimals' => 2,
                'rounding_mode' => 'half_up',
                'is_active' => true,
            ]
        );
    }
}
