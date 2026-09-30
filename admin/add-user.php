<?php

require_once "../includes/auth.php";
requireRole("admin");

require_once "../config/database.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

$username = trim($_POST["username"]);
$password = $_POST["password"];
$full_name = trim($_POST["full_name"]);
$email = trim($_POST["email"]);
$phone = trim($_POST["phone"]);
$role = $_POST["role"];
$department = trim($_POST["department"]);

if (
    empty($username) ||
    empty($password) ||
    empty($full_name) ||
    empty($role)
) {

    $error = "Please fill in all required fields.";

} else {

    $check_sql = "SELECT id FROM users WHERE username = ?";

    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();

    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {

        $error = "Username already exists.";

    } else {

        $hashed_password = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $sql = "INSERT INTO users
                (username, password, full_name, email, phone, role, department)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "sssssss",
            $username,
            $hashed_password,
            $full_name,
            $email,
            $phone,
            $role,
            $department
        );

        if ($stmt->execute()) {

            $success = "User created successfully.";

        } else {

            $error = "Unable to create user.";

        }

        $stmt->close();
    }

    $check_stmt->close();
}

}

require_once "../includes/header.php";

?>

<section>
<h1>Add New User</h1>

<p>Create an account for a student, teacher or librarian.</p>

<br>

<?php if (!empty($error)): ?>

    <div class="error-message">
        <?php echo htmlspecialchars($error); ?>
    </div>

<?php endif; ?>


<?php if (!empty($success)): ?>

    <div class="success-message">
        <?php echo htmlspecialchars($success); ?>
    </div>

<?php endif; ?>


<form method="POST" class="admin-form">

    <div class="form-group">

        <label for="full_name">
            Full Name *
        </label>

        <input
            type="text"
            id="full_name"
            name="full_name"
            required
        >

    </div>


    <div class="form-group">

        <label for="username">
            Username *
        </label>

        <input
            type="text"
            id="username"
            name="username"
            required
        >

    </div>


    <div class="form-group">

        <label for="password">
            Password *
        </label>

        <input
            type="password"
            id="password"
            name="password"
            required
        >

    </div>


    <div class="form-group">

        <label for="email">
            Email
        </label>

        <input
            type="email"
            id="email"
            name="email"
        >

    </div>


    <div class="form-group">

        <label for="phone">
            Phone
        </label>

        <input
            type="text"
            id="phone"
            name="phone"
        >

    </div>


    <div class="form-group">

        <label for="role">
            Role *
        </label>

        <select id="role" name="role" required>

            <option value="">Select Role</option>

            <option value="student">
                Student
            </option>

            <option value="teacher">
                Teacher
            </option>

            <option value="librarian">
                Librarian
            </option>

        </select>

    </div>


    <div class="form-group">

        <label for="department">
            Department
        </label>

        <input
            type="text"
            id="department"
            name="department"
        >

    </div>


    <button type="submit" class="btn-primary">
        Create User
    </button>

</form>
</section> <?php require_once "../includes/footer.php"; ?>