=== Lead Magnet For Utiliko ===
Contributors: codengine
Tags: CRM, Lead Sync, WPForms, API Integration
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.1.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

**Lead Magnet For Utiliko** is a WordPress plugin that integrates WPForm plugin with the Utiliko CRM, allowing seamless lead synchronization.

== Description ==

Lead Magnet For Utiliko connects WPForms with Utiliko CRM, automating lead generation and management by syncing form data via API.

**Key Features:**

* Seamless integration with Utiliko CRM.
* Supports WPForms plugin.
* Fetches CRM access tokens and handles secure data transmission.
* Allows dynamic form ID mapping and custom field mappings.
* Simple configuration and setup with intuitive UI.

== External Services ==

This plugin connects to the Utiliko CRM API to synchronize leads collected from WPForms. Below are the details about the data being transmitted to the external service and the conditions under which this occurs:

* **Service:** Utiliko CRM API  
  **Purpose:** The plugin uses the Utiliko CRM API to submit form entries collected via WPForms to the CRM for lead management and tracking.  
  **Data Sent:** The following form data is sent to the CRM whenever a form is submitted on your WordPress site:
  - Lead Name
  - First Name
  - Last Name
  - Business Email
  - Company Name
  - Company Phone
  - Phone
  - Email
  - Location
  - Address
  - Message
  - WPForms Entry ID (for internal tracking purposes)

  **When Data Is Sent:** Data is transmitted when a form submission occurs on your site and when the plugin attempts to sync the lead data with the Utiliko CRM.  
  **Where Data Is Sent:** The data is sent to the following API endpoint: `https://api.utiliko.io/api/v4/WordpressIntegrations/createLeadByAccessToken`  
  **Conditions for Data Transmission:** Data transmission happens upon successful form submission, and the plugin ensures that a valid access token is retrieved before sending the data to the CRM.

  **Utiliko CRM Links:**
  - [Privacy Policy](https://www.utiliko.io/privacy-policy/)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/lead-magnet-for-utiliko` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to the Lead Magnet For Utiliko settings page to configure the plugin with your CRM API credentials.
4. Set up form mappings and begin syncing leads to your Utiliko CRM.

== Frequently Asked Questions ==

= How do I get started with Lead Magnet For Utiliko? =

Once you install and activate the plugin, go to the dashboard, Click on Lead Magnet For Utiliko, Enter your Utiliko CRM URL, Click on Connect button, Allow to auth screen, Click on Field Mapping, Select your WPForm from "Select Form" dropdown and configure your form mappings.

= Which form plugins are supported? =

Lead-Magnet-For-Utiliko supports WPForms.

= How do I map form fields to CRM fields? =

Field mappings can be configured through the Lead Magnet For Utiliko page. Select the form you want to map and assign the corresponding CRM fields.

= Can I use Lead-Magnet-For-Utiliko with other CRMs? =

Currently, Lead-Magnet-For-Utiliko is designed specifically for integration with Utiliko CRM.

== Screenshots ==

1. **Screenshot 1:** Lead Magnet For Utiliko page to configure API URL and Access token.
2. **Screenshot 2:** Form mapping interface.
3. **Screenshot 3:** Successful lead synchronization confirmation.

== Changelog ==

= 1.1.5 =
* Improved database query security by ensuring proper escaping of table names using `esc_sql()` and utilizing `$wpdb->prepare()` with placeholders for dynamic values.
* Implemented caching mechanism using `wp_cache_get()`, `wp_cache_set()`, and `wp_cache_delete()` to optimize database queries for CRM access token retrieval.
* Fixed an issue where interpolated variables in SQL queries were not properly prepared, which could lead to potential security risks.
* Enhanced error handling and messaging for AJAX-based CRM access token retrieval.

= 1.1.4 =
* Enhanced security by ensuring no development-specific code is present in production environments.

= 1.1.3 =
* Removed error_log() calls to prevent debug code in production.
* Wrapped necessary error_log() statements with a WP_DEBUG conditional to ensure they only run in development mode.
* Enhanced security by ensuring no development-specific code is present in production environments.

= 1.1.2 =
* Improved error handling for API integration, including detailed logging for failed API requests.

= 1.1.1 =
* Improved error handling for API integration, including detailed logging for failed API requests.
* Optimized form field mapping process to enhance lead syncing performance with Utiliko CRM.
* Refined API request validation to ensure more accurate form submission data is sent to Utiliko.
* Updated UI on the settings page for better user experience and easier navigation.

= 1.0.0 =
* Initial release of Lead-Magnet-For-Utiliko.
* Added support for WPForms.
* Implemented CRM access token fetching and secure lead submission.

== Upgrade Notice ==

= 1.0.0 =
Initial release of Lead-Magnet-For-Utiliko. Supports WPForm plugin for lead synchronization with Utiliko CRM.

== License ==

This plugin is licensed under the GPLv2 or later. See the LICENSE file for more details.

== Credits ==

Developed by [CodeNgine Technologies](https://www.codengine.co/).
