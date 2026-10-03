// table and card render
$(document).ready(function () {
    $('#options-dropdown, #startDate, #endDate, #country-dropdown').on('change', function () {
        let userId = $('#options-dropdown').val();
        let startdate = $('#startDate').val();
        let enddate = $('#endDate').val();
        let countryId = $('#country-dropdown').val();
        let isValid = false;

        if (userId || countryId) {
            isValid = true;
        }
        if (startdate && enddate) {
            isValid = true;
        }

        if (isValid) {
            $.ajax({
                url: window.earningSummaryUrl,
                type: 'GET',
                data: {
                    user_id: userId || null,
                    country_id: countryId || null,
                    start_date: startdate || null,
                    end_date: enddate || null
                },
                success: function (response) {
                    $('#cards-container').html(response.cards);       
                    $('#dashboardtable').html(response.table); 
                },
                error: function (xhr) {
                    alert('Data load failed: ' + xhr.statusText);
                }
            });
        }
    });
});

// donut chart
$(document).ready(function () {
    let donutChart;
    const legendColors = ['#07407B', '#f06115', '#ebeff2', '#28a745', '#8e44ad'];


    function getSelectedCompanyId() {
        return $('#options-dropdown').val();
    }

    function getSelectedCountryId() {
        return $('#country-dropdown').val();
    }

    function showLoadingChart() {
        $('#lifetime-sales').html('<div class="text-center py-5"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');
    }

    function renderChart(data) {
        const total = data.reduce((sum, d) => sum + d.value, 0);
        let chartData = data;
        let chartColors = legendColors;

        if (total === 0) {
            chartData = [{ label: "Confirmed", value: 100 }];
            chartColors = [legendColors[0]];
        }

        $('#lifetime-sales').empty();

        donutChart = new Morris.Donut({
            element: 'lifetime-sales',
            data: chartData,
            colors: chartColors,
            formatter: function (y, dataItem) {
                const actualItem = data.find(d => d.label === dataItem.label);
                return (actualItem?.value ?? 0) + '%';
            }
        });

        const legendContainer = document.querySelector('#lifetime-sales').parentElement;
        legendContainer.querySelectorAll('.d-flex.px-3').forEach(e => e.remove());

        const labels = ['confirmed', 'pending', 'cancelled', 'completed', 'refunded'];
        labels.forEach((label, i) => {
            const percentage = data.find(d => d.label.toLowerCase() === label)?.value ?? 0;
            const legend = `
                <div class="d-flex justify-content-between px-3">
                    <span>
                        <i class="mdi mdi-square" style="color: ${legendColors[i]}"></i>
                        ${label.charAt(0).toUpperCase() + label.slice(1)}
                    </span>
                    <span>${percentage}%</span>
                </div>
            `;
            legendContainer.insertAdjacentHTML('beforeend', legend);
        });
    }

    function fetchAndRenderChart(filter = 'custom') {
        const userId = getSelectedCompanyId();
        const countryId = getSelectedCountryId();
        const startDate = $('#startDate').val();
        const endDate = $('#endDate').val();

        const ajaxParams = {
            filter: filter,
            user_id: userId || null,
            country_id: countryId || null,
            start_date: startDate || null,
            end_date: endDate || null
        };

        showLoadingChart();

        $.ajax({
            url: window.orderStatusRoute,
            type: 'GET',
            data: ajaxParams,
            success: function (response) {
             const chartData = [
                { label: "Confirmed", value: response.confirmed.percentage },
                { label: "Pending", value: response.pending.percentage },
                { label: "Cancelled", value: response.cancelled.percentage },
                { label: "Completed", value: response.completed.percentage },
                { label: "Refunded", value: response.refunded.percentage } 
            ];
                renderChart(chartData);
            },
            error: function () {
                $('#lifetime-sales').html('<div class="text-danger text-center py-5">Failed to load chart data.</div>');
            }
        });
    }

    fetchAndRenderChart();

    $('#options-dropdown, #startDate, #endDate, #country-dropdown').on('change', function () {
        fetchAndRenderChart();
    });

    $('.filter-option').on('click', function (e) {
        e.preventDefault();
        const selectedFilter = $(this).data('filter') || 'custom';
        const selectedText = $(this).text().trim();

        $(this).closest('.dropdown').find('.dropdown-toggle').html(selectedText + ' <i class="fas fa-angle-down ms-2"></i>');

        fetchAndRenderChart(selectedFilter);
    });
});

// booking chart
document.addEventListener("DOMContentLoaded", function () {
    const ctx = document.getElementById("bookingsChart").getContext("2d");

    let chart = new Chart(ctx, {
        type: "bar",
        data: {
            labels: [],
            datasets: [{
                label: "Bookings",
                data: [],
                backgroundColor: [],
                borderRadius: 5,
            }],
        },
        options: {
            responsive: true,
            plugins: {
                tooltip: {
                    enabled: true,
                    backgroundColor: "#000",
                    bodyColor: "#fff",
                    displayColors: false,
                    titleColor: "#ddd",
                    padding: 10,
                    callbacks: {
                        title: function (tooltipItems) {
                            return `Date: ${tooltipItems[0].label}`;
                        },
                        label: function (tooltipItem) {
                            return `Bookings: ${tooltipItem.raw}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    suggestedMax: 10,
                },
            },
            onHover: (event, chartElement) => {
                if (chartElement.length) {
                    const index = chartElement[0].index;
                    const backgroundColors = chart.data.datasets[0].data.map((_, i) => i === index ? 'orange' : 'navy');
                    chart.data.datasets[0].backgroundColor = backgroundColors;
                    chart.update();
                }
            }
        },
    });

    function getSelectedCompanyId() {
        return document.getElementById('options-dropdown').value;
    }

    function getSelectedCountryId() {
        return document.getElementById('country-dropdown')?.value || null;
    }

    function getDateRange() {
        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');
        if (startDateInput && endDateInput) {
            const startDate = startDateInput.value;
            const endDate = endDateInput.value;
            if (startDate && endDate) {
                return { startDate, endDate };
            }
        }
        return null;
    }

    function fetchData(type = 'this_year') {
        const userId = getSelectedCompanyId();
        const countryId = getSelectedCountryId();
        const dateRange = getDateRange();

        const url = new URL(window.bookingChartRoute);

        if (userId) {
            url.searchParams.append('user_id', userId);
        }

        if (countryId) {
            url.searchParams.append('country_id', countryId);
        }

        if (dateRange) {
            url.searchParams.append('start_date', dateRange.startDate);
            url.searchParams.append('end_date', dateRange.endDate);
        } else {
            url.searchParams.append('type', type);
        }

        fetch(url)
            .then(res => res.json())
            .then(response => {
                chart.data.labels = response.labels;
                chart.data.datasets[0].data = response.data;
                chart.data.datasets[0].backgroundColor = response.data.map((_, i) => i === 5 ? 'orange' : 'navy');
                chart.update();

                const labelMap = {
                    'this_year': 'This Year',
                    'month': 'This Month',
                    'last_month': 'Last Month'
                };
                document.getElementById('bookingDropdown').textContent = labelMap[type] || 'Select Range';
            });
    }

    window.updateChart = fetchData;

    fetchData('this_year');

    document.getElementById('options-dropdown').addEventListener('change', () => {
        fetchData(document.getElementById('bookingDropdown').dataset.type || 'this_year');
    });

    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');

    if (startDateInput && endDateInput) {
        startDateInput.addEventListener('change', () => {
            fetchData(document.getElementById('bookingDropdown').dataset.type || 'this_year');
        });
        endDateInput.addEventListener('change', () => {
            fetchData(document.getElementById('bookingDropdown').dataset.type || 'this_year');
        });
    }

    const countryDropdown = document.getElementById('country-dropdown');
    if (countryDropdown) {
        countryDropdown.addEventListener('change', () => {
            fetchData(document.getElementById('bookingDropdown').dataset.type || 'this_year');
        });
    }
});
// earning summary 
document.addEventListener("DOMContentLoaded", function () {
    let chart;

    function initChart(labels, data, currencySymbol = '$') {
        const options = {
            chart: {
                type: 'line',
                height: 300,
                toolbar: { show: false }
            },
            series: [{
                name: "Earnings",
                data: data
            }],
            xaxis: {
                categories: labels
            },
            stroke: { curve: 'smooth', width: 3 },
            markers: {
                size: 0,
                hover: { size: 6, colors: ['#07407B'] }
            },
            colors: ['#07407B'],
            tooltip: {
                enabled: true,
                x: { show: true },
                y: {
                    formatter: val => `${currencySymbol} ${parseFloat(val).toLocaleString()}`
                }
            }
        };

        chart = new ApexCharts(document.querySelector("#earnings-chart"), options);
        chart.render();
    }

    window.fetchEarnings = function (months = 12, label = 'Last 12 Months') {
        const userId = document.getElementById("options-dropdown").value;
        const countryId = document.getElementById("country-dropdown")?.value;
        const startDate = document.getElementById("startDate").value;
        const endDate = document.getElementById("endDate").value;

        const params = new URLSearchParams({
            months: months,
            ...(userId && { user_id: userId }),
            ...(countryId && { country_id: countryId }),
            ...(startDate && { start_date: startDate }),
            ...(endDate && { end_date: endDate })
        });

        fetch(`${window.earningsdata}?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                const currencySymbol = data.defaultCurrencySymbol || '$';

                if (chart) {
                    chart.updateOptions({
                        series: [{ data: data.data }],
                        xaxis: { categories: data.labels },
                        tooltip: {
                            y: {
                                formatter: val => `${currencySymbol} ${parseFloat(val).toLocaleString()}`
                            }
                        }
                    });
                } else {
                    initChart(data.labels, data.data, currencySymbol);
                }

                document.getElementById("dropdownBtn").innerText = label;
            });
    };

    document.getElementById("options-dropdown").addEventListener("change", () => fetchEarnings());
    document.getElementById("startDate").addEventListener("change", () => fetchEarnings());
    document.getElementById("endDate").addEventListener("change", () => fetchEarnings());
    document.getElementById("country-dropdown")?.addEventListener("change", () => fetchEarnings());

    fetchEarnings(12, 'Last 12 Months');
});
