=== Quick Toolbar Links ===
Contributors: HeatherFeuer, ecommnet, gavin.williams
Tags: admin, adminbar, toolbar,quick links
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html

Gives you the ability to add custom links to the admin toolbar in addition to your frequently used admin and plugin links.

== Description ==

Note: This is a completely remastered and updated version of the Quick Links plugin originally created by Ecommnet Ltd.

Due to the extensive library of WordPress plugins available, the admin menu can become unwieldy very quickly. Quick Toolbar can help.

Are you constantly digging into menus and sub menus to access the same admin and plugin links? Using Quick Toolbar you can add them as Quick Links to the admin toolbar at the top of every admin page, giving you easy access to the pages you frequently use.

Along with adding admin and plugin links, Quick Toolbar gives you the option of adding custom links to the toolbar.

= Summary of Features =
* Add admin and plugin links to the admin toolbar.
* Add custom links to external pages to the admin toolbar.
* Toolbar links are visible on the front-end to logged in users.
* Links are not displayed if the current user doesn't have the correct permissions.
* Enhanced security with nonces and proper data sanitization.
* Fully compatible with modern WordPress and PHP versions.
* Improved accessibility with ARIA attributes.
* Better jQuery compatibility for WordPress 5.5+.

= Future Additions =
* User and role specific links.
* Editing custom links.
* Rearranging links in the toolbar.
* 'Add to Quick Toolbar' button on all admin pages.

== Installation ==

This section describes how to install the plugin and get it working.

1. Unzip package contents
1. Upload the "`quick-toolbar`" directory to the "`/wp-content/plugins/`" directory
1. Activate the plugin through the "`Plugins`" menu in WordPress
1. Add your links by going to "`Quick Toolbar`".
1. Enjoy your new toolbar.

== Screenshots ==

1. Screen shot of the toolbar with your Quick Links.
2. Screen shot of the options page for adding existing admin and plugin links.
3. Screen shot of the options page for adding custom links.

== Frequently Asked Questions ==

= Is this plugin compatible with the latest WordPress? =

Yes! Version 1.0.0 has been completely updated to be fully compatible with WordPress 6.7 and PHP 8.x.

= Will my existing settings be preserved when updating? =

Yes, your existing toolbar links and custom links will be preserved when updating to the new version.

= Is this plugin secure? =

Yes, the updated version includes proper nonces, data sanitization, and escaping to ensure security best practices are followed.

== Changelog ==

= 1.0.0 =
* Major update for modern WordPress compatibility (6.x)
* Added PHP 7.4/8.x compatibility
* Security improvements: Added nonces to all forms
* Security improvements: Proper data sanitization and escaping
* Updated jQuery code for WordPress 5.5+ compatibility
* Fixed deprecated function usage
* Improved unique ID generation for toolbar items
* Added proper text domain for translations
* Enhanced accessibility with ARIA attributes
* Added "Select All/None" buttons for better UX
* Better error handling for AJAX operations
* Code cleanup and optimization
* Updated minimum requirements

= 0.4 =
* Fixing a bug that removes other plugins from the menu
* Adding Published, Draft, Pending and Trashed Quick Links to Posts, Pages and Custom Post Types

= 0.3 =
* Responsive compatibility

= 0.2 =
* Adding Icons to the Quick Toolbar links
* Prefixing stylesheet to avoid conflict

= 0.1 =
* First version of the plugin

== Upgrade Notice ==

= 1.0.0 =
Major update for compatibility with modern WordPress (6.x) and PHP (7.4/8.x). Includes important security improvements and bug fixes. Recommended for all users.
