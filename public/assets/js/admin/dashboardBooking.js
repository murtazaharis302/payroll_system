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
                url: window.bookingDashboardUrl, 
                type: 'GET',
                data: {
                    user_id: userId || null,
                    country_id: countryId || null,
                    start_date: startdate || null,
                    end_date: enddate || null
                },
                success: function (response) {
                    $('#dashboardtable').html(response.table);
                    $('#cards-container').html(response.cards);
                },
                error: function (xhr) {
                    alert('Data load failed: ' + xhr.statusText);
                }
            });
        }
    });
});


// chart
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('bookingsChart').getContext('2d');
    let chart;

    function loadChartData(months = 12) {
        let userId = $('#options-dropdown').val();
        let countryId = $('#country-dropdown').val();
        let startDate = $('#startDate').val();
        let endDate = $('#endDate').val();

        let params = new URLSearchParams({
            months: months,
            user_id: userId || '',
            country_id: countryId || '',
            start_date: startDate || '',
            end_date: endDate || ''
        });

        fetch(`${window.bookingOverviewDataRoute}?${params.toString()}`)
            .then(res => res.json())
            .then(data => {
                const labels = data.map(d => d.month);
                const confirmed = data.map(d => d.confirmed);
                const cancelled = data.map(d => -d.cancelled);

                if (chart) {
                    chart.data.labels = labels;
                    chart.data.datasets[0].data = confirmed;
                    chart.data.datasets[1].data = cancelled;
                    chart.update();
                } else {
                    chart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [
                                {
                                    label: 'Confirmed',
                                    data: confirmed,
                                    backgroundColor: '#E67E22',
                                    borderRadius: 8,
                                    barThickness: 20
                                },
                                {
                                    label: 'Cancelled',
                                    data: cancelled,
                                    backgroundColor: '#07407B',
                                    borderRadius: 8,
                                    barThickness: 20
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            layout: { padding: { bottom: 30 } },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function (context) {
                                            const label = context.dataset.label || '';
                                            const value = Math.abs(context.parsed.y);
                                            return `${label}: ${value}`;
                                        }
                                    },
                                    backgroundColor: "#EAF4FC",
                                    titleColor: "#000",
                                    titleFont: { size: 12 },
                                    bodyColor: "#000",
                                    bodyFont: { weight: 'bold' },
                                    displayColors: false
                                }
                            },
                            scales: {
                                x: { stacked: true, grid: { display: false } },
                                y: {
                                    stacked: true,
                                    grid: { color: "#ddd" },
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1,
                                        callback: value => Math.abs(value)
                                    }
                                }
                            }
                        }
                    });
                }
            });
    }
    loadChartData();

    document.getElementById('monthSelector').addEventListener('change', function () {
        loadChartData(this.value);
    });

    $('#options-dropdown, #startDate, #endDate, #country-dropdown').on('change', function () {
        const selectedMonths = document.getElementById('monthSelector').value;
        loadChartData(selectedMonths);
    });
});
