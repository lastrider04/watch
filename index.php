<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // Set to 0 in production

session_name('MOVIE_WATCHLIST_SESSID');
session_start();

// --- LOAD SENSITIVE API KEY CONFIGURATION ---
// IMPORTANT: Replace 'your_cpanel_username' with your actual cPanel username.
// This path assumes 'app_config' is in your cPanel home directory,
// and 'api_keys.php' is inside 'app_config'.
// $apiKeyConfigFile = '/home/your_cpanel_username/app_config/api_keys.php';

// Alternatively, if index.php is in public_html/your_app_folder/
// and app_config is in /home/your_cpanel_username/
// $apiKeyConfigFile = __DIR__ . '/../../app_config/api_keys.php'; // Adjust '../' as needed

if (file_exists($apiKeyConfigFile)) {
    require_once $apiKeyConfigFile;
} else {
    error_log("CRITICAL ERROR: API key configuration file not found at: " . $apiKeyConfigFile);
    die("A critical configuration error occurred (API Key). Please contact the administrator.");
}

// Check if the API key constant was defined in the included file
if (!defined('MY_APP_TMDB_API_KEY')) {
    error_log("CRITICAL ERROR: MY_APP_TMDB_API_KEY is not defined after including config file.");
    die("API key is not configured. Application cannot continue.");
}

// Redirect to login if not logged in (must be after config load in case session needs it)
if (!isset($_SESSION['username']) || !isset($_SESSION['user_file_id'])) {
    header('Location: login.php');
    exit;
}

$loggedInUsername = $_SESSION['username'];
$loggedInUserFileId = $_SESSION['user_file_id'];
$isAdmin = isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true;

// --- OTHER CONSTANTS ---
// TMDB_API_KEY is no longer defined here directly
define('TMDB_BASE_URL', 'https://api.themoviedb.org/3');
define('TMDB_IMAGE_BASE_URL', 'https://image.tmdb.org/t/p/w500');
define('TMDB_BACKDROP_BASE_URL', 'https://image.tmdb.org/t/p/w1280');
define('PLACEHOLDER_IMAGE', 'images/placeholder.png');
define('WATCHLIST_DIR', 'watchlists/');
define('APP_LOGO_PATH', 'images/app_logo.png');
define('CONFIG_FILE', 'config.json'); // For app settings like registration status

// --- CONFIG LOADING FUNCTION (for app settings) ---
function loadConfig() {
    $defaults = ['registrations_enabled' => true];
    if (file_exists(CONFIG_FILE)) {
        $config_json = file_get_contents(CONFIG_FILE);
        if ($config_json === false) {
            error_log("Failed to read app config file: " . CONFIG_FILE);
            return $defaults;
        }
        $config_data = json_decode($config_json, true);
        if (is_array($config_data)) {
            return array_merge($defaults, $config_data);
        }
    } else {
        error_log("App config file not found: " . CONFIG_FILE . ". Using defaults.");
    }
    return $defaults;
}

function saveConfig($config_data) {
    if (file_put_contents(CONFIG_FILE, json_encode($config_data, JSON_PRETTY_PRINT)) === false) {
        error_log("Failed to write to app config file: " . CONFIG_FILE);
        return false;
    }
    return true;
}

$config = loadConfig();

// --- WATCHLIST FILE FUNCTIONS (USER-SPECIFIC) ---
function getUserWatchlistPath($userFileId) {
    if (!is_dir(WATCHLIST_DIR)) {
        if (!mkdir(WATCHLIST_DIR, 0755, true)) {
            error_log("Failed to create watchlist directory: " . WATCHLIST_DIR);
            die("Error: Could not create watchlist directory. Please check server permissions.");
        }
    }
    return WATCHLIST_DIR . $userFileId . '_watchlist.json';
}

function loadWatchlist($userFileId) {
    $filePath = getUserWatchlistPath($userFileId);
    if (!file_exists($filePath)) { return []; }
    $json = file_get_contents($filePath);
    if ($json === false) { error_log("Failed to read watchlist file: " . $filePath); return []; }
    $watchlist = json_decode($json, true);
    return is_array($watchlist) ? $watchlist : [];
}

function saveWatchlist($userFileId, $watchlist) {
    $filePath = getUserWatchlistPath($userFileId);
    if (file_put_contents($filePath, json_encode(array_values($watchlist), JSON_PRETTY_PRINT)) === false) {
        error_log("Failed to write to watchlist file: " . $filePath);
    }
}

// --- API CALL HELPER ---
function callTmdbApi($endpoint, $params = []) {
    $params['api_key'] = MY_APP_TMDB_API_KEY; // Use the loaded API key
    $url = TMDB_BASE_URL . $endpoint . '?' . http_build_query($params);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code == 200) { return json_decode($response, true); }
    error_log("TMDB API Error: HTTP $http_code for $url. Response: $response");
    return null;
}

// --- FUNCTION TO FETCH AND PREPARE ITEM DETAILS ---
function fetchAndPrepareItemDetails($itemId, $itemMediaType) {
    $detailsData = null; $endpoint = '';
    if ($itemMediaType === 'movie') { $endpoint = "/movie/{$itemId}"; }
    elseif ($itemMediaType === 'tv') { $endpoint = "/tv/{$itemId}"; }

    if (!empty($endpoint)) {
        $details = callTmdbApi($endpoint);
        $credits = callTmdbApi("{$endpoint}/credits");
        $images = callTmdbApi("{$endpoint}/images");
        if ($details) {
            $detailsData = $details; $detailsData['media_type'] = $itemMediaType;
            $detailsData['cast'] = isset($credits['cast']) ? array_slice($credits['cast'], 0, 10) : [];
            $detailsData['backdrops'] = isset($images['backdrops']) ? array_slice($images['backdrops'], 0, 5) : [];
            $detailsData['posters'] = isset($images['posters']) ? array_slice($images['posters'], 0, 5) : [];
            if ($itemMediaType === 'movie') {
                $detailsData['display_title'] = $detailsData['title'] ?? 'N/A';
                $detailsData['display_date'] = $detailsData['release_date'] ?? 'N/A';
            } else {
                $detailsData['display_title'] = $detailsData['name'] ?? 'N/A';
                $detailsData['display_date'] = $detailsData['first_air_date'] ?? 'N/A';
            }
        }
    }
    return $detailsData;
}

$action = $_REQUEST['action'] ?? null;
$currentWatchlist = loadWatchlist($loggedInUserFileId);
$message = ''; $admin_action_message = ''; $selectedItemDetails = null;

// --- ADMIN ACTION: TOGGLE REGISTRATIONS (INLINE) ---
if ($isAdmin && $action === 'toggle_registrations_inline') {
    $new_status = !$config['registrations_enabled'];
    $config['registrations_enabled'] = $new_status;
    if (saveConfig($config)) {
        $admin_action_message = "Registrations " . ($new_status ? "ENABLED" : "DISABLED") . ".";
    } else {
        $admin_action_message = "Error updating config. Check permissions for " . CONFIG_FILE . ".";
    }
    header("Location: index.php?admin_msg=" . urlencode($admin_action_message));
    exit;
}

// --- AJAX ACTION: LIVE SEARCH ---
if ($action === 'ajax_live_search' && isset($_GET['query'])) {
    header('Content-Type: application/json');
    $query = trim($_GET['query']); $results = ['items' => []];
    if (strlen($query) >= 2) {
        $apiResults = callTmdbApi('/search/multi', ['query' => $query, 'include_adult' => 'false', 'page' => 1]);
        if ($apiResults && isset($apiResults['results'])) {
            foreach (array_slice($apiResults['results'], 0, 10) as $item) {
                if (isset($item['media_type']) && ($item['media_type'] === 'movie' || $item['media_type'] === 'tv')) {
                    $title = ''; $year = 'N/A'; $posterPath = ''; $mediaType = $item['media_type'];
                    if ($mediaType === 'movie') { $title = $item['title'] ?? 'Unknown Movie'; $year = !empty($item['release_date']) ? substr($item['release_date'], 0, 4) : 'N/A'; $posterPath = $item['poster_path'] ?? ''; }
                    elseif ($mediaType === 'tv') { $title = $item['name'] ?? 'Unknown TV Show'; $year = !empty($item['first_air_date']) ? substr($item['first_air_date'], 0, 4) : 'N/A'; $posterPath = $item['poster_path'] ?? ''; }
                    $results['items'][] = ['id' => $item['id'], 'title' => htmlspecialchars($title), 'year' => $year, 'poster_url' => !empty($posterPath) ? TMDB_IMAGE_BASE_URL . $posterPath : PLACEHOLDER_IMAGE, 'media_type' => $mediaType];
                }
            }
        }
    }
    echo json_encode($results); exit;
}
// --- AJAX ACTION: ADD TO WATCHLIST ---
elseif ($action === 'ajax_add_to_watchlist' && isset($_POST['item_id'])) {
    header('Content-Type: application/json');
    $itemId = intval($_POST['item_id']); $itemTitle = $_POST['item_title']; $itemPosterUrl = $_POST['item_poster_url']; $itemMediaType = $_POST['item_media_type'];
    $posterPathForStorage = '';
    if (strpos($itemPosterUrl, TMDB_IMAGE_BASE_URL) === 0) { $posterPathForStorage = str_replace(TMDB_IMAGE_BASE_URL, '', $itemPosterUrl); }
    elseif ($itemPosterUrl === PLACEHOLDER_IMAGE) { $posterPathForStorage = ''; }
    $exists = false;
    foreach ($currentWatchlist as $watchlistItem) { if ($watchlistItem['id'] === $itemId && $watchlistItem['media_type'] === $itemMediaType) { $exists = true; break; } }
    $response = [];
    if (!$exists) {
        $newItem = ['id' => $itemId, 'title' => $itemTitle, 'poster_path' => $posterPathForStorage, 'media_type' => $itemMediaType];
        $currentWatchlist[] = $newItem; saveWatchlist($loggedInUserFileId, $currentWatchlist);
        $response = ['success' => true, 'message' => htmlspecialchars($itemTitle) . " added to your watchlist!", 'item' => ['id' => $newItem['id'], 'title' => htmlspecialchars($newItem['title']), 'poster_url_for_list' => !empty($newItem['poster_path']) ? TMDB_IMAGE_BASE_URL . $newItem['poster_path'] : PLACEHOLDER_IMAGE, 'poster_path_for_picker' => !empty($newItem['poster_path']) ? TMDB_IMAGE_BASE_URL . $newItem['poster_path'] : PLACEHOLDER_IMAGE, 'media_type' => $newItem['media_type']]];
    } else { $response = ['success' => false, 'message' => htmlspecialchars($itemTitle) . " is already in your watchlist."]; }
    echo json_encode($response); exit;
}
// --- ACTION: REMOVE ITEM FROM WATCHLIST ---
elseif ($action === 'remove' && isset($_POST['item_id_to_remove']) && isset($_POST['item_media_type_to_remove'])) {
    $itemIdToRemove = intval($_POST['item_id_to_remove']); $itemMediaTypeToRemove = $_POST['item_media_type_to_remove'];
    $currentWatchlist = array_filter($currentWatchlist, function ($item) use ($itemIdToRemove, $itemMediaTypeToRemove) { return !($item['id'] === $itemIdToRemove && $item['media_type'] === $itemMediaTypeToRemove); });
    saveWatchlist($loggedInUserFileId, $currentWatchlist);
    header("Location: index.php?removed=" . urlencode($itemIdToRemove . '_' . $itemMediaTypeToRemove)); exit;
}
// --- PICK RANDOM ITEM (INITIATE PROCESS) ---
elseif ($action === 'pick_random') { /* Page reloads to main view */ }
// --- SHOW DETAILS OF THE RANDOMLY SELECTED ITEM ---
elseif ($action === 'show_selected_random' && isset($_POST['selected_item_id']) && isset($_POST['selected_item_media_type'])) {
    $selectedItemId = intval($_POST['selected_item_id']); $selectedItemMediaType = $_POST['selected_item_media_type'];
    $selectedItemDetails = fetchAndPrepareItemDetails($selectedItemId, $selectedItemMediaType);
    if ($selectedItemDetails) { $_SESSION['last_picked_item_for_user'][$loggedInUserFileId] = ['id' => $selectedItemId, 'media_type' => $selectedItemMediaType]; }
    else { $message = "Could not fetch details for the randomly selected item."; unset($_SESSION['last_picked_item_for_user'][$loggedInUserFileId]); }
}
// --- ACTION: SHOW DETAILS OF A SPECIFIC WATCHLIST ITEM ---
elseif ($action === 'show_watchlist_item_details' && isset($_POST['item_id_to_show']) && isset($_POST['item_media_type_to_show'])) {
    $itemIdToShow = intval($_POST['item_id_to_show']); $itemMediaTypeToShow = $_POST['item_media_type_to_show'];
    $selectedItemDetails = fetchAndPrepareItemDetails($itemIdToShow, $itemMediaTypeToShow);
    if (!$selectedItemDetails) { $message = "Could not fetch details for the selected watchlist item."; }
}
// --- CLEAR SELECTED ITEM (No longer directly used by a button, "Spin Again" serves this) ---
elseif ($action === 'clear_selection') { $selectedItemDetails = null; header("Location: index.php"); exit; }

// Messages from GET params
if (isset($_GET['removed'])) { $message = "Item removed from watchlist."; }
if (isset($_GET['admin_msg'])) { $admin_action_message = htmlspecialchars(urldecode($_GET['admin_msg'])); }
if (isset($_GET['error_msg'])) { $message = htmlspecialchars(urldecode($_GET['error_msg']));}

$lastPickedItemJson = 'null';
if (isset($_SESSION['last_picked_item_for_user'][$loggedInUserFileId]) && is_array($_SESSION['last_picked_item_for_user'][$loggedInUserFileId])) {
    $lastPickedItemJson = json_encode($_SESSION['last_picked_item_for_user'][$loggedInUserFileId]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movie or TV Show? - <?php echo htmlspecialchars($loggedInUsername); ?></title>
    <link rel="stylesheet" href="style.css">
	<link rel="shortcut icon" href="/images/favicon.ico">
</head>
<body data-last-picked-item='<?php echo htmlspecialchars($lastPickedItemJson, ENT_QUOTES, 'UTF-8'); ?>'>
    <div class="container">

        <div class="user-info-header">
            <div class="user-greeting">
                Logged in as: <strong><?php echo htmlspecialchars($loggedInUsername); ?></strong>
                <?php if ($isAdmin): ?>(Admin)<?php endif; ?>
            </div>
            <div class="user-actions">
                <a href="logout.php" class="logout-link">Logout</a>
                <?php if ($isAdmin): ?>
                    <form method="POST" action="index.php" class="admin-toggle-form-icon">
                        <input type="hidden" name="action" value="toggle_registrations_inline">
                        <button type="submit"
                                class="admin-toggle-icon-btn <?php echo $config['registrations_enabled'] ? 'regs-enabled-icon' : 'regs-disabled-icon'; ?>"
                                title="Toggle Registrations (Currently <?php echo $config['registrations_enabled'] ? 'Enabled' : 'Disabled'; ?>)">
                            <?php echo $config['registrations_enabled'] ? '🔓' : '🔒'; ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($admin_action_message)): ?>
            <div class="admin-action-message <?php echo (strpos(strtolower($admin_action_message), 'error') === false) ? 'success' : 'error'; ?>">
                <?php echo $admin_action_message; ?>
            </div>
        <?php endif; ?>

        <div class="app-header">
            <?php if (file_exists(APP_LOGO_PATH)): ?>
                <img src="<?php echo APP_LOGO_PATH; ?>" alt="App Logo" id="appLogo">
            <?php endif; ?>
            <h1>Choose what to watch!</h1>
        </div>

        <div id="globalMessageArea" class="message" style="display: none;"></div>
        <?php if (!empty($message) && empty($selectedItemDetails) && empty($admin_action_message)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const phpMessage = "<?php echo addslashes(htmlspecialchars($message)); ?>";
                    if (phpMessage) {
                        const globalMsgArea = document.getElementById('globalMessageArea');
                        globalMsgArea.textContent = phpMessage;
                        globalMsgArea.className = 'message message-info';
                        globalMsgArea.style.display = 'block';
                        setTimeout(() => { globalMsgArea.style.display = 'none'; }, 4000);
                    }
                });
            </script>
            <?php $message = ''; ?>
        <?php endif; ?>

        <?php if (isset($selectedItemDetails) && $selectedItemDetails): // --- RESULT PAGE --- ?>
            <div class="result-page">
                <h2>You're Watching: <?php echo htmlspecialchars($selectedItemDetails['display_title']); ?>
                    <span class="media-type-badge"><?php echo strtoupper($selectedItemDetails['media_type']); ?></span>
                </h2>
                <div class="show-details-grid">
                    <div class="show-poster-main">
                        <?php $posterToShow = $selectedItemDetails['poster_path'] ?? ''; ?>
                        <img src="<?php echo !empty($posterToShow) ? TMDB_IMAGE_BASE_URL . $posterToShow : PLACEHOLDER_IMAGE; ?>" alt="<?php echo htmlspecialchars($selectedItemDetails['display_title']); ?> Poster">
                    </div>
                    <div class="show-info">
                        <p><strong>Overview:</strong> <?php echo nl2br(htmlspecialchars($selectedItemDetails['overview'] ?? 'N/A')); ?></p>
                        <p><strong><?php echo $selectedItemDetails['media_type'] === 'movie' ? 'Release Date' : 'First Aired'; ?>:</strong> <?php echo htmlspecialchars($selectedItemDetails['display_date']); ?></p>
                        <p><strong>Status:</strong> <?php echo htmlspecialchars($selectedItemDetails['status'] ?? 'N/A'); ?></p>
                        <p><strong>Rating:</strong> <?php echo htmlspecialchars(isset($selectedItemDetails['vote_average']) ? number_format($selectedItemDetails['vote_average'], 1) : 0); ?>/10 (<?php echo htmlspecialchars($selectedItemDetails['vote_count'] ?? 0); ?> votes)</p>
                        <p><strong>Genres:</strong>
                            <?php
                            if (!empty($selectedItemDetails['genres'])) {
                                $genres = array_map(function ($g) { return htmlspecialchars($g['name']); }, $selectedItemDetails['genres']);
                                echo implode(', ', $genres);
                            } else { echo 'N/A'; }
                            ?>
                        </p>
                        <?php if ($selectedItemDetails['media_type'] === 'tv' && isset($selectedItemDetails['number_of_seasons'])): ?>
                            <p><strong>Seasons:</strong> <?php echo htmlspecialchars($selectedItemDetails['number_of_seasons']); ?></p>
                        <?php endif; ?>
                        <?php if ($selectedItemDetails['media_type'] === 'movie' && isset($selectedItemDetails['runtime'])): ?>
                            <p><strong>Runtime:</strong> <?php echo htmlspecialchars($selectedItemDetails['runtime']); ?> minutes</p>
                        <?php endif; ?>
                    </div>
                </div>
                <h3>Main Cast</h3>
                <div class="cast-list">
                    <?php if (!empty($selectedItemDetails['cast'])): ?>
                        <?php foreach ($selectedItemDetails['cast'] as $castMember): ?>
                            <div class="cast-member">
                                <?php $profilePath = $castMember['profile_path'] ?? ''; ?>
                                <img src="<?php echo !empty($profilePath) ? TMDB_IMAGE_BASE_URL . $profilePath : PLACEHOLDER_IMAGE; ?>" alt="<?php echo htmlspecialchars($castMember['name']); ?>">
                                <p><strong><?php echo htmlspecialchars($castMember['name']); ?></strong><br> as <?php echo htmlspecialchars($castMember['character'] ?? 'N/A'); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?> <p>Cast information not available.</p> <?php endif; ?>
                </div>
                <?php
                    $allImages = [];
                    if (!empty($selectedItemDetails['backdrops'])) $allImages = array_merge($allImages, $selectedItemDetails['backdrops']);
                    if (!empty($selectedItemDetails['posters'])) {
                        foreach($selectedItemDetails['posters'] as $p) {
                            if ($p['file_path'] !== ($selectedItemDetails['poster_path'] ?? '')) { $allImages[] = $p; }
                        }
                    }
                ?>
                <?php if (!empty($allImages)): ?>
                    <h3>Images</h3>
                    <div class="image-gallery">
                        <?php foreach ($allImages as $img): ?>
                             <?php
                                $imgUrl = TMDB_IMAGE_BASE_URL;
                                if ((isset($img['width']) && isset($img['height']) && $img['width'] > $img['height'] * 1.2) || (isset($img['aspect_ratio']) && $img['aspect_ratio'] > 1.2)) {
                                     $imgUrl = TMDB_BACKDROP_BASE_URL;
                                }
                            ?>
                            <img src="<?php echo $imgUrl . $img['file_path']; ?>" alt="Image related to <?php echo htmlspecialchars($selectedItemDetails['display_title']); ?>">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="result-actions">
                     <form method="POST" action="index.php" style="display: inline;">
                         <input type="hidden" name="action" value="pick_random">
                         <button type="submit">Spin Again!</button>
                     </form>
                </div>
            </div>
        <?php else: // --- MAIN PAGE (SEARCH, WATCHLIST, PICKER) --- ?>
            <div class="search-section">
                <h2>Add Movies or TV Shows to Your Watchlist</h2>
                <input type="text" id="liveSearchInput" placeholder="Start typing title...">
                <div id="liveSearchResultsContainer" class="live-search-results"></div>
            </div>
            <div class="watchlist-section">
                <h2>Your Watchlist</h2>
                <ul id="watchListDisplay">
                    <?php if (empty($currentWatchlist)): ?>
                        <p id="emptyWatchlistMessage">Your watchlist is empty. Search and add some items!</p>
                    <?php else: ?>
                        <?php foreach ($currentWatchlist as $item): ?>
                            <li class="watchlist-item-clickable">
                                <form method="POST" action="index.php" class="view-details-form">
                                    <input type="hidden" name="action" value="show_watchlist_item_details">
                                    <input type="hidden" name="item_id_to_show" value="<?php echo $item['id']; ?>">
                                    <input type="hidden" name="item_media_type_to_show" value="<?php echo $item['media_type']; ?>">
                                    <button type="submit" class="watchlist-item-button">
                                        <?php $posterUrlForList = !empty($item['poster_path']) ? TMDB_IMAGE_BASE_URL . $item['poster_path'] : PLACEHOLDER_IMAGE; ?>
                                        <img src="<?php echo $posterUrlForList; ?>" alt="<?php echo htmlspecialchars($item['title']); ?> Poster" class="watchlist-poster">
                                        <span class="watchlist-title-text">
                                            <?php echo htmlspecialchars($item['title']); ?>
                                            <span class="media-type-badge"><?php echo strtoupper($item['media_type']); ?></span>
                                        </span>
                                    </button>
                                </form>
                                <form method="POST" action="index.php" class="remove-form" onsubmit="return confirm('Remove <?php echo htmlspecialchars(addslashes($item['title'])); ?> (<?php echo strtoupper($item['media_type']); ?>) from watchlist?');">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="item_id_to_remove" value="<?php echo $item['id']; ?>">
                                    <input type="hidden" name="item_media_type_to_remove" value="<?php echo $item['media_type']; ?>">
                                    <button type="submit" class="remove-btn">Remove</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="picker-section" <?php if (empty($currentWatchlist)) echo 'style="display:none;"'; ?>>
                <h2>Ready to Watch?</h2>
                <form id="randomPickerForm" method="POST" action="index.php">
                    <input type="hidden" name="action" value="show_selected_random">
                    <input type="hidden" id="selectedItemIdInput" name="selected_item_id" value="">
                    <input type="hidden" id="selectedItemMediaTypeInput" name="selected_item_media_type" value="">
                    <button type="button" id="pickRandomButton">Decide!</button>
                </form>
                <div id="animationCanvas" class="animation-canvas" style="display:none;">
                    <img id="animatedPoster" src="<?php echo PLACEHOLDER_IMAGE; ?>" alt="Picking..." />
                    <div id="animatedTitle"></div>
                    <div id="animatedMediaType" class="media-type-badge"></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script src="script.js"></script>
</body>
</html>