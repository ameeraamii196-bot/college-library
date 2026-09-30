<?php

require_once "config/database.php";

$search = isset($_GET["search"]) ? trim($_GET["search"]) : "";

$category_id = isset($_GET["category"])
    ? (int) $_GET["category"]
    : 0;

$availability = isset($_GET["availability"])
    ? trim($_GET["availability"])
    : "";

$sort = isset($_GET["sort"])
    ? trim($_GET["sort"])
    : "title";

$today = date("Y-m-d");


/*
|--------------------------------------------------------------------------
| Get Categories
|--------------------------------------------------------------------------
*/

$sql_categories = "SELECT id, name
                   FROM categories
                   ORDER BY name ASC";

$categories_result = $conn->query($sql_categories);


/*
|--------------------------------------------------------------------------
| Get Books
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            b.id,
            b.title,
            b.author,
            b.summary,
            b.cover_image,

            c.name AS category_name,

            COUNT(bc.id) AS total_copies,

            SUM(
                CASE
                    WHEN bc.status = 'available'
                    THEN 1
                    ELSE 0
                END
            ) AS available_copies,

            SUM(
                CASE
                    WHEN bc.status = 'issued'
                    THEN 1
                    ELSE 0
                END
            ) AS issued_copies,

            SUM(
                CASE
                    WHEN bc.status = 'reserved'
                    THEN 1
                    ELSE 0
                END
            ) AS reserved_copies,

            SUM(
                CASE
                    WHEN bc.status = 'issued'
                    AND EXISTS (
                        SELECT 1
                        FROM loans l
                        WHERE l.copy_id = bc.id
                        AND l.due_date = ?
                        AND l.status IN ('issued', 'overdue')
                    )
                    THEN 1
                    ELSE 0
                END
            ) AS returning_today

        FROM books b

        LEFT JOIN categories c
            ON b.category_id = c.id

        LEFT JOIN book_copies bc
            ON b.id = bc.book_id

        WHERE 1=1";

$params = [
    $today
];

$types = "s";


/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $sql .= " AND (
                b.title LIKE ?
                OR b.author LIKE ?
              )";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "ss";
}


/*
|--------------------------------------------------------------------------
| Category Filter
|--------------------------------------------------------------------------
*/

if ($category_id > 0) {

    $sql .= " AND b.category_id = ?";

    $params[] = $category_id;

    $types .= "i";
}


$sql .= " GROUP BY b.id";


/*
|--------------------------------------------------------------------------
| Availability Filter
|--------------------------------------------------------------------------
*/

if ($availability === "available") {

    $sql .= " HAVING available_copies > 0";

} elseif ($availability === "returning" || $availability === "returning_today") {

    $sql .= " HAVING returning_today > 0";

} elseif ($availability === "reserved") {

    $sql .= " HAVING reserved_copies > 0";

} elseif ($availability === "unavailable") {

    $sql .= " HAVING available_copies = 0
              AND returning_today = 0
              AND reserved_copies = 0";
}


/*
|--------------------------------------------------------------------------
| Sorting
|--------------------------------------------------------------------------
*/

switch ($sort) {

    case "newest":
        $sql .= " ORDER BY b.created_at DESC";
        break;

    case "availability":
        $sql .= " ORDER BY available_copies DESC, b.title ASC";
        break;

    case "title":
    default:
        $sql .= " ORDER BY b.title ASC";
        break;
}


/*
|--------------------------------------------------------------------------
| Prepare Statement
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();
$books = $result;


require_once "includes/header.php";

?>

<div class="catalogue-page">

    <section class="catalogue-header">
        <div class="catalogue-header-content">
            <div>
                <p class="catalogue-eyebrow">College Library</p>
                <h1>Explore the Library</h1>
                <p class="catalogue-description">
                    Discover books, check availability, and find your next read.
                </p>
            </div>
            <div class="catalogue-icon" aria-hidden="true">&#128218;</div>
        </div>
    </section>

    <section class="catalogue-toolbar" aria-label="Search and filter catalogue">
        <form method="GET" action="books.php" class="catalogue-filter-form">
            <div class="catalogue-search">
                <label class="sr-only" for="search">Search books or authors</label>
                <span class="catalogue-search-icon" aria-hidden="true">&#128269;</span>
                <input
                    type="search"
                    id="search"
                    name="search"
                    placeholder="Search books or authors..."
                    value="<?php echo htmlspecialchars($search); ?>"
                >
            </div>

            <div class="catalogue-filter-group">
                <label for="category">Category</label>
                <select name="category" id="category">
                    <option value="0">All Categories</option>
                    <?php while ($category = $categories_result->fetch_assoc()): ?>
                        <option
                            value="<?php echo (int) $category["id"]; ?>"
                            <?php echo $category_id === (int) $category["id"] ? "selected" : ""; ?>
                        >
                            <?php echo htmlspecialchars($category["name"]); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="catalogue-filter-group">
                <label for="availability">Availability</label>
                <select name="availability" id="availability">
                    <option value="">All</option>
                    <option value="available" <?php echo $availability === "available" ? "selected" : ""; ?>>Available</option>
                    <option value="returning" <?php echo $availability === "returning" || $availability === "returning_today" ? "selected" : ""; ?>>Returning Today</option>
                    <option value="reserved" <?php echo $availability === "reserved" ? "selected" : ""; ?>>Reserved</option>
                    <option value="unavailable" <?php echo $availability === "unavailable" ? "selected" : ""; ?>>Unavailable</option>
                </select>
            </div>

            <div class="catalogue-filter-group">
                <label for="sort">Sort By</label>
                <select name="sort" id="sort">
                    <option value="title" <?php echo $sort === "title" ? "selected" : ""; ?>>Title A-Z</option>
                    <option value="newest" <?php echo $sort === "newest" ? "selected" : ""; ?>>Recently Added</option>
                    <option value="availability" <?php echo $sort === "availability" ? "selected" : ""; ?>>Availability</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary catalogue-filter-button">Apply</button>

            <?php if ($search !== "" || $category_id > 0 || $availability !== "" || $sort !== "title"): ?>
                <a href="books.php" class="catalogue-clear-button">Clear</a>
            <?php endif; ?>
        </form>
    </section>

    <section class="catalogue-results">
        <div class="catalogue-results-header">
            <div>
                <p class="catalogue-results-label">Library Collection</p>
                <h2>
                    <?php echo (int) $result->num_rows; ?>
                    <?php echo $result->num_rows === 1 ? "Book" : "Books"; ?>
                </h2>
            </div>

            <?php if ($search !== "" || $category_id > 0 || $availability !== ""): ?>
                <p class="catalogue-active-filter">
                    <?php if ($search !== ""): ?>
                        Searching for <strong>&ldquo;<?php echo htmlspecialchars($search); ?>&rdquo;</strong>
                    <?php elseif ($availability !== ""): ?>
                        Showing <strong><?php echo htmlspecialchars(ucfirst(str_replace("_", " ", $availability))); ?></strong>
                    <?php else: ?>
                        Filtered catalogue results
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div class="catalogue-book-grid">
                <?php while ($book = $result->fetch_assoc()): ?>
                    <?php
                    $available = (int) $book["available_copies"];
                    $total = (int) $book["total_copies"];
                    $returning = (int) $book["returning_today"];
                    $reserved = (int) $book["reserved_copies"];
                    $cover_src = trim((string) ($book["cover_image"] ?? ""));

                    if (
                        $cover_src !== "" &&
                        !preg_match("~^(https?:)?//~i", $cover_src) &&
                        strpos($cover_src, "/") !== 0
                    ) {
                        $cover_src = "/college-library/" . $cover_src;
                    }

                    $summary = (string) ($book["summary"] ?? "");
                    if (strlen($summary) > 120) {
                        $summary = substr($summary, 0, 117) . "...";
                    }
                    ?>

                    <article class="catalogue-book-card">
                        <a
                            href="book-details.php?id=<?php echo (int) $book["id"]; ?>"
                            class="catalogue-book-cover"
                            aria-label="View <?php echo htmlspecialchars($book["title"]); ?> details"
                        >
                            <?php if ($cover_src !== ""): ?>
                                <img
                                    src="<?php echo htmlspecialchars($cover_src); ?>"
                                    alt="Cover of <?php echo htmlspecialchars($book["title"]); ?>"
                                    loading="lazy"
                                >
                            <?php else: ?>
                                <span class="catalogue-cover-placeholder">
                                    <span aria-hidden="true">&#128218;</span>
                                    <small>No cover</small>
                                </span>
                            <?php endif; ?>

                            <?php if ($available > 0): ?>
                                <span class="catalogue-status available">Available</span>
                            <?php elseif ($returning > 0): ?>
                                <span class="catalogue-status returning">Returning Today</span>
                            <?php elseif ($reserved > 0): ?>
                                <span class="catalogue-status reserved">Reserved</span>
                            <?php else: ?>
                                <span class="catalogue-status unavailable">Unavailable</span>
                            <?php endif; ?>
                        </a>

                        <div class="catalogue-book-info">
                            <?php if (!empty($book["category_name"])): ?>
                                <p class="catalogue-book-category">
                                    <?php echo htmlspecialchars($book["category_name"]); ?>
                                </p>
                            <?php endif; ?>

                            <h3>
                                <a href="book-details.php?id=<?php echo (int) $book["id"]; ?>">
                                    <?php echo htmlspecialchars($book["title"]); ?>
                                </a>
                            </h3>

                            <p class="catalogue-book-author">
                                By <?php echo htmlspecialchars($book["author"]); ?>
                            </p>

                            <?php if ($summary !== ""): ?>
                                <p class="catalogue-book-summary">
                                    <?php echo htmlspecialchars($summary); ?>
                                </p>
                            <?php endif; ?>

                            <div class="catalogue-book-meta">
                                <span>
                                    <?php echo $total; ?>
                                    <?php echo $total === 1 ? "copy" : "copies"; ?>
                                </span>

                                <?php if ($available > 0): ?>
                                    <span class="meta-available"><?php echo $available; ?> available</span>
                                <?php elseif ($returning > 0): ?>
                                    <span class="meta-returning"><?php echo $returning; ?> returning today</span>
                                <?php endif; ?>
                            </div>

                            <a href="book-details.php?id=<?php echo (int) $book["id"]; ?>" class="catalogue-view-button">
                                View Details <span aria-hidden="true">&rarr;</span>
                            </a>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="catalogue-empty">
                <div class="catalogue-empty-icon" aria-hidden="true">&#128269;</div>
                <h2>No books found</h2>
                <p>We couldn't find any titles matching the current search or filters.</p>
                <a href="books.php" class="btn btn-primary">View All Books</a>
            </div>
        <?php endif; ?>
    </section>
</div>


<?php

$stmt->close();

require_once "includes/footer.php";

?>