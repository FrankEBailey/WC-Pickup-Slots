# WC Pickup Location & Collection Slots

A WooCommerce plugin that lets customers select a physical pickup location and provide at least two preferred collection date/time slots at checkout. Built for stores with multiple collection points and incompatible with standard delivery-date plugins. Compatible with the **Elementor checkout widget**.

Developed by [Frank Bailey](https://github.com/FrankEBailey) / [Studio256](https://github.com/FrankEBailey/WC-Pickup-Slots).

---

## Features

- **Admin-managed pickup locations** — define as many physical collection points as needed, each with its own schedule
- **Per-location configuration** — weekend disabling, blackout dates, and available collection hours per location
- **Hour-range time slots** — select available hours via checkboxes; adjacent hours are automatically merged into a window (e.g. 07:00–17:00)
- **Convenience buttons** — Select All, Clear, and Weekday Hours (06:00–18:00) per location
- **Minimum 2 preferred slots** — customers must provide at least 2 date/time preferences; more can be added
- **Elementor checkout widget compatible** — works with Elementor's checkout widget as well as the classic WooCommerce shortcode
- **Local pickup detection** — collection details section only appears when a local pickup shipping method is selected
- **Global lead time and booking window** — control how far ahead customers can book (30, 60, or 90 days) and minimum lead time in days
- **Saves to order meta** — supports both classic post meta and WooCommerce HPOS (High-Performance Order Storage)
- **Displays in admin order view**, order confirmation email, and thank-you page

---

## Requirements

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+
- A local pickup shipping method configured in WooCommerce → Settings → Shipping

---

## Installation

1. Download or clone this repository
2. Copy `wc-pickup-slots.php` into `/wp-content/plugins/wc-pickup-slots/`
3. Activate the plugin in **WordPress → Plugins**
4. Go to **WooCommerce → Pickup Slots** to configure your locations and schedules

---

## Configuration

### Global Settings

| Setting | Description |
|---|---|
| Minimum Lead Time | How many days ahead the earliest selectable date is. 0 = same day, 1 = next day, etc. |
| Booking Window | How far ahead customers can book — 30, 60, or 90 days |

### Pickup Locations

Add as many locations as needed. Each location has:

| Field | Description |
|---|---|
| Location Name | Displayed in the checkout dropdown and on the order |
| Address | Optional — shown beneath the dropdown when the customer selects this location |
| Disable Weekend Pickups | Blocks Saturdays and Sundays in the date picker |
| Blackout Dates | Click to select individual dates to block (holidays, closures, etc.) |
| Available Collection Hours | 24 checkboxes (00:00–23:00). Adjacent checked hours are merged into a single window |

**Convenience buttons per location:**
- **Select All** — checks all 24 hours
- **Clear** — unchecks all hours
- **Weekday hours (06:00–18:00)** — checks hours 6 through 17 in one click

---

## How It Works

### On the Checkout Page

1. When the customer selects a **Local Pickup** shipping method, the Collection Details section appears
2. The customer selects a **pickup location** from the dropdown
3. The location's address is shown (if configured)
4. Two date/time slot rows appear, pre-filtered to that location's available days and hours
5. The customer can add more slots with **+ Add another preferred time**
6. At least 2 complete slots (date + time both selected) are required to place the order

### Order Data

The following is saved to the order and displayed in:
- **Admin order view** (below billing address)
- **Order confirmation email**
- **Thank-you / order details page**

```
Pickup Location: Cape Town Warehouse
Preferred Collection Slots:
  1. March 10, 2026, 7:00 AM – 5:00 PM
  2. March 18, 2026, 7:00 AM – 5:00 PM
```

---

## Order Meta Keys

| Key | Description |
|---|---|
| `_wcps_location_index` | Index of the selected location in the plugin settings array |
| `_wcps_location_name` | Human-readable name of the selected location |
| `_wcps_slots` | Array of slot objects: `date`, `time`, `formatted` |

---

## Compatibility Notes

- **Elementor checkout widget** — the plugin uses `woocommerce_after_order_notes` for field output and JS-based DOM repositioning to place the section correctly within Elementor's rendered layout
- **WooCommerce HPOS** — order meta is saved via both `woocommerce_checkout_update_order_meta` (classic) and `woocommerce_checkout_create_order` (HPOS)
- **Flatpickr** — loaded under the handle `wcps-flatpickr` to avoid conflicts with other plugins or Elementor registering their own `flatpickr` handle

---

## Changelog

### 1.4.0
- JS DOM repositioning to place collection details below the shipping method table
- Switched email output to `woocommerce_email_order_meta` action for proper HTML rendering with `<br>`-separated slot lines
- Fixed time validation regex to accept window format (`07:00-17:00`) rather than single times only
- Improved server-side pickup detection to read `$_POST['shipping_method']` directly

### 1.3.0
- Renamed Flatpickr script/style handles to `wcps-flatpickr` to prevent Elementor conflicts
- Moved all checkout JS to `wp_add_inline_script` for guaranteed load order
- Added `wp_deregister_script('flatpickr')` to clear conflicting registrations
- Improved submit sync: hooks into form submit, Place Order button click, and `checkout_place_order`
- Fixed server-side pickup detection using POST data as primary source

### 1.2.0
- Replaced comma-separated time slot input with 24-hour checkbox grid
- Adjacent checked hours automatically merged into collection windows (e.g. 07:00–17:00)
- Added Select All / Clear / Weekday Hours convenience buttons per location
- Grid flows vertically (6 rows × 4 columns) for intuitive reading

### 1.1.0
- Replaced WC shipping zone detection with admin-managed location list
- Per-location name, address, weekend toggle, blackout dates, and time slots
- Dynamic add/remove location cards in admin with live heading update
- Location address shown beneath checkout dropdown when selected
- Slot rows rebuilt when customer switches location

### 1.0.0
- Initial release
- Checkout field rendered via `woocommerce_after_order_notes`
- Flatpickr date picker with per-location blackout dates and weekend disabling
- At least 2 preferred date/time slots required
- Saves to order meta, displays in admin order view, confirmation email, and thank-you page

---

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) for details.
