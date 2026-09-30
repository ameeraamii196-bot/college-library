<?php

require_once "../config/database.php";
require_once "../includes/auth.php";

requireRole("librarian");

$sql = "SELECT
            r.id,
            r.user_id,
            r.book_id,
            r.loan_id,
            r.reserved_at,
            r.status,

            b.title,
            b.author,

            u.full_name,
            u.username,
            u.role

        FROM reservations r

        INNER JOIN books b
            ON r.book_id = b.id

        INNER JOIN users u
            ON r.user_id = u.id

        WHERE r.status = 'pending'

        ORDER BY r.reserved_at ASC";

$stmt = $conn->prepare($sql);
$stmt->execute();

$reservations = $stmt->get_result();

include "../includes/header.php";

?>

<div class="container">

    <div class="page-header">

        <div>

            <p class="dashboard-eyebrow">
                Librarian
            </p>

            <h1>Reservation Requests</h1>

            <p>
                Manage student and teacher book requests.
            </p>

        </div>

    </div>


    <?php if ($reservations->num_rows === 0): ?>

        <div class="empty-state">

            <div class="empty-state-icon">
                ✓
            </div>

            <h2>No Pending Requests</h2>

            <p>
                There are currently no reservation requests.
            </p>

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


                    <div class="reservation-user">

                        <strong>
                            Requested by
                        </strong>

                        <p>
                            <?php echo htmlspecialchars($reservation["full_name"]); ?>
                        </p>

                        <small>
                            <?php echo htmlspecialchars(ucfirst($reservation["role"])); ?>
                            ·
                            @<?php echo htmlspecialchars($reservation["username"]); ?>
                        </small>

                    </div>


                    <div class="reservation-info">

                        <span class="reservation-status pending">
                            ⏳ Pending
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
                            href="issue-book.php?reservation_id=<?php echo $reservation["id"]; ?>"
                            class="btn btn-primary"
                        >
                            Issue Book
                        </a>

                    </div>

                </div>

            <?php endwhile; ?>

        </div>

    <?php endif; ?>

</div>

<?php

$stmt->close();

include "../includes/footer.php";

?>