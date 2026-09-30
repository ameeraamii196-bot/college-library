<?php

require_once "config/database.php";

$username = "admin";
$password = "Admin@123";
$full_name = "Library Administrator";
$email = "admin@college.edu";
$role = "admin";

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users
(username, password, full_name, email, role)
VALUES (?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
"sssss",
$username,
$hashed_password,
$full_name,
$email,
$role
);

if ($stmt->execute()) {

echo "Admin account created successfully.<br><br>";
echo "Username: admin<br>";
echo "Password: Admin@123";

} else {

echo "Error: " . $stmt->error;

}

$stmt->close();

?>