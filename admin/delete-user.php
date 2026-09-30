<?php

require_once "../includes/auth.php";
requireRole("admin");

require_once "../config/database.php";

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
	header("Location: users.php?notice=invalid");
	exit;
}

$user_id = (int) $_GET["id"];

/*

Prevent the currently logged-in administrator
from deleting their own account.
*/
if ($user_id === (int) $_SESSION["user_id"]) {
	header("Location: users.php?notice=self");
	exit;
}

$sql = "DELETE FROM users WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);

try {

	if ($stmt->execute() && $stmt->affected_rows > 0) {
		$stmt->close();

		header("Location: users.php?notice=deleted");
		exit;
	}

} catch (mysqli_sql_exception $exception) {
	// Retain accounts referenced by loans, reservations, or other records.
}

$stmt->close();

$sql_deactivate = "UPDATE users
				   SET status = 'inactive'
				   WHERE id = ?";

$stmt_deactivate = $conn->prepare($sql_deactivate);
$stmt_deactivate->bind_param("i", $user_id);

try {

	if ($stmt_deactivate->execute()) {
		$stmt_deactivate->close();

		header("Location: users.php?notice=deactivated");
		exit;
	}

} catch (mysqli_sql_exception $exception) {
	$stmt_deactivate->close();
}

die("Unable to delete or deactivate this user.");

?>