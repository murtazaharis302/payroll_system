!(function ($, CalendarApp) {
    "use strict";

    CalendarApp.prototype.configureCalendar = function () {
        this.$modal = new bootstrap.Modal(document.getElementById("event-modal"), { keyboard: false });
        var currentDate = new Date($.now());

        new FullCalendar.Draggable(document.getElementById("external-events"), {
            itemSelector: ".external-event",
            eventData: function (eventEl) {
                return {
                    title: eventEl.innerText,
                    className: $(eventEl).data("class")
                };
            }
        });

        var vehicles= function vehicleDetails()
            {
                return new Promise((resolve) => {
                    $.ajax({
                            url: window.baseUrl+ '/getVehicles',
                            method: "GET",
                            dataType: "json",
                            success: function (response) {

                            console.log(response);
                            resolve(response);

                            //document.getElementById("uploadThumbnailPreview").src = "/storage/" + selectedEvent.extendedProps.thumbnail;
                            $("#ThumbnailPreview").empty();
                            const thumb = $("<img>").attr("src", "/storage/" + selectedEvent.extendedProps.thumbnail);
                            $("#ThumbnailPreview").append(thumb);

                            $("#currentImagePreview").empty();
                            const images = selectedEvent.extendedProps.images;
                            if (Array.isArray(images)) {
                                images.forEach(image => {
                                    const img = $("<img>")
                                        .attr("src", "/storage/" + image)
                                        .addClass("image") // optional class
                                        .css({ width: "100px", margin: "5px" }); // optional styling
                                    $("#currentImagePreview").append(img);
                                });
                            }

                            const fields = [
                                "model", "vehicle_id", "category", "city", "location",
                                "fuel_type", "transmission", "drive", "weight", "doors",
                                "year", "engine_size", "luggage", "seats", "fuel_economy",
                                "exterior_color", "interior_color", "lisence_plate", "rent",
                                "mileage", "detail", "status", "date_added",
                            ];
                            fields.forEach(field => {
                                // const value = this.$selectedEvent.getExtendedProp(field);
                                const value = selectedEvent.extendedProps.field;
                                const element = document.getElementById(field);
                                if (element) element.value = value ?? "";
                            });
                            let features = selectedEvent.extendedProps.features;
                            features.forEach(feature => {
                                let checkbox = document.querySelector(`input[type="checkbox"][value="${feature}"]`);
                                if (checkbox) {
                                    checkbox.checked = true;
                                }
                            });

                        },
                        error: function (xhr, status, error) {
                            console.error("Error fetching events:", xhr.responseText);
                        }
                    });
                });
            };


        var self = this;
        this.$calendarObj = new FullCalendar.Calendar(this.$calendar[0], {

            eventDrop: function(info) {
                const event = info.event;

                $.ajax({
                    url: window.baseUrl+'/updateEventDate',
                    method: 'POST',
                    data: {
                        vehicle_id: event.extendedProps.vehicle_id,
                        new_start: event.start.toISOString(),
                    },
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            alert('Date updated successfully');
                        } else {
                            alert('Update failed: ' + response.message);
                            info.revert();
                        }
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                        alert('Error updating date');
                        info.revert(); // Revert drag on error
                    }
                });
            },


            slotDuration: "00:15:00",
            slotMinTime: "08:00:00",
            slotMaxTime: "19:00:00",
            themeSystem: "bootstrap",
            bootstrapFontAwesome: false,
            buttonText: {
                today: "Today",
                month: "Month",
                week: "Week",
                day: "Day",
                list: "List",
                prev: "Prev",
                next: "Next"
            },
            initialView: "dayGridMonth",
            handleWindowResize: true,
            height: $(window).height() - 200,
            headerToolbar: {
                left: "prev,next today",
                center: "title",
                right: "dayGridMonth,listMonth"
                //right: "dayGridMonth,listMonth"
            },
            events: vehicles,
            editable: true,
            droppable: true,
            selectable: true,
            dateClick: function (info) {
                self.onSelect(info);
            },
            eventClick: function (info) {
                self.onEventClick(info);
            },
            onSelect: function(info){
                console.log(info+"something");
            }
        });

        this.$calendarObj.render();

        this.$btnNewEvent.on("click", function () {
            self.onSelect({ date: new Date(), allDay: true });
        });
        // $.ajaxSetup({
        //     headers: {
        //         'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        //     }
        // });
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

    };
})(window.jQuery, window.PayrolCalendar.CalendarApp);
