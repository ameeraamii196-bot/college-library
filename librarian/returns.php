<?php

require_once "../includes/auth.php";
requireRole("librarian");

require_once "../config/database.php";
require_once "../includes/functions.php";

$error = "";
$success = "";


/*
|--------------------------------------------------------------------------
| Return Book
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $loan_id = (int) $_POST["loan_id"];

    if ($loan_id <= 0) {

        $error = "Invalid loan selected.";

    } else {

        /*
        | Find active loan
        */

        $loan_sql = "SELECT
                        id,
                        user_id,
                        copy_id,
                        due_date

                     FROM loans

                     WHERE id = ?
                     AND status = 'issued'

                     LIMIT 1";

        $loan_stmt = $conn->prepare($loan_sql);

        $loan_stmt->bind_param(
            "i",
            $loan_id
        );

        $loan_stmt->execute();

        $loan_result = $loan_stmt->get_result();


        if ($loan_result->num_rows !== 1) {

            $error = "Active loan not found.";

        } else {

            $loan = $loan_result->fetch_assoc();

            $return_date = date("Y-m-d");


            /*
            | A returned book is marked as returned,
            | even if it was returned late.
            */

            $loan_status = "returned";


            /*
            | Update loan
            */

            $update_loan_sql = "UPDATE loans

                                SET return_date = ?,
                                    status = ?

                                WHERE id = ?";

            $update_loan_stmt =
                $conn->prepare($update_loan_sql);

            $update_loan_stmt->bind_param(
                "ssi",
                $return_date,
                $loan_status,
                $loan_id
            );


            if ($update_loan_stmt->execute()) {

                /*
                | Find the associated book and check whether someone
                | is waiting for it before placing the copy back into circulation.
                */

                $book_sql = "SELECT book_id
                             FROM book_copies
                             WHERE id = ?
                             LIMIT 1";

                $book_stmt = $conn->prepare($book_sql);
                $book_stmt->bind_param("i", $loan["copy_id"]);
                $book_stmt->execute();

                $book_result = $book_stmt->get_result();
                $book_data = $book_result->fetch_assoc();

                $book_stmt->close();

                $book_id = $book_data["book_id"] ?? 0;

                $copy_status = "available";

                if ($book_id > 0) {
                    $reservation_sql = "SELECT id
                                        FROM reservations
                                        WHERE book_id = ?
                                        AND status = 'pending'
                                        ORDER BY reserved_at ASC
                                        LIMIT 1";

                    $reservation_stmt = $conn->prepare($reservation_sql);
                    $reservation_stmt->bind_param("i", $book_id);
                    $reservation_stmt->execute();

                    $reservation_result = $reservation_stmt->get_result();
                    $reservation_data = $reservation_result->fetch_assoc();

                    $reservation_stmt->close();

                    if ($reservation_data) {
                        $copy_status = "reserved";
                    }
                }

                $update_copy_sql = "UPDATE book_copies
                                    SET status = ?
                                    WHERE id = ?";

                $update_copy_stmt = $conn->prepare($update_copy_sql);

                $update_copy_stmt->bind_param(
                    "si",
                    $copy_status,
                    $loan["copy_id"]
                );

                $update_copy_stmt->execute();

                $update_copy_stmt->close();

                recordActivity($conn, (int) $loan["user_id"], "return");


                $success = "Book returned successfully.";

            } else {

                $error = "Unable to process the return.";

            }


            $update_loan_stmt->close();
        }


        $loan_stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| Active Loans
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            l.id,
            l.issue_date,
            l.due_date,

            u.full_name,
            u.username,
            u.role,

            b.title,
            b.author,

            bc.accession_number

        FROM loans l

        INNER JOIN users u
            ON l.user_id = u.id

        INNER JOIN book_copies bc
            ON l.copy_id = bc.id

        INNER JOIN books b
            ON bc.book_id = b.id

        WHERE l.status = 'issued'

        ORDER BY l.due_date ASC";


$result = $conn->query($sql);


require_once "../includes/header.php";

?>

<section class="admin-section">

    <div class="page-header">

        <h1>Return Books</h1>

        <p>
            View currently issued books and process returns.
        </p>

    </div>


    <?php if (!empty($success)): ?>

        <div class="success-message">
            <?php echo htmlspecialchars($success); ?>
        </div>

    <?php endif; ?>


    <?php if (!empty($error)): ?>

        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>


    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>Book</th>

                    <th>Copy</th>

                    <th>Borrower</th>

                    <th>Issue Date</th>

                    <th>Due Date</th>

                    <th>Action</th>

                </tr>

            </thead>


            <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while ($loan = $result->fetch_assoc()): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $loan["title"]
                                    );
                                    ?>
                                </strong>

                                <br>

                                <small>
                                    <?php
                                    echo htmlspecialchars(
                                        $loan["author"]
                                    );
                                    ?>
                                </small>

                            </td>


                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $loan["accession_number"]
                                );
                                ?>
                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $loan["full_name"]
                                );
                                ?>

                                <br>

                                <small>
                                    <?php
                                    echo htmlspecialchars(
                                        ucfirst($loan["role"])
                                    );
                                    ?>
                                </small>

                            </td>


                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $loan["issue_date"]
                                );
                                ?>
                            </td>


                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $loan["due_date"]
                                );
                                ?>
                            </td>


                            <td>

                                <form
                                    method="POST"
                                    action=""
                                >

                                    <input
                                        type="hidden"
                                        name="loan_id"
                                        value="<?php echo $loan["id"]; ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn-primary"
                                    >
                                        Return
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="6"
                            style="text-align:center;"
                        >
                            No books are currently issued.

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<?php

require_once "../includes/footer.php";

?>