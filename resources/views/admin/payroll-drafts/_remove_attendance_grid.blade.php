@php
    $basicPayInForAttendanceSync = collect($payIns ?? [])->first(
        fn ($payIn) => ($payIn->incomeType?->system_key ?? null) === 'basic'
    );
    $overtimePayInForAttendanceSync = collect($payIns ?? [])->first(
        fn ($payIn) => ($payIn->incomeType?->system_key ?? null) === 'overtime'
    );
    $isDailyEmployeeForAttendanceSync = (float) ($employee->daily_rate ?? 0) > 0
        && (float) ($employee->monthly_rate ?? 0) <= 0;
@endphp

<style>
    /*
     * Attendance quantities are no longer an editable payroll-draft input source.
     * The server still keeps the stored header values for reporting/history.
     */
    #payrollForm .card:has(#days_worked) {
        display: none !important;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('payrollForm');
        const daysWorkedInput = document.getElementById('days_worked');

        if (!form || !daysWorkedInput) {
            return;
        }

        const attendanceCard = daysWorkedInput.closest('.card');
        const originalValues = {
            days_worked: daysWorkedInput.value,
            days_absent: document.getElementById('days_absent')?.value ?? '',
            overtime_hours: document.getElementById('overtime_hours')?.value ?? '',
            late_hours: document.getElementById('late_hours')?.value ?? '',
        };

        function ensureHiddenInput(name, value) {
            let input = form.querySelector(`input[type="hidden"][data-attendance-preserved="${name}"]`);
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.dataset.attendancePreserved = name;
                form.appendChild(input);
            }
            input.value = value ?? '';
            return input;
        }

        const hiddenDaysWorked = ensureHiddenInput('days_worked', originalValues.days_worked);
        ensureHiddenInput('days_absent', originalValues.days_absent);
        const hiddenOvertimeHours = ensureHiddenInput('overtime_hours', originalValues.overtime_hours);
        ensureHiddenInput('late_hours', originalValues.late_hours);

        const isDailyEmployee = @json($isDailyEmployeeForAttendanceSync);
        const basicPayInputId = @json($basicPayInForAttendanceSync ? 'pay_in_num_' . $basicPayInForAttendanceSync->id : null);
        const overtimePayInputId = @json($overtimePayInForAttendanceSync ? 'pay_in_num_' . $overtimePayInForAttendanceSync->id : null);

        if (isDailyEmployee && basicPayInputId) {
            const basicQtyInput = document.getElementById(basicPayInputId);
            if (basicQtyInput) {
                const syncBasicQty = function () {
                    hiddenDaysWorked.value = basicQtyInput.value;
                };
                ['input', 'change'].forEach(eventName => basicQtyInput.addEventListener(eventName, syncBasicQty));
                syncBasicQty();
            }
        }

        if (overtimePayInputId) {
            const overtimeQtyInput = document.getElementById(overtimePayInputId);
            if (overtimeQtyInput) {
                const syncOvertimeQty = function () {
                    hiddenOvertimeHours.value = overtimeQtyInput.value;
                };
                ['input', 'change'].forEach(eventName => overtimeQtyInput.addEventListener(eventName, syncOvertimeQty));
                syncOvertimeQty();
            }
        }

        if (attendanceCard) {
            attendanceCard.remove();
        }
    });
</script>
