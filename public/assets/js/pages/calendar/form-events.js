!(function ($, CalendarApp) {
    "use strict";

    CalendarApp.prototype.bindFormEvents = function () {
        var self = this;
        let clickedButtonId = null;

        $('#btn-save-event, #btn-edit-event').on('click', function () {
            clickedButtonId = this.id;
        });

        this.$formEvent.on("submit", function (event) {
            event.preventDefault();
            $("#error-message").addClass("d-none");
            var form = self.$formEvent[0];
            if (form.checkValidity()) {
                if (self.$selectedEvent) {

                    const formData = new FormData();

                    formData.append('_method', 'PUT');

                    const thumbnailInput = document.getElementById("thumbnail");
                    const thumbnailProp = thumbnailInput.files[0];
                    if (thumbnailInput.files.length > 0) {
                        formData.append("thumbnail", thumbnailInput.files[0]);
                    }

                    const imageInput = document.getElementById("images");
                    for (let i = 0; i < imageInput.files.length; i++) {
                        formData.append("images[]", imageInput.files[i]);
                    }

                    formData.append('lisence_plate', $('#lisence_plate').val());
                    formData.append('vehicle_id', $('#vehicle_id').val());
                    formData.append('car_model_id', $('#model').val());
                    formData.append('car_category_id', $('#category').val());
                    formData.append('city', $('#city').val());
                    formData.append('car_location_id', $('#location').val());
                    formData.append('fuel_type', $('#fuel_type').val());
                    formData.append('transmission', $('#transmission').val());
                    formData.append('drive', $('#drive').val());
                    formData.append('weight', $('#weight').val());
                    formData.append('doors', $('#doors').val());
                    formData.append('year', $('#year').val());
                    formData.append('engine_size', $('#engine_size').val());
                    formData.append('luggage', $('#luggage').val());
                    formData.append('seats', $('#seats').val());
                    formData.append('fuel_economy', $('#fuel_economy').val());
                    formData.append('exterior_color', $('#exterior_color').val());
                    formData.append('interior_color', $('#interior_color').val());
                    formData.append('rent', $('#rent').val());
                    formData.append('mileage', $('#mileage').val());
                    formData.append('detail', $('#detail').val());
                    formData.append('status', $('#status').val());

                    $("input[name='features[]']:checked").each(function () {
                        formData.append("features[]", $(this).val());
                    });

                    $.ajax({
                        headers: {
                            'Accept': 'application/json'
                        },
                        url: window.baseUrl+"/updateVehicle",
                        method: "POST",
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response){
                            if(response.status == 'error'){
                                let errorMessage = '';

                                if (response.errors) {
                                    $.each(response.errors, function(key, value) {
                                        errorMessage += '<div>' + value[0] + '</div>';
                                    });
                                } else {
                                    errorMessage = response.message;
                                }

                                $("#error-message")
                                .html(errorMessage)
                                .removeClass("d-none")
                                .show();
                                self.$modal.show();
                            }

                            if(response.status === 'success'){
                                location.reload();
                                var thumbNewPath = response.thumbnailProp;
                                console.log(thumbNewPath)+here;
                                if (thumbnailInput.files.length > 0){
                                    self.$selectedEvent.setExtendedProp("thumbnail", thumbNewPath);
                                }
                                //self.$selectedEvent.setExtendedProp("location", $("#event-location").val());
                                self.$selectedEvent.setProp("title", $('#lisence_plate').val());

                                // $("#ThumbnailPreview").empty();
                                // const thumb = $("<img>").attr("src", "/storage/" + selectedEvent.extendedProps.thumbnail);
                                // $("#ThumbnailPreview").append(thumb);

                                // $("#currentImagePreview").empty();
                                // const images = selectedEvent.extendedProps.images;
                                // if (Array.isArray(images)) {
                                //     images.forEach(image => {
                                //         const img = $("<img>")
                                //             .attr("src", "/storage/" + image)
                                //             .addClass("image") // optional class
                                //             .css({ width: "100px", margin: "5px" }); // optional styling
                                //         $("#currentImagePreview").append(img);
                                //     });
                                // }

                                if(imageInput.files.length > 0){
                                    self.$selectedEvent.setExtendedProp("images", imageInput);
                                }
                                // Correctly update all extendedProps
                                const props = [
                                    "model", "category", "city", "location", "fuel_type", "transmission",
                                    "drive", "weight", "doors", "year", "engine_size", "luggage", "seats",
                                    "fuel_economy", "exterior_color", "interior_color", "lisence_plate",
                                    "rent", "mileage", "detail", "status",
                                ];

                                props.forEach(prop => {
                                    self.$selectedEvent.setExtendedProp(prop, $("#" + prop).val());
                                });


                                // const thumbnail = this.$selectedEvent.extendedProps.thumbnail;
                                // if (thumbnail) {
                                //     $("#uploadThumbnailPreview").attr("src", '/storage/' + thumbnail);
                                // }

                                self.$selectedEvent.setExtendedProp("features", selectedFeatures);
                                alert(response.message);
                                self.$modal.hide();
                            }else {
                                console.error("Server responded with failure:", response.message);
                            }
                        },
                        error: function (xhr) {
                            console.error("Save failed:", xhr.responseText);
                        }
                    });
                } else {

                    var start = self.$newEventData.dateStr || new Date().toISOString();

                    // const selectedFeatures = [];
                    // $("input[name='features[]']:checked").each(function () {
                    //     selectedFeatures.push($(this).val());
                    // });

                    const formData = new FormData();
                    formData.append('start', start)
                    const thumbnailInput = document.getElementById("thumbnail");
                    const thumbnailProp = thumbnailInput.files[0];
                    if (thumbnailInput.files.length > 0) {
                        formData.append("thumbnail", thumbnailInput.files[0]);
                    }

                    const imageInput = document.getElementById("images");
                    for (let i = 0; i < imageInput.files.length; i++) {
                        formData.append("images[]", imageInput.files[i]);
                    }

                    formData.append('lisence_plate', $('#lisence_plate').val());
                    formData.append('vehicle_id', $('#vehicle_id').val());
                    formData.append('car_model_id', $('#model').val());
                    formData.append('car_category_id', $('#category').val());
                    formData.append('city', $('#city').val());
                    formData.append('car_location_id', $('#location').val());
                    formData.append('fuel_type', $('#fuel_type').val());
                    formData.append('transmission', $('#transmission').val());
                    formData.append('drive', $('#drive').val());
                    formData.append('weight', $('#weight').val());
                    formData.append('doors', $('#doors').val());
                    formData.append('year', $('#year').val());
                    formData.append('engine_size', $('#engine_size').val());
                    formData.append('luggage', $('#luggage').val());
                    formData.append('seats', $('#seats').val());
                    formData.append('fuel_economy', $('#fuel_economy').val());
                    formData.append('exterior_color', $('#exterior_color').val());
                    formData.append('interior_color', $('#interior_color').val());
                    formData.append('rent', $('#rent').val());
                    formData.append('mileage', $('#mileage').val());
                    formData.append('detail', $('#detail').val());
                    formData.append('status', $('#status').val());

                    $("input[name='features[]']:checked").each(function () {
                        formData.append("features[]", $(this).val());
                    });

                    $.ajax({
                        headers: {
                            'Accept': 'application/json'
                        },
                        url: window.baseUrl+ "/storeVehicle",
                        method: "POST",
                        data: formData,
                        processData: false,
                        contentType: false,

                    // $.ajax({
                    //     url: "/admin/storeVehicle",
                    //     method: "POST",

                    //     data: {
                    //         lisence_plate: $("#lisence_plate").val(),
                    //         start: start,
                    //         car_model_id: $('#model').val(),
                    //         car_category_id : $('#category').val(),
                    //         car_location_id: $('#location').val(),
                    //         city: $('#city').val(),
                    //         fuel_type: $('#fuel_type').val(),
                    //         transmission: $('#transmission').val(),
                    //         drive: $('#drive').val(),
                    //         weight: $('#weight').val(),
                    //         doors: $('#doors').val(),
                    //         year: $('#year').val(),
                    //         engine_size: $('#engine_size').val(),
                    //         luggage: $('#luggage').val(),
                    //         seats: $('#seats').val(),
                    //         fuel_economy: $('#fuel_economy').val(),
                    //         exterior_color: $('#exterior_color').val(),
                    //         interior_color: $('#interior_color').val(),
                    //         rent: $('#rent').val(),
                    //         mileage: $('#mileage').val(),
                    //         detail: $('#detail').val(),
                    //         status: $('#status').val(),
                    //         features: selectedFeatures,
                    //     },
                        success: function (response) {

                            if(response.status == 'error'){
                                let errorMessage = '';

                                if (response.errors) {
                                    $.each(response.errors, function(key, value) {
                                        errorMessage += '<div>' + value[0] + '</div>';
                                    });
                                } else {
                                    errorMessage = response.message;
                                }

                                $("#error-message")
                                .html(errorMessage)
                                .removeClass("d-none")
                                .show();
                                self.$modal.show();
                            }

                            if(response.status == 'success'){
                                var start = self.$newEventData.dateStr || new Date().toISOString();
                                var end = start;
                                var title = response['licensePlate'];
                                var className = 'bg-success';
                                // // Add to calendar
                                // self.$calendarObj.addEvent({
                                //     title: title,
                                //     start: start,
                                //     end: end,
                                //     className: className,
                                //     extendedProps: {
                                //         lisence_plate: $("#lisence_plate").val(),
                                //         model: $("#model").val(),
                                //         category: $("#category").val(),
                                //         location: $("#location").val(),
                                //         city: response['city'],
                                //         fuel_type: $("#fuel_type").val(),
                                //         transmission: $("#transmission").val(),
                                //         drive: $("#drive").val(),
                                //         weight: $("#weight").val(),
                                //         doors: $("#doors").val(),
                                //         year: $("#year").val(),
                                //         engine_size: $("#engine_size").val(),
                                //         luggage: $("#luggage").val(),
                                //         seats: $("#seats").val(),
                                //         fuel_economy: $("#fuel_economy").val(),
                                //         exterior_color: $("#exterior_color").val(),
                                //         interior_color: $("#interior_color").val(),
                                //         rent: $("#rent").val(),
                                //         mileage: $("#mileage").val(),
                                //         detail: $("#detail").val(),
                                //         status: $("#status").val(),
                                //     },
                                // });

                                alert(response.message);
                                self.$modal.hide();
                                self.$calendarObj.refetchEvents();
                            }
                        },
                        error: function (xhr) {
                            console.error("Save failed:", xhr.responseText);
                        }
                    });
                }
                self.$modal.hide();

            } else {
                event.stopPropagation();
                form.classList.add("was-validated");
            }
        });

        $(this.$btnDeleteEvent).on("click", function () {
            if (self.$selectedEvent) {
                    $.ajax({
                        url: window.baseUrl+ "/deleteVehicle",
                        method: "DELETE",

                        data: {
                            vehicle_id: $('#vehicle_id').val(),
                        },
                        success: function (response) {

                            self.$selectedEvent.remove();
                            self.$selectedEvent = null;
                            self.$modal.hide();
                            alert(response.message);
                        },
                        error: function (xhr) {
                            console.error("Save failed:", xhr.responseText);
                        }
                    });
            }
        });
    };
})(window.jQuery, window.PayrolCalendar.CalendarApp);
