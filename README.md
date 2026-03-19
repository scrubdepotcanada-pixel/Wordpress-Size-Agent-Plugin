# Size Agent (WordPress + WooCommerce Plugin)

Size Agent is a thin WordPress wrapper around an external sizing API. It adds a **Find My Size** widget to WooCommerce product pages (and via shortcode), collects shopper measurements, and sends product + user inputs to your external endpoint.

## File Tree

```text
size-agent/
├── size-agent.php
├── uninstall.php
├── includes/
│   ├── class-size-agent-plugin.php
│   ├── class-size-agent-settings.php
│   ├── class-size-agent-api.php
│   └── class-size-agent-frontend.php
├── assets/
│   ├── css/
│   │   └── size-agent.css
│   └── js/
│       └── size-agent.js
└── README.md
```

## What This Plugin Includes

- Object-oriented plugin architecture with activation hook.
- Admin settings page under **Settings → Size Agent** with:
  - External API base URL
  - API key
  - Button text
  - Primary color
  - Enable/disable on WooCommerce product pages
  - Optional manual product field mapping (JSON)
  - Debug mode (safe summary logs when `WP_DEBUG` is enabled)
  - Test mode for staging/QA (returns server-side mock recommendations, skips external API calls)
  - Cleanup-on-uninstall toggle
- WooCommerce integration:
  - Automatically renders button on single product pages when WooCommerce is active
  - If WooCommerce is missing, admin sees a notice and shortcode still works manually
  - Pulls product title, SKU, permalink, featured image, brand (when available), and optional size chart image
  - Provides `[size_agent]` shortcode (supports optional `product_id` attribute)
- Frontend widget:
  - Modal form fields for height, weight, gender, fit preference, optional notes
  - Uses secure WordPress AJAX endpoint + nonce
  - Displays recommended size, confidence, fit note
  - UX improvements: loading state, double-submit prevention, clear success/error states, ESC/click-outside close
- Security basics:
  - Nonce verification
  - Input sanitization and output escaping
  - API key stored only server-side (never sent to frontend)

## Installation Instructions

1. Copy this plugin folder into your WordPress plugins directory:
   - `wp-content/plugins/size-agent`
2. In wp-admin, activate **Size Agent**.
3. Go to **Settings → Size Agent**.
4. Enter your API base URL + API key, then save.
5. Open a WooCommerce product page and click **Find My Size** or place `[size_agent]` on a page/post.

## Where to Insert External API Endpoint Logic

Core outbound API logic is in:

- `includes/class-size-agent-api.php`

Update this method to match your API:

- `Size_Agent_API::request_size_recommendation()`

Customization points:

1. Endpoint path (currently: `{$base_url}/recommendations`)
2. Auth/header strategy (currently Bearer token)
3. Payload shape
4. Response field mapping (`recommended_size`, `confidence`, `fit_note`)

Payload assembly is prepared in:

- `Size_Agent_Frontend::handle_ajax()`

## Notes

- This plugin intentionally **does not** include sizing intelligence.
- It is a transport/UI layer for an external size recommendation engine.
