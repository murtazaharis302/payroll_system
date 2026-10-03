$(document).ready(function () {
    $(".load-more-btn").click(function () {
        let button = $(this);
        let targetList = $("#" + button.data("target"));
        let loadMoreUrl = button.data("url");
        let offset = parseInt(button.data("offset")) || 8; // Default offset to 8
        let model = button.data("model");
        let totalRecords = parseInt(button.data("total")) || 0; // Default to 0

        // Check if filters are applied
        let transmission = $("#transmission-filter").val();
        let sort = $("#sort-filter").val();
        let carModelId = $("#carModelDropdownBtn").attr("data-selected") || "";
        let address = $("#addressFilter").val();
        let date = $("#dateFilter").val();
        let time = $("#timeFilter").val();
        let categoryId = $(".category-filter.active").data("category") || "";

        $.ajax({
            url: loadMoreUrl,
            type: "GET",
            data: {
                offset: offset,
                model: model,
                transmission: transmission,
                sort: sort,
                car_model_id: carModelId,
                address: address,
                date: date,
                time: time,
                category_id: categoryId
            },
            beforeSend: function () {
                button.text("Loading...");
            },
            success: function (response) {
                if ($.trim(response.data) === "") {
                    button.hide();
                } else {
                    targetList.append(response.data);

                    // Determine if we should use filtered count or total count
                    let newTotal = response.filteredCount || totalRecords;

                    // Update offset
                    let newOffset = offset + 8;
                    if (newOffset > newTotal) {
                        newOffset = newTotal;
                    }
                    button.data("offset", newOffset);

                    // Update current count if applicable
                    let currentCountElement = $("#current-count");
                    if (currentCountElement.length) {
                        let currentCount = Math.min(newOffset, newTotal) || 0;
                        currentCountElement.text(currentCount);
                    }

                    // Hide button if no more records to load
                    if (!response.hasMore) {
                        button.hide();
                    } else {
                        button.text("Load More");
                    }
                }
            },
            error: function () {
                button.text("Load More").prop("disabled", false);
                alert("Something went wrong. Please try again.");
            }
        });
    });
});
