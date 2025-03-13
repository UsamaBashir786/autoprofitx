<!-- Add this button at the top of your content, after the header section -->
<style>
  .go-back-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background-color: #4a5568;
    /* gray-700 */
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-weight: 600;
    margin-bottom: 1rem;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }

  .go-back-btn:hover {
    background-color: #2d3748;
    /* gray-800 */
  }

  .go-back-btn i {
    margin-right: 0.5rem;
  }

  /* Mobile optimization */
  @media (max-width: 768px) {
    .go-back-btn {
      padding: 0.625rem 1.25rem;
      font-size: 1rem;
    }
  }
</style>

<!-- Add this button right after your header section -->
<a href="../games.php" class="go-back-btn">
  <i class="fas fa-arrow-left"></i> Go Back
</a>

<!-- Alternatively, if you want a JavaScript back button -->
<!-- 
<button onclick="goBack()" class="go-back-btn">
  <i class="fas fa-arrow-left"></i> Go Back
</button>

<script>
function goBack() {
  window.history.back();
}
</script>
-->