<?php

require_once "includes/auth.php";
requireLogin();

if ($_SESSION["role"] !== "student" && $_SESSION["role"] !== "teacher") {
    header("Location: index.php");
    exit;
}

require_once "config/database.php";
require_once "includes/functions.php";

$user_id = $_SESSION["user_id"];
$library_streak = getLibraryStreak($conn, $user_id);


/*
|--------------------------------------------------------------------------
| Current Issued Books
|--------------------------------------------------------------------------
*/

$issued_sql = "SELECT
                    l.id AS loan_id,
                    l.issue_date,
                    l.due_date,
                    l.status,

                    b.id AS book_id,
                    b.title,
                    b.author,
                    b.cover_image,

                    bc.accession_number

               FROM loans l

               INNER JOIN book_copies bc
                   ON l.copy_id = bc.id

               INNER JOIN books b
                   ON bc.book_id = b.id

               WHERE l.user_id = ?
               AND l.status = 'issued'

               ORDER BY l.due_date ASC";


$issued_stmt = $conn->prepare($issued_sql);

$issued_stmt->bind_param(
    "i",
    $user_id
);

$issued_stmt->execute();

$issued_result = $issued_stmt->get_result();


/*
|--------------------------------------------------------------------------
| Total Borrowed
|--------------------------------------------------------------------------
*/

$total_sql = "SELECT COUNT(*) AS total
              FROM loans
              WHERE user_id = ?";

$total_stmt = $conn->prepare($total_sql);

$total_stmt->bind_param(
    "i",
    $user_id
);

$total_stmt->execute();

$total_result = $total_stmt->get_result();

$total_borrowed = $total_result->fetch_assoc()["total"];

$total_stmt->close();


/*
|--------------------------------------------------------------------------
| Total Returned
|--------------------------------------------------------------------------
*/

$returned_sql = "SELECT COUNT(*) AS total
                 FROM loans
                 WHERE user_id = ?
                 AND status = 'returned'";

$returned_stmt = $conn->prepare($returned_sql);

$returned_stmt->bind_param(
    "i",
    $user_id
);

$returned_stmt->execute();

$returned_result = $returned_stmt->get_result();

$total_returned = $returned_result->fetch_assoc()["total"];

$returned_stmt->close();


/*
|--------------------------------------------------------------------------
| Currently Borrowed
|--------------------------------------------------------------------------
*/

$currently_borrowed = $issued_result->num_rows;


/*
|--------------------------------------------------------------------------
| My Pending Reservations
|--------------------------------------------------------------------------
*/

$sql_reservations = "SELECT
                        r.id,
                        r.reserved_at,
                        r.status,
                        b.title,
                        b.author
                     FROM reservations r
                     INNER JOIN books b
                         ON r.book_id = b.id
                     WHERE r.user_id = ?
                     AND r.status = 'pending'
                     ORDER BY r.reserved_at DESC
                     LIMIT 5";

$stmt_reservations = $conn->prepare($sql_reservations);

$stmt_reservations->bind_param(
    "i",
    $user_id
);

$stmt_reservations->execute();

$reservations = $stmt_reservations->get_result();


require_once "includes/header.php";

?>


<section class="dashboard-section">

    <div class="dashboard-welcome">

        <div>

            <p class="dashboard-eyebrow">
                <?php echo ucfirst($_SESSION["role"]); ?> Dashboard
            </p>

            <h1>
                Welcome back,
                <?php echo htmlspecialchars($_SESSION["full_name"]); ?> 👋
            </h1>

            <p>
                Here's an overview of your library activity.
            </p>

        </div>

    </div>


    <div class="dashboard-stats">

        <div class="stat-card">

            <div class="stat-icon">
                📚
            </div>

            <div>

                <h3>
                    <?php echo $total_borrowed; ?>
                </h3>

                <p>
                    Total Borrowed
                </p>

            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon">
                ↩️
            </div>

            <div>

                <h3>
                    <?php echo $total_returned; ?>
                </h3>

                <p>
                    Total Returned
                </p>

            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon">
                📖
            </div>

            <div>

                <h3>
                    <?php echo $currently_borrowed; ?>
                </h3>

                <p>
                    Currently Issued
                </p>

            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon">
                🔥
            </div>

            <div>

                <h3>
                    <?php echo $library_streak; ?>
                </h3>

                <p>
                    Day Library Streak
                </p>

            </div>

        </div>

    </div>


    <section class="dashboard-section">

        <div class="section-heading">

            <div>

                <h2>Quick Actions</h2>

                <p>
                    Find your next book or manage your library activity.
                </p>

            </div>

        </div>

        <div class="quick-actions">

            <a
                href="books.php"
                class="quick-action-card"
            >

                <span class="quick-action-icon">
                    🔎
                </span>

                <span>
                    <strong>Browse Books</strong>
                    <small>Explore the library catalogue</small>
                </span>

            </a>

            <a
                href="my-reservations.php"
                class="quick-action-card"
            >

                <span class="quick-action-icon">
                    📌
                </span>

                <span>
                    <strong>My Reservations</strong>
                    <small>Check your reserved books</small>
                </span>

            </a>

        </div>

    </section>


    <section class="dashboard-section">

        <div class="section-heading">

            <div>

                <h2>Currently Borrowed</h2>

                <p>
                    Books currently issued to you.
                </p>

            </div>

        </div>

        <?php if ($issued_result->num_rows > 0): ?>

            <div class="loan-list">

                <?php while ($loan = $issued_result->fetch_assoc()): ?>

                    <?php

                    $today_date = new DateTime();
                    $due_date = new DateTime($loan["due_date"]);
                    $days_difference = (int) $today_date->diff($due_date)->format("%r%a");

                    ?>

                    <div class="loan-card">

                        <div class="loan-book-info">

                            <h3>
                                <?php echo htmlspecialchars($loan["title"]); ?>
                            </h3>

                            <p>
                                By
                                <?php echo htmlspecialchars($loan["author"]); ?>
                            </p>

                        </div>

                        <div class="loan-details">

                            <div>

                                <span>
                                    Issued
                                </span>

                                <strong>
                                    <?php echo htmlspecialchars($loan["issue_date"]); ?>
                                </strong>

                            </div>

                            <div>

                                <span>
                                    Due Date
                                </span>

                                <strong>
                                    <?php echo htmlspecialchars($loan["due_date"]); ?>
                                </strong>

                            </div>

                            <div>

                                <?php if ($days_difference < 0): ?>

                                    <span class="loan-status overdue">
                                        ⚠ Overdue
                                    </span>

                                <?php elseif ($days_difference === 0): ?>

                                    <span class="loan-status due-today">
                                        ⏰ Due Today
                                    </span>

                                <?php elseif ($days_difference === 1): ?>

                                    <span class="loan-status due-soon">
                                        Due Tomorrow
                                    </span>

                                <?php else: ?>

                                    <span class="loan-status issued">
                                        ✓ Issued
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                <?php endwhile; ?>

            </div>

        <?php else: ?>

            <div class="empty-state loan-empty-state">

                <h3>No books currently borrowed</h3>

                <p>
                    Browse the catalogue and discover something interesting.
                </p>

                <a
                    href="books.php"
                    class="btn-primary"
                >
                    Browse Books
                </a>

            </div>

        <?php endif; ?>

    </section>


    <section class="dashboard-section">

        <div class="section-heading">

            <div>

                <h2>My Reservations</h2>

                <p>
                    Books you've requested for reservation.
                </p>

            </div>

            <a
                href="my-reservations.php"
                class="section-link"
            >
                View All
            </a>

        </div>

        <?php if ($reservations->num_rows > 0): ?>

            <div class="reservation-mini-list">

                <?php while ($reservation = $reservations->fetch_assoc()): ?>

                    <div class="reservation-mini-card">

                        <div>

                            <h3>
                                <?php echo htmlspecialchars($reservation["title"]); ?>
                            </h3>

                            <p>
                                <?php echo htmlspecialchars($reservation["author"]); ?>
                            </p>

                        </div>

                        <span class="reservation-status reservation-pending">
                            Pending
                        </span>

                    </div>

                <?php endwhile; ?>

            </div>

        <?php else: ?>

            <div class="empty-state small">

                <p>
                    You don't have any pending reservations.
                </p>

                <a
                    href="books.php"
                    class="btn-secondary"
                >
                    Find a Book
                </a>

            </div>

        <?php endif; ?>

    </section>

</section>


<?php

$stmt_reservations->close();
$issued_stmt->close();

require_once "includes/footer.php";

?>