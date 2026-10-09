<?php

require_once "../config/database.php";
require_once "../includes/auth.php";

requireRole("librarian");

$success = $_GET["success"] ?? "";
$error = $_GET["error"] ?? "";

$status_filter = isset($_GET["status"]) ? trim($_GET["status"]) : "all";
$allowed_statuses = ["all", "pending", "approved", "rejected", "cancelled", "fulfilled"];

if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = "all";
}

$sql = "SELECT
            r.id,
            r.user_id,
            r.book_id,
            r.loan_id,
            r.status,
            r.reserved_at,

            b.title,
            b.author,

            u.full_name,
            u.username,
            u.role

        FROM reservations r

        INNER JOIN books b
            ON r.book_id = b.id

        INNER JOIN users u
            ON r.user_id = u.id";

$params = [];
$types = "";

if ($status_filter !== "all") {
    $sql .= " WHERE r.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY
            CASE
                WHEN r.status = 'pending' THEN 1
                WHEN r.status = 'approved' THEN 2
                WHEN r.status = 'rejected' THEN 3
                WHEN r.status = 'cancelled' THEN 4
                WHEN r.status = 'fulfilled' THEN 5
                ELSE 6
            END,
            r.reserved_at DESC";

$stmt = $conn->prepare($sql);

if ($params !== []) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$reservations = $stmt->get_result();

$count_sql = "SELECT
                SUM(status = 'pending') AS pending_count,
                SUM(status = 'approved') AS approved_count,
                SUM(status = 'rejected') AS rejected_count
              FROM reservations";

$count_result = $conn->query($count_sql);
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;

if ($count_result) {
    $counts = $count_result->fetch_assoc();
    $pending_count = (int) ($counts["pending_count"] ?? 0);
    $approved_count = (int) ($counts["approved_count"] ?? 0);
    $rejected_count = (int) ($counts["rejected_count"] ?? 0);
}

include "../includes/header.php";

?>

<div class="container reservation-management-page">

    <div class="page-header">

        <div>
            <p class="dashboard-eyebrow">Librarian</p>
            <h1>Reservation Management</h1>
            <p>Review and manage student and teacher reservation requests.</p>
        </div>

        <a href="dashboard.php" class="btn btn-secondary">
            ← Back to Dashboard
        </a>

    </div>

    <?php if ($success === "approved"): ?>
        <div class="alert success-alert">✓ Reservation approved successfully.</div>
    <?php elseif ($success === "rejected"): ?>
        <div class="alert success-alert">✓ Reservation rejected.</div>
    <?php elseif ($success === "issued"): ?>
        <div class="alert success-alert">✓ Book issued successfully and reservation completed.</div>
    <?php elseif ($error === "no_copy"): ?>
        <div class="alert error-alert">⚠ No available copy is currently available.</div>
    <?php elseif ($error === "issue_failed"): ?>
        <div class="alert error-alert">⚠ Unable to issue the book. Please try again.</div>
    <?php endif; ?>

    <div class="reservation-stats">
        <div class="reservation-stat">
            <span class="stat-number"><?php echo $pending_count; ?></span>
            <span class="stat-label">Pending</span>
        </div>
        <div class="reservation-stat">
            <span class="stat-number"><?php echo $approved_count; ?></span>
            <span class="stat-label">Approved</span>
        </div>
        <div class="reservation-stat">
            <span class="stat-number"><?php echo $rejected_count; ?></span>
            <span class="stat-label">Rejected</span>
        </div>
    </div>

    <div class="reservation-filter-bar">
        <?php foreach ($allowed_statuses as $option): ?>
            <a
                href="reservations.php?status=<?php echo htmlspecialchars($option); ?>"
                class="reservation-filter-pill <?php echo $status_filter === $option ? "active" : ""; ?>"
            >
                <?php echo ucfirst($option); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($reservations->num_rows === 0): ?>

        <div class="empty-state">
            <div class="empty-state-icon">📚</div>
            <h2>No Reservation Records</h2>
            <p>
                There are no reservation requests in the selected category.
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
                            by <?php echo htmlspecialchars($reservation["author"]); ?>
                        </p>
                    </div>

                    <div class="reservation-user">
                        <strong>Requested by</strong>
                        <p><?php echo htmlspecialchars($reservation["full_name"]); ?></p>
                        <small>
                            <?php echo htmlspecialchars(ucfirst($reservation["role"])); ?>
                            · @<?php echo htmlspecialchars($reservation["username"]); ?>
                        </small>
                    </div>

                    <div class="reservation-info">
                        <span class="reservation-status <?php echo htmlspecialchars(strtolower($reservation["status"])); ?>">
                            <?php echo htmlspecialchars(ucfirst($reservation["status"])); ?>
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
                        <?php if ($reservation["status"] === "pending"): ?>
                            <a
                                href="approve-reservation.php?id=<?php echo (int) $reservation["id"]; ?>"
                                class="btn btn-success"
                                onclick="return confirm('Approve this reservation request?');"
                            >
                                ✓ Approve
                            </a>

                            <a
                                href="reject-reservation.php?id=<?php echo (int) $reservation["id"]; ?>"
                                class="btn btn-danger"
                                onclick="return confirm('Reject this reservation request?');"
                            >
                                ✕ Reject
                            </a>

                        <?php elseif ($reservation["status"] === "approved"): ?>
                            <a
                                href="issue-reserved-book.php?id=<?php echo (int) $reservation["id"]; ?>"
                                class="btn btn-primary"
                            >
                                📚 Issue Book
                            </a>
                        <?php endif; ?>
                    </div>

                </div>

            <?php endwhile; ?>

        </div>

    <?php endif; ?>

</div>

<style>
    .reservation-management-page {
        padding: 30px 0 40px;
    }

    .reservation-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-bottom: 30px;
    }

    .reservation-stat {
        display: flex;
        flex-direction: column;
        gap: 5px;
        padding: 20px;
        background: #fff;
        border: 1px solid #e7e1d8;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(15, 23, 42, 0.04);
    }

    .stat-number {
        font-size: 28px;
        font-weight: 700;
        color: var(--primary, #1f3a5f);
    }

    .stat-label {
        font-size: 13px;
        opacity: 0.65;
    }

    .reservation-filter-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 20px 0 28px;
    }

    .reservation-filter-pill {
        display: inline-block;
        padding: 8px 14px;
        border-radius: 999px;
        background: #f3f4f6;
        color: #374151;
        font-size: 13px;
        font-weight: 600;
        border: 1px solid #e5e7eb;
    }

    .reservation-filter-pill.active {
        background: #1f3a5f;
        color: #fff;
        border-color: #1f3a5f;
    }

    .reservation-card {
        display: grid;
        grid-template-columns: 1.4fr 1.2fr 1fr auto;
        gap: 20px;
        align-items: center;
        background: #fff;
        border-radius: 14px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 4px 15px rgba(15, 23, 42, 0.04);
        padding: 22px;
    }

    .reservation-book h3 {
        margin: 0 0 6px;
        font-size: 22px;
    }

    .reservation-book p {
        margin: 0;
        color: #6b7280;
    }

    .reservation-user strong,
    .reservation-info strong {
        display: block;
        margin-bottom: 6px;
    }

    .reservation-user p,
    .reservation-info small {
        margin: 0;
        color: #4b5563;
    }

    .reservation-user small {
        display: block;
        margin-top: 4px;
        color: #6b7280;
    }

    .reservation-info {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .reservation-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: fit-content;
        padding: 7px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.4px;
        text-transform: uppercase;
    }

    .reservation-status.pending {
        background: #fff7ed;
        color: #c2410c;
    }

    .reservation-status.approved {
        background: #dcfce7;
        color: #166534;
    }

    .reservation-status.rejected {
        background: #fef2f2;
        color: #b91c1c;
    }

    .reservation-status.cancelled {
        background: #f3f4f6;
        color: #374151;
    }

    .reservation-status.fulfilled {
        background: #eef2ff;
        color: #4338ca;
    }

    .reservation-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 10px;
    }

    .alert {
        padding: 14px 18px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 14px;
    }

    .success-alert {
        background: #e4f4e8;
        color: #28663a;
    }

    .error-alert {
        background: #f9e1e1;
        color: #963434;
    }

    @media (max-width: 900px) {
        .reservation-card {
            grid-template-columns: 1fr;
            align-items: flex-start;
        }

        .reservation-actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 600px) {
        .reservation-stats {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php

$stmt->close();

include "../includes/footer.php";

?>