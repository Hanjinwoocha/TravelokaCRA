<?php
session_start();
session_unset();
session_destroy();
header('Location: /Traveloka/auth/signin.php');
exit;