const scrollTopBtn = document.getElementById("scrollTopBtn");

window.addEventListener("scroll", () => {
  if (window.scrollY > 200) {
    scrollTopBtn.style.display = "block";
  } else {
    scrollTopBtn.style.display = "none";
  }
});

scrollTopBtn.addEventListener("click", () => {
  window.scrollTo({
    top: 0,
    behavior: "smooth"
  });
});
// Simple scroll animation
$('a[href*="#"]').on('click', function(e) {
  e.preventDefault();
  $('html, body').animate({
      scrollTop: $($(this).attr('href')).offset().top,
    },
    500,
    'linear'
  );
});

// Mobile menu functionality
$(document).ready(function() {
  // Toggle mobile menu
  $('#mobile-menu-button').on('click', function() {
    $('#mobile-menu').removeClass('-translate-y-full').addClass('translate-y-0');
  });

  // Close mobile menu
  $('#close-menu-button').on('click', function() {
    $('#mobile-menu').removeClass('translate-y-0').addClass('-translate-y-full');
  });

  // Close menu when clicking a link
  $('.mobile-menu-link').on('click', function() {
    setTimeout(function() {
      $('#mobile-menu').removeClass('translate-y-0').addClass('-translate-y-full');
    }, 300);
  });
});