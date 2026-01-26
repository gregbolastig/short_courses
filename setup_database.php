<?php
// Database setup script - Run this once to initialize the database
require_once 'config/database.php';

// Use the consolidated setup function with visual feedback
setupDatabaseWithFeedback();
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 50px auto;
    padding: 20px;
    background-color: #f5f5f5;
}

table {
    background-color: white;
    width: 100%;
    margin: 10px 0;
}

th {
    background-color: #800000;
    color: white;
    padding: 8px;
}

td {
    padding: 8px;
}

a {
    color: #800000;
    text-decoration: none;
    font-weight: bold;
}

a:hover {
    text-decoration: underline;
}
</style>