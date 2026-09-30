<?php

require_once "../includes/auth.php";
requireRole("admin");

require_once "../config/database.php";

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
die("Invalid user ID.");
}

$user_id = (int) $_GET["id"];

$error = "";
$success = "";

$sql = "SELECT * FROM users WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
die("User not found.");
}

$user = $result->fetch_assoc();

$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

$full_name = trim($_POST["full_name"]);
$email = trim($_POST["email"]);
$phone = trim($_POST["phone"]);
$role = $_POST["role"];
$department = trim($_POST["department"]);
$status = $_POST["status"];
$password = $_POST["password"] ?? "";

if (empty($full_name) || empty($role)) {

    $error = "Please fill in all required fields.";

} else {

    if ($password !== "") {

        $password_hash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $update_sql = "UPDATE users
                       SET full_name = ?,
                           email = ?,
                           phone = ?,
                           role = ?,
                           department = ?,
                           status = ?,
                           password = ?
                       WHERE id = ?";

        $update_stmt = $conn->prepare($update_sql);

        $update_stmt->bind_param(
            "sssssssi",
            $full_name,
            $email,
            $phone,
            $role,
            $department,
            $status,
            $password_hash,
            $user_id
        );

    } else {

        $update_sql = "UPDATE users
                       SET full_name = ?,
                           email = ?,
                           phone = ?,
                           role = ?,
                           department = ?,
                           status = ?
                       WHERE id = ?";

        $update_stmt = $conn->prepare($update_sql);

        $update_stmt->bind_param(
            "ssssssi",
            $full_name,
            $email,
            $phone,
            $role,
            $department,
            $status,
            $user_id
        );
    }

    if ($update_stmt->execute()) {

        $success = "User updated successfully.";

        $user["full_name"] = $full_name;
        $user["email"] = $email;
        $user["phone"] = $phone;
        $user["role"] = $role;
        $user["department"] = $department;
        $user["status"] = $status;

    } else {

        $error = "Unable to update user.";

    }

    $update_stmt->close();
}

}

require_once "../includes/header.php";

?>

<section>
<h1>Edit User</h1>

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

        <label>Username</label>

        <input
            type="text"
            value="<?php echo htmlspecialchars($user["username"]); ?>"
            disabled
        >

    </div>


    <div class="form-group">

        <label for="full_name">
            Full Name *
        </label>

        <input
            type="text"
            id="full_name"
            name="full_name"
            value="<?php echo htmlspecialchars($user["full_name"]); ?>"
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
            value="<?php echo htmlspecialchars($user["email"] ?? ""); ?>"
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
            value="<?php echo htmlspecialchars($user["phone"] ?? ""); ?>"
        >

    </div>


    <div class="form-group">

        <label for="role">
            Role *
        </label>

        <select id="role" name="role" required>

            <option value="student"
                <?php if ($user["role"] === "student") echo "selected"; ?>>
                Student
            </option>

            <option value="teacher"
                <?php if ($user["role"] === "teacher") echo "selected"; ?>>
                Teacher
            </option>

            <option value="librarian"
                <?php if ($user["role"] === "librarian") echo "selected"; ?>>
                Librarian
            </option>

            <option value="admin"
                <?php if ($user["role"] === "admin") echo "selected"; ?>>
                Admin
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
            value="<?php echo htmlspecialchars($user["department"] ?? ""); ?>"
        >

    </div>


    <div class="form-group">

        <label for="status">
            Status
        </label>

        <select id="status" name="status">

            <option value="active"
                <?php if ($user["status"] === "active") echo "selected"; ?>>
                Active
            </option>

            <option value="inactive"
                <?php if ($user["status"] === "inactive") echo "selected"; ?>>
                Inactive
            </option>

        </select>

    </div>


    <div class="form-group">

        <label for="password">
            New Password (leave blank to keep current password)
        </label>

        <input
            type="password"
            id="password"
            name="password"
            autocomplete="new-password"
        >

    </div>


    <button type="submit" class="btn-primary">
        Save Changes
    </button>

</form>
</section> <?php require_once "../includes/footer.php"; ?>