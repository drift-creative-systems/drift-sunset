# Changelog

All notable changes to this project are documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-11-02

First release as **Drift: Sunset**, a free WordPress.org plugin by Drift Creative Systems. Based on Bonsai Post Expiry 1.2.1, which carries on separately for Bonsai client sites. There is no data migration between the two.

### Added
- Tools → Drift: Sunset screen with **Overview** and **Settings** tabs, the Drift monogram in fuchsia (`#d946ef`) and the copyright and company details in the admin footer. Editors see the Overview; only administrators see Settings.
- Copyright and company details in the README, readme.txt and the main file header.
- `uninstall.php`: removes the options, expiry post meta and queued cron events, on every site in a multisite network.
- `readme.txt`, `LICENSE` (GPLv2), WordPress.org icon and banners, release workflow with optional SVN deploy.

### Changed
- Renamed to Drift: Sunset: slug and text domain `drift-sunset`, prefix `drift_sunset_` / `DRIFT_SUNSET_` / `Drift_Sunset_`.
- Stored data renamed: post meta `_drift_sunset_expires` and `_drift_sunset_expired_at`, options `drift_sunset_settings` and `drift_sunset_version`, cron hooks `drift_sunset_expire_post` and `drift_sunset_sweep`.
- Hooks renamed: `drift_sunset_post_types`, `drift_sunset_required_categories`, `drift_sunset_post_expired`.
- Overview and Settings moved from the Bonsai menu to Tools → Drift: Sunset. The overview styles that came from the Bonsai Hub are now in `assets/admin.css`.
- Minimum WordPress raised to 6.0.

### Removed
- Plugin Update Checker and Composer. Updates come from WordPress.org.
- Bundled Bonsai Hub menu, header and suite installer.
