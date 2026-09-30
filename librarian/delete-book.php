<?php

require_once "../includes/auth.php";
requireRole("librarian");

require_once "../config/database.php";

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
die("Invalid book ID.");
}

$book_id = (int) $_GET["id"];

$sql = "DELETE FROM books WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $book_id);

if ($stmt->execute()) {

header("Location: books.php");
exit;

} else {

die("Unable to delete book.");

}

?>