<?php

require_once "../includes/auth.php";
requireRole("librarian");

require_once "../config/database.php";

$sql_reservation_count = "SELECT COUNT(*) AS total
                          FROM reservations
                          WHERE status='pending'";

$reservation_result = $conn->query($sql_reservation_count);
$reservation_count_data = $reservation_result->fetch_assoc();
$reservation_count = (int) ($reservation_count_data["total"] ?? 0);

require_once "../includes/header.php";

?>

<section>
<h1>Librarian Dashboard</h1>

<p>
    Welcome,
    <?php echo htmlspecialchars($_SESSION["full_name"]); ?>.
</p>

<br>

<div class="dashboard-actions">

    <a href="categories.php" class="dashboard-card">

        <h2>📂 Categories</h2>

        <p>
            Create and manage book categories.
        </p>

    </a>


    <a href="books.php" class="dashboard-card">

        <h2>📚 Books</h2>

        <p>
            Add, edit and manage library books.
        </p>

    </a>


    <a href="copies.php" class="dashboard-card">

        <h2>🏷️ Book Copies</h2>

        <p>
            Manage physical copies, accession numbers and shelf locations.
        </p>

    </a>


    <a href="issue-book.php" class="dashboard-card">

        <h2>📤 Issue Book</h2>

        <p>
            Issue books to students and teachers.
        </p>

    </a>

    <a href="returns.php" class="dashboard-card">

        <h2>↩ Return Book</h2>

        <p>
            Process library returns and update copy status.
        </p>

    </a>

    <a href="reservations.php" class="dashboard-card reservation-dashboard-card">

        <div class="dashboard-card-content">
            <h3>🔖 Reservations</h3>
            <p>
                Manage book reservation requests
            </p>
        </div>

        <?php if ($reservation_count > 0): ?>
            <span class="notification-badge">
                <?php echo $reservation_count; ?>
            </span>
        <?php endif; ?>

    </a>

</div>

<br>

<a href="../logout.php" class="btn-primary">
    Logout
</a>
</section> <?php require_once "../includes/footer.php"; ?>