<?php

require_once "config/database.php";
require_once "includes/auth.php";
require_once "includes/functions.php";

requireLogin();

if ($_SESSION["role"] !== "student" && $_SESSION["role"] !== "teacher") {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION["user_id"];

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: books.php");
    exit;
}

$book_id = (int) $_GET["id"];


/* -----------------------------------
   1. Check whether book exists
----------------------------------- */

$sql_book = "SELECT id, title
             FROM books
             WHERE id = ?";

$stmt_book = $conn->prepare($sql_book);
$stmt_book->bind_param("i", $book_id);
$stmt_book->execute();

$result_book = $stmt_book->get_result();

if ($result_book->num_rows === 0) {
    $stmt_book->close();

    header("Location: books.php");
    exit;
}

$stmt_book->close();


/* -----------------------------------
   2. Check existing pending request
----------------------------------- */

$sql_existing = "SELECT id
                 FROM reservations
                 WHERE user_id = ?
                 AND book_id = ?
                 AND status = 'pending'
                 LIMIT 1";

$stmt_existing = $conn->prepare($sql_existing);
$stmt_existing->bind_param("ii", $user_id, $book_id);
$stmt_existing->execute();

$result_existing = $stmt_existing->get_result();

if ($result_existing->num_rows > 0) {
    $stmt_existing->close();

    header("Location: book-details.php?id=" . $book_id);
    exit;
}

$stmt_existing->close();


/* -----------------------------------
   3. Check whether user already has
      this book issued
----------------------------------- */

$sql_issued = "SELECT l.id
               FROM loans l
               INNER JOIN book_copies bc
                   ON l.copy_id = bc.id
               WHERE l.user_id = ?
               AND bc.book_id = ?
               AND l.status IN ('issued', 'overdue')
               LIMIT 1";

$stmt_issued = $conn->prepare($sql_issued);
$stmt_issued->bind_param("ii", $user_id, $book_id);
$stmt_issued->execute();

$result_issued = $stmt_issued->get_result();

if ($result_issued->num_rows > 0) {
    $stmt_issued->close();

    header("Location: book-details.php?id=" . $book_id);
    exit;
}

$stmt_issued->close();


/* -----------------------------------
   4. Start transaction
----------------------------------- */

$conn->begin_transaction();

try {

    $sql_copy = "SELECT id
                 FROM book_copies
                 WHERE book_id = ?
                 AND status = 'available'
                 ORDER BY id ASC
                 LIMIT 1
                 FOR UPDATE";

    $stmt_copy = $conn->prepare($sql_copy);
    $stmt_copy->bind_param("i", $book_id);
    $stmt_copy->execute();

    $result_copy = $stmt_copy->get_result();

    $copy_id = null;

    if ($result_copy->num_rows > 0) {
        $copy = $result_copy->fetch_assoc();
        $copy_id = (int) $copy["id"];
    }

    $stmt_copy->close();

    $sql_reservation = "INSERT INTO reservations
                        (user_id, book_id, status)
                        VALUES (?, ?, 'pending')";

    $stmt_reservation = $conn->prepare($sql_reservation);
    $stmt_reservation->bind_param("ii", $user_id, $book_id);
    $stmt_reservation->execute();

    $stmt_reservation->close();

    if ($copy_id !== null) {

        $sql_reserve_copy = "UPDATE book_copies
                             SET status = 'reserved'
                             WHERE id = ?
                             AND status = 'available'";

        $stmt_reserve_copy = $conn->prepare($sql_reserve_copy);
        $stmt_reserve_copy->bind_param("i", $copy_id);
        $stmt_reserve_copy->execute();
        $stmt_reserve_copy->close();
    }

    recordActivity($conn, $user_id, "reserve");

    $conn->commit();

} catch (Exception $e) {

    $conn->rollback();

    die("Reservation request failed. Please try again.");
}

header("Location: book-details.php?id=" . $book_id);
exit;

?>