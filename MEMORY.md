# MEMORY.md — Drift: Sunset

Decisions, gotchas and open items that aren't obvious from the code. Keep entries short and dated.

## Key decisions

- **2026-11-02: Forked from Bonsai Post Expiry 1.2.1 as a free WordPress.org plugin.** Bonsai Post Expiry stays on Bonsai client sites. No migration: all meta keys, options, cron hooks and filters have new `drift_sunset_` names, so both can even run on one site without touching each other's data (each would show its own box).
- **2026-11-02: Name "Sunset".** "Sunsetting" content is the usual term for retiring it on a date. Accent fuchsia `#d946ef` / `#a21caf`, as the warm hues were taken (Clearview orange, Curtain red, Control amber).
- **2026-11-02: Screen under Tools, not Settings.** Editors need the overview (`edit_others_posts`) and can see the Tools menu. The Settings tab is `manage_options` only, matching options.php's default for the option group.
- **Carried over from Bonsai Post Expiry (2026-10-08):**
  - Expire to Draft, never trash or delete. It's reversible.
  - Single event per post plus an hourly sweep. `expire()` re-reads the meta, so stale or duplicate events are harmless.
  - Saves need the box's nonce, so quick edit, REST-only saves and the plugin's own `wp_update_post()` can't wipe or re-schedule an expiry.
  - Required expiry blocks publishing, not drafts. Block editor: lock saving while the pre-publish panel is open or the post is already live, plus `editor.preSavePost` where it exists. Classic: JS on `#publish`, with a server fallback in `wp_insert_post_data` that never unpublishes.
  - `maybe_upgrade()` on `init`: updates don't run activation hooks, so each new version re-checks the sweep. One autoloaded option read per request.
  - Cron health is judged by how overdue the sweep is (warns past 1 hour), not by `DISABLE_WP_CRON`. Managed hosts set that constant as standard alongside a server cron.

## Gotchas

- With `DISABLE_WP_CRON` and no server cron, nothing expires. Server crons that request `wp-cron.php` over HTTP can be blocked or cached by a WAF/CDN.
- WP-Cron only runs on page loads. Low-traffic sites can expire a few minutes late.
- The block editor doesn't re-render meta boxes after saving. "Expired on …" only appears after the edit screen is reloaded.
- The block editor saves the post over REST *before* it posts meta boxes, so the server can't see the box's date during the status change. That's why the block editor check is JS only.
- Block editor with pre-publish checks off, on a WP version without `editor.preSavePost`: a required post can be published without a date.
- REST/API, Quick Edit and bulk edit publishes aren't checked for required expiry.
- The sweep processes at most 100 posts per hourly run.
- Deactivating clears all queued events. After reactivating, future expiries only fire through the hourly sweep (up to an hour late) until each post is re-saved.
- Tools pages don't print the "Settings saved." notice themselves, so `Drift_Sunset_Admin::render()` calls `settings_errors()`.

## Open items / tech debt

- [ ] Screenshots for WordPress.org (`.wordpress-org/screenshot-1..3.png`).
- [ ] Run Plugin Check and PHPCS before submitting to WordPress.org.
- [ ] No PHPUnit tests: `save()` (timezone, default time, invalid date), `expire()` (stale event, draft/trashed post), `sweep()`, `Drift_Sunset_Required::enforce()`.
- [ ] `expire()` on a post that isn't expirable (e.g. already a draft) still records `_drift_sunset_expired_at`, so it appears under "Recently expired" although nothing changed.
- [ ] Possible: settings for the default time and post types, email notification on expiry, "expire to" status other than Draft.
