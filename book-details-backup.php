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

?>

<section class="book-details-section">

    <a href="books.php" class="back-link">
        ← Back to Catalogue
    </a>


    <div class="book-details-card">


        <!-- Book Cover -->

        <div class="book-details-cover">

            <?php if (!empty($book["cover_image"])): ?>

                <img
                    src="<?php echo htmlspecialchars($book["cover_image"]); ?>"
                    alt="<?php echo htmlspecialchars($book["title"]); ?>"
                >

            <?php else: ?>

                <div class="book-placeholder large">
                    📚
                </div>

            <?php endif; ?>

        </div>


        <!-- Book Information -->

        <div class="book-details-info">

            <?php if (!empty($book["category_name"])): ?>

                <span class="book-category">
                    <?php echo htmlspecialchars($book["category_name"]); ?>
                </span>

            <?php endif; ?>


            <h1>
                <?php echo htmlspecialchars($book["title"]); ?>
            </h1>


            <p class="book-author large-author">
                By <?php echo htmlspecialchars($book["author"]); ?>
            </p>


            <?php if (!empty($book["summary"])): ?>

                <div class="book-description">

                    <h2>About this book</h2>

                    <p>
                        <?php echo nl2br(htmlspecialchars($book["summary"])); ?>
                    </p>

                </div>

            <?php endif; ?>


            <div class="book-meta">

                <?php if (!empty($book["isbn"])): ?>

                    <div>
                        <strong>ISBN:</strong>
                        <?php echo htmlspecialchars($book["isbn"]); ?>
                    </div>

                <?php endif; ?>


                <?php if (!empty($book["publisher"])): ?>

                    <div>
                        <strong>Publisher:</strong>
                        <?php echo htmlspecialchars($book["publisher"]); ?>
                    </div>

                <?php endif; ?>


                <?php if (!empty($book["publication_year"])): ?>

                    <div>
                        <strong>Publication Year:</strong>
                        <?php echo htmlspecialchars($book["publication_year"]); ?>
                    </div>

                <?php endif; ?>

            </div>


            <!-- Availability -->

            <div class="availability-panel">

                <h2>Availability</h2>


                <div class="availability-grid">

                    <div class="availability-item">

                        <span class="availability-number">
                            <?php echo (int)($copy_stats["total_copies"] ?? 0); ?>
                        </span>

                        <span>
                            Total Copies
                        </span>

                    </div>


                    <div class="availability-item available-item">

                        <span class="availability-number">
                            <?php echo (int)($copy_stats["available_copies"] ?? 0); ?>
                        </span>

                        <span>
                            Available
                        </span>

                    </div>


                    <div class="availability-item">

                        <span class="availability-number">
                            <?php echo (int)($copy_stats["issued_copies"] ?? 0); ?>
                        </span>

                        <span>
                            Issued
                        </span>

                    </div>


                    <div class="availability-item">

                        <span class="availability-number">
                            <?php echo (int)($copy_stats["reserved_copies"] ?? 0); ?>
                        </span>

                        <span>
                            Reserved
                        </span>

                    </div>

                </div>


                <p class="privacy-note">
                    Borrower information is private and is not displayed.
                </p>

            </div>


            <?php if ((int)($copy_stats["available_copies"] ?? 0) > 0): ?>

                <span class="availability available">
                    Available
                </span>

            <?php elseif ($returning_today > 0): ?>

                <span class="availability returning-today">
                    Returning Today
                </span>

            <?php elseif ((int)($copy_stats["reserved_copies"] ?? 0) > 0): ?>

                <span class="availability reserved">
                    Reserved
                </span>

            <?php else: ?>

                <span class="availability unavailable">
                    Currently Unavailable
                </span>

            <?php endif; ?>


            <?php if (isset($_SESSION["user_id"])): ?>

                <?php if ($_SESSION["role"] === "student" || $_SESSION["role"] === "teacher"): ?>

                    <?php if ($has_pending_reservation): ?>

                        <div class="book-action-status reserved">
                            Reservation Requested
                        </div>

                    <?php elseif ($has_active_loan): ?>

                        <div class="book-action-status already-borrowed">
                            You already have this book
                        </div>

                    <?php elseif ((int)($copy_stats["available_copies"] ?? 0) > 0 || $returning_today > 0): ?>

                        <a
                            href="reserve-book.php?id=<?php echo $book_id; ?>"
                            class="btn btn-primary"
                        >
                            Request to Reserve
                        </a>

                    <?php else: ?>

                        <div class="book-action-status unavailable">
                            No copies currently available
                        </div>

                    <?php endif; ?>

                <?php endif; ?>

            <?php else: ?>

                <a
                    href="login.php"
                    class="btn btn-primary"
                >
                    Login to Reserve
                </a>

            <?php endif; ?>


        </div>

    </div>

</section>


<?php

require_once "includes/footer.php";

?>