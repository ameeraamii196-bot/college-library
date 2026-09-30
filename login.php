<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "config/database.php";

if (isset($_SESSION["user_id"])) {
    if ($_SESSION["role"] === "admin") {
        header("Location: admin/dashboard.php");
    } elseif ($_SESSION["role"] === "librarian") {
        header("Location: librarian/dashboard.php");
    } else {
        header("Location: dashboard.php");
    }

    exit;
}

$error = "";
$username = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {
        $error = "Please enter both username and password.";
    } else {
        $sql = "SELECT
                    id,
                    username,
                    password,
                    full_name,
                    role,
                    status
                FROM users
                WHERE username = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if ($user["status"] !== "active") {
                $error = "Your account is inactive. Please contact the administrator.";
            } elseif (password_verify($password, $user["password"])) {
                session_regenerate_id(true);

                $_SESSION["user_id"] = (int) $user["id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["role"] = $user["role"];

                $stmt->close();

                if ($user["role"] === "admin") {
                    header("Location: admin/dashboard.php");
                } elseif ($user["role"] === "librarian") {
                    header("Location: librarian/dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }

                exit;
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }

        $stmt->close();
    }
}

include "includes/header.php";

?>

<section class="login-page">

    <div class="login-container">

        <section class="login-intro">

            <a href="index.php" class="login-brand">
                <img
                    src="/college-library/assets/images/NCERC.jpg"
                    alt=""
                    class="login-brand-logo"
                >
                <span>NCERC Library</span>
            </a>

            <div class="login-intro-content">
                <p class="login-eyebrow">Your digital library</p>
                <h1>Welcome<br><span>back.</span></h1>
                <p>
                    Sign in to explore books, manage your borrowing activity,
                    and keep track of library reservations.
                </p>
            </div>

            <div class="login-features">
                <div class="login-feature">
                    <span aria-hidden="true">&#128269;</span>
                    <div><strong>Discover</strong><p>Explore the library catalogue.</p></div>
                </div>
                <div class="login-feature">
                    <span aria-hidden="true">&#128278;</span>
                    <div><strong>Reserve</strong><p>Request books through your account.</p></div>
                </div>
                <div class="login-feature">
                    <span aria-hidden="true">&#128218;</span>
                    <div><strong>Manage</strong><p>Track issued books and returns.</p></div>
                </div>
            </div>

        </section>

        <section class="login-card" aria-labelledby="login-title">

            <div class="login-card-header">
                <img
                    src="/college-library/assets/images/NCERC.jpg"
                    alt=""
                    class="mobile-login-icon"
                >
                <h2 id="login-title">Sign in</h2>
                <p>Enter your library account details.</p>
            </div>

            <?php if ($error !== ""): ?>
                <div class="alert alert-error login-alert" role="alert">
                    <span class="alert-icon" aria-hidden="true">&#9888;</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="login-form">

                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-wrapper">
                        <span class="input-icon" aria-hidden="true">&#128100;</span>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            value="<?php echo htmlspecialchars($username); ?>"
                            placeholder="Enter your username"
                            autocomplete="username"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon" aria-hidden="true">&#128274;</span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >
                        <button
                            type="button"
                            class="password-toggle"
                            id="passwordToggle"
                            aria-label="Show password"
                            aria-pressed="false"
                        >&#128065;</button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary login-submit">
                    Sign In <span aria-hidden="true">&rarr;</span>
                </button>

            </form>

            <div class="login-note">
                <span aria-hidden="true">&#128274;</span>
                <p>Account creation is managed by the library administrator.</p>
            </div>

        </section>

    </div>

</section>

<?php include "includes/footer.php"; ?>