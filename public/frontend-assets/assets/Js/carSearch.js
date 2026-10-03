$(document).ready(function () {
    let brandDropdown = $('#brandDropdown');
    let modelDropdown = $('#modelDropdown');
    let transmissionDropdown = $('#transmissionDropdown');
    let carSearchForm = $('#carSearchForm');

    // CSRF Token Setup
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    function fetchData() {
        const brandLabel = $('#brandDropdown').data('label');
        const transmissionLabel = $('#transmissionDropdown').data('label');
        const modelLabel = $('#modelDropdown').data('label');
        $.ajax({
            url: brandDropdown.data('url'),
            method: 'GET',
            success: function (response) {
                if (response.status) {
                    brandDropdown.html('<option disabled selected>'+brandLabel+'</option>');
                    $.each(response.brands, function (i, brand) {
                        brandDropdown.append(`<option value="${brand.id}">${brand.name}</option>`);
                    });

                    transmissionDropdown.html('<option disabled selected>'+transmissionLabel+'</option>');
                    $.each(response.transmissions, function (i, trans) {
                        transmissionDropdown.append(`<option value="${trans}">${trans}</option>`);
                    });

                    modelDropdown.html('<option disabled selected>'+modelLabel+'</option>');
                    $.each(response.models, function (i, model) {
                        modelDropdown.append(`<option value="${model.id}">${model.name}</option>`);
                    });
                }
            },
            error: function () {
                alert('Failed to load filter data.');
            }
        });
    }

    function loadLocations() {
        let locationDropdown = $('#locationDropdown');
        let url = locationDropdown.data('url');
        const locationLabel = $('#area_name').data('label');
        $('#area_name').attr('placeholder', locationLabel);
        $.ajax({
            url: url,
            method: 'GET',
            success: function (response) {
                if (response.status) {
                    locationDropdown.html('<option disabled selected>'+locationLabel+'</option>');
                    $.each(response.locations, function (i, loc) {
                        locationDropdown.append(`<option value="${loc.id}">${loc.area_name}</option>`);
                    });
                }
            },
            error: function () {
                showToast("Failed to load locations.", "error");
            }
        });
    }

    fetchData();
    loadLocations();

    brandDropdown.on('change', function () {
        let selectedBrandId = $(this).val();

        $.ajax({
            url: brandDropdown.data('url'),
            method: 'GET',
            data: { brand_id: selectedBrandId },
            success: function (response) {
                if (response.status) {
                    modelDropdown.html('<option disabled selected>Model</option>');
                    $.each(response.models, function (i, model) {
                        modelDropdown.append(`<option value="${model.id}">${model.name}</option>`);
                    });
                }
            },
            error: function () {
                showToast('Failed to fetch models for selected brand.', "error");
            }
        });
    });

    carSearchForm.on('submit', function (e) {
        e.preventDefault();
        let form = $(this);
        let submitBtn = form.find('button[type="submit"]');

        submitBtn.prop('disabled', true).text('Processing...');

        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.status && response.redirect_url) {
                    setTimeout(function () {
                        window.location.href = response.redirect_url;
                    }, 300);
                } else {
                    showToast('No cars found or redirect failed.', "error");
                    submitBtn.prop('disabled', false).text('Search');
                }
            },
            error: function (xhr) {
                submitBtn.prop('disabled', false).text('Search');

                if (xhr.status === 422 && xhr.responseJSON.errors) {
                    let errorMsg = '';
                    $.each(xhr.responseJSON.errors, function (key, val) {
                        errorMsg += val[0] + '\n';
                    });
                    showToast(errorMsg, "error");
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    showToast(xhr.responseJSON.message, "error");
                } else {
                    showToast('Something went wrong.', "error");
                }
            }
        });
    });
});
