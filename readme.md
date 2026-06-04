# Screenhots

<img width="1003" height="161" alt="image" src="https://github.com/user-attachments/assets/46cca580-5d9c-4be1-9f19-2d5dbd8af1f3" />


<img width="119" height="100" alt="image" src="https://github.com/user-attachments/assets/50b3cb64-7170-497c-8d07-efef33f6a109" />

# Multilang
Contributors: c-andrejew, Claude Opus 4.8
Tags: multilingual, language, translation, wpbakery, acf, switcher
Requires at least: 5.6
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight, fast multilingual system with per-language URL prefixes (/de/, /en/),
a modern language switcher, and WPBakery + ACF compatible translatable text.

## Description

Multilang gives a WordPress site multiple languages without the weight of a full
translation suite. Content stays on the *same* post/page; each translatable string
holds every language, and the active one is shown based on the URL.

## Features:

* Configure languages with an uploadable flag, full name and code (e.g. "de").
* Mark one language as the **main** language. Every URL gets a prefix, e.g.
  `example.com/de/`.
* Visitors without a prefix are redirected to their language (cookie / browser),
  falling back to the main language.
* Modern, accessible language switcher: `[multilang_switcher]` — flag + name with a
  small dropdown. Inline variant and theme function `mlr_switcher()` included.
* WPBakery elements: "Multilang Text" and "Multilang Title" (one field per
  configured language) plus "Multilang ACF" (outputs a translated ACF field).
* ACF compatible: inline tokens in any text field resolve automatically.
* Compatible with the Impreza theme (uses standard `home_url`, `the_content`,
  `the_title`, menu and WPBakery filters).
* SEO: `hreflang` alternates, correct `<html lang>` and front-end `get_locale()`.

## Translating content

Switcher:           [multilang_switcher]
Inline tokens:      [:de]Hallo Welt[:en]Hello world[:]
Short string:       [ml de="Hallo" en="Hello"]
WPBakery:           add "Multilang Text", "Multilang Title" or "Multilang ACF"
ACF:                put [:de]…[:en]…[:] into any text/textarea/wysiwyg field
Theme code:         echo mlr_t( array( 'de' => 'Hallo', 'en' => 'Hello' ) );
                    echo mlr__( '[:de]Hallo[:en]Hello[:]' );

Inline tokens may be used anywhere: post titles, menu items, widgets, term
descriptions and ACF fields. End a block with `[:]` when it sits inside other text.

## Installation

1. Copy the `multilang` folder into `wp-content/plugins/` (or upload the ZIP).
2. Activate the plugin.
3. Go to **Languages**, add your languages, choose flags, set the main language,
   and save. Saving rebuilds the permalink rules automatically.
4. Place `[multilang_switcher]` in a menu/widget/page where you want the switcher.

## Changelog

= 1.0.0 =
* Initial release.
