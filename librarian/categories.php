<?php

require_once "../includes/auth.php";
requireRole("librarian");

require_once "../config/database.php";
require_once "../includes/header.php";

$sql = "SELECT * FROM categories ORDER BY name ASC";

$result = $conn->query($sql);

?>

<section>
<div class="page-header">

    <div>
        <h1>Book Categories</h1>
        <p>Manage categories used to organize books.</p>
    </div>

    <a href="add-category.php" class="btn-primary">
        + Add Category
    </a>

</div>


<div class="table-container">

    <table class="data-table">

        <thead>

            <tr>
                <th>ID</th>
                <th>Category</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>

        </thead>


        <tbody>

            <?php if ($result->num_rows > 0): ?>

                <?php while ($category = $result->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?php echo $category["id"]; ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($category["name"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($category["description"] ?? ""); ?>
                        </td>

                        <td>

                            <a
                                href="delete-category.php?id=<?php echo $category["id"]; ?>"
                                onclick="return confirm('Delete this category?');"
                            >
                                Delete
                            </a>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td colspan="4">
                        No categories found.
                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>
</section> <?php require_once "../includes/footer.php"; ?>