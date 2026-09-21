# HS15 Web Gallery

> A PHP-based digital archive for the photos and videos of HS15 - Komunitas Keliling Banjar.

![Status](https://img.shields.io/badge/status-active-2f855a?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-8%2B-777bb4?style=flat-square&logo=php&logoColor=white)
![Database](https://img.shields.io/badge/MySQL-compatible-4479a1?style=flat-square&logo=mysql&logoColor=white)

## Overview

HS15 Web Gallery is a lightweight photo and video archive built with plain PHP, HTML, CSS, and JavaScript. It was originally created as an illustration gallery and has since been redesigned as a private documentation space for the HS15 community.

The current project is organized into separate frontend, backend, media, and static-page directories. The interface focuses on simple navigation, responsive layouts, account management, and convenient media viewing.

## Features

- User registration, login, logout, and password reset.
- A post-login home page with direct access to the photo and video galleries.
- Photo gallery with automatic PHP GD thumbnails, filename-based captions, pagination, and a fullscreen lightbox.
- Video gallery with native HTML5 controls, optional FFmpeg poster thumbnails, portrait-video support, and pagination.
- Account settings for changing the profile photo, email address, and password.
- Permanent account deletion with password and confirmation checks.
- Password hashing with `password_hash()` and prepared statements for database queries.
- Responsive styles for authentication, navigation, galleries, and account pages.

## Tech Stack

| Area | Technology |
| --- | --- |
| Backend | PHP 8 or newer |
| Database | MySQL or MariaDB |
| Frontend | HTML, CSS, and vanilla JavaScript |
| Image processing | PHP GD |
| Icons | Ionicons via CDN |
| Local server | Laragon, XAMPP, WAMP, or Apache/Nginx |
| Video thumbnails | FFmpeg (optional) |

## Project Structure

```text
project_hs15/
├── index.php              # Root entry point; redirects to php/index.php
├── css/                   # Page-specific and shared stylesheets
├── js/                    # Gallery, authentication, and navigation scripts
├── html/
│   ├── choose.html        # Post-login home page
│   └── index.html         # Static login redirect page
├── php/
│   ├── index.php          # Login page
│   ├── register.php       # Registration page and handler
│   ├── fgpass.php         # Password reset page and handler
│   ├── login.php          # Login handler
│   ├── logout.php         # Logout handler
│   ├── account.php        # Account settings and deletion
│   ├── gallery.php        # Photo gallery and image thumbnails
│   ├── vidgallery.php     # Video gallery and poster thumbnails
│   ├── profile_image.php  # Profile image endpoint
│   └── connect.php        # Database connection configuration
├── gallery/               # Uploaded photos and videos
│   └── thumbs/            # Generated image and video thumbnails
└── img/
	└── profiles/          # Uploaded profile photos
```

## Requirements

- A local PHP web server such as Laragon, XAMPP, or WAMP.
- MySQL or MariaDB.
- PHP 8+ with the `mysqli` and `gd` extensions enabled.
- A modern browser with HTML5 video support.
- FFmpeg only if automatic video poster thumbnails are required.

## Installation

1. Place the project inside your web server document root, such as Laragon's `www` directory.
2. Start the web server and MySQL/MariaDB.
3. Create an empty database using phpMyAdmin, HeidiSQL, Adminer, or another database tool.
4. Select the database and create the base `users` table:

```sql
CREATE TABLE users (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	email VARCHAR(255) NOT NULL UNIQUE,
	password VARCHAR(255) NOT NULL
);
```

5. Update the connection values in `php/connect.php`:

```php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "azyuca";
```

The default values match a typical Laragon installation. `php/account.php` can add the `profile_photo`, `role`, and `created_at` columns when they are missing, but the base `users` table must exist first.

6. Open the project in a browser, for example:

```text
http://localhost/project_hs15/
```

Laragon users may also use a configured virtual host such as `http://project_hs15.test/`.

## Adding Media

### Photos

Put `JPG`, `JPEG`, `PNG`, `GIF`, or `WEBP` files in `gallery/`. Image thumbnails are generated in `gallery/thumbs/` when the photo gallery is opened. The gallery displays up to 16 photos per page.

### Videos

Put `MP4`, `WEBM`, `OGG`, or `M4V` files in `gallery/`. To generate poster thumbnails, place `ffmpeg.exe` in `php/`. Videos remain playable without FFmpeg, but no automatic poster image will be generated. The video gallery displays up to 12 videos per page.

Portrait videos keep their original aspect ratio and use a black playback area so that the content is not cropped.

### Filename Captions

Recognized timestamp formats are converted into readable captions:

- `IMG_20240915_103000.jpg` -> `15 September 2024 - 10:30`
- `VID_20240915_103000.mp4` -> `15 September 2024 - 10:30`
- `video_20240915_103000.mp4` -> `15 September 2024 - 10:30`
- `VID-20240915-WA0000.mp4` -> `15 September 2024`

Other filenames are displayed as regular title-cased captions.

## User Flow

```text
Register -> Login -> Home
					|-> Photo Gallery
					|-> Video Gallery
					`-> Account Settings
```

Gallery pagination keeps the first, previous, next, and last controls visible for a consistent layout. Unavailable controls are shown in a disabled state, and the layout becomes more compact on mobile screens.

## Development Notes

- Do not commit database passwords or other production secrets.
- Keep `gallery/` and `img/profiles/` writable so generated thumbnails and profile photos can be stored.
- Use a strong database password for public deployments.
- Review and disable development-only configuration before deploying the application publicly.

## License

This project was created for HS15 community documentation. Usage and distribution terms can be added according to the project owner's agreement.
