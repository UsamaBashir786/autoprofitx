<?php
// Database connection settings
$servername = "localhost";
$username = "root"; // Replace with your database username
$password = ""; // Replace with your database password
$dbname = "autoproftx";   // Replace with your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Currency formatting function
function format_currency($amount, $decimal_places = 2)
{
  // Use $ symbol and format with 2 decimal places (standard for dollars)
  return '$' . number_format($amount, $decimal_places);
}
