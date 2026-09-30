<?php

if (session_status() === PHP_SESSION_NONE) {
session_start();
}

function requireLogin()
{
if (!isset($_SESSION['user_id'])) {
header("Location: /college-library/login.php");
exit;
}
}

function requireRole($role)
{
requireLogin();

if ($_SESSION['role'] !== $role) {
    header("Location: /college-library/index.php");
    exit;
}

}

?>