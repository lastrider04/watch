<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// SET A UNIQUE SESSION NAME
session_name('MOVIE_WATCHLIST_SESSID');

session_start();
session_unset();
session_destroy();

// Optional: Also delete the session cookie for a cleaner logout
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

header('Location: login.php');
exit;
?>