<?php

require_once "../includes/auth.php";
requireRole("librarian");

require_once "../config/database.php";

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: books.php");
    exit;
}

$book_id = (int) $_GET["id"];
$error = "";
$success = "";
$current_year = (int) date("Y");

$stmt_book = $conn->prepare("SELECT * FROM books WHERE id = ? LIMIT 1");
$stmt_book->bind_param("i", $book_id);
$stmt_book->execute();
$result_book = $stmt_book->get_result();

if ($result_book->num_rows !== 1) {
    $stmt_book->close();
    header("Location: books.php");
    exit;
}

$book = $result_book->fetch_assoc();
$stmt_book->close();

$category_result = $conn->query(
    "SELECT id, name FROM categories ORDER BY name ASC"
);

$form_values = [
    "title" => $book["title"],
    "author" => $book["author"],
    "isbn" => $book["isbn"] ?? "",
    "category_id" => $book["category_id"] ?? "",
    "summary" => $book["summary"] ?? "",
    "publisher" => $book["publisher"] ?? "",
    "publication_year" => $book["publication_year"] ?? "",
];

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
    $cover_image = $book["cover_image"] ?? null;
    $new_upload_path = null;

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
            "SELECT id FROM books WHERE isbn = ? AND id <> ? LIMIT 1"
        );
        $stmt_isbn->bind_param("si", $isbn, $book_id);
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

                    $new_upload_path = $upload_directory . $filename;
                    $cover_image = "assets/images/" . $filename;

                    if (!move_uploaded_file($image["tmp_name"], $new_upload_path)) {
                        $error = "Unable to save the cover image.";
                        $new_upload_path = null;
                        $cover_image = $book["cover_image"] ?? null;
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
            $update_stmt = $conn->prepare(
                "UPDATE books
                 SET title = ?,
                     author = ?,
                     isbn = ?,
                     category_id = ?,
                     summary = ?,
                     publisher = ?,
                     publication_year = ?,
                     cover_image = ?
                 WHERE id = ?"
            );

            $update_stmt->bind_param(
                "sssissisi",
                $title,
                $author,
                $isbn_value,
                $category_id,
                $summary_value,
                $publisher_value,
                $publication_year,
                $cover_image,
                $book_id
            );

            if ($update_stmt->execute()) {
                $update_stmt->close();

                $old_cover = $book["cover_image"] ?? "";
                $book["title"] = $title;
                $book["author"] = $author;
                $book["isbn"] = $isbn_value;
                $book["category_id"] = $category_id;
                $book["summary"] = $summary_value;
                $book["publisher"] = $publisher_value;
                $book["publication_year"] = $publication_year;
                $book["cover_image"] = $cover_image;
                $success = "Book updated successfully.";

                if (
                    $new_upload_path !== null &&
                    preg_match('/^assets\/images\/book_[a-f0-9]{32}\.(jpg|png|webp)$/', $old_cover)
                ) {
                    $stmt_reference = $conn->prepare(
                        "SELECT id FROM books WHERE cover_image = ? AND id <> ? LIMIT 1"
                    );
                    $stmt_reference->bind_param("si", $old_cover, $book_id);
                    $stmt_reference->execute();
                    $old_cover_is_shared = $stmt_reference->get_result()->num_rows > 0;
                    $stmt_reference->close();

                    $old_cover_path = __DIR__ . "/../" . $old_cover;
                    if (!$old_cover_is_shared && is_file($old_cover_path)) {
                        unlink($old_cover_path);
                    }
                }
            } else {
                $update_stmt->close();
                $error = "Unable to update the book. Please try again.";
            }
        } catch (mysqli_sql_exception $exception) {
            $error = "Unable to update the book. Check the ISBN and try again.";
        }

        if ($error !== "" && $new_upload_path !== null && is_file($new_upload_path)) {
            unlink($new_upload_path);
        }
    }
}

$cover_src = $book["cover_image"] ?? "";
if (
    $cover_src !== "" &&
    !preg_match('/^(https?:)?\/\//i', $cover_src) &&
    $cover_src[0] !== "/"
) {
    $cover_src = "../" . $cover_src;
}

require_once "../includes/header.php";

?>

<div class="container">

    <div class="page-header">
        <div>
            <p class="dashboard-eyebrow">Librarian</p>
            <h1>Edit Book</h1>
            <p>Update catalogue details and book cover.</p>
        </div>

        <a href="books.php" class="btn-secondary">Back to Books</a>
    </div>

    <?php if ($error !== ""): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <div class="success-message">
            <?php echo htmlspecialchars($success); ?>
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
                                    <?php echo (string) $category["id"] === (string) $form_values["category_id"] ? "selected" : ""; ?>
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
                            value="<?php echo htmlspecialchars((string) $form_values["publication_year"]); ?>"
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

                <div class="cover-upload-box edit-cover-upload-box">
                    <?php if ($cover_src !== ""): ?>
                        <div class="current-cover">
                            <p>Current cover</p>
                            <img
                                src="<?php echo htmlspecialchars($cover_src); ?>"
                                alt="Current cover for <?php echo htmlspecialchars($book["title"]); ?>"
                                class="cover-preview-image"
                            >
                        </div>
                    <?php else: ?>
                        <div class="current-cover no-cover">
                            <p>No cover image is currently set.</p>
                        </div>
                    <?php endif; ?>

                    <h3>Replace cover image</h3>
                    <p>JPG, PNG or WEBP. Maximum size 2 MB.</p>

                    <label class="cover-file-label" for="cover_image">Choose Replacement Image</label>
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
                <button type="submit" class="btn-primary">Save Changes</button>
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
    preview.alt = "Replacement book cover preview";
    preview.className = "cover-preview-image";
    preview.onload = () => URL.revokeObjectURL(preview.src);
    imagePreview.appendChild(preview);
});
</script>

<?php include "../includes/footer.php"; ?>