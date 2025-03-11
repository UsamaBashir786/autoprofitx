<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to the admin login page
header("Location: admin-login.php");
exit();
?>