<?php

require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/functions.php";

requireRole("librarian");

$reservation_id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($reservation_id <= 0) {
    header("Location: reservations.php");
    exit;
}

$sql_reservation = "SELECT
                        r.id,
                        r.user_id,
                        r.book_id,
                        r.status
                    FROM reservations r
                    WHERE r.id = ?
                    LIMIT 1";

$stmt_reservation = $conn->prepare($sql_reservation);
$stmt_reservation->bind_param("i", $reservation_id);
$stmt_reservation->execute();

$reservation = $stmt_reservation->get_result()->fetch_assoc();
$stmt_reservation->close();

if (!$reservation || $reservation["status"] !== "approved") {
    header("Location: reservations.php");
    exit;
}

$sql_copy = "SELECT id
             FROM book_copies
             WHERE book_id = ?
             AND status = 'available'
             ORDER BY id ASC
             LIMIT 1";

$stmt_copy = $conn->prepare($sql_copy);
$stmt_copy->bind_param("i", $reservation["book_id"]);
$stmt_copy->execute();

$copy = $stmt_copy->get_result()->fetch_assoc();
$stmt_copy->close();

if (!$copy) {
    header("Location: reservations.php?error=no_copy");
    exit;
}

$issue_date = date("Y-m-d");
$due_date = date("Y-m-d", strtotime("+14 days"));

$conn->begin_transaction();

try {
    $sql_loan = "INSERT INTO loans
                 (user_id, copy_id, issue_date, due_date, status)
                 VALUES (?, ?, ?, ?, 'issued')";

    $stmt_loan = $conn->prepare($sql_loan);
    $stmt_loan->bind_param("iiss", $reservation["user_id"], $copy["id"], $issue_date, $due_date);

    if (!$stmt_loan->execute()) {
        throw new Exception("Loan insert failed.");
    }

    $loan_id = $stmt_loan->insert_id;
    $stmt_loan->close();

    $sql_update_copy = "UPDATE book_copies
                        SET status = 'issued'
                        WHERE id = ?
                        AND status = 'available'";

    $stmt_update_copy = $conn->prepare($sql_update_copy);
    $stmt_update_copy->bind_param("i", $copy["id"]);
    $stmt_update_copy->execute();
    $stmt_update_copy->close();

    $sql_update_reservation = "UPDATE reservations
                               SET status = 'fulfilled', loan_id = ?
                               WHERE id = ?
                               AND status = 'approved'";

    $stmt_update_reservation = $conn->prepare($sql_update_reservation);
    $stmt_update_reservation->bind_param("ii", $loan_id, $reservation_id);
    $stmt_update_reservation->execute();
    $stmt_update_reservation->close();

    recordActivity($conn, $reservation["user_id"], "borrow");

    $conn->commit();

    header("Location: reservations.php?success=issued");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    header("Location: reservations.php?error=issue_failed");
    exit;
}
