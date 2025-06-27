<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News From AI</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <h1>News From AI</h1>
        <!-- Optional: Add a theme switcher button if manual override is desired -->
        <!-- <button id="theme-toggle">Toggle Theme</button> -->
    </header>
    <main id="news-feed">
        <!-- News items will be loaded here by js/main.js -->
        <p>Loading news...</p>
    </main>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> News From AI. Powered by AI.</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
