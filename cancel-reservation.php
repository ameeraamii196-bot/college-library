<?php

require_once "config/database.php";
require_once "includes/auth.php";

requireLogin();

if (
    $_SESSION["role"] !== "student" &&
    $_SESSION["role"] !== "teacher"
) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION["user_id"];

if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {
    header("Location: my-reservations.php");
    exit;
}

$reservation_id = (int) $_GET["id"];


/* -----------------------------------
   Start transaction
----------------------------------- */

$conn->begin_transaction();

try {

    $sql_reservation = "SELECT id, book_id, status
                        FROM reservations
                        WHERE id = ?
                        AND user_id = ?
                        AND status = 'pending'
                        LIMIT 1";

    $stmt_reservation = $conn->prepare($sql_reservation);
    $stmt_reservation->bind_param("ii", $reservation_id, $user_id);
    $stmt_reservation->execute();

    $result_reservation = $stmt_reservation->get_result();

    if ($result_reservation->num_rows === 0) {
        $stmt_reservation->close();
        $conn->rollback();

        header("Location: my-reservations.php");
        exit;
    }

    $reservation = $result_reservation->fetch_assoc();
    $stmt_reservation->close();

    $book_id = (int) $reservation["book_id"];

    $sql_cancel = "UPDATE reservations
                   SET status = 'cancelled'
                   WHERE id = ?
                   AND user_id = ?
                   AND status = 'pending'";

    $stmt_cancel = $conn->prepare($sql_cancel);
    $stmt_cancel->bind_param("ii", $reservation_id, $user_id);
    $stmt_cancel->execute();
    $stmt_cancel->close();

    $sql_other = "SELECT id
                  FROM reservations
                  WHERE book_id = ?
                  AND status = 'pending'
                  ORDER BY reserved_at ASC
                  LIMIT 1";

    $stmt_other = $conn->prepare($sql_other);
    $stmt_other->bind_param("i", $book_id);
    $stmt_other->execute();

    $result_other = $stmt_other->get_result();
    $has_other_request = $result_other->num_rows > 0;
    $stmt_other->close();

    if (!$has_other_request) {

        $sql_release = "UPDATE book_copies
                        SET status = 'available'
                        WHERE book_id = ?
                        AND status = 'reserved'
                        LIMIT 1";

        $stmt_release = $conn->prepare($sql_release);
        $stmt_release->bind_param("i", $book_id);
        $stmt_release->execute();
        $stmt_release->close();
    }

    $conn->commit();

} catch (Exception $e) {

    $conn->rollback();
    die("Unable to cancel reservation.");
}

header("Location: my-reservations.php");
exit;

?>
