<?php

require_once "../includes/auth.php";
requireRole("librarian");

require_once "../config/database.php";

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
die("Invalid category ID.");
}

$category_id = (int) $_GET["id"];

$sql = "DELETE FROM categories WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $category_id);

if ($stmt->execute()) {

header("Location: categories.php");
exit;

} else {

die("Unable to delete category.");

}

?>