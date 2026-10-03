<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <title>@yield('title') | Payroll System</title>
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="A fully featured admin theme which can be used to build CRM, CMS, etc." name="description" />
        <meta content="Coderthemes" name="author" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <!-- App favicon -->
        <link rel="shortcut icon" href="{{asset('/')}}assets/images/favicon.ico">
        <!-- Plugin css -->
        <link href="{{asset('/')}}assets/libs/fullcalendar/main.min.css" rel="stylesheet" type="text/css" />
        <!-- Bootstrap css -->
        <link href="{{asset('/')}}assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <!-- App css -->
        <link href="{{asset('/')}}assets/css/app.min.css" rel="stylesheet" type="text/css" id="app-style"/>
        <!-- icons -->
        <link href="{{asset('/')}}assets/css/icons.min.css" rel="stylesheet" type="text/css" />
        <!-- Head js -->
        <script src="{{asset('/')}}assets/js/head.js"></script>

        <link href="{{asset('/')}}assets/css/admin.css" rel="stylesheet" type="text/css" />

        <!-- company dashboard css -->
        <link href="{{asset('/')}}assets/css/company-dashboard.css" rel="stylesheet" type="text/css" id="app-style"/>

        {{-- For Delete Confirmation  --}}
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
        <link href="https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.min.css" rel="stylesheet">
        <script src="https://cdn.datatables.net/2.2.2/js/dataTables.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
        <!-- Select2 CSS -->
        <link href="{{ asset('assets/libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />

    </head>
    <body data-layout-mode="default" data-theme="light" data-topbar-color="dark" data-menu-position="fixed" data-leftbar-color="dark" data-leftbar-size='default' data-sidebar-user='false'>
        <script>
            (function() {
                var savedSize = localStorage.getItem('leftbar_size');
                if (savedSize === 'condensed' || savedSize === 'default') {
                    document.body.setAttribute('data-leftbar-size', savedSize);
                }
            })();
        </script>
        @include('admin.layouts.navbar')
        @include('admin.layouts.leftbar')
        <div id="wrapper">
            <div class="content-page">
                @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Whoops!</strong> There were some problems with your input.<br><br>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                @endif

                <div class="content">
                    @include('admin.layouts.rightbar')
                    @yield('content')
                </div>
                @include('admin.layouts.footer')
            </div>
            <div class="rightbar-overlay"></div>
        </div>
        <!-- Vendor js -->
        <script src="{{asset('/')}}assets/js/vendor.min.js"></script>
        <!-- plugin js -->
        <script src="{{asset('/')}}assets/libs/moment/min/moment.min.js"></script>
        <script src="{{asset('/')}}assets/libs/fullcalendar/main.min.js"></script>
        <!-- Calendar init -->
        <script src="{{asset('/')}}assets/js/pages/calendar/core.js"></script>
        <script src="{{asset('/')}}assets/js/pages/calendar/setup.js"></script>
        <script src="{{asset('/')}}assets/js/pages/calendar/form-events.js"></script>
        <script src="{{asset('/')}}assets/js/pages/calendar/entry.js"></script>
        <!-- App js -->
        <script src="{{asset('/')}}assets/js/app.min.js"></script>
        <script src="{{asset('/')}}assets/js/admin.js"></script>
        <!-- Permissions -->
        <script src="{{asset('/')}}assets/js/admin/permissions.js"></script>
        <!-- Select2 JS -->
        <script src="{{ asset('assets/libs/select2/js/select2.min.js') }}"></script>
        <!-- notifications -->
        {{-- <script src="{{asset('/')}}assets/js/admin/notifications.js"></script> --}}
        <script type="text/javascript">
            // For Delete Confirmation
            $(".btn-delete").click(function(){
                return confirm("Are you sure?");
            });
            // For Approving the company
            $(".btn-aprove-company").click(function(){
                return confirm("Are you sure to Confirm?");
            });
            // For Delete Confirmation For Activity Logs Only
            $(".btn-delete-logs").click(function(){
                return confirm("Are you sure ?");
            });
            // hide status and auto-dismiss alerts after 5 seconds
            $(document).ready(function(){
                $("#div1").delay(6000).fadeOut(1500);
                setTimeout(function() {
                    $('.alert.alert-dismissible').fadeOut(500, function() {
                        $(this).remove();
                    });
                }, 5000);
            });
        </script>
        @if(auth()->check())
        <script>
            window.baseUrl = "{{ url('/' . panelPrefix()) }}";
        </script>
        @endif
        <script type="text/javascript">
            window.initSearchableSelects = function(container) {
                if (!window.jQuery || !$.fn.select2) return;

                var $context = container ? $(container) : $(document);

                // 1. Payroll Period Selectors
                var periodSelectors = $context.find('select[data-period-search], select.select2-payroll-period, select[name="payroll_period_id"], select[name="payroll_period_id[]"]')
                    .add($context.filter('select[data-period-search], select.select2-payroll-period, select[name="payroll_period_id"], select[name="payroll_period_id[]"]'));

                periodSelectors.each(function() {
                    var $el = $(this);
                    if ($el.hasClass('select2-hidden-accessible')) return;

                    var $modal = $el.closest('.modal');
                    var config = {
                        width: '100%',
                        placeholder: $el.data('placeholder') || $el.attr('placeholder') || '-- Select Payroll Period --',
                        allowClear: true,
                        matcher: function(params, data) {
                            if (!params.term || $.trim(params.term) === '') {
                                return data;
                            }

                            var term = params.term.toLowerCase();

                            // Match option text
                            if (data.text && data.text.toLowerCase().indexOf(term) > -1) {
                                return data;
                            }

                            // Match data-search attribute
                            var searchAttr = $(data.element).data('search');
                            if (searchAttr && String(searchAttr).toLowerCase().indexOf(term) > -1) {
                                return data;
                            }

                            // Match optgroup label or children options
                            if (data.children && data.children.length > 0) {
                                var filteredChildren = [];
                                var groupMatch = data.text && data.text.toLowerCase().indexOf(term) > -1;

                                $.each(data.children, function(idx, child) {
                                    if (groupMatch ||
                                        (child.text && child.text.toLowerCase().indexOf(term) > -1) ||
                                        ($(child.element).data('search') && String($(child.element).data('search')).toLowerCase().indexOf(term) > -1)) {
                                        filteredChildren.push(child);
                                    }
                                });

                                if (filteredChildren.length > 0) {
                                    var modifiedData = $.extend({}, data, true);
                                    modifiedData.children = filteredChildren;
                                    return modifiedData;
                                }
                            }

                            return null;
                        }
                    };

                    if ($modal.length) {
                        config.dropdownParent = $modal;
                    }

                    $el.select2(config);
                });

                // 2. Employee Search Selectors
                var employeeSelectors = $context.find('select[data-employee-search], select.select2-employee-search, select[name="employee_id"], select[name="employee_id[]"]')
                    .add($context.filter('select[data-employee-search], select.select2-employee-search, select[name="employee_id"], select[name="employee_id[]"]'));

                employeeSelectors.each(function() {
                    var $el = $(this);
                    if ($el.hasClass('select2-hidden-accessible')) return;

                    var searchUrl = $el.data('search-url') || (window.baseUrl ? window.baseUrl + '/employees/search' : '/api/employees/search');
                    var companyId = $el.data('company-id');
                    var $modal = $el.closest('.modal');

                    var config = {
                        width: '100%',
                        placeholder: $el.data('placeholder') || $el.attr('placeholder') || '-- Search / Select Employee --',
                        allowClear: true,
                        ajax: {
                            url: searchUrl,
                            dataType: 'json',
                            delay: 250,
                            data: function(params) {
                                return {
                                    q: params.term || '',
                                    page: params.page || 1,
                                    limit: 25,
                                    company_id: companyId
                                };
                            },
                            processResults: function(data, params) {
                                params.page = params.page || 1;
                                return {
                                    results: data.results || [],
                                    pagination: {
                                        more: data.pagination ? data.pagination.more : false
                                    }
                                };
                            },
                            cache: true
                        },
                        minimumInputLength: 0
                    };

                    if ($modal.length) {
                        config.dropdownParent = $modal;
                    }

                    $el.select2(config);
                });
            };

            $(document).ready(function() {
                window.initSearchableSelects();
            });

            $(document).on('shown.bs.modal shown.bs.collapse shown.bs.tab', function(e) {
                window.initSearchableSelects(e.target);
            });
        </script>
        <!-- Toaster  -->
        <!-- CSS -->
        <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
        <!-- JS -->
        <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
        <script src="{{ asset('/frontend-assets/assets/Js/toaster-alert.js') }}"></script>

        @if(session('status') || session('success') || session('statusSuccess'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof showToast === 'function') {
                    showToast("{{ session('status') ?? session('success') ?? session('statusSuccess') }}", "success");
                }
            });
        </script>
        @endif
        @if(session('statusDanger') || session('error'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof showToast === 'function') {
                    showToast("{{ session('statusDanger') ?? session('error') }}", "error");
                }
            });
        </script>
        @endif

        @yield('scripts')
    </body>
</html>
