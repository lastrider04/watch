<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_name('MOVIE_WATCHLIST_SESSID');
session_start();

define('USERS_DIR', 'users/');
define('CONFIG_FILE', 'config.json'); // Define path to config file

$error_message = '';
$success_message = '';
$registrations_allowed = true; // Default to true if config is missing/malformed

// Load registration status from config
if (file_exists(CONFIG_FILE)) {
    $config_json = file_get_contents(CONFIG_FILE);
    $config_data = json_decode($config_json, true);
    if (isset($config_data['registrations_enabled'])) {
        $registrations_allowed = (bool)$config_data['registrations_enabled'];
    }
} else {
    // Optional: Log if config file is missing, for now, we default to allowed
    error_log("Config file " . CONFIG_FILE . " not found. Registrations defaulted to allowed.");
}


if (isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

if (!$registrations_allowed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $error_message = "Registrations are currently disabled by the administrator.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (rest of your existing registration logic: username, password validation, file creation)
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error_message = "All fields are required.";
    } elseif (strlen($username) < 3) {
        $error_message = "Username must be at least 3 characters long.";
    } elseif (!preg_match("/^[a-zA-Z0-9_]+$/", $username)) {
        $error_message = "Username can only contain letters, numbers, and underscores.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        $sanitized_username_for_file = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $username));
        $user_file = USERS_DIR . $sanitized_username_for_file . '.json';

        if (file_exists($user_file)) {
            $error_message = "Username already exists. Please choose a different one.";
        } else {
            if (!is_dir(USERS_DIR)) {
                if (!mkdir(USERS_DIR, 0755, true)) {
                    $error_message = "Failed to create users directory. Check permissions.";
                    error_log("Failed to create directory: " . USERS_DIR);
                }
            }
            if (empty($error_message)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $user_data = [
                    'username' => $username,
                    'password_hash' => $hashed_password
                    // No isAdmin flag set here by default for new users
                ];
                if (file_put_contents($user_file, json_encode($user_data, JSON_PRETTY_PRINT))) {
                    $success_message = "Registration successful! You can now <a href='login.php'>login</a>.";
                } else {
                    $error_message = "Could not create user file. Please check permissions.";
                    error_log("Failed to write user file: " . $user_file);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Movie or TV Show?</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="/images/favicon.ico">
</head>
<body>
    <div class="register-container">
        <h1>Register</h1>
        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <p class="success-message"><?php echo $success_message; // Allows link to login ?></p>
        <?php endif; ?>

        <?php if (empty($success_message) && $registrations_allowed): // Show form only if no success and registrations allowed ?>
        <form method="POST" action="register.php">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit">Register</button>
        </form>
        <?php elseif (!$registrations_allowed && empty($success_message)): ?>
            <p> </p>
        <?php endif; ?>

        <div class="login-link">
            <p>Already have an account? <a href="login.php">Login here</a></p>
            <?php if (!$registrations_allowed): ?>
                <p style="font-style: italic; font-size: 0.9em; margin-top:10px;">(New registrations are currently disabled)</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>