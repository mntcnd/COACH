const container = document.querySelector('.card__container');
const cards = document.querySelectorAll('.card__article');
const prevBtn = document.querySelector('.prev-btn');
const nextBtn = document.querySelector('.next-btn');

let currentIndex = 0;

function updateCarousel() {
  const cardWidth = cards[0].offsetWidth + 16; // adjust if your gap is different
  container.style.transform = `translateX(-${currentIndex * cardWidth}px)`;

  // Disable buttons at edges
  prevBtn.disabled = currentIndex === 0;
  nextBtn.disabled = currentIndex >= cards.length - 1;
}

nextBtn.addEventListener('click', () => {
  if (currentIndex < cards.length - 1) {
    currentIndex++;
    updateCarousel();
  }
});

prevBtn.addEventListener('click', () => {
  if (currentIndex > 0) {
    currentIndex--;
    updateCarousel();
  }
});

// Wait until everything is rendered
window.addEventListener('load', updateCarousel);
