<div align="center">

<img src="img/logo.png" alt="HS15 Community Logo" width="130" height="130" />

# HS15 Web Gallery & Archive

**A high-performance, private digital media archive and gallery system engineered for HS15 - Komunitas Keliling Banjar.**  
Crafted with **Native PHP** and **MySQL** with zero third-party framework overhead, delivering lightweight performance, rapid load times, and frictionless deployment.

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.0%20--%208.5%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP Version" />
  <img src="https://img.shields.io/badge/MySQL-5.7%20|%208.0%20|%208.4%20LTS-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL" />
  <img src="https://img.shields.io/badge/Architecture-Native%20%2F%20Zero--Dependency-00599C?style=for-the-badge" alt="Architecture" />
  <img src="https://img.shields.io/badge/Security-CSRF%20%26%20Rate--Limited-2ea44f?style=for-the-badge&logo=securityscorecard&logoColor=white" alt="Security" />
  <img src="https://img.shields.io/badge/UI%20Design-Dark%20Mode-1e293b?style=for-the-badge" alt="Dark Mode UI" />
</p>

</div>

---

## Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
  - [1. Photo Gallery Engine](#1-photo-gallery-engine)
  - [2. Video Gallery & Streaming Engine](#2-video-gallery--streaming-engine)
  - [3. Profile & Account Management](#3-profile--account-management)
  - [4. Unified Administration Suite](#4-unified-administration-suite)
  - [5. Security & System Architecture](#5-security--system-architecture)
- [System Requirements](#system-requirements)
- [Directory Structure](#directory-structure)
- [Installation & Deployment Guide](#installation--deployment-guide)
  - [A. Local Environment Setup (Laragon / XAMPP)](#a-local-environment-setup-laragon--xampp)
  - [B. Production Deployment (cPanel / Shared Hosting / VPS)](#b-production-deployment-cpanel--shared-hosting--vps)
- [Role-Based Access Control (RBAC)](#role-based-access-control-rbac)
- [License & Usage Policy](#license--usage-policy)

---

## Overview

**HS15 Web Gallery & Archive** serves as the private, centralized media documentation repository for members of the **HS15 - Komunitas Keliling Banjar** community.

Unlike heavy CMS or modern bloated frameworks, this application is deliberately crafted with **clean, modern Native PHP (8.0 through 8.5+)** paired with **Vanilla CSS3** and **Vanilla ES6+ JavaScript**. It delivers instant page response times, minimal server resource utilization, and hassle-free deployment across standard shared hosting (cPanel), VPS environments, or local development stacks.

### Key Design Highlights
- **Sleek Dark Mode**: Modern dark-themed palette tailored with consistent CSS variables and subtle backdrop blur effects.
- **Adaptive Layout**: Fully fluid and responsive across desktop displays, tablets, and mobile smartphones.
- **Strict Role-Based Visual Cues**: Clear, aesthetic color identities for administrators (Amethyst Purple) and members (Electric Cyan/Blue).
- **Self-Healing Data Layer**: Automated database schema provisioning on initial launch.

---

## Key Features

### 1. Photo Gallery Engine
- **Automated WebP Thumbnail Pipeline**: High-resolution photos are dynamically converted into lightweight WebP thumbnails (480px width) on-demand using the PHP GD library upon first access. Thumbnails are permanently cached in `media/thumbs/`, guaranteeing near-instant subsequent page renders.
- **Progressive Lazy Loading & Shimmer**: Integrated native progressive loading (`loading="lazy"` and `decoding="async"`) paired with smooth CSS shimmer placeholders to prevent cumulative layout shifts (CLS).
- **Interactive Fullscreen Lightbox**: Click any photo to launch an immersive modal viewer displaying full resolution imagery, title, capture date/time, and activity notes. Features complete keyboard accessibility (`Left` / `Right` arrow navigation and `ESC` to close) alongside a lossless 1-click download button.
- **Folder Filtering & Adaptive Pagination**: Clean organization by activity albums/folders with an 18-photo-per-page responsive layout and smart pagination controls.

### 2. Video Gallery & Streaming Engine
- **Client-Side Poster Generation (Zero FFmpeg)**: Video cover posters are captured directly from the first frame in the client's browser via HTML5 Canvas, then uploaded and cached as server-side `.jpg` files. This eliminates the need for resource-intensive server-side FFmpeg installations.
- **HTTP 206 Partial Content Streaming**: The `media.php` gateway fully implements HTTP Byte Range Requests (`bytes=X-Y`), enabling smooth video playback and instant timeline seeking/scrubbing without forcing clients to download the entire video upfront.
- **Smart Auto-Pause Controller**: Integrated playback monitor automatically pauses any active video whenever another video begins playing, preventing overlapping audio.
- **Orientation Resilience**: Responsive video cards seamlessly accommodate both landscape (16:9) and vertical portrait videos (9:16 reels/shorts) without distortion or awkward clipping.

### 3. Profile & Account Management
- **Role-Based Visual Identity**:
  - **Administrator**: Glowing amethyst purple avatar border with matching `Administrator` badge.
  - **Member**: Electric cyan/blue avatar border with matching `Member` badge.
- **Admin Quick-Access Shortcut**: A dedicated quick-access button with smooth purple gradients appears on the profile page for authenticated administrators to jump directly into the Admin Panel.
- **Interactive 1:1 Avatar Cropper**: Built-in square profile image cropper featuring smooth drag controls, rounded corner handles, and a high-fidelity 512x512 canvas exporter. Automatically prunes obsolete profile images from the server storage upon update.
- **Self-Service Security**: Secure password change form with real-time feedback and self-service account deletion with confirmation safeguards.

### 4. Unified Administration Suite
- **Media Repository Management**:
  - Comprehensive media ledger showing thumbnails, MIME indicators, capture timestamps, and folder categories.
  - Custom animated filter dropdowns featuring clean focus outlines and active state indicators.
  - Real-time search and multi-parameter sorting (*Newest*, *Oldest*, *A-Z*, *Z-A*).
  - **Context-Aware Dynamic Deletion**:
    - *Default (None selected)*: Button reads **Delete**.
    - *Partial selection (e.g., 5 of 15 selected)*: Button automatically transitions to **Delete Selected (5)**.
    - *All items selected on current page*: Button transitions to **Delete All**.
- **Batch Media Ingestion**:
  - Multi-file drag-and-drop batch uploader for both photos and videos.
  - Assign uploads to existing albums or create new directories on the fly directly from the modal interface.
- **User Governance**:
  - Directory of all registered members with role escalation/demotion capabilities and protected deletion workflows.

### 5. Security & System Architecture
- **Isolated Media Storage Gatekeeper**: The physical `media/` directory is hardened via `.htaccess` (`Deny from all`). Direct file downloads are prohibited; every photo and video stream must route through the `media.php` controller with active session verification.
- **Brute-Force Attack Mitigation**: Failed authentication attempts are tracked per IP and account (maximum 5 consecutive failed attempts per 15-minute window).
- **Cryptographic CSRF Protection**: Every state-changing form (login, upload, edit, delete, password update) is fortified with cryptographically secure anti-CSRF tokens verified using `hash_equals()`.
- **Restricted Onboarding Pipeline**: User registration (`register.php`) and password resets are restricted to authenticated administrators, preventing unauthorized signups.
- **Prepared Statements Everywhere**: Database queries strictly utilize MySQLi Prepared Statements, eliminating SQL Injection attack vectors.
- **Directory Traversal Defense**: Rigorous canonical path checks and filename sanitization prevent directory traversal attacks across all upload and retrieval endpoints.

---

## System Requirements

| Component | Minimum Version | Recommended / Tested Version |
| :--- | :--- | :--- |
| **Web Server** | Apache 2.4.x | **Apache 2.4.58+ / 2.4.68+** (with `mod_rewrite` enabled) |
| **PHP Runtime** | PHP 8.0 | **PHP 8.1, 8.2, 8.3, 8.4, 8.5+** *(fully tested)* |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ | **MySQL 8.0+ or MySQL 8.4 LTS** *(strict SQL mode compliant)* |

### Mandatory PHP Extensions
- `mysqli` — Secure database connection and prepared statements.
- `gd` — WebP thumbnail compression, video poster storage, and avatar processing.
- `fileinfo` — Server-side MIME-type inspection for secure uploads.
- `mbstring` — Multi-byte UTF-8 string manipulation.
- `curl` & `openssl` — Secure token generation and cryptography.
- `session` — Hardened cookie and authentication session lifecycle.

---

## Directory Structure

```text
project_hs15/
├── index.php                 # Root application dispatcher / redirect handler
├── README.md                 # Project documentation and deployment guide
├── favicon.ico               # Application favicon
├── css/                      # Presentation layer (Vanilla CSS3)
│   ├── global.css            # Design tokens, CSS variables, role badges, navbar
│   ├── auth.css              # Authentication views (login, registration, password recovery)
│   ├── choose.css            # Main dashboard / hub page layout
│   ├── gallery.css           # Photo gallery grid, masonry, and lightbox styling
│   ├── vidgallery.css        # Video gallery cards, player controls, and shimmer
│   ├── account.css           # Profile settings and avatar cropper modal
│   ├── admin.css             # Unified administration dashboard and media tables
│   └── background.css        # Dynamic ambient backdrop animation
├── js/                       # Client-side behavior layer (Vanilla ES6+)
│   ├── nav.js                # Top navigation and mobile 3-dots action menu
│   ├── gallery.js            # Interactive photo lightbox with keyboard listeners
│   ├── vidgallery.js         # Video player auto-pause and canvas poster generator
│   ├── account.js            # 1:1 Avatar cropping engine (drag, scale, canvas export)
│   ├── auth.js               # Password visibility toggles and client-side form validation
│   ├── choose.js             # Dashboard card animations and interactions
│   └── interactions.js       # Global UI helpers and micro-animations
├── php/                      # Core application backend & controllers
│   ├── connect.php           # MySQL database connection configuration
│   ├── security_helper.php   # Security module (CSRF, rate-limiting, thumbs, auth gate)
│   ├── stats_helper.php      # Aggregate statistics calculation (photo/video tallies)
│   ├── media.php             # Secure media gateway (HTTP 206 streaming & auth verification)
│   ├── video_thumb.php       # Ingestion endpoint for browser-generated video posters
│   ├── choose.php            # Primary authenticated dashboard
│   ├── gallery.php           # Photo gallery controller and view
│   ├── vidgallery.php        # Video gallery controller and view
│   ├── account.php           # User profile and account preferences
│   ├── admin.php             # Unified administrative management suite
│   ├── index.php             # User login portal
│   ├── register.php          # Member registration endpoint (Admin restricted)
│   ├── fgpass.php            # Password recovery initiation portal
│   ├── reset_password.php    # Password modification endpoint with token validation
│   ├── profile_image.php     # Dynamic JSON endpoint for active user profile photo
│   └── logout.php            # Session termination and invalidation
├── media/                    # Media asset repository (Protected)
│   ├── .htaccess             # Hardened security directive blocking direct HTTP access
│   └── thumbs/               # Cached WebP photo thumbnails and video posters
└── img/                      # Public static graphic assets
    ├── logo.png              # Official HS15 community emblem
    ├── logo.jpg              # Legacy HS15 community emblem
    └── profiles/             # User avatar uploads (managed automatically)
```

---

## Installation & Deployment Guide

### A. Local Environment Setup (Laragon / XAMPP)

1. **Clone or Copy Repository**:
   Place the project directory within your web server's root folder:
   - **Laragon**: `C:\laragon\www\project_hs15` (or `D:\laragon\www\project_hs15`)
   - **XAMPP**: `C:\xampp\htdocs\project_hs15`

2. **Start Services**:
   Launch the **Apache** and **MySQL** services via your Laragon or XAMPP dashboard.

3. **Create Database**:
   - Access phpMyAdmin at `http://localhost/phpmyadmin`.
   - Create a new UTF8mb4 database (e.g., `project_hs15` or `azyuca`).
   
   > [!NOTE]
   > You do **not** need to manually import any `.sql` schema file. The application features automated schema provisioning (`init_security_tables()`) that automatically generates `users`, `media`, `login_attempts`, and `password_resets` tables on first launch.

4. **Configure Database Credentials**:
   Open [`php/connect.php`](file:///d:/laragon/www/project_hs15/php/connect.php) and configure your connection credentials:
   ```php
   $host = "localhost";
   $user = "root";              // Default Laragon / XAMPP username
   $pass = "";                  // Default Laragon / XAMPP password (blank)
   $db   = "your_database_name"; // Name of your newly created database
   ```

5. **MySQL 8.4 LTS Compatibility (If Applicable)**:
   If using modern MySQL 8.4+ and authenticating with legacy native password hashing, ensure the parameter is enabled in your `my.ini` file:
   ```ini
   [mysqld]
   mysql_native_password=ON
   ```

6. **Launch the Application**:
   Open your browser and navigate to:
   ```text
   http://localhost/project_hs15/
   ```
   *(Or `http://project_hs15.test/` if utilizing Laragon's automatic virtual host feature)*.

---

### B. Production Deployment (cPanel / Shared Hosting / VPS)

1. **Upload & Extract Archive**:
   - Compress the repository files into a `.zip` archive.
   - Verify that hidden configuration files (notably `media/.htaccess`) are included.
   - Using cPanel **File Manager**, upload and extract the archive into `public_html` (or your chosen subdomain root).

2. **Provision Database in cPanel**:
   - Navigate to **MySQL Database Wizard**.
   - Create a database (e.g., `cpaneluser_hs15`).
   - Create a database user, generate a secure password, and grant **ALL PRIVILEGES**.

3. **Update Connection Settings**:
   Edit `php/connect.php` via cPanel File Editor:
   ```php
   $host = "localhost";
   $user = "cpaneluser_dbuser";
   $pass = "YourStrongGeneratedPassword";
   $db   = "cpaneluser_hs15";
   ```

4. **Configure PHP Version & Extensions**:
   - In cPanel, navigate to **Select PHP Version**.
   - Choose **PHP 8.1, 8.2, 8.3, or 8.4+**.
   - Verify that `mysqli`, `gd`, `fileinfo`, `mbstring`, `curl`, and `openssl` are activated.

5. **Tune Media Upload Limits**:
   In cPanel **MultiPHP INI Editor**, calibrate the directives to accommodate high-definition video uploads:
   ```ini
   upload_max_filesize = 512M
   post_max_size = 512M
   memory_limit = 512M
   max_execution_time = 300
   max_input_time = 300
   ```

6. **Folder Permissions**:
   Ensure write permissions (`0755`) are granted to runtime storage directories:
   - `media/`
   - `media/thumbs/`
   - `img/profiles/`

---

## Role-Based Access Control (RBAC)

The application implements a strict two-tiered permission hierarchy:

| Feature / Capability | Member | Administrator |
| :--- | :---: | :---: |
| Browse Photo Gallery & Lightbox | Yes | Yes |
| Stream & Scrub Videos (HTTP 206) | Yes | Yes |
| Download Lossless Original Media | Yes | Yes |
| Personal Profile & 1:1 Avatar Cropper | Yes | Yes |
| Self-Service Password Management | Yes | Yes |
| Access Unified Admin Dashboard | No | Yes |
| Batch Upload Photos & Videos | No | Yes |
| Create & Manage Albums / Folders | No | Yes |
| Edit Media Metadata (Titles, Notes) | No | Yes |
| Single & Batch Delete Media Files | No | Yes |
| Register New Members & Manage Roles | No | Yes |
| **Visual Indicator** | **Electric Cyan Border & Badge** | **Amethyst Purple Border & Badge** |

> [!TIP]
> **Bootstrap Admin Initialization**: When launching on an empty database, the very first user account created automatically receives **Administrator** privileges to facilitate immediate system configuration. Subsequent registrations are strictly locked down to administrators.

---

## License & Usage Policy

- **Community Archive**: This software was designed and developed specifically for internal documentation and archival use by **HS15 - Komunitas Keliling Banjar**.
- **Privacy & Protection**: All captured media assets, personal avatars, and community records are protected behind authenticated session barriers to safeguard member privacy.

---

<div align="center">
  <sub>Developed with pride for <b>HS15 - Komunitas Keliling Banjar</b>. Built for speed, security, and simplicity.</sub>
</div>