=== Photo Gallery Plugin ===
Contributors: babybrands
Tags: gallery, photos, shortcode, winners, distributor
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Winner photo gallery with an admin approval workflow, a winner photo release form, a legacy manual gallery shortcode, and a distributor registration form with database storage.

== Description ==

The plugin provides the following:

* **Approved winner gallery** — Displays approved submissions in a responsive grid with lightbox-style viewing (`[winner_photo_gallery]`).
* **Winner photo release form** — Public form for winners to submit details and a photo; submissions are **pending** until approved in **Winners Gallery** under the WordPress admin menu.
* **Distributor registration** — Multi-step distributor application form; data is stored in prefixed database tables created automatically on load when missing. Reference SQL is included under `schema/distributor_registration.sql`.
* **Legacy gallery** — Optional shortcode that outputs a simple grid from explicit media attachment IDs (`[photo_gallery_plugin]`).

== Installation ==

1. Copy the `photo-gallery-plugin` folder into `wp-content/plugins/`.
2. Activate the plugin from **Plugins** in the WordPress admin.
3. Add the shortcodes you need to a page or post (see below).

== Shortcodes ==

=== `[winner_photo_gallery]` ===

Shows approved winner photos from the submissions table.

* `limit` — Number of items (0–60). Default: `24`.
* `columns` — Grid columns (1–6). Default: `3`.
* `order` — `ASC` or `DESC` by creation date. Default: `DESC`.

Example:

`[winner_photo_gallery limit="12" columns="4" order="DESC"]`

=== `[winner_photo_release_form]` or `[winnerphotorelease]` ===

Renders the winner photo release form (and optional recent winners strip).

* `show_winners` — Set to `0`, `false`, or `no` to hide the winners preview. Default: shown.
* `winner_limit` — How many recent approved winners to show (1–12). Default: `4`.
* `contact_email` — Contact address shown on the form. Default: `info@babybrands.com`.

=== `[distributor_registration_form]` or `[distributorregistration]` ===

Renders the distributor application form (multi-step). On successful submit, visitors are redirected with a success flag and see an inline thank-you message on the same page unless you override the redirect.

* `thank_you_url` — Optional absolute URL to redirect to after a successful submission. If empty, the user stays on the current page and sees the built-in thank-you view.
* `contact_email` — Shown on the thank-you screen. Default: `info@babybrands.com`. Developers may adjust behavior with the `pgp_distributor_registration_contact_email` filter.

Example:

`[distributor_registration_form contact_email="info@example.com"]`

=== `[photo_gallery_plugin]` (legacy) ===

Outputs a gallery from WordPress media library attachment IDs.

* `ids` — Comma-separated attachment IDs (required for output).
* `columns` — 1–6. Default: `3`.

Example:

`[photo_gallery_plugin ids="12,34,56" columns="3"]`

== Admin ==

**Winners Gallery** (admin menu) lists winner submissions. Use it to approve or reject entries so they appear in `[winner_photo_gallery]` when approved.

== Changelog ==

= 1.1.0 =
* Distributor registration shortcode, styling, thank-you flow, and database layer (tables created automatically when missing).
* Continued winner gallery, release form, and admin approval workflow.

= 1.0.0 =
* Initial release: winner gallery, release form, and legacy photo shortcode.
