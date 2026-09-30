<?php

require_once "../includes/auth.php";
requireRole("librarian");

require_once "../config/database.php";
require_once "../includes/header.php";

$sql = "SELECT
book_copies.id,
book_copies.accession_number,
book_copies.shelf_location,
book_copies.status,
books.title
FROM book_copies
INNER JOIN books
ON book_copies.book_id = books.id
ORDER BY book_copies.id DESC";

$result = $conn->query($sql);

?>

<section>
<div class="page-header">

    <div>
        <h1>Book Copies</h1>
        <p>Manage physical copies of books.</p>
    </div>

    <a href="add-copy.php" class="btn-primary">
        + Add Copy
    </a>

</div>


<div class="table-container">

    <table class="data-table">

        <thead>

            <tr>
                <th>ID</th>
                <th>Book</th>
                <th>Accession Number</th>
                <th>Shelf</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>

        </thead>


        <tbody>

            <?php if ($result->num_rows > 0): ?>

                <?php while ($copy = $result->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?php echo $copy["id"]; ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($copy["title"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($copy["accession_number"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($copy["shelf_location"] ?? ""); ?>
                        </td>

                        <td>
                            <?php echo ucfirst($copy["status"]); ?>
                        </td>

                        <td>

                            <a
                                href="delete-copy.php?id=<?php echo $copy["id"]; ?>"
                                onclick="return confirm('Delete this physical copy?');"
                            >
                                Delete
                            </a>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td colspan="6">
                        No book copies found.
                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>
</section> <?php require_once "../includes/footer.php"; ?>