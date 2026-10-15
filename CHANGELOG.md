# Changelog

Versions follow [semantic versioning](https://semver.org/). Within API version 1, nothing in [core/api.php](core/api.php) is removed or changed ([docs/en/compatibility.md](docs/en/compatibility.md)). Any change to the HTML the core prints for themes is listed under **HTML changes**.

## 1.3.0

- **The page editor shows the page as visitors will see it, and sections can be dragged.** Pages → a page now has the form on the left and the page itself on the right, redrawn about half a second after every change, on a computer or phone screen. The line between them can be dragged (or moved with the arrow keys, a double click puts it back) to give more room to the form or to the page, and both sides follow the width they get; the choice is remembered. Both sides scroll with the design system's thin scrollbar: a line of 2px in the brick colour, with no arrows (the page beside the form always has one, even when its theme hides its own). Where the theme allows it (the default theme does, for the home page), the sections are rows in a list: drag the handle to a new place, or focus it and use the arrow keys, and the page follows at once. On the page itself, hovering a section shows its name and a handle to drag, and clicking it opens its fields. A theme chooses which sections can move ([docs/en/themes.md](docs/en/themes.md#letting-the-client-reorder-the-sections)); a theme that says nothing keeps its fixed order and everything works as before.
- **The client adds sections.** In the page editor, **Add section** (and a "+" between sections, in the list and on the page itself) opens a gallery of nine kinds with a drawing of each: text, photo with text, cards, questions and answers, testimonials, call to action, numbers, photo gallery and YouTube video. A section can be moved like any other, duplicated, switched off or removed (with **Undo**); a section of the theme that holds the same thing as one of the kinds can be duplicated too. The core prints them as plain HTML with no CSS and no script and with stable class names, and a theme draws them its own way with a file in `templates/blocks/`; a theme turns them on per page type with `'blocks' => true` and prints a page with `pb_sections()`. New in the API: `pb_sections()`, `pb_block()`, `pb_youtube_id()` and `PbGroup::piece()`/`type()`; the content API gives `blocks`. A page that never chose an order no longer stores one (it is "the theme's own"), so its draft equals the page.
- **The sign-in pages have a wall of bricks fading into the background**, in the colour of the text: dark lines on the light theme, light lines on the dark one.
- **The page beside the form follows almost at once.** A section that is moved shows its new place in about a hundredth of a second, with no page loaded; typing shows in about a quarter of a second, because only the sections that changed are replaced in the page that is already there (the rest keeps its scroll and its pictures). A page that cannot be patched is loaded again behind the old one and takes its place without a flash, and a request that is no longer the latest is dropped, so the page never gets stuck on an old state. A theme can switch off what waits for a scroll to show itself while the client edits: `<html>` has class `pb-editing` in the preview.
- **Menus are a split screen too.** Pages → Menus is redone: a card for each menu with one line for each item (drag the handle, or use the arrow keys on it), a list to add a page with one click, a note on an item whose page is a draft, and the site beside it showing the menus as they are being typed, on any page of the site you choose, scrolled to the menu being edited (top or bottom). Like pages, menus save a draft by themselves and only change for visitors when you click **Publish menus**; a draft can be thrown away.
- **Draft and Publish for every page.** Nothing a person types reaches visitors until they click **Publish**. The changes of a published page save themselves as a draft a moment after each change (the bar says when), survive closing the browser, and can be thrown away (**More actions → Discard unpublished changes**); the version that was on the site goes to the history each time. A page that was never published is simply saved, as before, and **More actions** can take a published page off the site. The pages list marks the pages that have changes waiting.
- **A new text editor in the panel**, replacing the old one. It has a toolbar, a floating menu over the selected text and a "/" menu (type `/` at the start of a line to insert a heading, list, quote, divider, picture or table). New in it: **tables** (with a header row and tools to add and remove rows and columns), **dividers**, underline, strikethrough and **pictures from the media library**, which it asks you to describe for people who cannot see them. It works with the keyboard, on a phone and in light or dark. Text written with the old editor opens as it was and stays exactly as saved until someone edits it.
- **HTML changes.** Rich text can now contain `table`, `thead`, `tbody`, `tr`, `th`, `td`, `hr`, `s` and `img`. A list item or table cell with a single paragraph holds just its text, without the `<p>`. A picture's `<img>` is written by PageBrick from the media library (`alt`, `width`, `height`, lazy loading) and follows the site if it moves. Text that uses none of this is printed exactly as before. A theme should style tables and pictures inside its text ([how](docs/en/themes.md#rich-text-style-what-your-client-can-write)); the default theme already does.
- The content API gives the pictures inside rich text full addresses, so they work on another domain.
- For themes: `$page->order([...])` and `pb_section($name, $html)` (new in the API, nothing existing changes); the content API adds `section_order` to a page only when the client chose an order. Visitors get exactly the same HTML as before, in the same order, until a client moves something.
- For developers: the editor is TipTap on ProseMirror (MIT), built from [tools/editor](tools/editor) into one file (`core/assets/editor.js`); nobody needs Node to run a site.

## 1.2.3

- The front page of the repository now shows badges, a short film of the panel, the five pillars behind the update promise and a guide for contributors ([CONTRIBUTING.md](CONTRIBUTING.md)). Only texts and pictures changed; nothing in a site changes.

## 1.2.2

- **A theme sent again shows its new picture on the Themes screen.** The picture's address never changed, so the browser (or the hosting in front of the site) kept showing the old one even though the file had been replaced, and it looked as if the theme had to be sent twice. The address now carries the file's time, like the theme's CSS and scripts.

## 1.2.1

- PageBrick is now described as what it is: a simple, open-source CMS for any kind of website, for anyone to install and for developers to extend. Only the texts of the README and of the package changed; nothing in a site changes.

## 1.2.0

- PageBrick is now released under the MIT License ([LICENSE](LICENSE)): use it, change it and share it in any project, as long as the notice stays. Nothing changes for the sites that already run it: no setting, no theme and no plugin is touched by the update.

## 1.1.2

- On the Updates screen, "Continue" and "Go back to version X" now sit side by side as buttons, and after an update "Go back" names the version it will really bring back. On Themes and Plugins "Go back" is a button with an icon too, not a link.
- A new PageBrick version shows up by itself, in the bell, on the dashboard and in the side menu, within about an hour, without pressing "Check now": the check PageBrick already ran every hour now asks the catalog instead of reading a copy up to 12 hours old.
- A theme or a plugin that is sent from a .zip, installed from the catalog or put back, and PageBrick itself, are seen at once by every PHP process of the host. On hosts that run several, some visitors kept getting the old version for a few minutes after an update, which looked like an upload that did nothing until it was sent again. Now, for 15 minutes after files are replaced, every request makes PHP's code cache check the files, and the files get the time of the install.
- A .zip is sent once per install instead of on every step; when a step gets an answer that is not PageBrick's (a firewall page, a time-out), the log says the HTTP status; after a stopped install the form comes back with the file still chosen.

## 1.1.1

- The "Continue" under the steps of an install or an update (a theme, a plugin or PageBrick itself) is now a main button with an arrow, like the buttons of the rest of the panel, instead of a bare link; it takes the focus, so Enter goes on.

## 1.1.0

- A new, cleaner panel. A narrow bar of icons (Content, Plugins, Settings) with the screens of the section you are in beside it, and a header with only the PageBrick icon, the site's name and whether it is on the air, "View site", a bell for administrators (a new version, a plugin that was switched off) and the account menu. On a phone the bar and the list open from a button in the header. The sign-in screen and the installer keep the whole logo.
- The sign-in, forgot-my-password and new-password screens are redone: a card in the middle of the screen with the site's name, icons in the fields and a way back to the site, in light or dark. They do not show the PageBrick version to people who have not signed in.
- "Appearance & contact" is now two screens. **Contact** (WhatsApp, phone, e-mail, address, opening hours, social links and footer) stays under Content, for whoever edits the site, at the same address as before. **Appearance** (the site's name, logo, icon, color, font style and share image) moves to Settings and is for administrators only: editors can no longer change the look. Saving one screen never touches what the other holds. A theme can send one of its own setting groups to Appearance with `'screen' => 'appearance'`; without it the group stays in Contact, where it was (an addition: themes keep working as they are).
- The dashboard shows how many pages are published and how many are drafts, how many files are in the media library and how many languages the site has, the pages edited last and shortcuts.
- A discreet bar at the bottom of the site, only for people signed in to the panel (visitors never get it, and nothing changes in what they receive): the PageBrick brick takes them back to the panel in the same tab, and there are quick actions: edit the page being seen, reload, a live view and the site in a phone-sized window. With the live view on, the page reloads by itself a few seconds after something is saved in the panel (a page, the contact details, a menu, a photo, the appearance, a blog post), unless someone is typing on it; it can be switched off. It also says when the site is under construction or in maintenance, in place of the old strip. A theme or plugin turns it off with `pb_add_filter('admin_bar', fn() => false);`; plugins can add what they own to what the bar watches with the `live_stamp` filter (an addition to the API).
- Light, dark or the system's, chosen in the account menu with three icons (sun, moon and a computer screen). The system's is where everyone starts, and it follows the computer on its own; the choice is kept with the account and never handed to the next person on a shared computer.
- Plugins keep working as they were: their screens appear under the Plugins section, and the colors and classes they draw with (`--bg`, `--card`, `--accent`, `.card`, `.help`, `.badge`…) are all still there. Nothing changes in the database or in what the site shows visitors.
- Easier to use: a "skip to content" link, headings and names for what a screen reader reads, colors checked in both themes, and link-like buttons large enough to tap.

## 1.0.6

- The page editor fits a phone's width: on pages with a rich text field, the toolbar no longer makes the whole page wider than the screen (the browser zoomed out and the text turned tiny); its buttons wrap instead.

## 1.0.5

- E-mails now leave from `no-reply@yourdomain` unless a sender is set in Settings → E-mail.
- Plugins have their own icon in the top bar (a plug), apart from the gear: their screens are listed there, for whoever each plugin allows. Editors can use plugin screens and plugin settings; only administrators install, switch on and off, update and delete plugins ("Manage plugins" in that same menu).
- Each person's preferences are kept with their account and follow them to any browser: the light/dark choice (new) and the panel's language. A new person starts in the site's language. What a browser remembers is never handed to the next person who signs in on it (whoever chose dark before 1.0.5 chooses once more).
- The installer's language choices line up (the round button sat in the middle of each box).
- For plugins (API, only additions): `pb_http()` makes a request to another service (https only, with headers, an optional POST body, a size limit, and a way for tests to answer instead of the network).

## 1.0.4

- Sending a theme or plugin (.zip), updating one or installing one from the catalog shows each step as it happens, like PageBrick's own updates: sending or downloading, checking the signature or the package, keeping a copy, installing and, when it's the one in use, opening every page with it. If a page breaks, the previous version is put back right there and the screen says why.
- Backups made in the same second are told apart by version, so "Roll back" always takes the newest.

## 1.0.3

- The panel's language is independent of the site's: each account has its own (set when it is made, changed with the new globe in the top bar or in My account), and "the same as the site" is gone. Accounts that never chose one keep the language they saw. The sign-in screen follows the browser's language.
- Importing a theme's content written in another language offers "Import and make English the site's language": one click, and the panel stays as it is.
- Updating PageBrick on the Updates screen shows each step as it really happens (download, signature, copy, swap, every page opened), like the demo on pagebrick.org.
- Automatic updates also hand the page over first on LiteSpeed servers (as on PHP-FPM), so no visitor waits for the check or the update. Settings → Updates says whether this server makes a visitor wait.

## 1.0.2

- Password fields have an eye: open to show the password, closed to hide it again (sign-in, account, users, e-mail, installer).
- Changing the site's main language no longer changes the panel's: whoever never picked a panel language keeps the one they had.
- Ready-made content can say which language it is written in (`'locale' => 'en'`); importing it on a site with another main language is refused with a clear message, instead of putting English texts where the Portuguese ones belong.
- Themes that can't be deleted say why: the active one (activate another first) and the default one (the safety net).

## 1.0.1

- The dashboard announces new versions, and the gear icon gets a dot. Settings → Updates chooses how PageBrick updates: manual, automatic for fixes only (1.0.x, the default) or automatic for everything. An automatic update has the same backup, page check and undo as the button, never retries a version that was undone, and e-mails the administrators. It runs after a request, at most once an hour.
- Settings move behind a gear icon (administrators only): Address and languages, Themes, Plugins, Updates, E-mail, Users.
- The site's official address can be changed (to https:// after installing without a certificate).
- The main language can change after installing: ready-made content finds the pages at their old addresses (/sobre on a site switched to English) and moves them, instead of making copies.

## 1.0.0

First public version.

- Step-by-step installer (language, server check, database with readable errors, site), in Portuguese, English or Spanish, with a finished example site.
- Pages with fields, hideable sections, history, drafts and preview; media library with WebP; menus; SEO basics.
- "Forgot my password" on the sign-in screen: an e-mailed link that works once, for one hour (only its hash is stored), with the same answer whether the e-mail has an account or not.
- Sites in more than one language: extra languages under /pt-br, /es-es or /en-us, page translations, translated menus and Appearance & contact texts, `hreflang` tags, and `pb_language_links()` for themes.
- Light/dark switch in the panel header (remembered per browser; follows the system until used).
- Default theme; themes as packages with preview, test before activation and circuit breaker.
- Plugins with hooks, slots, routes, panel screens, settings and their own tables; circuit breakers and safe mode.
- Official plugins: contact form and blog with categories.
- Signed catalog, .zip upload, backups, roll back, automatic undo of broken updates, and core updates that check the whole site.
- Themes can replace any template or stylesheet a plugin shows on the site, and bring ready-made content (`demo.php`) with translations, which can also turn a standard page into another one (`'replaces'`).
- "Under construction" and "Maintenance" switches on the dashboard; themes can draw the notice in `templates/closed.php`.
- Content API (JSON) for headless front ends: `/api/v1/site`, `/api/v1/pages`, `/api/v1/pages/{slug}` and, with the Blog, `/api/v1/blog`.
- Public API version 1 frozen in `tests/fixtures/api-v1.json`.
