=== Baltic Stairbuilder ===
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 2.43.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Staircase configurator with instant indicative pricing and PDF quote generation.

== Changelog ==

= 2.43.0 =
* Customer PDF layout refresh (better_stair_builder_pdf.png mockup): Your Details moved above the quote box with Project Delivery as an ordinary row (bordered badge removed); the indicative-quote disclaimer moved into the quote box (its mini-heading removed); customer notes become a full-width band — short notes sit between the top section and the spec tables, long notes (over 280 characters or 4 lines) move below the spec tables so only they flow to a second page; the staircase plan scales to a taller box aligned with the quote box; the footer band and its two settings removed.
* New setting: PDF — Top strip contact names (empty default). When set, the top strip reads "Call {phone} and ask for {names} | {hours}"; when empty the clause is omitted.

= 2.42.0 =
* PDF/admin: material labels resolved row-exactly (code+price) at submit time and frozen with the lead — same-code materials (e.g. two pine tread thicknesses) no longer print the wrong variant.

= 2.41.1 =
* Price updates immediately when floor height or going changes the valid riser count, instead of waiting for the risers dropdown to be touched.
