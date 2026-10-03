$(document).ready(function () {
    $('#getUserName').on('change', function () {
        var selectedUserId = $(this).val();

        $.ajax({
            url: window.baseUrl + '/getUserPermissions',
            type: "GET",
            data: { selectedUserId: selectedUserId },
            success: function (response) {
                $('#permissionsTable').removeClass('d-none').html(response.html);

                bindMasterCheckbox();

                $('#savePermissionsBtn').removeClass('d-none');
                $('#checkAll').removeClass('d-none');

                updateMasterCheckboxState();
                updateRowCheckboxes();
            },
            error: function (xhr) {
                console.error('Error:', xhr.responseText);
                alert('Something went wrong while fetching permissions.');
            }
        });
    });
});

function bindMasterCheckbox() {
    const masterCheckbox = document.getElementById('masterCheckbox');
    const permissionCheckboxes = document.querySelectorAll('.permission-checkbox');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');

    if (!masterCheckbox) return;

    const newMasterCheckbox = masterCheckbox.cloneNode(true);
    masterCheckbox.parentNode.replaceChild(newMasterCheckbox, masterCheckbox);

    newMasterCheckbox.addEventListener('change', function () {
        permissionCheckboxes.forEach(cb => cb.checked = newMasterCheckbox.checked);
        updateRowCheckboxes();
        updateMasterCheckboxState();
    });

    permissionCheckboxes.forEach(cb => {
        cb.addEventListener('change', function () {
            updateRowCheckboxes();
            updateMasterCheckboxState();
        });
    });

    rowCheckboxes.forEach(rowCb => {
        rowCb.addEventListener('change', function () {
            const rowKey = this.dataset.row;
            const rowChecks = document.querySelectorAll(`.permission-checkbox[data-row="${rowKey}"]`);
            rowChecks.forEach(cb => cb.checked = rowCb.checked);
            updateMasterCheckboxState();
        });
    });

    updateRowCheckboxes();
    updateMasterCheckboxState();
}

function updateMasterCheckboxState() {
    const masterCheckbox = document.getElementById('masterCheckbox');
    const checkboxes = document.querySelectorAll('.permission-checkbox');
    const total = checkboxes.length;
    const checked = Array.from(checkboxes).filter(cb => cb.checked).length;

    masterCheckbox.checked = checked === total;
    masterCheckbox.indeterminate = checked > 0 && checked < total;
}

function updateRowCheckboxes() {
    document.querySelectorAll('.row-checkbox').forEach(rowCb => {
        const rowKey = rowCb.dataset.row;
        const rowChecks = document.querySelectorAll(`.permission-checkbox[data-row="${rowKey}"]`);
        const total = rowChecks.length;
        const checked = Array.from(rowChecks).filter(cb => cb.checked).length;

        rowCb.checked = checked === total;
        rowCb.indeterminate = checked > 0 && checked < total;
    });
}
