!(function ($) {
    "use strict";

    function CalendarApp() {
        this.$body = $("body");
        this.$modal = $("#event-modal");
        this.$calendar = $("#calendar");
        this.$formEvent = $("#form-event");
        this.$btnNewEvent = $("#btn-new-event");
        this.$btnDeleteEvent = $("#btn-delete-event");
        this.$btnSaveEvent = $("#btn-save-event");
        this.$btnEditEvent = $("#btn-edit-event");
        this.$modalTitle = $("#modal-title");

        this.$calendarObj = null;
        this.$selectedEvent = null;
        this.$newEventData = null;
    }

    CalendarApp.prototype.onEventClick = function (event) {

        this.$formEvent[0].reset();
        this.$formEvent.removeClass("was-validated");
        this.$newEventData = null;
        this.$btnDeleteEvent.show();
        this.$modalTitle.text("Edit Vehicle");
        this.$modal.show();
        this.$btnEditEvent.show();
        this.$btnSaveEvent.hide();
        this.$selectedEvent = event.event;
        // $("#event-title").val(this.$selectedEvent.title);

        $("#lisence_plate").val(this.$selectedEvent.title);
        $("#event-category").val(this.$selectedEvent.classNames[0]);

        // let thumbnail = this.$selectedEvent.extendedProps.thumbnail;
        // if (typeof thumbnail !== "string") {
        //       thumbnail = event.event.extendedProps.thumbnail;
        //       console.log(thumbnail)
        // }

        // if (thumbnail) {
        //     $("#uploadThumbnailPreview").attr("src", '/storage/' + thumbnail);
        // }
        $("#ThumbnailPreview").empty();
        const SelectedThumbnail = this.$selectedEvent.extendedProps.thumbnail;
        const thumb = $("<img>").attr("src", "/storage/" + SelectedThumbnail).addClass("thumbnail").css({ width: "120px", margin: "5px" });
        $("#ThumbnailPreview").append(thumb);

        $("#currentImagePreview").empty();
        const images = this.$selectedEvent.extendedProps.images;
        if (Array.isArray(images)) {
            images.forEach(image => {
                const img = $("<img>")
                .attr("src", "/storage/" + image)
                .css({
                    width: "120px",
                    height: "120px",
                    borderRadius: "8px",
                    margin: "5px",
                    boxShadow: "0 2px 5px rgba(0, 0, 0, 0.2)"
                });

                $("#currentImagePreview").append(img);
            });
        }


        const props = [
            "vehicle_id", "model", "category", "city", "location",
            "fuel_type", "transmission", "drive", "weight", "doors",
            "year", "engine_size", "luggage", "seats", "fuel_economy",
            "exterior_color", "interior_color", "rent", "mileage",
            "detail", "status", "date_added", "features",
        ];

        props.forEach(prop => {
        const el = document.getElementById(prop);
        const value = this.$selectedEvent.extendedProps[prop] ?? "";

        if (prop === "features") {
            const features = Array.isArray(value) ? value : [];
            document.querySelectorAll("input[name='features[]']").forEach((checkbox) => {
                checkbox.checked = features.includes(checkbox.value);
            });
            return;
        }

        if (el) {
            el.value = value;
        }

    });


        window.PayrolCalendar.loadArea(
            this.$selectedEvent.extendedProps.city,
            this.$selectedEvent.extendedProps.location
        );
    };

    window.PayrolCalendar = {
        CalendarApp: CalendarApp,
        loadArea: function (city_id, selected_location_id = null) {
            if (city_id) {
                $.ajax({
                    url: window.getLocationsUrl + '/' + city_id,
                    type: 'GET',
                    dataType: 'json',
                    success: function (data) {
                        const $locationSelect = $('select[name="location"]');
                        $locationSelect.prop('disabled', false).empty();
                        $locationSelect.append('<option value="" selected>Select Area</option>');

                        $.each(data, function (key, value) {
                            const selected = selected_location_id == value.id ? 'selected' : '';
                            $locationSelect.append('<option value="' + value.id + '" ' + selected + '>' + value.area_name + '</option>');
                        });
                    },
                    error: function (xhr) {
                        console.log(xhr.responseText);
                    }
                });
            } else {
                const $locationSelect = $('select[name="location"]');
                $locationSelect.prop('disabled', true).empty();
                $locationSelect.append('<option value="" selected>Select Area</option>');
            }
        }
    };
})(window.jQuery);

    CalendarApp.prototype.onSelect = function (event) {

        this.$formEvent[0].reset();
        this.$formEvent.removeClass("was-validated");
        this.$selectedEvent = null;
        this.$newEventData = event;
        $("#currentImagePreview").html('');
        $("#ThumbnailPreview").html('');

        // Manually clear location dropdown and disable it
        $('#location').html('<option disabled selected>Select</option>').prop('disabled', true);

        // // Reset file inputs
        // $('#thumbnail').val('');
        // $('#images').val('');

        // // Clear preview images
        //$('#uploadThumbnailPreview').removeAttr('src');
        // // Clear uploaded image previews
        // $('#uploadImagePreview').empty().hide();

        // // Clear existing (preloaded) image previews, if any
        // $('#currentImagePreview').empty().hide();


        // // Clear selected features
        // $("input[name='features[]']").prop('checked', false);

        // // Clear hidden ID field
        // $('#vehicle_id').val('');

        this.$btnDeleteEvent.hide();
        this.$btnEditEvent.hide();
        this.$btnSaveEvent.show();
        this.$modalTitle.text("Create Vehicle");
        this.$modal.show();
        this.$calendarObj.unselect();
    };
