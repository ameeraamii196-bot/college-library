<?php

require_once "../includes/auth.php";
requireRole("librarian");

require_once "../config/database.php";

$error = "";

$book_result = $conn->query(
"SELECT id, title, author
FROM books
ORDER BY title ASC"
);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

$book_id = (int) $_POST["book_id"];
$accession_number = trim($_POST["accession_number"]);
$shelf_location = trim($_POST["shelf_location"]);
$status = $_POST["status"];

if (
    empty($book_id) ||
    empty($accession_number)
) {

    $error = "Book and accession number are required.";

} else {

    $check_sql = "SELECT id
                  FROM book_copies
                  WHERE accession_number = ?";

    $check_stmt = $conn->prepare($check_sql);

    $check_stmt->bind_param(
        "s",
        $accession_number
    );

    $check_stmt->execute();

    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {

        $error = "This accession number already exists.";

    } else {

        $sql = "INSERT INTO book_copies
                (
                    book_id,
                    accession_number,
                    shelf_location,
                    status
                )
                VALUES (?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "isss",
            $book_id,
            $accession_number,
            $shelf_location,
            $status
        );

        if ($stmt->execute()) {

            header("Location: copies.php");
            exit;

        } else {

            $error = "Unable to add book copy.";

        }

        $stmt->close();
    }

    $check_stmt->close();
}

}

require_once "../includes/header.php";

?>

<section>
<h1>Add Physical Book Copy</h1>

<p>
    Register an individual physical copy of a book.
</p>

<br>

<?php if (!empty($error)): ?>

    <div class="error-message">
        <?php echo htmlspecialchars($error); ?>
    </div>

<?php endif; ?>


<form method="POST" class="admin-form">

    <div class="form-group">

        <label for="book_id">
            Book *
        </label>

        <select
            id="book_id"
            name="book_id"
            required
        >

            <option value="">
                Select Book
            </option>

            <?php while ($book = $book_result->fetch_assoc()): ?>

                <option value="<?php echo $book["id"]; ?>">

                    <?php
                    echo htmlspecialchars(
                        $book["title"]
                        . " - "
                        . $book["author"]
                    );
                    ?>

                </option>

            <?php endwhile; ?>

        </select>

    </div>


    <div class="form-group">

        <label for="accession_number">
            Accession Number *
        </label>

        <input
            type="text"
            id="accession_number"
            name="accession_number"
            placeholder="Example: ACC-001"
            required
        >

    </div>


    <div class="form-group">

        <label for="shelf_location">
            Shelf Location
        </label>

        <input
            type="text"
            id="shelf_location"
            name="shelf_location"
            placeholder="Example: A-03"
        >

    </div>


    <div class="form-group">

        <label for="status">
            Status
        </label>

        <select
            id="status"
            name="status"
        >

            <option value="available">
                Available
            </option>

            <option value="issued">
                Issued
            </option>

            <option value="reserved">
                Reserved
            </option>

            <option value="lost">
                Lost
            </option>

            <option value="damaged">
                Damaged
            </option>

        </select>

    </div>


    <button type="submit" class="btn-primary">
        Add Copy
    </button>

</form>
</section> <?php require_once "../includes/footer.php"; ?>