<?php

require_once "../includes/auth.php";
requireRole("librarian");

require_once "../config/database.php";
require_once "../includes/header.php";

$sql = "SELECT
books.id,
books.title,
books.author,
books.isbn,
books.publisher,
books.publication_year,
categories.name AS category_name
FROM books
LEFT JOIN categories
ON books.category_id = categories.id
ORDER BY books.id DESC";

$result = $conn->query($sql);

?>

<section>
<div class="page-header">

    <div>
        <h1>Books</h1>
        <p>Manage books in the library catalogue.</p>
    </div>

    <a href="add-book.php" class="btn-primary">
        + Add Book
    </a>

</div>


<div class="table-container">

    <table class="data-table">

        <thead>

            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Author</th>
                <th>ISBN</th>
                <th>Category</th>
                <th>Publisher</th>
                <th>Year</th>
                <th>Actions</th>
            </tr>

        </thead>


        <tbody>

            <?php if ($result->num_rows > 0): ?>

                <?php while ($book = $result->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?php echo $book["id"]; ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($book["title"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($book["author"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($book["isbn"] ?? ""); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($book["category_name"] ?? "Uncategorized"); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($book["publisher"] ?? ""); ?>
                        </td>

                        <td>
                            <?php echo $book["publication_year"] ?? ""; ?>
                        </td>

                        <td>

                            <a href="edit-book.php?id=<?php echo $book["id"]; ?>">
                                Edit
                            </a>

                            |

                            <a
                                href="delete-book.php?id=<?php echo $book["id"]; ?>"
                                onclick="return confirm('Delete this book and all its copies?');"
                            >
                                Delete
                            </a>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td colspan="8">
                        No books found.
                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>
</section> <?php require_once "../includes/footer.php"; ?>