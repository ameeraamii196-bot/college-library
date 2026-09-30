<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "config/database.php";

$is_logged_in = isset($_SESSION["user_id"]);
$user_id = $is_logged_in ? (int) $_SESSION["user_id"] : 0;
$user_role = $_SESSION["role"] ?? "";
$recommended_books = [];
$has_personalized_recommendations = false;

if ($user_role === "admin") {
    $dashboard_url = "/college-library/admin/dashboard.php";
} elseif ($user_role === "librarian") {
    $dashboard_url = "/college-library/librarian/dashboard.php";
} else {
    $dashboard_url = "/college-library/dashboard.php";
}

$inventory_join = "LEFT JOIN (
                       SELECT
                           book_id,
                           COUNT(*) AS available_copies
                       FROM book_copies
                       WHERE status = 'available'
                       GROUP BY book_id
                   ) inventory
                       ON inventory.book_id = b.id";

if ($user_role === "student" || $user_role === "teacher") {

    $sql_recommended = "SELECT
                            b.id,
                            b.title,
                            b.author,
                            b.cover_image,
                            b.created_at,
                            c.name AS category_name,
                            COALESCE(inventory.available_copies, 0) AS available_copies,
                            preferences.preference_score
                        FROM books b
                        LEFT JOIN categories c
                            ON c.id = b.category_id
                        $inventory_join
                        INNER JOIN (
                            SELECT category_id, MAX(preference_score) AS preference_score
                            FROM user_preferences
                            WHERE user_id = ?
                            GROUP BY category_id
                        ) preferences
                            ON preferences.category_id = b.category_id
                        ORDER BY preferences.preference_score DESC,
                                 b.created_at DESC
                        LIMIT 6";

    $stmt_recommended = $conn->prepare($sql_recommended);
    $stmt_recommended->bind_param("i", $user_id);
    $stmt_recommended->execute();

    $result_recommended = $stmt_recommended->get_result();

    while ($row = $result_recommended->fetch_assoc()) {
        $recommended_books[] = $row;
    }

    $stmt_recommended->close();
    $has_personalized_recommendations = !empty($recommended_books);
}

if (empty($recommended_books)) {

    $sql_recent = "SELECT
                       b.id,
                       b.title,
                       b.author,
                       b.cover_image,
                       b.created_at,
                       c.name AS category_name,
                       COALESCE(inventory.available_copies, 0) AS available_copies
                   FROM books b
                   LEFT JOIN categories c
                       ON c.id = b.category_id
                   $inventory_join
                   ORDER BY b.created_at DESC
                   LIMIT 6";

    $result_recent = $conn->query($sql_recent);

    while ($row = $result_recent->fetch_assoc()) {
        $recommended_books[] = $row;
    }
}

$sql_categories = "SELECT
                       c.id,
                       c.name,
                       COUNT(b.id) AS book_count
                   FROM categories c
                   LEFT JOIN books b
                       ON b.category_id = c.id
                   GROUP BY c.id
                   ORDER BY c.name ASC";

$result_categories = $conn->query($sql_categories);

$total_books = (int) $conn->query(
    "SELECT COUNT(*) AS total FROM books"
)->fetch_assoc()["total"];

$total_categories = (int) $conn->query(
    "SELECT COUNT(*) AS total FROM categories"
)->fetch_assoc()["total"];

function homeCoverUrl($cover_image)
{
    $cover_image = trim((string) $cover_image);

    if ($cover_image === "") {
        return "";
    }

    if (preg_match("~^https?://~i", $cover_image)) {
        return $cover_image;
    }

    if (strpos($cover_image, "/college-library/") === 0) {
        return $cover_image;
    }

    return "/college-library/" . ltrim($cover_image, "/");
}

include "includes/header.php";

?>

<section class="home-hero">
    <div class="container">
        <div class="hero-content">
            <div class="hero-text">
                <p class="hero-eyebrow">Knowledge builds brighter futures</p>

                <h1>
                    Welcome to Your
                    <span>College Library</span>
                </h1>

                <p class="hero-description">
                    Discover thousands of books, manage your borrowings,
                    and access knowledge whenever you need it.
                </p>

                <div class="hero-actions">
                    <a href="books.php" class="btn btn-primary">Browse Books</a>

                    <?php if ($is_logged_in): ?>
                        <a href="<?php echo htmlspecialchars($dashboard_url); ?>" class="btn btn-secondary">
                            My Account
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-secondary">Login</a>
                    <?php endif; ?>
                </div>

                <form action="books.php" method="GET" class="hero-search">
                    <label class="sr-only" for="home-search">Search books and authors</label>
                    <input
                        id="home-search"
                        type="search"
                        name="search"
                        placeholder="Search books or authors"
                    >
                    <button type="submit" class="btn-primary">Search</button>
                </form>
            </div>
        </div>
    </div>
</section>

<section class="home-stats" aria-label="Library highlights">
    <div class="container home-stat-grid">
        <div class="home-stat-card">
            <span class="home-stat-icon" aria-hidden="true">&#128218;</span>
            <div><strong><?php echo $total_books; ?></strong><span>Book titles</span></div>
        </div>
        <div class="home-stat-card">
            <span class="home-stat-icon" aria-hidden="true">&#128193;</span>
            <div><strong><?php echo $total_categories; ?></strong><span>Categories</span></div>
        </div>
        <div class="home-stat-card">
            <span class="home-stat-icon" aria-hidden="true">&#128269;</span>
            <div><strong>Discover</strong><span>Search the catalogue</span></div>
        </div>
        <div class="home-stat-card">
            <span class="home-stat-icon" aria-hidden="true">&#128278;</span>
            <div><strong>Request</strong><span>Reserve books</span></div>
        </div>
    </div>
</section>

<section class="home-section recommendations-section">
    <div class="container">
        <div class="section-heading-row">
            <div>
                <p class="section-eyebrow">
                    <?php echo $has_personalized_recommendations ? "Picked for you" : "Explore the collection"; ?>
                </p>
                <h2>
                    <?php echo $has_personalized_recommendations ? "Recommended for You" : "Featured Books"; ?>
                </h2>
                <p>
                    <?php echo $has_personalized_recommendations
                        ? "Books selected from your preferred categories."
                        : "Discover titles from across the college library."; ?>
                </p>
            </div>
            <a href="books.php" class="section-link">View All Books <span aria-hidden="true">&rarr;</span></a>
        </div>

        <?php if (!empty($recommended_books)): ?>
            <div class="home-book-grid">
                <?php foreach ($recommended_books as $book): ?>
                    <a href="book-details.php?id=<?php echo (int) $book["id"]; ?>" class="home-book-card">
                        <div class="home-book-cover">
                            <?php $cover_url = homeCoverUrl($book["cover_image"] ?? ""); ?>
                            <?php if ($cover_url !== ""): ?>
                                <img
                                    src="<?php echo htmlspecialchars($cover_url); ?>"
                                    alt="Cover of <?php echo htmlspecialchars($book["title"]); ?>"
                                    loading="lazy"
                                >
                            <?php else: ?>
                                <span class="book-cover-placeholder" aria-hidden="true">&#128218;</span>
                            <?php endif; ?>

                            <?php if ((int) $book["available_copies"] > 0): ?>
                                <span class="book-availability available">Available</span>
                            <?php else: ?>
                                <span class="book-availability unavailable">Unavailable</span>
                            <?php endif; ?>
                        </div>

                        <div class="home-book-info">
                            <?php if (!empty($book["category_name"])): ?>
                                <span class="book-category"><?php echo htmlspecialchars($book["category_name"]); ?></span>
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($book["title"]); ?></h3>
                            <p><?php echo htmlspecialchars($book["author"]); ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon" aria-hidden="true">&#128218;</div>
                <h3>No books yet</h3>
                <p>Books added to the catalogue will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="home-section categories-section">
    <div class="container">
        <div class="section-heading-row">
            <div>
                <p class="section-eyebrow">Browse by interest</p>
                <h2>Explore Categories</h2>
                <p>Find books by subject and area of study.</p>
            </div>
            <a href="books.php" class="section-link">Browse Catalogue <span aria-hidden="true">&rarr;</span></a>
        </div>

        <div class="category-scroll">
            <?php if ($result_categories && $result_categories->num_rows > 0): ?>
                <?php while ($category = $result_categories->fetch_assoc()): ?>
                    <a href="books.php?category=<?php echo (int) $category["id"]; ?>" class="category-card">
                        <span class="category-icon" aria-hidden="true">&#128214;</span>
                        <span class="category-copy">
                            <strong><?php echo htmlspecialchars($category["name"]); ?></strong>
                            <small>
                                <?php echo (int) $category["book_count"]; ?>
                                <?php echo (int) $category["book_count"] === 1 ? "book" : "books"; ?>
                            </small>
                        </span>
                        <span class="category-arrow" aria-hidden="true">&rarr;</span>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No categories yet</h3>
                    <p>Categories will appear here when the librarian adds them.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="home-section how-section">
    <div class="container">
        <div class="section-heading-centered">
            <p class="section-eyebrow">Simple and convenient</p>
            <h2>Your Library, Simplified</h2>
            <p>Find, request, and manage your books in one place.</p>
        </div>

        <div class="how-grid">
            <article class="how-card">
                <span class="how-number">01</span>
                <span class="how-icon" aria-hidden="true">&#128269;</span>
                <h3>Discover</h3>
                <p>Search the catalogue by title, author, or category.</p>
            </article>
            <article class="how-card">
                <span class="how-number">02</span>
                <span class="how-icon" aria-hidden="true">&#128278;</span>
                <h3>Request</h3>
                <p>Request a reservation and follow its status from your account.</p>
            </article>
            <article class="how-card">
                <span class="how-number">03</span>
                <span class="how-icon" aria-hidden="true">&#128218;</span>
                <h3>Borrow</h3>
                <p>Keep track of issued books and due dates on your dashboard.</p>
            </article>
        </div>
    </div>
</section>

<section class="home-cta">
    <div class="container home-cta-content">
        <div>
            <p class="section-eyebrow">Continue exploring</p>
            <h2>Find something worth reading.</h2>
            <p>Search the collection and discover your next book.</p>
        </div>
        <a href="books.php" class="btn btn-primary">Explore Books <span aria-hidden="true">&rarr;</span></a>
    </div>
</section>

<?php include "includes/footer.php"; ?>