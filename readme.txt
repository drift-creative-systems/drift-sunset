=== Drift: Sunset ===
Contributors: driftcreativesystems
Tags: post expiry, expire posts, unpublish, content expiration, schedule
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Set an expiry date and time on any post or page. When it passes, the post is switched to Draft.

== Description ==

Offers end, events finish and job adverts close, but the posts stay live until someone remembers to take them down. Drift: Sunset adds a **Post Expiry** box to the edit screen. Pick a date (and a time, if you like), and when it passes the post is switched back to Draft. Nothing is deleted, so you can republish it with a new date at any time.

Features:

* **Post Expiry box** on posts, pages and public custom post types, in both the block editor and the classic editor.
* **Time defaults to 23:59** when you only pick a date, so a post stays up for the whole of its last day.
* **Expires column** on the posts and pages lists.
* **Tools → Drift: Sunset** lists upcoming expiries (soonest first, overdue ones flagged) and the last 20 posts to expire. Editors and administrators can see it.
* **Required expiry by category**: tick categories such as Events under the Settings tab, and posts in them can't be published, scheduled or updated without an expiry date. Drafts can still be saved without one.
* **Cron health check**: the overview warns you if WP-Cron isn't running, which is the usual reason posts don't expire on time.
* Published, scheduled and private posts are switched to Draft. Each expiry is a single WP-Cron event, with an hourly check as a safety net for events lost to migrations, restores or cron failures.
* Everything is removed when you delete the plugin.

= Privacy =

Drift: Sunset doesn't connect to any external service, track you or collect data. It adds nothing to the front end of your site.

= For developers =

* `drift_sunset_post_types` (filter): which post types get the expiry box, column and overview. Default: all public post types except attachments.
* `drift_sunset_required_categories` (filter): category term IDs that need an expiry date before publishing. Runs after the Settings tab value.
* `drift_sunset_post_expired` (action, `$post_id`, `$post`): fires after a post is switched to Draft. Use it for notifications, webhooks or cache purges.

Example, to only offer expiry on posts and an `event` post type:

`add_filter( 'drift_sunset_post_types', function () { return array( 'post', 'event' ); } );`

Source code, issues and pull requests: [github.com/drift-creative-systems/drift-sunset](https://github.com/drift-creative-systems/drift-sunset).

= Copyright =

Copyright © 2026 Drift Creative Systems Ltd

Drift Creative Systems Ltd. Registered in England and Wales, company number 17548220. Registered office: 167-169 Great Portland Street, 5th Floor, London W1W 5PF

== Installation ==

1. In your WordPress admin, go to Plugins → Add New, search for "Drift: Sunset", then install and activate it.
2. Edit a post or page. Set a date in the **Post Expiry** box in the sidebar and save.
3. See what's coming up under Tools → Drift: Sunset.

== Frequently Asked Questions ==

= What happens when a post expires? =

Its status changes to Draft. It's no longer visible on the site, but nothing is deleted. The "Expires" column and the overview show when it expired. To put it back, set a new expiry date (or clear it) and publish it again.

= My post didn't expire on time =

Expiry runs on WP-Cron. By default WP-Cron only runs when someone visits the site, so a quiet site can expire posts a few minutes late. If WP-Cron is turned off in wp-config.php (`DISABLE_WP_CRON`), your host's server cron must call `wp-cron.php`, or nothing expires. Tools → Drift: Sunset warns you when the hourly check is overdue.

= Can I expire a post at a set time, not just a date? =

Yes. Set the time field as well. If you only pick a date, the time defaults to 23:59 in the site timezone (Settings → General).

= Who can see Tools → Drift: Sunset? =

Editors and administrators (the `edit_others_posts` capability). Only administrators see the Settings tab.

= Does the required expiry check unpublish live posts? =

No. It stops new posts in a required category from being published without a date. A post that's already live gets a warning, but it stays live.

= Does deactivating the plugin keep my expiry dates? =

Yes. Deactivating stops the scheduled events, but the dates stay with each post. When you reactivate, the hourly check picks up anything overdue. Deleting the plugin removes the dates and its settings.

== Screenshots ==

1. The Post Expiry box in the block editor sidebar.
2. Tools → Drift: Sunset, with upcoming and recently expired posts.
3. The Settings tab, where you choose the categories that need an expiry date.

== Changelog ==

= 1.0.0 =
* First public release: expiry date and time per post, switch to Draft, Expires column, overview of upcoming and recent expiries, required expiry by category and a cron health check.

== Upgrade Notice ==

= 1.0.0 =
First public release.
