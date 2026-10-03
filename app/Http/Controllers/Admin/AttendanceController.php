<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\EmployeeMaster;
use App\Models\Company;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Auth;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $companyId = getActiveCompanyId();

        $query = AttendanceLog::with(['employee', 'company']);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($request->has('employee_id') && $request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->where('log_date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->where('log_date', '<=', $request->date_to);
        }

        $perPage = (int) $request->input('per_page', 50);
        $attendanceLogs = $query->orderBy('log_date', 'desc')
            ->orderBy('log_time', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $employeesQuery = EmployeeMaster::where('status', 'active');
        if ($companyId) {
            $employeesQuery->where('company_id', $companyId);
        }
        $employees = $employeesQuery->orderBy('last_name')->orderBy('first_name')->get();

        return view('admin.attendance.index', compact('attendanceLogs', 'employees'));
    }

    public function import()
    {
        $companyId = getActiveCompanyId();

        $employeesQuery = EmployeeMaster::where('status', 'active');
        if ($companyId) {
            $employeesQuery->where('company_id', $companyId);
        }
        $employees = $employeesQuery->get();

        $companiesQuery = company::where('status', 1);
        if ($companyId) {
            $companiesQuery->where('id', $companyId);
        }
        $companies = $companiesQuery->get();

        return view('admin.attendance.import', compact('employees', 'companies'));
    }

    public function processImport(Request $request)
    {
        $validatedData = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'import_file' => 'required|file|mimes:txt,csv,dat|max:10240',
        ]);

        authorizeCompanyAccess((int) $validatedData['company_id']);

        $file = $request->file('import_file');
        $content = file_get_contents($file->getRealPath());
        $lines = explode("\n", $content);
        
        $importedCount = 0;
        $skippedCount = 0;
        $errors = [];
        $importBatch = uniqid('batch_');
        
        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Parse different ZKTeco formats
            // Format 1: BiometricID,DateTime (e.g., "101,2026-07-04 08:30:00")
            // Format 2: BiometricID\tDateTime\tType (tab-separated)
            
            $parts = preg_split('/[\t,]/', $line);
            
            if (count($parts) < 2) {
                $skippedCount++;
                continue;
            }
            
            $biometricId = trim($parts[0]);
            $dateTimeStr = trim($parts[1]);
            
            // Find employee by biometric_id
            $employee = EmployeeMaster::where('biometric_id', $biometricId)
                ->where('company_id', $validatedData['company_id'])
                ->first();
            
            if (!$employee) {
                $errors[] = "Line {$lineNumber}: Employee with Biometric ID '{$biometricId}' not found";
                $skippedCount++;
                continue;
            }
            
            try {
                $dateTime = Carbon::parse($dateTimeStr);
                
                // Determine log_type matching mysql enum ['in', 'out', 'unknown']
                $rawLogType = isset($parts[2]) ? strtolower(trim($parts[2])) : '';
                if (in_array($rawLogType, ['in', 'out', 'unknown'])) {
                    $logType = $rawLogType;
                } else {
                    $hasLogToday = AttendanceLog::where('employee_id', $employee->id)
                        ->whereDate('log_date', $dateTime->toDateString())
                        ->exists();
                    $logType = $hasLogToday ? 'out' : 'in';
                }
                
                // Check for duplicate
                $exists = AttendanceLog::where('employee_id', $employee->id)
                    ->whereDate('log_date', $dateTime->toDateString())
                    ->where(function ($q) use ($dateTime) {
                        $q->where('log_time', $dateTime->toTimeString())
                            ->orWhereTime('log_time', $dateTime->toTimeString());
                    })
                    ->exists();
                
                if ($exists) {
                    $skippedCount++;
                    continue;
                }
                
                AttendanceLog::create([
                    'employee_id' => $employee->id,
                    'company_id' => $validatedData['company_id'],
                    'biometric_id' => $biometricId,
                    'log_date' => $dateTime->toDateString(),
                    'log_time' => $dateTime->toTimeString(),
                    'log_datetime' => $dateTime,
                    'log_type' => $logType,
                    'device_serial' => $request->device_serial ?? null,
                    'import_batch' => $importBatch,
                    'imported_by' => auth()->id(),
                    'imported_at' => now(),
                ]);
                
                $importedCount++;
                
            } catch (\Exception $e) {
                $errors[] = "Line {$lineNumber}: " . $e->getMessage();
                $skippedCount++;
            }
        }
        
        $message = "Import completed: {$importedCount} records imported, {$skippedCount} skipped.";
        
        if (count($errors) > 0) {
            $errorSummary = implode('<br>', array_slice($errors, 0, 10));
            if (count($errors) > 10) {
                $errorSummary .= '<br>... and ' . (count($errors) - 10) . ' more errors';
            }
            return redirect()->route('attendance.import')
                ->with('statusWarning', $message)
                ->with('importErrors', $errorSummary);
        }
        
        return redirect()->route('attendance.index')->with('status', $message);
    }

    public function destroy($id)
    {
        $attendanceLog = AttendanceLog::findOrFail($id);
        authorizeCompanyAccess($attendanceLog->company_id);
        $attendanceLog->delete();

        return redirect()->route('attendance.index')->with('statusDanger', 'Attendance Log Deleted Successfully.');
    }

    public function deleteBatch(Request $request)
    {
        $validatedData = $request->validate([
            'import_batch' => 'required|string',
        ]);
        
        $deleted = AttendanceLog::where('import_batch', $validatedData['import_batch'])->delete();
        
        return redirect()->route('attendance.index')->with('statusDanger', "Deleted {$deleted} attendance records from batch.");
    }

    /**
     * Show manual attendance log generator form.
     */
    public function manualGenerateForm(Request $request)
    {
        $currentUser = Auth::user();
        $isSuperAdmin = $currentUser->role === 'admin' || (int)$currentUser->role_id === \App\Models\Role::SUPER_ADMIN;

        $companies = collect();
        if ($isSuperAdmin) {
            $companies = Company::where('status', 1)->orderBy('name')->get();
            $selectedCompanyId = $request->input('company_id') ?? ($companies->first()?->id ?? getActiveCompanyId());
        } else {
            $selectedCompanyId = getActiveCompanyId() ?? $currentUser->company_id;
        }

        $employees = collect();
        if ($selectedCompanyId) {
            $employees = EmployeeMaster::where('company_id', $selectedCompanyId)
                ->where('status', 'active')
                ->orderBy('first_name')
                ->get();
        }

        return view('admin.attendance.manual_generate', compact('companies', 'employees', 'selectedCompanyId', 'isSuperAdmin'));
    }

    /**
     * AJAX endpoint to fetch employees when Super Admin selects a company.
     */
    public function getCompanyEmployeesAjax($companyId)
    {
        authorizeCompanyAccess((int)$companyId);

        $employees = EmployeeMaster::where('company_id', $companyId)
            ->where('status', 'active')
            ->select('id', 'first_name', 'last_name', 'employee_code')
            ->orderBy('first_name')
            ->get()
            ->map(function ($emp) {
                return [
                    'id' => $emp->id,
                    'name' => $emp->full_name . ' (' . ($emp->employee_code ?? 'EMP-' . $emp->id) . ')',
                ];
            });

        return response()->json(['success' => true, 'employees' => $employees]);
    }

    /**
     * Process manual batch or single attendance generation.
     */
    public function processManualGenerate(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'employee_scope' => 'required|in:all,selected',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'exists:employees_master,id',
            'period_preset' => 'required|in:biweekly_1_15,biweekly_16_end,full_month,custom',
            'month_year' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'work_days' => 'required|array|min:1',
            'time_in' => 'required',
            'time_out' => 'required',
        ]);

        authorizeCompanyAccess((int)$validated['company_id']);
        $companyId = (int)$validated['company_id'];

        // Determine date range
        $startDate = null;
        $endDate = null;

        if ($validated['period_preset'] === 'custom') {
            if (empty($validated['date_from']) || empty($validated['date_to'])) {
                return back()->withErrors(['date_from' => 'Please specify both Date From and Date To for custom range.'])->withInput();
            }
            $startDate = Carbon::parse($validated['date_from'])->startOfDay();
            $endDate = Carbon::parse($validated['date_to'])->endOfDay();
        } else {
            $monthYear = $validated['month_year'] ?? date('Y-m');
            $yearMonthDate = Carbon::parse($monthYear . '-01');

            if ($validated['period_preset'] === 'biweekly_1_15') {
                $startDate = $yearMonthDate->copy()->startOfMonth();
                $endDate = $yearMonthDate->copy()->day(15);
            } elseif ($validated['period_preset'] === 'biweekly_16_end') {
                $startDate = $yearMonthDate->copy()->day(16);
                $endDate = $yearMonthDate->copy()->endOfMonth();
            } else { // full_month
                $startDate = $yearMonthDate->copy()->startOfMonth();
                $endDate = $yearMonthDate->copy()->endOfMonth();
            }
        }

        // Determine target employees
        if ($validated['employee_scope'] === 'all') {
            $employees = EmployeeMaster::where('company_id', $companyId)
                ->where('status', 'active')
                ->get();
        } else {
            if (empty($validated['employee_ids'])) {
                return back()->withErrors(['employee_ids' => 'Please select at least one employee.'])->withInput();
            }
            $employees = EmployeeMaster::whereIn('id', $validated['employee_ids'])
                ->where('company_id', $companyId)
                ->get();
        }

        if ($employees->isEmpty()) {
            return back()->with('statusDanger', 'No active employees found for the selected criteria.')->withInput();
        }

        $workDays = array_map('intval', $validated['work_days']); // 1 (Mon) to 7 (Sun)
        $timeInFormatted = date('H:i:s', strtotime($validated['time_in']));
        $timeOutFormatted = date('H:i:s', strtotime($validated['time_out']));
        $importBatch = 'manual_gen_' . date('Ymd_His');
        $createdLogsCount = 0;

        foreach ($employees as $employee) {
            $currentDate = $startDate->copy();
            $bioId = $employee->biometric_id ?? $employee->employee_code ?? (string)$employee->id;

            while ($currentDate->lte($endDate)) {
                if (in_array($currentDate->dayOfWeekIso, $workDays)) {
                    $dateStr = $currentDate->format('Y-m-d');

                    // Check-in log
                    $checkInExists = AttendanceLog::where('employee_id', $employee->id)
                        ->where('log_date', $dateStr)
                        ->where('log_type', 'in')
                        ->exists();

                    if (!$checkInExists) {
                        AttendanceLog::create([
                            'employee_id' => $employee->id,
                            'company_id' => $companyId,
                            'biometric_id' => $bioId,
                            'log_datetime' => $dateStr . ' ' . $timeInFormatted,
                            'log_date' => $dateStr,
                            'log_time' => $timeInFormatted,
                            'log_type' => 'in',
                            'import_status' => 'processed',
                            'import_batch' => $importBatch,
                            'imported_by' => Auth::id(),
                            'imported_at' => now(),
                        ]);
                        $createdLogsCount++;
                    }

                    // Check-out log
                    $checkOutExists = AttendanceLog::where('employee_id', $employee->id)
                        ->where('log_date', $dateStr)
                        ->where('log_type', 'out')
                        ->exists();

                    if (!$checkOutExists) {
                        AttendanceLog::create([
                            'employee_id' => $employee->id,
                            'company_id' => $companyId,
                            'biometric_id' => $bioId,
                            'log_datetime' => $dateStr . ' ' . $timeOutFormatted,
                            'log_date' => $dateStr,
                            'log_time' => $timeOutFormatted,
                            'log_type' => 'out',
                            'import_status' => 'processed',
                            'import_batch' => $importBatch,
                            'imported_by' => Auth::id(),
                            'imported_at' => now(),
                        ]);
                        $createdLogsCount++;
                    }
                }
                $currentDate->addDay();
            }
        }

        return redirect()->route('attendance.index')
            ->with('status', "Successfully generated {$createdLogsCount} attendance log entries for " . $employees->count() . " employee(s) (Batch ID: {$importBatch}).");
    }
}
