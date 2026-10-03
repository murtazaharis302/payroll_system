$(document).ready(function(){
    // Call once when the page loads
    loadNotificationsData();

    // Call every 5 seconds (5000 milliseconds)
    setInterval(function() {
        loadNotificationsData();
    }, 5000);
});

 
  function loadNotificationsData() {

    $.ajax({
        url: window.baseUrl+ "/notifications/getNotifications",
        type: "GET",
        cache: false,
        dataType: "json", // Expect JSON directly from server
        success: function (data) {
           
            if (data && data.html) {
                $(".loadbtn").hide();
                $(".notification_list").html(data.html); // Use `.html()` to replace instead of append
            }
        },
        error: function (xhr, status, error) {
            console.error("Failed to load notifications:", error);
        }
    });
}
