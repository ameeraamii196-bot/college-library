<CodeBlock id="step3-database-connection" language="php" filename="config/database.php"> <?php

$host = getenv("DB_HOST") ?: "localhost";
$username = getenv("DB_USER") ?: "root";
$password = getenv("DB_PASSWORD") ?: "";
$database = getenv("DB_NAME") ?: "college_library";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>
</CodeBlock>