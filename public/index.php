<?php
session_start();

// This file acts as a smart router.
// If logged in, go to the dashboard.
// If logged out, go to the public bicycle list.

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: bicycles.php');
}
exit();