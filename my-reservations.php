<?php

require_once "config/database.php";
require_once "includes/auth.php";

requireLogin();

if (
    $_SESSION["role"] !== "student" &&
    $_SESSION["role"] !== "teacher"
) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION["user_id"];

$sql = "SELECT
            r.id,
            r.book_id,
            r.reserved_at,
            r.status,
            b.title,
            b.author
        FROM reservations r
        INNER JOIN books b
            ON r.book_id = b.id
        WHERE r.user_id = ?
        ORDER BY
            CASE
                WHEN r.status = 'pending' THEN 1
                WHEN r.status = 'fulfilled' THEN 2
                ELSE 3
            END,
            r.reserved_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$reservations = $stmt->get_result();

include "includes/header.php";

?>

<div class="container">

    <div class="page-header">

        <div>
            <p class="dashboard-eyebrow">
                Library
            </p>

            <h1>My Reservation Requests</h1>

            <p>
                Track the books you have requested.
            </p>
        </div>

        <a
            href="books.php"
            class="btn btn-primary"
        >
            Browse Books
        </a>

    </div>


    <?php if ($reservations->num_rows === 0): ?>

        <div class="empty-state">

            <div class="empty-state-icon">
                📚
            </div>

            <h2>No Reservation Requests</h2>

            <p>
                You haven't requested any books yet.
            </p>

            <a
                href="books.php"
                class="btn btn-primary"
            >
                Browse Books
            </a>

        </div>

    <?php else: ?>

        <div class="reservation-list">

            <?php while ($reservation = $reservations->fetch_assoc()): ?>

                <div class="reservation-card">

                    <div class="reservation-book">

                        <h3>
                            <?php echo htmlspecialchars($reservation["title"]); ?>
                        </h3>

                        <p>
                            by
                            <?php echo htmlspecialchars($reservation["author"]); ?>
                        </p>

                    </div>


                    <div class="reservation-info">

                        <span class="reservation-status <?php echo htmlspecialchars($reservation["status"]); ?>">

                            <?php if ($reservation["status"] === "pending"): ?>

                                ⏳ Pending

                            <?php elseif ($reservation["status"] === "fulfilled"): ?>

                                ✓ Fulfilled

                            <?php else: ?>

                                ✕ Cancelled

                            <?php endif; ?>

                        </span>


                        <small>
                            Requested:
                            <?php
                            echo date(
                                "d M Y, h:i A",
                                strtotime($reservation["reserved_at"])
                            );
                            ?>
                        </small>

                    </div>


                    <div class="reservation-actions">

                        <a
                            href="book-details.php?id=<?php echo $reservation["book_id"]; ?>"
                            class="btn btn-secondary"
                        >
                            View Book
                        </a>


                        <?php if ($reservation["status"] === "pending"): ?>

                            <a
                                href="cancel-reservation.php?id=<?php echo $reservation["id"]; ?>"
                                class="btn btn-danger"
                                onclick="return confirm('Cancel this reservation request?');"
                            >
                                Cancel
                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endwhile; ?>

        </div>

    <?php endif; ?>

</div>

<?php

$stmt->close();

include "includes/footer.php";

?>
