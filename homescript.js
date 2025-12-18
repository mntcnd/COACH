document.addEventListener("DOMContentLoaded", function () {
    const fadeElements = document.querySelectorAll(".fade-in");

    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("show");
                observer.unobserve(entry.target); // Stop observing after effect triggers
            }
        });
    }, { threshold: 0.2 }); // Adjust threshold for effect timing

    fadeElements.forEach(element => observer.observe(element));

    // Slider functionality
    let index = 0;
    let autoSlideInterval;

    function moveSlide(direction) {
        const slides = document.querySelectorAll('.slide');
        const totalSlides = slides.length;

        index += direction;
        if (index < 0) {
            index = totalSlides - 1;
        } else if (index >= totalSlides) {
            index = 0;
        }

        const slider = document.querySelector('.slider');
        const offset = -index * 100; // Each slide takes up 100% width
        slider.style.transform = `translateX(${offset}%)`;
    }

    // Start the auto slide every 5 seconds
    function startAutoSlide() {
        autoSlideInterval = setInterval(() => {
            moveSlide(1);
        }, 5000);
    }

    // Stop auto slide
    function stopAutoSlide() {
        clearInterval(autoSlideInterval);
    }

    // Auto slide when page loads
    startAutoSlide();

    // Add click event to the next and prev buttons
    const nextButton = document.querySelector('.next');
    const prevButton = document.querySelector('.prev');

    if (nextButton && prevButton) {
        nextButton.addEventListener("click", function () {
            stopAutoSlide(); // Stop auto slide when user clicks
            moveSlide(1); // Move to next slide
            startAutoSlide(); // Restart auto slide after manual interaction
        });

        prevButton.addEventListener("click", function () {
            stopAutoSlide(); // Stop auto slide when user clicks
            moveSlide(-1); // Move to previous slide
            startAutoSlide(); // Restart auto slide after manual interaction
        });
    }
});
