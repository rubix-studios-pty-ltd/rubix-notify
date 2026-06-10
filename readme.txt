=== Rubix Notify ===
Contributors: rubixvi
Tags: ntfy, notifications, login alerts, security, admin
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4

Stable tag: 1.0.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send WordPress login notifications to ntfy when users sign in.

== Description ==

Rubix Notify sends login notifications from your WordPress site to an ntfy topic. Designed for site owners, administrators, agencies, and managed service providers who want simple push alerts when users access a WordPress dashboard.

The plugin can be used with ntfy.sh or a self-hosted ntfy server. Suitable for personal sites, client websites, internal systems and managed WordPress environments.

When a user logs in, the plugin can send a notification to your configured ntfy topic. This helps administrators keep track of access activity without needing to constantly check WordPress logs or email inboxes.

Use cases includes:

* Monitoring administrator logins
* Receiving alerts for client website access
* Tracking access to internal WordPress sites

Rubix Notify is lightweight and privacy-focused simple notification layer for login activity.

== Features ==

* Send login alerts to ntfy
* Supports ntfy.sh and self-hosted ntfy servers
* Works with custom ntfy server URLs
* Optional token-based authentication
* Lightweight WordPress integration
* Suitable for agencies, administrators, and managed WordPress sites

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/rubix-notify` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to the ntfy settings page in WordPress.
4. Enter your ntfy server URL.
5. Enter your topic name.
6. Add an authentication token if your ntfy topic requires one.
7. Save your settings.
8. Log in to WordPress to confirm that a notification is sent.

== Frequently Asked Questions ==

= Does this work with self-hosted ntfy servers? =

Yes. The plugin is designed to work with ntfy.sh as well as private ntfy servers hosted on custom domains or internal infrastructure.

= Does this replace a WordPress security plugin? =

No. This plugin only sends login notifications to ntfy. It should be used alongside normal WordPress security practices, such as strong passwords, two-factor authentication, regular updates, and appropriate user permissions.

= Can I use an authentication token? =

Yes. If your ntfy topic requires authentication, you can configure a token in the plugin settings.

= Will this send email notifications? =

No. This plugin sends notifications to ntfy. It does not send WordPress email alerts.

= Does the plugin create audit logs? =

No. The plugin focuses on sending login notifications. It is not intended to be a full audit logging system.

== Source Code ==

The human-readable source code used to generate the compiled admin asset is publicly available at:

https://github.com/rubix-studios-pty-ltd/rubix-notify

To rebuild the compiled admin files:

* Clone the repository.
* Install dependencies with pnpm install.
* Build the admin assets with pnpm build.

The public repository is provided so future maintainers, reviewers, and contributors can inspect, modify, and rebuild the react admin interface from source.

== Changelog ==

= 1.0.1 =
* Updated plugin readme documentation.

= 1.0.0 =
* Initial release.
* Added WordPress login notifications for ntfy.
* Added support for custom ntfy server URLs.
* Added support for optional authentication tokens.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
