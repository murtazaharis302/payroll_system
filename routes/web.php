<?php

require __DIR__ . '/web/public.php';

Route::middleware(['auth', 'CompanyAuth', 'CheckMenuAccess'])->group(function () {
    require __DIR__ . '/web/company.php';
    require __DIR__ . '/web/employee.php';
    require __DIR__ . '/web/admin_tenant.php';
    require __DIR__ . '/web/hr.php';
});
