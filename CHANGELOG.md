# Changelog

All notable changes to **Brink Multimedia Instellingen** are documented here.

## [1.6.0] - 2026-10-01

Includes everything from 1.5.0 ("Verwijderen uitsluiten" per role).

### Fixed
- **Auto-updater**: release tags starting with an uppercase `V` (e.g. `V1.5.0`) were not recognised as a newer version, because only a lowercase `v` was stripped. `v1.6.0`, `V1.6.0` and `1.6.0` are now all read as `1.6.0`; tags that are not a version number are ignored.
- **Auto-updater**: the update package is now taken from a `.zip` file attached to the GitHub release (preferably named `brink-multimedia-instellingen.zip`, containing the folder `brink-multimedia-instellingen/`). Only when a release has no zip attachment does it fall back to GitHub's source-code zip (zipball), as before.
- **Auto-updater**: safety check before installing an update. If the downloaded package does not contain the plugin's main file (for example because the repository holds a zip instead of the plugin files), the update is aborted with a clear message and the current version stays active. Previously such a package would have replaced the plugin folder and caused WordPress to deactivate the plugin on every site.

### Notes
- Sites still running 1.4.2 or 1.5.0 use the old updater, which always downloads the source-code zip (zipball) and only recognises tags with a lowercase `v`. For them to update to 1.6.0 automatically, the repository at tag `v1.6.0` must contain the plugin files themselves (not a zip). Alternatively, install 1.6.0 once manually on those sites; from then on a zip attached to the release is enough.

## [1.5.0] - 2026-10-01

### Added
- **Rollen & Rechten** tab: new section **Verwijderen** with a per-role checkbox "Verwijderen uitsluiten". A checked role can still add and edit everything it has access to, but can no longer delete or trash anything: pages, posts, media, custom post types, categories and tags.
  - Implemented through the `user_has_cap` filter: every `delete_*` capability (including those of custom post types) plus `remove_users` is withheld for the checked roles. Deleting categories/tags (`delete_term`, which WordPress maps to `manage_categories`) is blocked separately via `map_meta_cap`. WordPress hides the trash/delete links by itself once the capability is missing.
  - Roles stored in the database are not modified: unchecking a role, or deactivating the plugin, restores WordPress' default behaviour immediately.
  - The filters are registered on every request (not only in wp-admin), so deleting through the REST API (block editor) is covered too.
  - The Administrator role is never restricted.
  - Other plugins can follow the same rule by checking `current_user_can( 'delete_others_posts' )` before showing their own delete buttons.

## [1.4.2] - 2026-09-23

### Added
- GitHub-based auto-updater, consistent with the rest of the Brinkmulti suite: no external library, polls the GitHub Releases API for `Brinkmulti/brink-multimedia-instellingen`, caches the result (12 hours, or 15 minutes after a failed request), and fixes the GitHub zip's folder name after an update so WordPress doesn't treat the plugin as removed. Added the `Update URI: false` header to avoid conflicts with WordPress.org's own update check.
- From this version on, publishing a new GitHub Release with a tag matching the plugin version (e.g. `v1.5.0`) is enough for every site running this plugin to see a normal "Update available" notice in the WordPress dashboard.

## [1.4.1] - 2026-09-23

### Fixed
- **Inhoudsbeheer** tab: PHP warning `Undefined array key "show_reading_time"` on sites whose content options were saved before the "Leestijd tonen" setting existed. The stored options are now merged with the defaults. No change in behaviour or settings.

## [1.4.0] - 2026-09-22

### Added
- **Dashboard Layout** tab: dashboard widgets can now be hidden **per role**, and the list is no longer limited to WordPress' own six widgets. Every widget that appears on a site's dashboard — including those added by other plugins (e.g. Elementor, SMTP plugins, SEO plugins) — is detected automatically and listed in a widget × role matrix, with "Alles"/"Niets" quick-select links per role column. A checked box means the widget is hidden for that role; the Administrator role can be configured too.
  - Detection runs on WordPress' own `do_meta_boxes` action for the dashboard, which fires after all plugins have registered their widgets regardless of the hook priority they used, so late-registered widgets are caught as well. Detected widgets are stored in the `bmi_detected_dashboard_widgets` option.
  - A widget that has never been shown yet (e.g. from a freshly installed plugin) appears in the list after the dashboard has been opened once by a user who gets to see it.

### Changed
- The six fixed "Widgets verbergen" checkboxes (which applied to all roles at once) are replaced by the new matrix. Existing choices are migrated automatically: previously hidden widgets stay hidden for all roles, so nothing changes on existing sites until the matrix is saved.

## [1.3.3] - 2026-09-22

### Fixed
- **Rollen & Rechten** tab: the direct-access block (HTTP 403 "Je hebt geen toegang tot deze pagina.") also hit admin pages that are not menu items, so non-administrator roles (e.g. Editor) could see an allowed menu item but could not actually work with it: editing any post/page/custom post type item (`post.php`), saving plugin forms (`admin-post.php`), uploading media (`async-upload.php`) and saving settings (`options.php`) were all refused.
  - The check now runs on `admin_menu` (priority 999, right before items are hidden) instead of `admin_init`, so the site's complete admin menu is known and only real menu/submenu pages are blocked. This works generically on any site, regardless of which plugins or post types are installed.
  - Technical endpoints that don't load the admin menu (such as `admin-post.php` and `async-upload.php`) are no longer affected; WordPress' own capability checks still protect them.
  - Editing an item (`post.php`, including page builders such as Elementor) is now checked against the menu item of its post type (e.g. `edit.php?post_type=page`): if a role may see that post type's menu item, it may edit its items; if not, direct access stays blocked.
  - Pages that are not part of the admin menu at all fall back to WordPress' own capability checks instead of being blocked outright.
  - New, not-yet-allowed menu items remain blocked by default (security-first behaviour unchanged).

## [1.3.2] - 2026-09-22

### Fixed
- **Dashboard Layout** tab: when a custom admin menu width was set (without also enabling "Submenu's uitklappen"), the flyout-offset CSS also repositioned the submenu of the currently active top-level menu item (e.g. a third-party plugin's own menu, such as ACF), causing it to stay stuck as a floating panel over the page content after clicking, instead of appearing inline right underneath it as WordPress normally does. The active item's submenu is now always forced back to normal inline positioning.

## [1.3.1] - 2026-09-22

### Changed
- **Rollen & Rechten** tab: the menu/submenu matrix now works as an allow-list ("mag zien") instead of a deny-list ("verborgen"). Checking a box now means the role may see/use that item; anything left unchecked is hidden and blocked. This also means new menu items that appear later (e.g. from another plugin) are hidden by default until deliberately allowed, matching the security-first approach of the rest of the suite.
- Existing configurations are migrated automatically on first load after updating: everything that was visible stays visible, nothing changes on sites that haven't touched this tab yet.
- Checking a top-level item now also checks its submenu items for that role by default (still adjustable per submenu item afterwards); added "Alles"/"Niets" quick-select links per role column.
- The site's own Dashboard (`index.php`) and each user's own profile page (`profile.php`) are now always excluded from this matrix, to prevent an admin from accidentally locking a role out of their own profile or the page they land on right after logging in.

## [1.3.0] - 2026-09-22

### Added
- New **Rollen & Rechten** tab: per-role (all roles except Administrator) control over what a site's admin can see and do, since role setups and installed plugins differ per site:
  - A full menu/submenu matrix, built dynamically from each site's own admin menu (including entries WordPress registers as submenus, such as "Nieuw toevoegen" and "Widgets") — not a fixed, hardcoded list.
  - Hiding a menu/submenu item now also actively blocks direct access to that page's URL (HTTP 403), instead of only hiding the link.
  - Per-role restriction switches for this plugin's own optional features: the duplicate button, SVG uploads, and AVIF uploads — enforced both in the UI and at the actual upload/action level.

### Changed
- The former "hide menu items per role" control (top-level items only) moved from the Dashboard Layout tab into the new Rollen & Rechten tab, and now also covers submenus. Existing choices are migrated automatically; nothing needs to be reconfigured.

## [1.2.1] - 2026-09-17

### Fixed
- **Log in/uit | Registreer** tab: the "disable author archives" option did not actually stop username enumeration on sites with pretty permalinks. WordPress core's own `redirect_canonical()` (also hooked on `template_redirect`, registered earlier) was redirecting `?author=1` requests to `/author/username/` first, leaking the username via the `Location` header before this plugin's own check could run. Now also intercepts the `redirect_canonical` filter to cancel that redirect, so the username is never exposed.

## [1.2.0] - 2026-09-11

### Added
- **Log in/uit | Registreer** tab: brute-force protection (temporary IP lockout after too many failed login attempts, with configurable attempt limit and lockout duration).
- **Log in/uit | Registreer** tab: custom login screen appearance (logo, logo size, background color, accent color, background image), using the media library and a color picker.
- **Log in/uit | Registreer** tab: option to disable author archive pages, preventing username enumeration via `/?author=1`.
- **Dashboard Layout** tab: hide top-level admin menu items per user role (role/menu matrix, administrator excluded for safety).
- **Dashboard Layout** tab: toggle default dashboard widgets on/off (Welcome panel, At a Glance, Activity, Site Health, Quick Draft, WordPress Events and News).
- **Dashboard Layout** tab: custom dashboard widget with your own logo and/or welcome message, shown at the top of the dashboard.
- **Dashboard Layout** tab: adjustable admin menu font size.
- **Inhoudsbeheer** tab: optional "Leestijd" column in the posts/pages list, showing word count and estimated reading time.
- New **E-mail** tab: custom sender name and sender email address for all outgoing WordPress emails.

## [1.1.3] - 2026-09-09

### Fixed
- **Log in/uit | Registreer** tab: the previous fix for core PHP warnings after logout only covered requests through the custom login URL. The warnings could still appear on the (allowed, unblocked) direct `wp-login.php?loggedout=true` request used right after logging out. Warning suppression now runs on WordPress' own `login_init` hook, so it applies no matter how the login page is reached.

## [1.1.2] - 2026-09-09

### Fixed
- **Log in/uit | Registreer** tab: suppressed harmless WordPress core "Undefined variable" PHP warnings (`$user_login`, `$error`) that could appear on the custom login page, most noticeably right after logging out on PHP 8+ environments.

## [1.1.1] - 2026-09-09

### Fixed
- **Dashboard Layout** tab: the "expand submenus downward" option no longer opens on mouse hover. Submenus now only appear for the currently active menu item (after a click), with no animation.

## [1.1.0] - 2026-09-09

### Added
- **Dashboard Layout** tab: option to make submenus expand downward (pushing lower menu items down) instead of flying out to the right and overlapping page content.
- **Dashboard Layout** tab: ability to insert "whitespace" spacers between admin menu items in the drag-and-drop order list, to visually group related menu items.

## [1.0.0] - 2026-09-09

### Added
- Initial release of the plugin, with a tabbed settings screen (inspired by Admin and Site Enhancements).
- **Log in/uit | Registreer** tab: change the default `/wp-login.php` URL to a custom, secret login URL.
- **Dashboard Layout** tab:
  - Set a custom admin menu width in pixels.
  - Toggle a dark mode color scheme for the WordPress dashboard.
  - Reorder top-level admin menu items via drag-and-drop.
  - Add custom spacing (in pixels) between admin menu items.
- **Inhoudsbeheer** tab:
  - One-click duplication of posts and pages (copies content, meta and taxonomies, saves as draft).
  - Toggle to allow SVG uploads in the media library.
  - Toggle to allow AVIF uploads in the media library.
