<?php

session_start();

require_once "config/database.php";


/*
|--------------------------------------------------------------------------
| Get Book ID
|--------------------------------------------------------------------------
*/

$book_id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;


if ($book_id <= 0) {

    header("Location: books.php");
    exit;

}


/*
|--------------------------------------------------------------------------
| Get Book Information
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            b.id,
            b.title,
            b.author,
            b.isbn,
            b.summary,
            b.publisher,
            b.publication_year,
            b.cover_image,
            c.name AS category_name

        FROM books b

        LEFT JOIN categories c
            ON b.category_id = c.id

        WHERE b.id = ?

        LIMIT 1";


$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $book_id);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows !== 1) {

    header("Location: books.php");
    exit;

}


$book = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Get Copy Statistics
|--------------------------------------------------------------------------
*/

$sql_copies = "SELECT
    COUNT(*) AS total_copies,
    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available_copies,
    SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) AS issued_copies,
    SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) AS reserved_copies,
    SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) AS lost_copies,
    SUM(CASE WHEN status = 'damaged' THEN 1 ELSE 0 END) AS damaged_copies
    FROM book_copies
    WHERE book_id = ?";

$stmt_copies = $conn->prepare($sql_copies);
$stmt_copies->bind_param("i", $book_id);
$stmt_copies->execute();

$result_copies = $stmt_copies->get_result();

$copy_stats = $result_copies->fetch_assoc();

if (!$copy_stats) {
    $copy_stats = [
        "total_copies" => 0,
        "available_copies" => 0,
        "issued_copies" => 0,
        "reserved_copies" => 0,
        "lost_copies" => 0,
        "damaged_copies" => 0,
    ];
}

$stmt_copies->close();

$today = date("Y-m-d");

$sql_returning = "SELECT COUNT(*) AS returning_today
                  FROM loans l
                  INNER JOIN book_copies bc
                      ON l.copy_id = bc.id
                  WHERE bc.book_id = ?
                  AND l.due_date = ?
                  AND l.status IN ('issued', 'overdue')";

$stmt_returning = $conn->prepare($sql_returning);
$stmt_returning->bind_param("is", $book_id, $today);
$stmt_returning->execute();

$returning_data = $stmt_returning->get_result()->fetch_assoc();

$returning_today = (int) ($returning_data["returning_today"] ?? 0);

$stmt_returning->close();

$has_pending_reservation = false;
$has_active_loan = false;

if (isset($_SESSION["user_id"])) {

    $current_user_id = (int) $_SESSION["user_id"];

    if ($_SESSION["role"] === "student" || $_SESSION["role"] === "teacher") {

        $sql_pending = "SELECT id
                        FROM reservations
                        WHERE user_id = ?
                        AND book_id = ?
                        AND status = 'pending'
                        LIMIT 1";

        $stmt_pending = $conn->prepare($sql_pending);
        $stmt_pending->bind_param("ii", $current_user_id, $book_id);
        $stmt_pending->execute();

        $has_pending_reservation = $stmt_pending->get_result()->num_rows > 0;
        $stmt_pending->close();


        $sql_active_loan = "SELECT l.id
                           FROM loans l
                           INNER JOIN book_copies bc
                               ON l.copy_id = bc.id
                           WHERE l.user_id = ?
                           AND bc.book_id = ?
                           AND l.status IN ('issued', 'overdue')
                           LIMIT 1";

        $stmt_active_loan = $conn->prepare($sql_active_loan);
        $stmt_active_loan->bind_param("ii", $current_user_id, $book_id);
        $stmt_active_loan->execute();

        $has_active_loan = $stmt_active_loan->get_result()->num_rows > 0;
        $stmt_active_loan->close();
    }
}

require_once "includes/header.php";

$cover_src = trim((string) ($book["cover_image"] ?? ""));
if (
    $cover_src !== "" &&
    !preg_match("~^(https?:)?//~i", $cover_src) &&
    strpos($cover_src, "/college-library/") !== 0
) {
    $cover_src = "/college-library/" . ltrim($cover_src, "/");
}

$reservation_message = null;
if (isset($_GET["reservation"])) {
    switch ($_GET["reservation"]) {
        case "exists":
            $reservation_message = [
                "type" => "warning",
                "text" => "You already have a pending reservation for this book.",
            ];
            break;
        case "available":
            $reservation_message = [
                "type" => "error",
                "text" => "This book is currently available, so reservation requests are not allowed.",
            ];
            break;
        case "unavailable":
            $reservation_message = [
                "type" => "error",
                "text" => "No copy is currently returning today, so this book cannot be reserved right now.",
            ];
            break;
        case "requested":
            $reservation_message = [
                "type" => "success",
                "text" => "Your reservation request has been submitted.",
            ];
            break;
    }
}

?>

<div class="book-details-page">
    <?php if ($reservation_message !== null): ?>
        <div class="reservation-banner reservation-banner-<?php echo htmlspecialchars($reservation_message["type"]); ?>">
            <?php echo htmlspecialchars($reservation_message["text"]); ?>
        </div>
    <?php endif; ?>

    <a href="books.php" class="book-back-link">
        <span aria-hidden="true">&larr;</span>
        Back to Books
    </a>

    <section class="book-profile">

        <div class="book-profile-cover">
            <?php if ($cover_src !== ""): ?>
                <img
                    src="<?php echo htmlspecialchars($cover_src); ?>"
                    alt="Cover of <?php echo htmlspecialchars($book["title"]); ?>"
                >
            <?php else: ?>
                <div class="book-profile-placeholder">
                    <span aria-hidden="true">&#128218;</span>
                    <p>No Cover Available</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="book-profile-content">
            <?php if (!empty($book["category_name"])): ?>
                <p class="book-profile-category">
                    <?php echo htmlspecialchars($book["category_name"]); ?>
                </p>
            <?php endif; ?>

            <h1><?php echo htmlspecialchars($book["title"]); ?></h1>

            <p class="book-profile-author">
                by <strong><?php echo htmlspecialchars($book["author"]); ?></strong>
            </p>

            <div class="book-availability-panel">
                <div class="book-availability-heading">
                    <span class="availability-icon" aria-hidden="true">&#128218;</span>
                    <div>
                        <p>Availability</p>

                        <?php if ((int) ($copy_stats["available_copies"] ?? 0) > 0): ?>
                            <strong class="availability-text available">Available</strong>
                        <?php elseif ($returning_today > 0): ?>
                            <strong class="availability-text returning">Returning Today</strong>
                        <?php elseif ((int) ($copy_stats["reserved_copies"] ?? 0) > 0): ?>
                            <strong class="availability-text reserved">Reserved</strong>
                        <?php else: ?>
                            <strong class="availability-text unavailable">Currently Unavailable</strong>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="book-copy-stats">
                    <div>
                        <strong><?php echo (int) ($copy_stats["total_copies"] ?? 0); ?></strong>
                        <span>Total</span>
                    </div>
                    <div>
                        <strong><?php echo (int) ($copy_stats["available_copies"] ?? 0); ?></strong>
                        <span>Available</span>
                    </div>
                    <div>
                        <strong><?php echo (int) ($copy_stats["issued_copies"] ?? 0); ?></strong>
                        <span>Issued</span>
                    </div>
                    <div>
                        <strong><?php echo (int) ($copy_stats["reserved_copies"] ?? 0); ?></strong>
                        <span>Reserved</span>
                    </div>
                </div>

                <p class="book-availability-note">
                    Borrower information is private and is not displayed.
                </p>
            </div>

            <div class="book-profile-actions">
                <a href="books.php" class="book-secondary-button">
                    <span aria-hidden="true">&larr;</span>
                    Browse More
                </a>

                <?php if (
                    isset($_SESSION["user_id"]) &&
                    ($_SESSION["role"] === "student" || $_SESSION["role"] === "teacher")
                ): ?>
                    <?php if ($has_pending_reservation): ?>
                        <span class="book-action-status reserved">Reservation Requested</span>
                    <?php elseif ($has_active_loan): ?>
                        <span class="book-action-status already-borrowed">You already have this book</span>
                    <?php elseif ((int) ($copy_stats["available_copies"] ?? 0) === 0 && $returning_today > 0): ?>
                        <a href="reserve-book.php?id=<?php echo (int) $book["id"]; ?>" class="btn btn-primary">
                            Request to Reserve
                        </a>
                    <?php else: ?>
                        <span class="book-action-status unavailable">Reservation unavailable</span>
                    <?php endif; ?>
                <?php elseif (!isset($_SESSION["user_id"])): ?>
                    <a href="login.php" class="btn btn-primary">Login to Reserve</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="book-information-section">
        <div class="book-section-heading">
            <p>About the book</p>
            <h2>Book Summary</h2>
        </div>

        <div class="book-summary-panel">
            <?php if (!empty($book["summary"])): ?>
                <p><?php echo nl2br(htmlspecialchars($book["summary"])); ?></p>
            <?php else: ?>
                <p class="book-no-summary">No summary has been added for this book yet.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="book-information-section">
        <div class="book-section-heading">
            <p>Details</p>
            <h2>Book Information</h2>
        </div>

        <div class="book-info-grid">
            <?php if (!empty($book["isbn"])): ?>
                <div class="book-info-item">
                    <span>ISBN</span>
                    <strong><?php echo htmlspecialchars($book["isbn"]); ?></strong>
                </div>
            <?php endif; ?>

            <?php if (!empty($book["publisher"])): ?>
                <div class="book-info-item">
                    <span>Publisher</span>
                    <strong><?php echo htmlspecialchars($book["publisher"]); ?></strong>
                </div>
            <?php endif; ?>

            <?php if (!empty($book["publication_year"])): ?>
                <div class="book-info-item">
                    <span>Publication Year</span>
                    <strong><?php echo htmlspecialchars((string) $book["publication_year"]); ?></strong>
                </div>
            <?php endif; ?>

            <?php if (!empty($book["category_name"])): ?>
                <div class="book-info-item">
                    <span>Category</span>
                    <strong><?php echo htmlspecialchars($book["category_name"]); ?></strong>
                </div>
            <?php endif; ?>
        </div>
    </section>

</div>

<?php include "includes/footer.php"; ?>