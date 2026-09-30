<?php

require_once "../includes/auth.php";
requireRole("admin");

require_once "../config/database.php";
require_once "../includes/header.php";

$sql = "SELECT id, username, full_name, email, phone, role, department, status, created_at
FROM users
ORDER BY id DESC";

$result = $conn->query($sql);

$notice = $_GET["notice"] ?? "";

?>
<section>
<div class="page-header">

    <div>
        <h1>User Management</h1>
        <p>Manage students, teachers, librarians and administrators.</p>
    </div>

    <a href="add-user.php" class="btn-primary">
        + Add User
    </a>

</div>

<?php if ($notice === "deactivated"): ?>

    <div class="success-message">
        This account has loan or reservation history, so it was deactivated instead of deleted.
    </div>

<?php elseif ($notice === "deleted"): ?>

    <div class="success-message">
        User deleted successfully.
    </div>

<?php elseif ($notice === "self"): ?>

    <div class="error-message">
        You cannot delete your own account.
    </div>

<?php elseif ($notice === "invalid"): ?>

    <div class="error-message">
        Invalid user selection.
    </div>

<?php endif; ?>

<div class="table-container">

    <table class="data-table">

        <thead>

            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Department</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>

        </thead>

        <tbody>

            <?php if ($result->num_rows > 0): ?>

                <?php while ($user = $result->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?php echo $user["id"]; ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($user["full_name"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($user["username"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($user["email"] ?? ""); ?>
                        </td>

                        <td>
                            <?php echo ucfirst($user["role"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($user["department"] ?? ""); ?>
                        </td>

                        <td>
                            <?php echo ucfirst($user["status"]); ?>
                        </td>

                        <td>

                            <a href="edit-user.php?id=<?php echo $user["id"]; ?>">
                                Edit
                            </a>

                            |

                            <a href="delete-user.php?id=<?php echo $user["id"]; ?>"
                               onclick="return confirm('Are you sure you want to delete this user?');">
                                Delete
                            </a>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td colspan="8">
                        No users found.
                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>
</section> <?php require_once "../includes/footer.php"; ?>
