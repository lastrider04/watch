# Watch
A simple PHP and JavaScript-based Progressive Web App (PWA) that allows users to create a watchlist of movies and TV shows using The Movie Database (TMDB) API, and then randomly pick an item to watch. It features user registration/login, individual watchlists, and admin controls for registration.

## Features

*   **User Authentication:** Secure registration and login system using PHP `password_hash`.
*   **Individual Watchlists:** Each user has their own private watchlist stored in JSON files.
*   **TMDB API Integration:** Search for movies and TV shows via the TMDB API.
*   **Add & Remove from Watchlist:** Easily manage items in your watchlist.
*   **Click to View Details:** Click on any watchlist item to see its detailed information (overview, cast, images, etc.).
*   **Random Picker:** A "Decide!" button to randomly select an item from the user's watchlist, with an animation.
    *   Improved randomization to avoid picking the same item consecutively if other options exist.
*   **Admin Role:**
    *   Manually assignable admin role by editing a user's JSON file.
    *   Admin can toggle user registrations on/off via a discrete UI element.
*   **Flat-File Storage:** Uses JSON files for storing user data, watchlists, and application configuration (no database required).
*   **Responsive Design:** Basic responsive styling for usability on different screen sizes.

## Screenshot

![App Screenshot](images/app_screenshot_placeholder.png)  <!-- Replace with an actual screenshot path if you add one -->

## Technologies Used

*   PHP
*   JavaScript (Vanilla)
*   HTML5
*   CSS3
*   TMDB API
*   JSON (for data storage)

## Setup Instructions

Follow these steps to set up the application on your own web server (e.g., a cPanel host):

**1. Prerequisites:**

*   A web server with PHP support (PHP 7.x or higher recommended).
*   cURL extension for PHP enabled (for making API calls).
*   Write permissions for the PHP process on certain directories.

**2. Clone or Download the Repository:**

   ```bash
   git clone https://github.com/your-username/your-repo-name.git
   cd your-repo-name
