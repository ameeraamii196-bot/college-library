<?php

require_once "../includes/auth.php";
requireRole("librarian");

require_once "../config/database.php";

$error = "";
$current_year = (int) date("Y");
$form_values = [
    "title" => "",
    "author" => "",
    "isbn" => "",
    "category_id" => "",
    "summary" => "",
    "publisher" => "",
    "publication_year" => "",
];

$category_result = $conn->query(
    "SELECT id, name FROM categories ORDER BY name ASC"
);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    foreach ($form_values as $key => $value) {
        $form_values[$key] = trim($_POST[$key] ?? "");
    }

    $title = $form_values["title"];
    $author = $form_values["author"];
    $isbn = $form_values["isbn"];
    $category_id = $form_values["category_id"] !== ""
        ? (int) $form_values["category_id"]
        : null;
    $summary = $form_values["summary"];
    $publisher = $form_values["publisher"];
    $year_input = $form_values["publication_year"];
    $publication_year = $year_input !== "" ? (int) $year_input : null;
    $cover_image = null;
    $upload_path = null;

    if ($title === "" || $author === "") {
        $error = "Book title and author are required.";
    } elseif (
        $year_input !== "" &&
        (!ctype_digit($year_input) || $publication_year < 1000 || $publication_year > $current_year)
    ) {
        $error = "Enter a valid publication year.";
    }

    if ($error === "" && $isbn !== "") {

        $stmt_isbn = $conn->prepare(
            "SELECT id FROM books WHERE isbn = ? LIMIT 1"
        );
        $stmt_isbn->bind_param("s", $isbn);
        $stmt_isbn->execute();

        if ($stmt_isbn->get_result()->num_rows > 0) {
            $error = "A book with this ISBN already exists.";
        }

        $stmt_isbn->close();
    }

    if (
        $error === "" &&
        isset($_FILES["cover_image"]) &&
        $_FILES["cover_image"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {

        $image = $_FILES["cover_image"];
        $max_size = 2 * 1024 * 1024;

        if ($image["error"] !== UPLOAD_ERR_OK) {
            $error = "There was a problem uploading the cover image.";
        } elseif ($image["size"] > $max_size) {
            $error = "Cover image must be smaller than 2 MB.";
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = $finfo
                ? finfo_file($finfo, $image["tmp_name"])
                : false;

            if ($finfo) {
                finfo_close($finfo);
            }

            $extension_map = [
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/webp" => "webp",
            ];

            if (!isset($extension_map[$mime_type])) {
                $error = "Only JPG, PNG and WEBP images are allowed.";
            } else {
                $upload_directory = __DIR__ . "/../assets/images/";

                if (
                    !is_dir($upload_directory) &&
                    !mkdir($upload_directory, 0755, true) &&
                    !is_dir($upload_directory)
                ) {
                    $error = "Unable to access the cover image folder.";
                } else {
                    $filename = "book_"
                        . bin2hex(random_bytes(16))
                        . "."
                        . $extension_map[$mime_type];

                    $upload_path = $upload_directory . $filename;
                    $cover_image = "assets/images/" . $filename;

                    if (!move_uploaded_file($image["tmp_name"], $upload_path)) {
                        $error = "Unable to save the cover image.";
                        $cover_image = null;
                        $upload_path = null;
                    }
                }
            }
        }
    }

    if ($error === "") {

        $isbn_value = $isbn !== "" ? $isbn : null;
        $summary_value = $summary !== "" ? $summary : null;
        $publisher_value = $publisher !== "" ? $publisher : null;

        try {
            $stmt = $conn->prepare(
                "INSERT INTO books
                    (title, author, isbn, category_id, summary, publisher,
                     publication_year, cover_image)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "sssissis",
                $title,
                $author,
                $isbn_value,
                $category_id,
                $summary_value,
                $publisher_value,
                $publication_year,
                $cover_image
            );

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: books.php?success=book_added");
                exit;
            }

            $stmt->close();
            $error = "Unable to add the book. Please check the details and try again.";
        } catch (mysqli_sql_exception $exception) {
            $error = "Unable to add the book. Please check the ISBN and try again.";
        }

        if ($upload_path !== null && is_file($upload_path)) {
            unlink($upload_path);
        }
    }
}

require_once "../includes/header.php";

?>

<div class="container">

    <div class="page-header">
        <div>
            <p class="dashboard-eyebrow">Librarian</p>
            <h1>Add New Book</h1>
            <p>Add a new title to the library catalogue.</p>
        </div>

        <a href="books.php" class="btn-secondary">Back to Books</a>
    </div>

    <?php if ($error !== ""): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="form-card book-form-card">
        <form method="POST" enctype="multipart/form-data">

            <section class="form-section">
                <h2>Book Information</h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="title">Book Title *</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($form_values["title"]); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="author">Author *</label>
                        <input type="text" id="author" name="author" value="<?php echo htmlspecialchars($form_values["author"]); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="isbn">ISBN</label>
                        <input type="text" id="isbn" name="isbn" value="<?php echo htmlspecialchars($form_values["isbn"]); ?>">
                    </div>

                    <div class="form-group">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id">
                            <option value="">Select Category</option>
                            <?php while ($category = $category_result->fetch_assoc()): ?>
                                <option
                                    value="<?php echo (int) $category["id"]; ?>"
                                    <?php echo (string) $category["id"] === $form_values["category_id"] ? "selected" : ""; ?>
                                >
                                    <?php echo htmlspecialchars($category["name"]); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="publisher">Publisher</label>
                        <input type="text" id="publisher" name="publisher" value="<?php echo htmlspecialchars($form_values["publisher"]); ?>">
                    </div>

                    <div class="form-group">
                        <label for="publication_year">Publication Year</label>
                        <input
                            type="number"
                            id="publication_year"
                            name="publication_year"
                            min="1000"
                            max="<?php echo $current_year; ?>"
                            value="<?php echo htmlspecialchars($form_values["publication_year"]); ?>"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="summary">Book Summary</label>
                    <textarea id="summary" name="summary" rows="6"><?php echo htmlspecialchars($form_values["summary"]); ?></textarea>
                </div>
            </section>

            <section class="form-section">
                <h2>Book Cover</h2>

                <div class="cover-upload-box">
                    <div class="cover-upload-icon" aria-hidden="true">&#128444;</div>
                    <h3>Upload a cover image</h3>
                    <p>JPG, PNG or WEBP. Maximum size 2 MB.</p>

                    <label class="cover-file-label" for="cover_image">Choose Image</label>
                    <input
                        type="file"
                        id="cover_image"
                        name="cover_image"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    >

                    <div id="image-preview" class="image-preview" aria-live="polite"></div>
                </div>
            </section>

            <div class="form-actions">
                <a href="books.php" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Add Book</button>
            </div>

        </form>
    </div>

</div>

<script>
const coverInput = document.getElementById("cover_image");
const imagePreview = document.getElementById("image-preview");
const maxImageSize = 2 * 1024 * 1024;
const allowedImageTypes = ["image/jpeg", "image/png", "image/webp"];

coverInput.addEventListener("change", function () {
    imagePreview.replaceChildren();

    const file = this.files[0];
    if (!file) return;

    if (file.size > maxImageSize || !allowedImageTypes.includes(file.type)) {
        alert(file.size > maxImageSize
            ? "Cover image must be smaller than 2 MB."
            : "Only JPG, PNG and WEBP images are allowed.");
        this.value = "";
        return;
    }

    const preview = document.createElement("img");
    preview.src = URL.createObjectURL(file);
    preview.alt = "Selected book cover preview";
    preview.className = "cover-preview-image";
    preview.onload = () => URL.revokeObjectURL(preview.src);
    imagePreview.appendChild(preview);
});
</script>

<?php include "../includes/footer.php"; ?>