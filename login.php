<?php
// 1. Enable Full Error Reporting (Crucial for Debugging)
// error_reporting(E_ALL);
// ini_set('display_errors', 1); // Set to 0 in production

// 2. SET A UNIQUE SESSION NAME (MUST BE BEFORE session_start())
session_name('MOVIE_WATCHLIST_SESSID');

session_start();

define('USERS_DIR', 'users/'); // Directory to store user files
$error_message = '';

// If already logged in, redirect to index.php
if (isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = "Username and password are required.";
    } else {
        $sanitized_username_for_file = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $username));
        $user_file = USERS_DIR . $sanitized_username_for_file . '.json';

        if (file_exists($user_file)) {
            $user_data = json_decode(file_get_contents($user_file), true);

            if ($user_data && isset($user_data['password_hash']) && password_verify($password, $user_data['password_hash'])) {
                // Login successful
                $_SESSION['username'] = $user_data['username'];
                $_SESSION['user_file_id'] = $sanitized_username_for_file;
                // ADD THIS: Store admin status in session
                $_SESSION['isAdmin'] = isset($user_data['isAdmin']) && $user_data['isAdmin'] === true;

                // error_log("Login successful (Admin: " . ($_SESSION['isAdmin'] ? 'Yes' : 'No') . ") for: " . $_SESSION['username']);

                header('Location: index.php');
                exit;
            } else {
                $error_message = "Invalid username or password.";
            }
        } else {
            $error_message = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Movie or TV Show?</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="/images/favicon.ico">
</head>
<body>
    <div class="login-container">
        <h1>Login</h1>
        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <div class="register-link">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>
</body>
</html>