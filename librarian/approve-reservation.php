<?php

require_once "../config/database.php";
require_once "../includes/auth.php";

requireRole("librarian");

$reservation_id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($reservation_id <= 0) {
    header("Location: reservations.php");
    exit;
}

$sql_check = "SELECT id, status
              FROM reservations
              WHERE id = ?
              LIMIT 1";

$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("i", $reservation_id);
$stmt_check->execute();

$reservation = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();

if (!$reservation || $reservation["status"] !== "pending") {
    header("Location: reservations.php");
    exit;
}

$sql_update = "UPDATE reservations
               SET status = 'approved'
               WHERE id = ?
               AND status = 'pending'";

$stmt_update = $conn->prepare($sql_update);
$stmt_update->bind_param("i", $reservation_id);
$stmt_update->execute();
$stmt_update->close();

header("Location: reservations.php?status=pending&success=approved");
exit;
