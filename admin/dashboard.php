<?php

require_once "../includes/auth.php";
requireRole("admin");

require_once "../config/database.php";

/* User statistics */
$sql_user_stats = "SELECT
                       COUNT(*) AS total_users,
                       SUM(status = 'active') AS active_users,
                       SUM(status = 'inactive') AS inactive_users,
                       SUM(role = 'student' AND status = 'active') AS students,
                       SUM(role = 'teacher' AND status = 'active') AS teachers,
                       SUM(role = 'librarian' AND status = 'active') AS librarians
                   FROM users";

$user_stats = $conn->query($sql_user_stats)->fetch_assoc();

$total_users = (int) ($user_stats["total_users"] ?? 0);
$active_users = (int) ($user_stats["active_users"] ?? 0);
$inactive_users = (int) ($user_stats["inactive_users"] ?? 0);
$students = (int) ($user_stats["students"] ?? 0);
$teachers = (int) ($user_stats["teachers"] ?? 0);
$librarians = (int) ($user_stats["librarians"] ?? 0);

/* Recent users */
$sql_recent = "SELECT
                   username,
                   full_name,
                   role,
                   status,
                   created_at
               FROM users
               ORDER BY created_at DESC
               LIMIT 8";

$result_recent = $conn->query($sql_recent);

/* Catalogue statistics */
$sql_catalogue_stats = "SELECT
                            (SELECT COUNT(*) FROM books) AS total_books,
                            COUNT(*) AS total_copies,
                            SUM(status = 'issued') AS issued_copies,
                            SUM(status = 'available') AS available_copies
                        FROM book_copies";

$catalogue_stats = $conn->query($sql_catalogue_stats)->fetch_assoc();

$total_books = (int) ($catalogue_stats["total_books"] ?? 0);
$total_copies = (int) ($catalogue_stats["total_copies"] ?? 0);
$issued_copies = (int) ($catalogue_stats["issued_copies"] ?? 0);
$available_copies = (int) ($catalogue_stats["available_copies"] ?? 0);

include "../includes/header.php";

?>

<div class="container admin-dashboard">

    <div class="dashboard-welcome">

        <div>

            <p class="dashboard-eyebrow">
                Administration
            </p>

            <h1>
                Welcome, <?php echo htmlspecialchars($_SESSION["full_name"] ?? "Admin"); ?>
            </h1>

            <p>
                Manage users and monitor the library system.
            </p>

        </div>

    </div>


    <section class="dashboard-section">

        <div class="section-heading">

            <div>
                <p class="dashboard-eyebrow">User Overview</p>
                <h2>User Statistics</h2>
            </div>

            <a href="users.php" class="section-link">
                Manage Users &rarr;
            </a>

        </div>

        <div class="dashboard-stats admin-user-stats">

            <div class="stat-card">
                <div class="stat-icon">&#128101;</div>
                <div><h3><?php echo $total_users; ?></h3><p>Total Users</p></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">&#128994;</div>
                <div><h3><?php echo $active_users; ?></h3><p>Active Users</p></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">&#9898;</div>
                <div><h3><?php echo $inactive_users; ?></h3><p>Inactive Users</p></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">&#127891;</div>
                <div><h3><?php echo $students; ?></h3><p>Students</p></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">&#128104;&#8205;&#127979;</div>
                <div><h3><?php echo $teachers; ?></h3><p>Teachers</p></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">&#128218;</div>
                <div><h3><?php echo $librarians; ?></h3><p>Librarians</p></div>
            </div>

        </div>

    </section>


    <section class="dashboard-section">

        <div class="section-heading">

            <div>
                <p class="dashboard-eyebrow">Library Overview</p>
                <h2>Catalogue Statistics</h2>
            </div>

            <a href="../books.php" class="section-link">
                View Catalogue &rarr;
            </a>

        </div>

        <div class="dashboard-stats admin-catalogue-stats">

            <div class="stat-card">
                <div class="stat-icon">&#128214;</div>
                <div><h3><?php echo $total_books; ?></h3><p>Book Titles</p></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">&#128218;</div>
                <div><h3><?php echo $total_copies; ?></h3><p>Physical Copies</p></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">&#128994;</div>
                <div><h3><?php echo $available_copies; ?></h3><p>Available Copies</p></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">&#128213;</div>
                <div><h3><?php echo $issued_copies; ?></h3><p>Issued Copies</p></div>
            </div>

        </div>

    </section>


    <section class="dashboard-section">

        <div class="section-heading">

            <div>
                <p class="dashboard-eyebrow">Administration</p>
                <h2>Quick Actions</h2>
            </div>

        </div>

        <div class="quick-actions admin-quick-actions">

            <a href="add-user.php" class="quick-action-card">
                <div class="quick-action-icon">&#43;</div>
                <div>
                    <strong>Add User</strong>
                    <span>Create a new student, teacher, librarian or admin account.</span>
                </div>
            </a>

            <a href="users.php" class="quick-action-card">
                <div class="quick-action-icon">&#128101;</div>
                <div>
                    <strong>Manage Users</strong>
                    <span>Edit, deactivate or remove user accounts.</span>
                </div>
            </a>

            <a href="../books.php" class="quick-action-card">
                <div class="quick-action-icon">&#128218;</div>
                <div>
                    <strong>Browse Catalogue</strong>
                    <span>View the college library catalogue.</span>
                </div>
            </a>

        </div>

    </section>


    <section class="dashboard-section">

        <div class="section-heading">

            <div>
                <p class="dashboard-eyebrow">Latest Accounts</p>
                <h2>Recently Added Users</h2>
            </div>

            <a href="users.php" class="section-link">
                View All &rarr;
            </a>

        </div>

        <?php if ($result_recent && $result_recent->num_rows > 0): ?>

            <div class="recent-users-table">

                <div class="table-wrapper">

                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while ($user = $result_recent->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($user["full_name"]); ?></strong></td>
                                    <td>@<?php echo htmlspecialchars($user["username"]); ?></td>
                                    <td><span class="role-badge"><?php echo htmlspecialchars(ucfirst($user["role"])); ?></span></td>
                                    <td>
                                        <span class="status-badge <?php echo $user["status"] === "active" ? "active" : "inactive"; ?>">
                                            <?php echo htmlspecialchars(ucfirst($user["status"])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        echo !empty($user["created_at"])
                                            ? htmlspecialchars(date("d M Y", strtotime($user["created_at"])))
                                            : "-";
                                        ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                </div>

            </div>

        <?php else: ?>

            <div class="empty-state">
                <h2>No Users Found</h2>
                <p>Add your first user to the system.</p>
            </div>

        <?php endif; ?>

    </section>

</div>

<?php include "../includes/footer.php"; ?>