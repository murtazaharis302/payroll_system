import './bootstrap';

window.initSearchableSelects = function(container) {
    if (typeof jQuery === 'undefined' || !jQuery.fn.select2) return;
    var $ = jQuery;
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

                if (data.text && data.text.toLowerCase().indexOf(term) > -1) {
                    return data;
                }

                var searchAttr = $(data.element).data('search');
                if (searchAttr && String(searchAttr).toLowerCase().indexOf(term) > -1) {
                    return data;
                }

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

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof window.initSearchableSelects === 'function') {
            window.initSearchableSelects();
        }
    });
}
