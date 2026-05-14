<?php
// Place at: Traveloka/auth/provider_logout.php
if (session_status() === PHP_SESSION_NONE) session_start();
session_unset();
session_destroy();
header('Location: /Traveloka/auth/signin.php');
exit;