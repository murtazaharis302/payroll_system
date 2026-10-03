    
    function showToast(message, type = "info") {
        Toastify({
            text: message,
            duration: 6000,
            close: true,
            gravity: "top", // top or bottom
            position: "right", // left, center, or right
            backgroundColor: type === "success" ? "#2E8B57" :
                                type === "error" ? "#B22222" :
                                type === "warning" ? "orange" : "#333",
            stopOnFocus: true, // Prevents dismissing on hover
        }).showToast();
    }