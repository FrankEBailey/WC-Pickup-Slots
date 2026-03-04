<?php
/**
 * Plugin Name: WC Delivery Date Picker
 * Description: Adds a delivery date picker to WooCommerce checkout, compatible with Elementor checkout widget. Includes admin settings for blackout dates, weekend disabling, lead time, and booking window.
 * Version: 1.0.2
 * Author: Frank Bailey
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WCDP_OPTION', 'wcdp_settings' );

// ─────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────

function wcdp_get_settings() {
    return wp_parse_args( get_option( WCDP_OPTION, [] ), [
        'disable_weekends' => '1',
        'lead_days'        => '1',
        'window_days'      => '30',
        'blackout_dates'   => '',   // comma-separated Y-m-d
    ]);
}

// ─────────────────────────────────────────────
// ADMIN SETTINGS PAGE
// ─────────────────────────────────────────────

add_action( 'admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'Delivery Date Settings',
        'Delivery Dates',
        'manage_woocommerce',
        'wcdp-settings',
        'wcdp_settings_page'
    );
});

add_action( 'admin_init', function() {
    register_setting( 'wcdp_settings_group', WCDP_OPTION, 'wcdp_sanitize_settings' );
});

function wcdp_sanitize_settings( $input ) {
    return [
        'disable_weekends' => isset( $input['disable_weekends'] ) ? '1' : '0',
        'lead_days'        => max( 0, intval( $input['lead_days'] ?? 1 ) ),
        'window_days'      => in_array( intval( $input['window_days'] ?? 30 ), [30, 60, 90] )
                                ? intval( $input['window_days'] )
                                : 30,
        'blackout_dates'   => sanitize_text_field( $input['blackout_dates'] ?? '' ),
    ];
}

function wcdp_settings_page() {
    $s = wcdp_get_settings();
    ?>
    <div class="wrap">
        <h1>Delivery Date Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'wcdp_settings_group' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Disable Weekend Delivery</th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?= WCDP_OPTION ?>[disable_weekends]" value="1"
                                <?php checked( $s['disable_weekends'], '1' ) ?>>
                            Block Saturdays and Sundays
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Minimum Lead Time (days)</th>
                    <td>
                        <input type="number" min="0" max="30"
                            name="<?= WCDP_OPTION ?>[lead_days]"
                            value="<?= esc_attr( $s['lead_days'] ) ?>"
                            class="small-text">
                        <p class="description">How many days ahead the earliest selectable date is. 0 = same day, 1 = next day, etc.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Booking Window</th>
                    <td>
                        <select name="<?= WCDP_OPTION ?>[window_days]">
                            <?php foreach ( [30, 60, 90] as $d ): ?>
                                <option value="<?= $d ?>" <?php selected( $s['window_days'], $d ) ?>><?= $d ?> days</option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">How far ahead customers can book.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Blackout Dates</th>
                    <td>
                        <input type="text" id="wcdp-blackout-input"
                            name="<?= WCDP_OPTION ?>[blackout_dates]"
                            value="<?= esc_attr( $s['blackout_dates'] ) ?>"
                            style="width:100%;max-width:500px;"
                            placeholder="Click to select dates...">
                        <p class="description">Click to pick individual dates to block (holidays, closures, etc). Selected dates shown above.</p>
                        <div id="wcdp-blackout-display" style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;"></div>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Save Settings' ); ?>
        </form>
    </div>

    <!-- Flatpickr for admin blackout picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        #wcdp-blackout-display .wcdp-tag {
            background: #d63638;
            color: #fff;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 13px;
        }
    </style>
    <script>
    (function() {
        const input = document.getElementById('wcdp-blackout-input');
        const display = document.getElementById('wcdp-blackout-display');

        const existing = input.value
            ? input.value.split(',').map(d => d.trim()).filter(Boolean)
            : [];

        function renderTags(dates) {
            display.innerHTML = '';
            dates.forEach(d => {
                const tag = document.createElement('span');
                tag.className = 'wcdp-tag';
                tag.textContent = d;
                display.appendChild(tag);
            });
        }

        renderTags(existing);

        flatpickr(input, {
            mode: 'multiple',
            dateFormat: 'Y-m-d',
            defaultDate: existing,
            conjunction: ',',
            onChange: function(selectedDates, dateStr) {
                input.value = dateStr;
                renderTags(dateStr ? dateStr.split(',').map(d => d.trim()) : []);
            },
            onReady: function(selectedDates, dateStr, fp) {
                // Prevent clearing the input on direct typing
                fp.input.setAttribute('readonly', true);
            }
        });
    })();
    </script>
    <?php
}

// ─────────────────────────────────────────────
// FRONTEND: ENQUEUE FLATPICKR + FIELD STYLES
// ─────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_checkout() ) return;

    wp_enqueue_style(
        'flatpickr',
        'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
        [],
        '4.6.13'
    );

    // Reinforce Flatpickr year/month selector styles against WC/theme overrides
    wp_add_inline_style( 'flatpickr', '
        .flatpickr-calendar .numInputWrapper {
            position: relative !important;
            height: 28px !important;
            display: inline-block !important;
        }
        .flatpickr-calendar .numInputWrapper .numInput {
            display: inline-block !important;
            width: auto !important;
            min-width: 4ch !important;
            padding: 0 4px !important;
            margin: 0 !important;
            border: none !important;
            border-radius: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            color: inherit !important;
            line-height: 28px !important;
            height: 28px !important;
            vertical-align: middle !important;
            -moz-appearance: textfield !important;
            appearance: textfield !important;
        }
        .flatpickr-calendar .numInputWrapper .numInput::-webkit-outer-spin-button,
        .flatpickr-calendar .numInputWrapper .numInput::-webkit-inner-spin-button {
            -webkit-appearance: none !important;
            margin: 0 !important;
        }
        .flatpickr-calendar .numInputWrapper .arrowUp,
        .flatpickr-calendar .numInputWrapper .arrowDown {
            display: block !important;
            position: absolute !important;
            right: 0 !important;
            width: 14px !important;
            height: 50% !important;
            line-height: 1 !important;
            cursor: pointer !important;
            opacity: 0 !important;
            padding: 0 4px !important;
            box-sizing: border-box !important;
            border: none !important;
            background: transparent !important;
        }
        .flatpickr-calendar .numInputWrapper .arrowUp {
            top: 0 !important;
            border-bottom: 0 !important;
        }
        .flatpickr-calendar .numInputWrapper .arrowDown {
            top: 50% !important;
        }
        .flatpickr-calendar .numInputWrapper .arrowUp::after {
            display: block !important;
            content: "" !important;
            width: 0 !important;
            height: 0 !important;
            border-left: 4px solid transparent !important;
            border-right: 4px solid transparent !important;
            border-bottom: 5px solid rgba(57,57,57,0.6) !important;
            margin: 2px auto !important;
        }
        .flatpickr-calendar .numInputWrapper .arrowDown::after {
            display: block !important;
            content: "" !important;
            width: 0 !important;
            height: 0 !important;
            border-left: 4px solid transparent !important;
            border-right: 4px solid transparent !important;
            border-top: 5px solid rgba(57,57,57,0.6) !important;
            margin: 2px auto !important;
        }
        .flatpickr-calendar .numInputWrapper:hover .arrowUp,
        .flatpickr-calendar .numInputWrapper:hover .arrowDown {
            opacity: 1 !important;
        }
    ' );
    wp_enqueue_script(
        'flatpickr',
        'https://cdn.jsdelivr.net/npm/flatpickr',
        [],
        '4.6.13',
        true
    );

    $s            = wcdp_get_settings();
    $lead         = max( 0, intval( $s['lead_days'] ) );
    $window       = max( 1, intval( $s['window_days'] ) );
    $disable_wknd = $s['disable_weekends'] === '1';
    $blackout     = array_filter( array_map( 'trim', explode( ',', $s['blackout_dates'] ) ) );

    // Compute min/max dates server-side for reliability
    $min_date = ( new DateTime() );
    $min_date->modify( "+{$lead} days" );

    $max_date = clone $min_date;
    $max_date->modify( "+{$window} days" );

    wp_add_inline_script( 'flatpickr', sprintf(
        'window.wcdpConfig = %s;',
        wp_json_encode([
            'minDate'        => $min_date->format('Y-m-d'),
            'maxDate'        => $max_date->format('Y-m-d'),
            'disableWeekends'=> $disable_wknd,
            'blackoutDates'  => array_values( $blackout ),
        ])
    ), 'before' );

    // Init script — runs after flatpickr loads
    wp_add_inline_script( 'flatpickr', <<<'WCDPJS'
        function wcdpInit() {
            var el = document.getElementById('wcdp_delivery_date');
            if (!el || el._flatpickr) return;

            var cfg = window.wcdpConfig || {};
            var disabled = cfg.blackoutDates ? cfg.blackoutDates.slice() : [];

            if (cfg.disableWeekends) {
                disabled.push(function(date) {
                    return date.getDay() === 0 || date.getDay() === 6;
                });
            }

            flatpickr(el, {
                dateFormat: 'Y-m-d',
                minDate: cfg.minDate || 'today',
                maxDate: cfg.maxDate || null,
                disable: disabled,
                disableMobile: false,
                allowInput: false,
            });
        }

        function wcdpIsLocalPickup() {
            var checked = document.querySelector('input[name^="shipping_method"]:checked');
            if (checked) return checked.value.indexOf('local_pickup') !== -1;
            var hidden = document.querySelector('input[name^="shipping_method"][type="hidden"]');
            if (hidden) return hidden.value.indexOf('local_pickup') !== -1;
            return false;
        }

        function wcdpToggleVisibility() {
            var wrap = document.getElementById('wcdp_delivery_date_wrap');
            if (!wrap) return;
            if (wcdpIsLocalPickup()) {
                wrap.style.display = 'none';
                var input = document.getElementById('wcdp_delivery_date');
                if (input && input._flatpickr) input._flatpickr.clear();
            } else {
                wrap.style.display = '';
                wcdpInit();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            wcdpInit();
            wcdpToggleVisibility();
        });

        jQuery(document.body).on(
            'updated_checkout wc_fragments_refreshed wc_fragment_refresh',
            function() {
                setTimeout(function() {
                    wcdpInit();
                    wcdpToggleVisibility();
                }, 200);
            }
        );

        jQuery(document.body).on(
            'change',
            'input[name^="shipping_method"]',
            function() { setTimeout(wcdpToggleVisibility, 100); }
        );
WCDPJS
    );
});

// ─────────────────────────────────────────────
// CHECKOUT FIELD
// Hooks that fire inside Elementor's checkout form
// ─────────────────────────────────────────────

add_action( 'woocommerce_before_checkout_billing_form', 'wcdp_render_field' );

function wcdp_render_field( $checkout ) {
    echo '<div id="wcdp_delivery_date_wrap" style="margin-bottom:20px;">';
    woocommerce_form_field( 'wcdp_delivery_date', [
        'type'        => 'text',
        'label'       => __( 'Preferred Delivery Date', 'wcdp' ),
        'placeholder' => __( 'Select a date (optional)', 'wcdp' ),
        'required'    => false,
        'class'       => [ 'form-row-wide' ],
        'input_class' => [ 'wcdp-date-input' ],
        'custom_attributes' => [
            'id'       => 'wcdp_delivery_date',
            'readonly' => 'readonly',
            'autocomplete' => 'off',
        ],
    ], $checkout->get_value( 'wcdp_delivery_date' ) );
    echo '</div>';
}

// ─────────────────────────────────────────────
// SAVE TO ORDER META
// ─────────────────────────────────────────────

add_action( 'woocommerce_checkout_update_order_meta', function( $order_id ) {
    if ( ! empty( $_POST['wcdp_delivery_date'] ) ) {
        $date = sanitize_text_field( $_POST['wcdp_delivery_date'] );
        // Basic Y-m-d validation
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            update_post_meta( $order_id, '_wcdp_delivery_date', $date );
        }
    }
});

// Also support HPOS (WooCommerce High-Performance Order Storage)
add_action( 'woocommerce_checkout_create_order', function( $order, $data ) {
    if ( ! empty( $_POST['wcdp_delivery_date'] ) ) {
        $date = sanitize_text_field( $_POST['wcdp_delivery_date'] );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            $order->update_meta_data( '_wcdp_delivery_date', $date );
        }
    }
}, 10, 2 );

// ─────────────────────────────────────────────
// DISPLAY IN ADMIN ORDER VIEW
// ─────────────────────────────────────────────

add_action( 'woocommerce_admin_order_data_after_billing_address', function( $order ) {
    $date = $order->get_meta( '_wcdp_delivery_date' );
    if ( $date ) {
        $formatted = date_i18n( get_option('date_format'), strtotime( $date ) );
        echo '<p><strong>' . __('Preferred Delivery Date', 'wcdp') . ':</strong> ' . esc_html( $formatted ) . '</p>';
    }
});

// ─────────────────────────────────────────────
// DISPLAY IN ORDER CONFIRMATION EMAIL
// ─────────────────────────────────────────────

add_filter( 'woocommerce_email_order_meta_fields', function( $fields, $sent_to_admin, $order ) {
    $date = $order->get_meta( '_wcdp_delivery_date' );
    if ( $date ) {
        $fields['wcdp_delivery_date'] = [
            'label' => __( 'Preferred Delivery Date', 'wcdp' ),
            'value' => date_i18n( get_option('date_format'), strtotime( $date ) ),
        ];
    }
    return $fields;
}, 10, 3 );

// ─────────────────────────────────────────────
// DISPLAY IN THANK YOU PAGE + ORDER DETAILS
// ─────────────────────────────────────────────

add_action( 'woocommerce_order_details_after_order_table', function( $order ) {
    $date = $order->get_meta( '_wcdp_delivery_date' );
    if ( $date ) {
        $formatted = date_i18n( get_option('date_format'), strtotime( $date ) );
        echo '<p><strong>' . __('Preferred Delivery Date', 'wcdp') . ':</strong> ' . esc_html( $formatted ) . '</p>';
    }
});
