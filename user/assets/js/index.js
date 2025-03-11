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

      // Dropdown functionality
      $('.dropdown button').on('click', function(e) {
        e.stopPropagation();
        $(this).siblings('.dropdown-content').toggleClass('hidden');
      });

      // Hide dropdowns when clicking outside
      $(document).on('click', function() {
        $('.dropdown-content').addClass('hidden');
      });
    });

  // Dropdown toggle functionality
  document.addEventListener('DOMContentLoaded', function() {
    // Get all dropdown buttons
    const dropdownButtons = document.querySelectorAll('.dropdown button');
    
    // Add click event to each dropdown button
    dropdownButtons.forEach(button => {
      button.addEventListener('click', function(e) {
        e.stopPropagation(); // Prevent event from bubbling up
        
        // Get the dropdown content element
        const dropdownContent = this.nextElementSibling;
        
        // Close all other dropdowns first
        document.querySelectorAll('.dropdown-content').forEach(content => {
          if (content !== dropdownContent) {
            content.classList.add('hidden');
          }
        });
        
        // Toggle current dropdown
        dropdownContent.classList.toggle('hidden');
      });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
      document.querySelectorAll('.dropdown-content').forEach(content => {
        content.classList.add('hidden');
      });
    });
    
    // Confirm before deleting
    const deleteLinks = document.querySelectorAll('a[href^="delete-payment.php"]');
    deleteLinks.forEach(link => {
      link.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to delete this payment method?')) {
          e.preventDefault();
        }
      });
    });
  });

  
