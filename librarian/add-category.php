<?php

require_once "../includes/auth.php";
requireRole("librarian");

require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

$name = trim($_POST["name"]);
$description = trim($_POST["description"]);

if (empty($name)) {

    $error = "Category name is required.";

} else {

    $check_sql = "SELECT id FROM categories WHERE name = ?";

    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $name);
    $check_stmt->execute();

    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {

        $error = "This category already exists.";

    } else {

        $sql = "INSERT INTO categories (name, description)
                VALUES (?, ?)";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "ss",
            $name,
            $description
        );

        if ($stmt->execute()) {

            header("Location: categories.php");
            exit;

        } else {

            $error = "Unable to create category.";

        }

        $stmt->close();
    }

    $check_stmt->close();
}

}

require_once "../includes/header.php";

?>

<section>
<h1>Add Category</h1>

<br>

<?php if (!empty($error)): ?>

    <div class="error-message">
        <?php echo htmlspecialchars($error); ?>
    </div>

<?php endif; ?>


<form method="POST" class="admin-form">

    <div class="form-group">

        <label for="name">
            Category Name *
        </label>

        <input
            type="text"
            id="name"
            name="name"
            required
        >

    </div>


    <div class="form-group">

        <label for="description">
            Description
        </label>

        <textarea
            id="description"
            name="description"
            rows="5"
        ></textarea>

    </div>


    <button type="submit" class="btn-primary">
        Add Category
    </button>

</form>
</section> <?php require_once "../includes/footer.php"; ?>