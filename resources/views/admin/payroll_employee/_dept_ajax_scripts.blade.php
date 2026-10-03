<script>
$(document).ready(function() {
    function loadDepartments(companyId, selectedDeptId, selectedSubDeptId) {
        var deptSelect = $('#dept_id');
        var subDeptSelect = $('#sub_dept_id');

        deptSelect.empty().append('<option value="">Loading...</option>');
        subDeptSelect.empty().append('<option value="">Select Sub-Department</option>');

        if (!companyId) {
            deptSelect.empty().append('<option value="">Select Department</option>');
            return;
        }

        $.ajax({
            url: '{{ panelRoute("payroll-employees.get-departments") }}',
            type: 'POST',
            data: {
                company_id: companyId,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                deptSelect.empty().append('<option value="">Select Department</option>');
                $.each(response.departments, function(i, dept) {
                    var selected = String(selectedDeptId) === String(dept.id) ? 'selected' : '';
                    deptSelect.append('<option value="' + dept.id + '" ' + selected + '>' + dept.name + ' (' + dept.code + ')</option>');
                });

                if (selectedDeptId) {
                    loadSubDepartments(companyId, selectedDeptId, selectedSubDeptId);
                }
            }
        });
    }

    function loadSubDepartments(companyId, departmentId, selectedSubDeptId) {
        var subDeptSelect = $('#sub_dept_id');
        subDeptSelect.empty().append('<option value="">Loading...</option>');

        if (!departmentId) {
            subDeptSelect.empty().append('<option value="">Select Sub-Department</option>');
            return;
        }

        $.ajax({
            url: '{{ panelRoute("payroll-employees.get-sub-departments") }}',
            type: 'POST',
            data: {
                company_id: companyId,
                department_id: departmentId,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                subDeptSelect.empty().append('<option value="">Select Sub-Department</option>');
                $.each(response.sub_departments, function(i, sub) {
                    var selected = String(selectedSubDeptId) === String(sub.id) ? 'selected' : '';
                    subDeptSelect.append('<option value="' + sub.id + '" ' + selected + '>' + sub.name + ' (' + sub.code + ')</option>');
                });
            }
        });
    }

    $('#company_id').on('change', function() {
        loadDepartments($(this).val(), '', '');
    });

    $('#dept_id').on('change', function() {
        var companyId = $('#company_id').val();
        loadSubDepartments(companyId, $(this).val(), '');
    });

    @if(isset($selectedCompanyId) && $selectedCompanyId)
        loadDepartments(
            '{{ old('company_id', $selectedCompanyId) }}',
            '{{ old('dept_id', $initialDeptId ?? '') }}',
            '{{ old('sub_dept_id', $initialSubDeptId ?? '') }}'
        );
    @endif
});
</script>
