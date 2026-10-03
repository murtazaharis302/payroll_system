
document.addEventListener("DOMContentLoaded", function () {
    let progressValue = document.getElementById("progress-value");
    let targetValue = 98;
    let currentValue = 0;

    function updateProgress() {
        if (currentValue < targetValue) {
            currentValue++;
            progressValue.textContent = currentValue + "%";
            setTimeout(updateProgress, 20);
        }
    }
    updateProgress();
});

window.addEventListener("scroll", function () {
let header = document.querySelector(".header-container");
if (window.scrollY > 50) {
header.classList.add("scrolled");
} else {
header.classList.remove("scrolled");
}
});

document.addEventListener("DOMContentLoaded", function () {
let links = document.querySelectorAll(".nav-link");
let currentUrl = window.location.href;

links.forEach(link => {
    if (link.href === currentUrl) {
        link.classList.add("active");
    }
});
});

function filterCars(category) {
    let carCards = document.querySelectorAll('.car-listing-card');
    carCards.forEach(card => {
        if (category === 'all' || card.classList.contains(category)) {
            card.style.display = "block";
        } else {
            card.style.display = "none";
        }
    });

    // Change active button color
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.classList.remove('btn-primary', 'active');
        btn.classList.add('btn-dark');
    });

    event.target.classList.add('btn-primary', 'active');
    event.target.classList.remove('btn-dark');
}

// Show all cars by default on page load
filterCars('all');


