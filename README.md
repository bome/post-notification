# Post Notification

**Contributors:** [bome](https://bome.com), [knandi76](https://github.com/knandi76)  
**Original author:** Moritz Strübe  
**GitHub:** [https://github.com/bome/post-notification](https://github.com/bome/post-notification)  
**Website:** [https://bome.com](https://bome.com)  
**Tested up to WordPress:** 6.8  
**Tested under PHP:** 8.4  
**License:** GPL-2.0 or later

---

## 📰 Description

Post Notification automatically sends an email to all registered subscribers whenever a new WordPress post is published.  
The mail content can be plain text or HTML and can be customized through templates.

Originally developed by **Moritz Strübe**, this plugin has been **fundamentally re-worked and maintained by Bome Software** since 2020.  
The entire codebase was modernized, secured, and aligned with WordPress coding standards, while preserving the original idea and functionality.

---

## 🚀 Features

- Sends personalized emails to each subscriber for every new post
- Handles thousands of subscribers efficiently
- Double Opt-In support
- Subscribers can choose individual categories
- Multilingual: German, French, Dutch (Frontend also: Hebrew, Portuguese, Spanish, Italian, Japanese)
- HTML and Text mail support
- Template-based frontend and email design (easily editable)
- Easy import/export of subscriber lists
- "Nervous Finger" option – short delay before dispatch
- Control number of mails per batch and delay between bursts
- Decide per post if notification should be sent
- Captcha support
- Works without WP-Cron
- Compatible with [WP Mail SMTP](https://wordpress.org/plugins/wp-mail-smtp/)
- Secure, UTF-8 safe, modernized for PHP 8+
- Integrated unsubscribe headers for email clients (e.g. Apple Mail One-Click Unsubscribe)

---

## 🧩 Technical Notes

| Setting | Description              |
|----------|--------------------------|
| **Requires WordPress** | ≥ 5.6                    |
| **Tested up to** | 6.8                      |
| **Tested with PHP** | 8.4                      |
| **Stable tag** | 1.2.11 (legacy base)     |
| **Maintained by** | Bome Software since 2020 |

---

## 🧱 Installation

1. Upload the `post-notification` folder to `/wp-content/plugins/`.
2. Activate the plugin in **Plugins → Installed Plugins**.
3. Go to **Settings → Post Notification** and configure according to your needs.
4. Make sure email delivery works – if not, install [WP Mail SMTP](https://wordpress.org/plugins/wp-mail-smtp/).

> ⚠️ When updating from older versions (≤ 1.1), some templates need to be updated:
> - `select.tmpl` added
> - `activated.tmpl` removed
> - `strings.php` + 5 new strings
> - `unsubscribe.tmpl` now requires `@@conf_url`
> - mail templates can use `@@author`

---

## 🧠 Background and Credits

Post Notification has a long history of community development:

| Year | Developer / Source | Notes |
|------|--------------------|-------|
| **2003** | *Jon Anhold* – Email Notification | Original idea |
| **2005** | *Brian Groce* – WP Email Notification | First WordPress release |
| **2006** | *Frank Büeltge* – Newsletter (DE) | German translation + enhancements |
| **2007–2009** | *Moritz Strübe* – Post Notification 1.x | Complete rewrite, multilingual support |
| **since 2020** | *Bome Software* | Modernized, PHP 8, security, code cleanup |

Special thanks to Frank Büeltge, Brian Groce, and Jon Anhold for their foundational work.

---

## ⚙️ Modernization by Bome Software

Main changes since 2020:

- No separate install scripts needed
- Configurable line-break handling (`\r\n` issues)
- Strict WordPress coding standards
- Mails sent after post publication
- Adjustable batch size + pause duration
- Fixed multiple security issues
- Improved HTML-to-Text conversion
- Full UTF-8 / Umlaut support
- Internationalization (I18N) underway
- Using Woocommerce's Mailer class for sending mails (optional)

---

## 💬 FAQ

**Q:** Does it work on modern WordPress versions?  
**A:** Yes — tested successfully up to WP 5.6 / PHP 8.0.

**Q:** Is it actively maintained?  
**A:** Yes, by [Bome Software](https://bome.com) since 2020.

**Q:** I get no emails — what can I do?  
**A:** Install [WP Mail SMTP](https://wordpress.org/plugins/wp-mail-smtp/) and check your mail configuration.

---

## 📜 License

This plugin is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 or later.

---

## 🔗 Resources

- GitHub: [https://github.com/bome/post-notification](https://github.com/bome/post-notification)
- Website: [https://bome.com](https://bome.com)
- Original Forum: [http://pn.xn--strbe-mva.de/](http://pn.xn--strbe-mva.de/)

---