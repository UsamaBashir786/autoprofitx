// File: js/investments.js

/**
 * Activates an investment plan when user clicks on the Buy Now button
 * @param {string} planType - The type of plan (Basic, Premium, Professional)
 * @param {number} amount - The investment amount
 */
function activatePlan(planType, amount) {
  // Check if user has sufficient balance first
  if (confirm("Are you sure you want to invest ₹" + amount + " in the " + planType + " plan?")) {
    // Show loading indicator or disable button
    const loadingElement = document.getElementById('loading-indicator');
    if (loadingElement) {
      loadingElement.classList.remove('hidden');
    }
    
    // Send AJAX request to process the investment
    fetch('process_investment.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'plan_type=' + encodeURIComponent(planType) + 
            '&amount=' + encodeURIComponent(amount)
    })
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.json();
    })
    .then(data => {
      // Hide loading indicator
      if (loadingElement) {
        loadingElement.classList.add('hidden');
      }
      
      if (data.success) {
        alert("Investment successful! You will receive your profit in 24 hours.");
        // Optionally reload the page to show updated investments
        window.location.reload();
      } else {
        alert("Error: " + data.message);
      }
    })
    .catch(error => {
      // Hide loading indicator
      if (loadingElement) {
        loadingElement.classList.add('hidden');
      }
      
      console.error('Error:', error);
      alert("An error occurred. Please try again.");
    });
  }
}