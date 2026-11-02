![Drift Creative Systems](.github/banner.jpg)

# Drift: Sunset

Free WordPress plugin by **Drift Creative Systems**. Adds an expiry date and time to posts. When it passes, a published, scheduled or private post is switched to **Draft**. Upcoming and recent expiries are listed under **Tools → Drift: Sunset**.

- WordPress.org slug: `drift-sunset` (submission pending)
- Licence: GPL-2.0-or-later
- Requires WordPress 6.0+, PHP 7.4+

The user-facing documentation is [readme.txt](readme.txt), which WordPress.org displays. This file is for developers.

## Structure

```
drift-sunset.php                              Bootstrap: duplicate-install guard, constants, requires, init, (de)activation hooks
includes/
  class-drift-sunset-expiry.php               Meta box, save + scheduling, cron callbacks (expire, sweep), list column, overview queries, activation/upgrade
  class-drift-sunset-settings.php             Settings tab (manage_options): required categories, drift_sunset_settings option
  class-drift-sunset-required.php             Required expiry by category: classic-editor server fallback, notices, shared message
  class-drift-sunset-admin.php                Tools → Drift: Sunset: Overview + Settings tabs, cron warnings, admin footer, Plugins-row link
assets/
  admin.js                                    jQuery: default time on date pick, "Clear expiry", classic-editor required check
  admin-block.js                              Block editor required check (wp.data lock + notice, editor.preSavePost). Only loaded when required categories apply
  admin.css                                   Box fields, Expired label, overview screen
uninstall.php                                 Removes options, post meta and cron events, on every site in a network
```

`Drift_Sunset_Expiry::init()` runs straight from the bootstrap, not on `plugins_loaded`, because the cron callbacks must be hooked when `wp-cron.php` fires them.

## How expiry works

1. Saving the edit screen with a date stores a UTC timestamp in `_drift_sunset_expires` and schedules a single `drift_sunset_expire_post` event with the post ID. Past dates are scheduled for "now", so they fire on the next cron run.
2. `Drift_Sunset_Expiry::expire()` re-reads the meta (the event may be stale), switches `publish` / `future` / `private` to `draft`, fires `drift_sunset_post_expired`, deletes `_drift_sunset_expires` and stores `_drift_sunset_expired_at`.
3. `drift_sunset_sweep` (hourly) catches anything overdue whose single event was lost. Max 100 posts per run.
4. `save()` bails without the box's nonce. That covers quick edit, REST-only saves and the plugin's own `wp_update_post()` during expiry, so expiry can't re-trigger a save.

## Stored data

| Key | Where | What |
|---|---|---|
| `_drift_sunset_expires` | post meta | Pending expiry, UTC unix timestamp |
| `_drift_sunset_expired_at` | post meta | When it actually expired. Cleared when a new expiry is set |
| `drift_sunset_version` | option (autoloaded) | Last version that ran `maybe_upgrade()` |
| `drift_sunset_settings` | option (not autoloaded) | `required_categories`: term IDs that need an expiry date |
| `drift_sunset_expire_post` / `drift_sunset_sweep` | cron | Per-post single event / hourly safety net |

All of it is removed in `uninstall.php`. Never rename these keys or hooks once released: sites have live data and queued events under them.

## Hooks

| Hook | Type | Purpose |
|---|---|---|
| `drift_sunset_post_types` | filter | Post types that get the box, column and overview. Default: public types except attachments |
| `drift_sunset_required_categories` | filter | Category term IDs that need an expiry date before publishing. Runs after the Settings tab value |
| `drift_sunset_post_expired` | action | `$post_id`, `$post`. Fires after a post is switched to Draft |

```php
// Email the author when their post expires.
add_action( 'drift_sunset_post_expired', function ( $post_id, $post ) {
	$author = get_userdata( $post->post_author );
	if ( $author ) {
		wp_mail( $author->user_email, 'Post expired', get_the_title( $post ) . ' is now a draft.' );
	}
}, 10, 2 );
```

`Drift_Sunset_Expiry::DEFAULT_TIME` (`23:59`) is the time used when only a date is picked. It's a constant, not a setting.

## Development

- No build step. Edit `assets/*.js` / `*.css` directly.
- Coding standard: `phpcs.xml.dist` (WordPress, prefixes `drift_sunset` / `DRIFT_SUNSET`).
- Run **Plugin Check** before every release. The PHPCS-based checks don't work in WordPress Playground (its filesystem can't `flock()`), so use a real install, e.g. WP-CLI with the SQLite drop-in:
  ```sh
  wp plugin install plugin-check --activate
  wp plugin check drift-sunset --include-low-severity-errors --include-low-severity-warnings \
    --require=./wp-content/plugins/plugin-check/cli.php
  ```
  Check a copy built from `.distignore`, not the repo, so dev files don't trip the `file_type` check.
- Expiry unpublishes live content. Test any change to `expire()`, `sweep()` or `save()` with a past date, a future date and a cleared date, on a site where WP-Cron is running.
- Directory guidelines review: the `wp-plugin-directory-guidelines` Claude Code skill.

## Releasing

1. Bump the version in **three** places: the `Version:` header, `DRIFT_SUNSET_VERSION` and `Stable tag:` in `readme.txt`. Add a changelog entry to both `readme.txt` and `CHANGELOG.md`.
2. Merge `develop` → `main`, then publish a GitHub release tagged `vX.Y.Z`.
3. `.github/workflows/release.yml` checks the three versions match, builds `drift-sunset.zip` from `.distignore` and attaches it to the release.
4. Once approved on WordPress.org, set the repo variable `WPORG_DEPLOY=true` and the secrets `SVN_USERNAME` / `SVN_PASSWORD`. Releases then deploy to SVN automatically, and readme/asset changes on `main` sync on their own (`wporg-assets.yml`).

WordPress.org assets (icon, banners, screenshots) live in `.wordpress-org/`. Screenshots still need adding as `screenshot-1.png` (Post Expiry box), `screenshot-2.png` (overview) and `screenshot-3.png` (Settings tab) to match `readme.txt`.

## Licence

GPL-2.0-or-later. See [LICENSE](LICENSE).

Copyright © 2026 Drift Creative Systems Ltd

Drift Creative Systems Ltd. Registered in England and Wales, company number 17548220. Registered office: 167-169 Great Portland Street, 5th Floor, London W1W 5PF
