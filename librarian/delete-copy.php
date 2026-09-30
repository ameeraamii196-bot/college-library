<?php

require_once "../includes/auth.php";
requireRole("librarian");

require_once "../config/database.php";

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
die("Invalid copy ID.");
}

$copy_id = (int) $_GET["id"];

$sql = "DELETE FROM book_copies WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $copy_id);

if ($stmt->execute()) {

header("Location: copies.php");
exit;

} else {

die("Unable to delete book copy.");

}

?>