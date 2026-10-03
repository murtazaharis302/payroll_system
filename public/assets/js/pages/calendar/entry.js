!(function ($, CalendarApp) {
    "use strict";

    CalendarApp.prototype.init = function () {
        this.$modal = new bootstrap.Modal(document.getElementById("event-modal"), { keyboard: false });
        this.configureCalendar();
        this.bindFormEvents();
    };

    $.CalendarApp = new CalendarApp();
    $.CalendarApp.Constructor = CalendarApp;
    $.CalendarApp.init();

    $(document).ready(function () {
        $('#event-modal').on('hidden.bs.modal', function () {
            $("#error-message").addClass("d-none");
            if ($("#images")[0].files.length > 0) {
                location.reload();
            }
        });
    });
})(window.jQuery, window.PayrolCalendar.CalendarApp);
