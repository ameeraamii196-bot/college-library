<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        NCERC Library Management System
    </title>

    <link
        rel="stylesheet"
        href="/college-library/assets/css/style.css?v=<?php echo filemtime(__DIR__ . "/../assets/css/style.css"); ?>"
    >

</head>


<body>


<header class="site-header">

    <div class="container header-content">


        <a
            href="/college-library/index.php"
            class="logo"
            aria-label="NCERC Library home"
        >
            <img
                src="/college-library/assets/images/NCERC.jpg"
                alt=""
                class="logo-image"
            >
            <span>NCERC Library</span>
        </a>


        <nav class="main-nav">

            <a href="/college-library/index.php">
                Home
            </a>

            <a href="/college-library/books.php">
                Books
            </a>


            <?php if (isset($_SESSION["user_id"])): ?>

                <?php if ($_SESSION["role"] === "admin"): ?>

                    <a href="/college-library/admin/dashboard.php">
                        Dashboard
                    </a>

                <?php elseif ($_SESSION["role"] === "librarian"): ?>

                    <a href="/college-library/librarian/dashboard.php">
                        Dashboard
                    </a>

                <?php else: ?>

                    <a href="/college-library/dashboard.php">
                        Dashboard
                    </a>

                <?php endif; ?>


                <?php if (
                    $_SESSION["role"] === "student" ||
                    $_SESSION["role"] === "teacher"
                ): ?>

                    <a href="/college-library/my-reservations.php">
                        My Reservations
                    </a>

                <?php endif; ?>


                <a href="/college-library/logout.php">
                    Logout
                </a>


            <?php else: ?>

                <a href="/college-library/login.php">
                    Login
                </a>

            <?php endif; ?>

        </nav>


    </div>

</header>


<main class="main-content">

<div class="container">