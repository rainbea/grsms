<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
if ($currentScript !== 'login.php' && empty($_SESSION['staff'])) {
    header('Location: login.php');
    exit;
}

//snippet ensures a PHP session is active and blocks unauthenticated users 
//from viewing protected pages by redirecting anyone without an active session 
//(other than on login.php) back to login.php
