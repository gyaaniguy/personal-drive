# Changelog

## Not versioned changes

- Go-to-folder quick navigation (Ctrl+G): type a folder name to filter and jump to any folder by keyboard
- Password-protected file upload (client-side AES-256 encrypted zip)
- Upload queue with per-upload progress reporting
- Favorites for files and folders, with a dedicated Favorites menu
- Sort by modification date, with selected sort preferences remembered
- Lazy-loaded image, text, and PDF viewers
- Reliability improvements for uploads, moves, downloads, and storage resync
- Html viewer
- Improved responsive mobile layout and drive controls
- Audio player
- Audiobook features: save position automatically, multiple rewind buttons
- Streamline settings for autoplaying media
- Improve setup.sh for better permission handling
- Better error messages for failed move/download and conflict uploads
- Show total shares count
- Handle too-long path names
- Security hardening: storage-path and symlink protection, safer file responses, sanitized text content, rate-limited two-factor attempts, and stronger share-access checks
- Support running behind reverse proxies by adding configurable/optional trusted proxies
- Support no auth mode
- Two Factor Authentication powered by TOTP protocol. Ex: Google Authenticator
- Safer setup: re-running `setup.sh` keeps your `.env` and `APP_KEY`, and later `.env` edits apply without clearing caches
- Search, move, and resync fixes: favorites and shares survive moves, search no longer breaks in subfolders, awkward file names handled
- Automated install testing for both the script and Docker install paths, plus a local check script run before every push

## [v1.0.0] - 2025-08-07

### Added
- Initial public release
- Core file management features (upload, download, delete, move, rename)
- File sharing with expiration, password, and custom URLs
- Media player, image viewer, PDF/text preview
- Drag-and-drop support for files/folders
- Admin dashboard
- Docker and manual setup options
- Basic authentication system
- lots more. Please see Readme for full list of features.

### Fixed
- Numerous pre-release bug fixes
- Permissions and upload error handling

### Notes
- Extensive testing with 94% code coverage

### Planned for v2
- Encryption
- Two factor Authentication
- Audiobook support
- Limited upload collaboration
- Preview more file types
- Support running behind reverse proxies. Trust Proxies support

## [v1.0-beta.13] - April 22, 2025

### Added
- Detect not installed application.
- Added Changelog

### Changed
- Improved setup process.
- Minor cleanup tasks.

### Fixed
- Fixed context issue.

---

## [v1.0-beta.12] - Previous Release

### Added
- No cursor for guests in txtviewer.

### Fixed
- Fixed txtviewer issue for shares.
- Resolved backslash issue.
- General bug fixes.
