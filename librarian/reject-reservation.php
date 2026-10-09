<?php

require_once "../config/database.php";
require_once "../includes/auth.php";

requireRole("librarian");

$reservation_id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($reservation_id <= 0) {
    header("Location: reservations.php");
    exit;
}

$sql = "UPDATE reservations
        SET status = 'rejected'
        WHERE id = ?
        AND status = 'pending'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $reservation_id);
$stmt->execute();
$stmt->close();

header("Location: reservations.php?status=pending&success=rejected");
exit;
