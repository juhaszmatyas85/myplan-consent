=== MyPlan Cookie Consent ===
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later

Cookie banner for EU sites: Google Consent Mode v2, script and embed blocking, consent log, WP Consent API, Global Privacy Control. Translatable; works with WPML, Polylang and TranslatePress.

== What it does ==

* Google Consent Mode v2, advanced or basic: all seven consent types default to "denied" (security_storage "granted") in an inline script printed before anything else in the head; a returning visitor's stored choice is applied in the same script, before any Google tag. Optional GTM / GA4 loading, ads_data_redaction, url_passthrough, wait_for_update.
* First layer: "Accept all", "Reject all" with equal prominence, and "Settings" (EDPB cookie banner taskforce, NAIH). No pre-ticked boxes, no cookie wall.
* Categories: strictly necessary (always on), functional, analytics, marketing; each can be switched off for a site. Per-category cookie list in the dialog and via [mpc_cookie_table].
* Withdrawal as easy as consent: floating button, [mpc_settings_link], or any link to #cookie-settings. Withdrawing a category deletes its listed cookies and reloads the page.
* Blocking: enqueued script handles (settings), hand-written <script type="text/plain" data-mpc-consent="analytics">, iframes in content by embed rules (YouTube, Maps, Vimeo…) with an "Allow and show" placeholder; mpc_embed( $iframe ) for theme markup.
* Consent expires after the configured days (default 180, refusals included); "Ask everyone again" bumps the policy version.
* Global Privacy Control: a browser-level refusal is recorded as "reject all" without a banner.
* Consent log for proof of consent: random consent ID (also in the visitor's cookie), choices, policy version, page; no IP or user agent. CSV export, automatic purge.
* WP Consent API: registered, opt-in regime, categories mapped.
* Accessibility (WCAG 2.2 AA / EAA): native <dialog>, keyboard operable, visible focus, focus moves to the banner, the page keeps room under the banner so focus is never hidden (2.4.11), 44 px targets, reduced motion.

== Multilingual ==

All built-in texts are gettext strings (text domain myplan-consent), in a formal and an informal variant (contexts "formal" / "informal"; Settings → Form of address). Hungarian translation included.

* WPML / Polylang: switch the locale, so the built-in texts follow the language; texts typed into the settings are registered strings (wpml-config.xml, pll_register_string).
* TranslatePress: the plugin loads its own translation for the TranslatePress language and marks those elements data-no-translation; texts typed into the settings are translated by TranslatePress like the rest of the page.

== JavaScript API ==

mpConsent.allowed( 'analytics' ), mpConsent.open(), mpConsent.onChange( fn ), mpConsent.whenAllowed( 'functional', fn ); event "mpconsent:change" on document; dataLayer event "mpc_consent_update".

== Not covered ==

IAB TCF and Google CMP certification. Advertisers may use their own banner with Consent Mode v2; publishers serving Google ads (AdSense, Ad Manager, AdMob) in the EEA/UK/CH need a Google-certified CMP.
