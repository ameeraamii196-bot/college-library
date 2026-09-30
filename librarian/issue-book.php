<?php

require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/functions.php";

requireRole("librarian");

$selected_reservation_id = isset($_GET["reservation_id"])
    ? (int) $_GET["reservation_id"]
    : 0;

$selected_reservation = null;

if ($selected_reservation_id > 0) {

    $sql_selected_reservation = "SELECT
                                    r.id,
                                    r.user_id,
                                    r.book_id,
                                    r.status,
                                    b.title,
                                    u.full_name,
                                    u.username

                                FROM reservations r

                                INNER JOIN books b
                                    ON r.book_id = b.id

                                INNER JOIN users u
                                    ON r.user_id = u.id

                                WHERE r.id = ?
                                AND r.status = 'pending'
                                LIMIT 1";

    $stmt_selected_reservation = $conn->prepare($sql_selected_reservation);
    $stmt_selected_reservation->bind_param("i", $selected_reservation_id);
    $stmt_selected_reservation->execute();

    $selected_result = $stmt_selected_reservation->get_result();

    if ($selected_result->num_rows === 1) {
        $selected_reservation = $selected_result->fetch_assoc();
    }

    $stmt_selected_reservation->close();
}

/*
|--------------------------------------------------------------------------
| Handle Book Issue
|--------------------------------------------------------------------------
*/

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $user_id = isset($_POST["user_id"])
        ? (int) $_POST["user_id"]
        : 0;

    $copy_id = isset($_POST["copy_id"])
        ? (int) $_POST["copy_id"]
        : 0;

    $due_date = $_POST["due_date"] ?? "";

    $reservation_id = isset($_POST["reservation_id"])
        ? (int) $_POST["reservation_id"]
        : 0;

    if ($reservation_id > 0 && $copy_id <= 0) {

        $sql_reserved_copy = "SELECT bc.id
                              FROM book_copies bc
                              INNER JOIN reservations r
                                  ON r.book_id = bc.book_id
                              WHERE r.id = ?
                              AND r.status = 'pending'
                              AND bc.status = 'reserved'
                              LIMIT 1";

        $stmt_reserved_copy = $conn->prepare($sql_reserved_copy);
        $stmt_reserved_copy->bind_param("i", $reservation_id);
        $stmt_reserved_copy->execute();

        $reserved_copy_result = $stmt_reserved_copy->get_result();

        if ($reserved_copy_result->num_rows === 1) {
            $reserved_copy = $reserved_copy_result->fetch_assoc();
            $copy_id = (int) $reserved_copy["id"];
        }

        $stmt_reserved_copy->close();
    }

    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if ($user_id <= 0 || $copy_id <= 0 || empty($due_date)) {

        $error = "Please fill in all required fields.";

    } else {

        $today = date("Y-m-d");

        if ($due_date < $today) {

            $error = "Due date cannot be before today.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Check User
            |--------------------------------------------------------------------------
            */

            $sql_user = "SELECT id
                         FROM users
                         WHERE id=?
                         AND role IN ('student', 'teacher')
                         AND status='active'
                         LIMIT 1";

            $stmt_user = $conn->prepare($sql_user);
            $stmt_user->bind_param("i", $user_id);
            $stmt_user->execute();

            $user_result = $stmt_user->get_result();

            if ($user_result->num_rows !== 1) {

                $error = "Invalid student/teacher account.";

            }

            $stmt_user->close();

            /*
            |--------------------------------------------------------------------------
            | Check Copy
            |--------------------------------------------------------------------------
            */

            if (empty($error)) {

                $sql_copy = "SELECT
                                bc.id,
                                bc.book_id,
                                bc.status
                             FROM book_copies bc
                             WHERE bc.id=?
                             LIMIT 1";

                $stmt_copy = $conn->prepare($sql_copy);
                $stmt_copy->bind_param("i", $copy_id);
                $stmt_copy->execute();

                $copy_result = $stmt_copy->get_result();

                if ($copy_result->num_rows !== 1) {

                    $error = "Book copy not found.";

                } else {

                    $copy = $copy_result->fetch_assoc();
                }

                $stmt_copy->close();
            }

            /*
            |--------------------------------------------------------------------------
            | Handle Reserved Copy
            |--------------------------------------------------------------------------
            */

            if (empty($error)) {

                if ($copy["status"] === "reserved") {

                    /*
                    | A reserved copy must have a matching pending reservation.
                    */

                    $sql_reservation = "SELECT
                                            id,
                                            user_id,
                                            book_id
                                         FROM reservations
                                         WHERE id=?
                                         AND user_id=?
                                         AND book_id=?
                                         AND status='pending'
                                         LIMIT 1";

                    $stmt_reservation = $conn->prepare($sql_reservation);

                    $stmt_reservation->bind_param(
                        "iii",
                        $reservation_id,
                        $user_id,
                        $copy["book_id"]
                    );

                    $stmt_reservation->execute();

                    $reservation_result =
                        $stmt_reservation->get_result();

                    if ($reservation_result->num_rows !== 1) {

                        $error =
                            "This reserved copy can only be issued to its reserved user.";

                    }

                    $stmt_reservation->close();

                } elseif ($copy["status"] !== "available") {

                    $error =
                        "This book copy is not currently available for issue.";
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Create Loan
            |--------------------------------------------------------------------------
            */

            if (empty($error)) {

                $issue_date = date("Y-m-d");

                $sql_loan = "INSERT INTO loans
                             (
                                user_id,
                                copy_id,
                                issue_date,
                                due_date,
                                status
                             )
                             VALUES (?, ?, ?, ?, 'issued')";

                $stmt_loan = $conn->prepare($sql_loan);

                $stmt_loan->bind_param(
                    "iiss",
                    $user_id,
                    $copy_id,
                    $issue_date,
                    $due_date
                );

                if ($stmt_loan->execute()) {

                    $new_loan_id = $stmt_loan->insert_id;

                    $stmt_loan->close();

                    recordActivity($conn, $user_id, "borrow");

                    /*
                    |--------------------------------------------------------------------------
                    | Update Copy Status
                    |--------------------------------------------------------------------------
                    */

                    $sql_update_copy =
                        "UPDATE book_copies
                         SET status='issued'
                         WHERE id=?";

                    $stmt_update_copy =
                        $conn->prepare($sql_update_copy);

                    $stmt_update_copy->bind_param(
                        "i",
                        $copy_id
                    );

                    $stmt_update_copy->execute();

                    $stmt_update_copy->close();

                    /*
                    |--------------------------------------------------------------------------
                    | Fulfill Reservation
                    |--------------------------------------------------------------------------
                    */

                    if ($reservation_id > 0) {

                        $sql_update_reservation =
                            "UPDATE reservations
                             SET status='fulfilled',
                                 loan_id=?
                             WHERE id=?
                             AND user_id=?
                             AND status='pending'";

                        $stmt_update_reservation =
                            $conn->prepare(
                                $sql_update_reservation
                            );

                        $stmt_update_reservation->bind_param(
                            "iii",
                            $new_loan_id,
                            $reservation_id,
                            $user_id
                        );

                        $stmt_update_reservation->execute();

                        $stmt_update_reservation->close();
                    }

                    $message =
                        "Book issued successfully.";

                } else {

                    $error =
                        "Unable to issue book: "
                        . $stmt_loan->error;

                    $stmt_loan->close();
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Get Active Students and Teachers
|--------------------------------------------------------------------------
*/

$sql_users = "SELECT
                id,
                full_name,
                username,
                role
              FROM users
              WHERE role IN ('student', 'teacher')
              AND status='active'
              ORDER BY full_name ASC";

$users = $conn->query($sql_users);

/*
|--------------------------------------------------------------------------
| Get Available Copies
|--------------------------------------------------------------------------
*/

if ($selected_reservation) {

    $sql_available = "SELECT
                        bc.id,
                        bc.accession_number,
                        b.title,
                        b.author

                      FROM book_copies bc

                      INNER JOIN books b
                          ON bc.book_id = b.id

                      WHERE bc.book_id = ?
                      AND bc.status = 'reserved'

                      ORDER BY bc.id ASC";

    $stmt_available = $conn->prepare($sql_available);
    $stmt_available->bind_param("i", $selected_reservation["book_id"]);
    $stmt_available->execute();

    $available_copies = $stmt_available->get_result();

} else {

    $sql_available = "SELECT
                        bc.id,
                        bc.accession_number,
                        b.title,
                        b.author

                      FROM book_copies bc

                      INNER JOIN books b
                          ON bc.book_id = b.id

                      WHERE bc.status='available'

                      ORDER BY b.title ASC";

    $available_copies = $conn->query($sql_available);
}

/*
|--------------------------------------------------------------------------
| Get Reserved Copies
|--------------------------------------------------------------------------
*/

$sql_reserved = "SELECT
                    bc.id,
                    bc.accession_number,
                    b.title,
                    b.author,

                    r.id AS reservation_id,
                    r.user_id AS reserved_user_id,

                    u.full_name AS reserved_for,
                    u.username AS reserved_username

                 FROM book_copies bc

                 INNER JOIN books b
                     ON bc.book_id = b.id

                 INNER JOIN reservations r
                     ON r.book_id = bc.book_id
                     AND r.status='pending'

                 INNER JOIN users u
                     ON u.id = r.user_id

                 WHERE bc.status='reserved'

                 ORDER BY r.reserved_at ASC";

$reserved_copies = $conn->query($sql_reserved);

require_once "../includes/header.php";

?>

<div class="page-header">

    <h1>Issue Book</h1>

    <p>
        Issue available books or complete pending reservations.
    </p>

</div>

<?php if (!empty($message)): ?>

    <div class="success-message">
        <?php echo htmlspecialchars($message); ?>
    </div>

<?php endif; ?>

<?php if (!empty($error)): ?>

    <div class="error-message">
        <?php echo htmlspecialchars($error); ?>
    </div>

<?php endif; ?>

<?php if ($selected_reservation): ?>

    <div class="reservation-request-banner">

        <h3>Reservation Selected</h3>

        <p>
            <strong>Book:</strong>
            <?php echo htmlspecialchars($selected_reservation["title"]); ?>
        </p>

        <p>
            <strong>User:</strong>
            <?php echo htmlspecialchars($selected_reservation["full_name"]); ?>
            (
            <?php echo htmlspecialchars($selected_reservation["username"]); ?>
            )
        </p>

    </div>

<?php endif; ?>

<!-- ==========================================================
     NORMAL BOOK ISSUE
=========================================================== -->

<div class="form-card">

    <h2>Issue Available Book</h2>

    <form method="POST">

        <div class="form-group">

            <label for="user_id">
                Student / Teacher
            </label>

            <select
                name="user_id"
                id="user_id"
                required
            >

                <option value="">
                    Select User
                </option>

                <?php if ($users): ?>

                    <?php while ($user = $users->fetch_assoc()): ?>

                        <option
                            value="<?php echo $user["id"]; ?>"
                            <?php if ($selected_reservation && (int) $selected_reservation["user_id"] === (int) $user["id"]): ?>
                                selected
                            <?php endif; ?>
                        >

                            <?php echo htmlspecialchars($user["full_name"]); ?>

                            -
                            <?php echo htmlspecialchars($user["username"]); ?>

                            -
                            <?php echo ucfirst($user["role"]); ?>

                        </option>

                    <?php endwhile; ?>

                <?php endif; ?>

            </select>

        </div>

        <div class="form-group">

            <label for="copy_id">
                Book Copy
            </label>

            <select
                name="copy_id"
                id="copy_id"
                required
            >

                <option value="">
                    Select Book Copy
                </option>

                <?php if ($available_copies): ?>

                    <?php while ($copy_item = $available_copies->fetch_assoc()): ?>

                        <option value="<?php echo $copy_item["id"]; ?>">

                            <?php echo htmlspecialchars($copy_item["title"]); ?>

                            -
                            <?php echo htmlspecialchars($copy_item["accession_number"]); ?>

                        </option>

                    <?php endwhile; ?>

                <?php endif; ?>

            </select>

        </div>

        <div class="form-group">

            <label for="due_date">
                Due Date
            </label>

            <input
                type="date"
                name="due_date"
                id="due_date"
                min="<?php echo date("Y-m-d"); ?>"
                required
            >

        </div>

        <input
            type="hidden"
            name="reservation_id"
            value="<?php echo htmlspecialchars((string) $selected_reservation_id); ?>"
        >

        <button
            type="submit"
            class="btn btn-primary"
        >
            Issue Book
        </button>

    </form>

</div>

<!-- ==========================================================
     RESERVED BOOKS
=========================================================== -->

<div class="page-header">

    <h2>Pending Reserved Books</h2>

    <p>
        These copies are reserved for specific users.
    </p>

</div>

<?php if ($reserved_copies && $reserved_copies->num_rows > 0): ?>

    <div class="reservation-list">

        <?php while ($reserved = $reserved_copies->fetch_assoc()): ?>

            <div class="reservation-card">

                <h3>
                    <?php echo htmlspecialchars($reserved["title"]); ?>
                </h3>

                <p>
                    <strong>Author:</strong>
                    <?php echo htmlspecialchars($reserved["author"]); ?>
                </p>

                <p>
                    <strong>Copy:</strong>
                    <?php echo htmlspecialchars($reserved["accession_number"]); ?>
                </p>

                <p>
                    <strong>Reserved for:</strong>
                    <?php echo htmlspecialchars($reserved["reserved_for"]); ?>
                </p>

                <p>
                    <strong>Username:</strong>
                    <?php echo htmlspecialchars($reserved["reserved_username"]); ?>
                </p>

                <form method="POST">

                    <input
                        type="hidden"
                        name="user_id"
                        value="<?php echo $reserved["reserved_user_id"]; ?>"
                    >

                    <input
                        type="hidden"
                        name="copy_id"
                        value="<?php echo $reserved["id"]; ?>"
                    >

                    <input
                        type="hidden"
                        name="reservation_id"
                        value="<?php echo $reserved["reservation_id"]; ?>"
                    >

                    <div class="form-group">

                        <label>
                            Due Date
                        </label>

                        <input
                            type="date"
                            name="due_date"
                            min="<?php echo date("Y-m-d"); ?>"
                            required
                        >

                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Issue to Reserved User
                    </button>

                </form>

            </div>

        <?php endwhile; ?>

    </div>

<?php else: ?>

    <div class="empty-state">

        <h3>No books waiting for reservation issue</h3>

        <p>
            Returned reserved books will appear here.
        </p>

    </div>

<?php endif; ?>

<?php

require_once "../includes/footer.php";

?>