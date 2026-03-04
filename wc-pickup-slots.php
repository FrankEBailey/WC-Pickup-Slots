<?php
/**
 * Plugin Name: WC Pickup Location & Collection Slots
 * Description: Customer selects a physical pickup location and provides at least 2 preferred
 *              collection date/time slots at checkout. Locations and schedules are managed in
 *              admin. Compatible with Elementor checkout widget.
 * Version: 1.4.0
 * Author: Frank Bailey
 * Author URI: https://github.com/FrankEBailey/WC-Pickup-Slots
 * Plugin URI: https://github.com/FrankEBailey/WC-Pickup-Slots
 * Company: Studio256
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WCPS_OPTION', 'wcps_settings' );

// ─────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────

function wcps_get_settings() {
    return get_option( WCPS_OPTION, [] );
}

function wcps_get_locations() {
    $s = wcps_get_settings();
    return isset( $s['locations'] ) && is_array( $s['locations'] ) ? $s['locations'] : [];
}

function wcps_get_location_name( $index ) {
    $locations = wcps_get_locations();
    $index     = intval( $index );
    return isset( $locations[ $index ] ) ? $locations[ $index ]['name'] : '';
}

function wcps_is_pickup_selected() {
    $chosen = WC()->session ? WC()->session->get( 'chosen_shipping_methods', [] ) : [];
    foreach ( (array) $chosen as $method ) {
        if ( strpos( $method, 'local_pickup' ) !== false ) return true;
    }
    return false;
}

// ─────────────────────────────────────────────
// ADMIN MENU
// ─────────────────────────────────────────────

add_action( 'admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'Pickup Slot Settings',
        'Pickup Slots',
        'manage_woocommerce',
        'wcps-settings',
        'wcps_settings_page'
    );
});

add_action( 'admin_init', function() {
    register_setting( 'wcps_settings_group', WCPS_OPTION, 'wcps_sanitize_settings' );
});

function wcps_sanitize_settings( $input ) {
    $out = [
        'lead_days'   => max( 0, intval( $input['lead_days'] ?? 1 ) ),
        'window_days' => in_array( intval( $input['window_days'] ?? 30 ), [30, 60, 90] )
                            ? intval( $input['window_days'] ) : 30,
        'locations'   => [],
    ];

    if ( ! empty( $input['locations'] ) && is_array( $input['locations'] ) ) {
        foreach ( $input['locations'] as $loc ) {
            $name = sanitize_text_field( $loc['name'] ?? '' );
            if ( $name === '' ) continue;
            $out['locations'][] = [
                'name'             => $name,
                'address'          => sanitize_text_field( $loc['address'] ?? '' ),
                'disable_weekends' => ! empty( $loc['disable_weekends'] ) ? '1' : '0',
                'blackout_dates'   => sanitize_text_field( $loc['blackout_dates'] ?? '' ),
                'hours'            => array_map( 'intval',
                                                array_filter( $loc['hours'] ?? [], 'is_numeric' ) ),
            ];
        }
    }

    return $out;
}

// ─────────────────────────────────────────────
// ADMIN SETTINGS PAGE
// ─────────────────────────────────────────────

function wcps_settings_page() {
    $s         = wcps_get_settings();
    $lead      = $s['lead_days'] ?? 1;
    $window    = $s['window_days'] ?? 30;
    $locations = wcps_get_locations();

    if ( empty( $locations ) ) {
        $locations = [ [ 'name' => '', 'address' => '', 'disable_weekends' => '1', 'blackout_dates' => '', 'hours' => [] ] ];
    }
    ?>
    <div class="wrap">
        <h1>Pickup Slot Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'wcps_settings_group' ); ?>

            <h2>Global Settings</h2>
            <table class="form-table">
                <tr>
                    <th>Minimum Lead Time (days)</th>
                    <td>
                        <input type="number" min="0" max="30" class="small-text"
                            name="<?= WCPS_OPTION ?>[lead_days]"
                            value="<?= esc_attr( $lead ) ?>">
                        <p class="description">0 = same day, 1 = next day, etc.</p>
                    </td>
                </tr>
                <tr>
                    <th>Booking Window</th>
                    <td>
                        <select name="<?= WCPS_OPTION ?>[window_days]">
                            <?php foreach ( [30, 60, 90] as $d ): ?>
                                <option value="<?= $d ?>" <?php selected( $window, $d ) ?>><?= $d ?> days ahead</option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <h2>Pickup Locations</h2>
            <p class="description">Define each physical collection point. These appear as a dropdown on the checkout.</p>

            <div id="wcps-locations-wrap">
                <?php foreach ( $locations as $i => $loc ): ?>
                    <?php wcps_render_location_card( $i, $loc ); ?>
                <?php endforeach; ?>
            </div>

            <p><button type="button" id="wcps-add-location" class="button">+ Add Location</button></p>

            <?php submit_button( 'Save Settings' ); ?>
        </form>
    </div>

    <script type="text/html" id="wcps-location-template">
        <?php wcps_render_location_card( '__INDEX__', [ 'name' => '', 'address' => '', 'disable_weekends' => '1', 'blackout_dates' => '', 'hours' => [] ], true ); ?>
    </script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        .wcps-location-card {
            background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;
            padding: 20px; margin: 16px 0; max-width: 860px; position: relative;
        }
        .wcps-location-card h3 { margin-top: 0; }
        .wcps-remove-location {
            position: absolute; top: 16px; right: 16px; color: #d63638;
            cursor: pointer; background: none; border: none; font-size: 13px; text-decoration: underline;
        }
        .wcps-tag { background: #d63638; color: #fff; padding: 3px 10px; border-radius: 12px; font-size: 13px; }
    </style>
    <script>
    (function() {
        var wrap  = document.getElementById('wcps-locations-wrap');
        var tmpl  = document.getElementById('wcps-location-template').innerHTML;
        var count = <?= count( $locations ) ?>;

        function initBlackoutPicker(card) {
            var input  = card.querySelector('.wcps-blackout-input');
            var tagsEl = card.querySelector('.wcps-blackout-tags');
            if (!input || input._flatpickr) return;

            var existing = input.value
                ? input.value.split(',').map(function(d){ return d.trim(); }).filter(Boolean)
                : [];

            function renderTags(dates) {
                tagsEl.innerHTML = '';
                dates.forEach(function(d) {
                    var tag = document.createElement('span');
                    tag.className = 'wcps-tag';
                    tag.textContent = d;
                    tagsEl.appendChild(tag);
                });
            }
            renderTags(existing);

            flatpickr(input, {
                mode: 'multiple',
                dateFormat: 'Y-m-d',
                defaultDate: existing,
                conjunction: ',',
                onReady: function(s, str, fp) { fp.input.setAttribute('readonly', true); },
                onChange: function(s, dateStr) {
                    input.value = dateStr;
                    renderTags(dateStr ? dateStr.split(',').map(function(d){ return d.trim(); }) : []);
                }
            });
        }

        document.querySelectorAll('.wcps-location-card').forEach(initBlackoutPicker);

        // Hours grid convenience buttons (delegated so they work on dynamically added cards)
        wrap.addEventListener('click', function(e) {
            var card = e.target.closest('.wcps-location-card');
            if (!card) return;
            var cbs = card.querySelectorAll('.wcps-hour-cb');
            if (e.target.classList.contains('wcps-hours-all')) {
                cbs.forEach(function(cb) { cb.checked = true; });
            } else if (e.target.classList.contains('wcps-hours-clear')) {
                cbs.forEach(function(cb) { cb.checked = false; });
            } else if (e.target.classList.contains('wcps-hours-weekday')) {
                cbs.forEach(function(cb) { cb.checked = parseInt(cb.value) >= 6 && parseInt(cb.value) < 18; });
            }
        });

        document.getElementById('wcps-add-location').addEventListener('click', function() {
            var html = tmpl.replace(/__INDEX__/g, count++);
            var div  = document.createElement('div');
            div.innerHTML = html;
            var card = div.firstElementChild;
            wrap.appendChild(card);
            initBlackoutPicker(card);
            updateHeadings();
        });

        wrap.addEventListener('click', function(e) {
            if (e.target.classList.contains('wcps-remove-location')) {
                if (wrap.querySelectorAll('.wcps-location-card').length > 1) {
                    e.target.closest('.wcps-location-card').remove();
                    updateHeadings();
                } else {
                    alert('You need at least one pickup location.');
                }
            }
        });

        wrap.addEventListener('input', function(e) {
            if (e.target.classList.contains('wcps-name-input')) {
                var card = e.target.closest('.wcps-location-card');
                card.querySelector('h3').textContent = e.target.value || 'New Location';
            }
        });

        function updateHeadings() {
            wrap.querySelectorAll('.wcps-location-card').forEach(function(card, i) {
                var nameVal = card.querySelector('.wcps-name-input').value;
                card.querySelector('h3').textContent = nameVal || ('Location ' + (i + 1));
            });
        }
    })();
    </script>
    <?php
}

function wcps_render_location_card( $i, $loc, $is_template = false ) {
    $name      = $is_template ? '' : esc_attr( $loc['name'] ?? '' );
    $address   = $is_template ? '' : esc_attr( $loc['address'] ?? '' );
    $dis_wknd  = $is_template ? '1' : ( $loc['disable_weekends'] ?? '1' );
    $blackout  = $is_template ? '' : esc_attr( $loc['blackout_dates'] ?? '' );
    $hours = $is_template ? [] : array_map( 'intval', $loc['hours'] ?? [] );
    $prefix    = WCPS_OPTION . '[locations][' . $i . ']';
    $heading   = $name ?: ( $is_template ? 'New Location' : 'Location ' . ( (int)$i + 1 ) );
    ?>
    <div class="wcps-location-card">
        <h3><?= esc_html( $heading ) ?></h3>
        <button type="button" class="wcps-remove-location">Remove</button>
        <table class="form-table" style="margin:0;">
            <tr>
                <th style="width:200px;">Location Name <span style="color:red;">*</span></th>
                <td>
                    <input type="text" class="wcps-name-input regular-text"
                        name="<?= $prefix ?>[name]" value="<?= $name ?>"
                        placeholder="e.g. Cape Town Warehouse" required>
                </td>
            </tr>
            <tr>
                <th>Address</th>
                <td>
                    <input type="text" class="regular-text"
                        name="<?= $prefix ?>[address]" value="<?= $address ?>"
                        placeholder="Optional — shown to customer on checkout">
                </td>
            </tr>
            <tr>
                <th>Disable Weekend Pickups</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?= $prefix ?>[disable_weekends]"
                            value="1" <?php checked( $dis_wknd, '1' ) ?>>
                        Block Saturdays and Sundays
                    </label>
                </td>
            </tr>
            <tr>
                <th>Blackout Dates</th>
                <td>
                    <input type="text" class="wcps-blackout-input"
                        name="<?= $prefix ?>[blackout_dates]" value="<?= $blackout ?>"
                        style="width:100%;max-width:500px;"
                        placeholder="Click to select dates to block...">
                    <div class="wcps-blackout-tags" style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;"></div>
                    <p class="description">Holidays, closures, etc.</p>
                </td>
            </tr>
            <tr>
                <th>Available Collection Hours</th>
                <td>
                    <div class="wcps-hours-grid" style="display:grid;grid-template-rows:repeat(6,auto);grid-auto-flow:column;gap:4px 16px;max-width:520px;margin-bottom:6px;">
                        <?php for ( $h = 0; $h < 24; $h++ ):
                            $end     = ( $h + 1 ) % 24;
                            $label   = sprintf( '%02d:00&ndash;%02d:00', $h, $end );
                            $checked = in_array( $h, $loc['hours'] ?? [] ) ? 'checked' : '';
                        ?>
                        <label style="display:flex;align-items:center;gap:5px;font-weight:normal;cursor:pointer;">
                            <input type="checkbox"
                                class="wcps-hour-cb"
                                name="<?= $prefix ?>[hours][]"
                                value="<?= $h ?>"
                                <?= $checked ?>>
                            <?= $label ?>
                        </label>
                        <?php endfor; ?>
                    </div>
                    <div style="margin-bottom:6px;display:flex;gap:10px;">
                        <button type="button" class="button button-small wcps-hours-all">Select All</button>
                        <button type="button" class="button button-small wcps-hours-clear">Clear</button>
                        <button type="button" class="button button-small wcps-hours-weekday">Weekday hours (06:00–18:00)</button>
                    </div>
                    <p class="description">Adjacent checked hours are merged into a single window on checkout (e.g. 06:00&ndash;11:00).</p>
                </td>
            </tr>
        </table>
    </div>
    <?php
}

// ─────────────────────────────────────────────
// FRONTEND: ENQUEUE ASSETS
// ─────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_checkout() ) return;

    // Deregister any existing flatpickr to prevent Elementor conflicts
    wp_deregister_script( 'flatpickr' );
    wp_deregister_style( 'flatpickr' );

    wp_enqueue_style( 'wcps-flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13' );

    wp_add_inline_style( 'wcps-flatpickr', '
        .flatpickr-calendar .numInputWrapper {
            position: relative !important; height: 28px !important; display: inline-block !important;
        }
        .flatpickr-calendar .numInputWrapper .numInput {
            display: inline-block !important; width: auto !important; min-width: 4ch !important;
            padding: 0 4px !important; margin: 0 !important; border: none !important;
            border-radius: 0 !important; background: transparent !important; box-shadow: none !important;
            font-size: 14px !important; font-weight: 600 !important; color: inherit !important;
            line-height: 28px !important; height: 28px !important; vertical-align: middle !important;
            -moz-appearance: textfield !important; appearance: textfield !important;
        }
        .flatpickr-calendar .numInputWrapper .numInput::-webkit-outer-spin-button,
        .flatpickr-calendar .numInputWrapper .numInput::-webkit-inner-spin-button {
            -webkit-appearance: none !important; margin: 0 !important;
        }
        .flatpickr-calendar .numInputWrapper .arrowUp,
        .flatpickr-calendar .numInputWrapper .arrowDown {
            display: block !important; position: absolute !important; right: 0 !important;
            width: 14px !important; height: 50% !important; line-height: 1 !important;
            cursor: pointer !important; opacity: 0 !important; padding: 0 4px !important;
            box-sizing: border-box !important; border: none !important; background: transparent !important;
        }
        .flatpickr-calendar .numInputWrapper .arrowUp  { top: 0 !important; }
        .flatpickr-calendar .numInputWrapper .arrowDown { top: 50% !important; }
        .flatpickr-calendar .numInputWrapper .arrowUp::after {
            display: block !important; content: "" !important; width: 0 !important; height: 0 !important;
            border-left: 4px solid transparent !important; border-right: 4px solid transparent !important;
            border-bottom: 5px solid rgba(57,57,57,0.6) !important; margin: 2px auto !important;
        }
        .flatpickr-calendar .numInputWrapper .arrowDown::after {
            display: block !important; content: "" !important; width: 0 !important; height: 0 !important;
            border-left: 4px solid transparent !important; border-right: 4px solid transparent !important;
            border-top: 5px solid rgba(57,57,57,0.6) !important; margin: 2px auto !important;
        }
        .flatpickr-calendar .numInputWrapper:hover .arrowUp,
        .flatpickr-calendar .numInputWrapper:hover .arrowDown { opacity: 1 !important; }
    ' );

    wp_enqueue_script( 'wcps-flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true );

    $s      = wcps_get_settings();
    $lead   = max( 0, intval( $s['lead_days'] ?? 1 ) );
    $window = max( 1, intval( $s['window_days'] ?? 30 ) );

    $min_date = new DateTime();
    $min_date->modify( "+{$lead} days" );
    $max_date = clone $min_date;
    $max_date->modify( "+{$window} days" );

    $js_locs = [];
    foreach ( wcps_get_locations() as $i => $loc ) {
        $blackout = array_values( array_filter(
            array_map( 'trim', explode( ',', $loc['blackout_dates'] ?? '' ) )
        ));

        // Build merged time windows from checked hours
        $checked_hours = array_map( 'intval', $loc['hours'] ?? [] );
        sort( $checked_hours );
        $windows = [];
        if ( ! empty( $checked_hours ) ) {
            $start = $checked_hours[0];
            $prev  = $checked_hours[0];
            for ( $ci = 1; $ci < count( $checked_hours ); $ci++ ) {
                $h = $checked_hours[ $ci ];
                if ( $h === $prev + 1 ) {
                    $prev = $h;
                } else {
                    $end_h    = ( $prev + 1 ) % 24;
                    $windows[] = [
                        'value' => sprintf( '%02d:00-%02d:00', $start, $end_h ),
                        'label' => sprintf( '%d:%02d %s – %d:%02d %s',
                            $start % 12 ?: 12, 0, $start < 12 ? 'AM' : 'PM',
                            $end_h  % 12 ?: 12, 0, $end_h  < 12 ? 'AM' : 'PM' ),
                    ];
                    $start = $h;
                    $prev  = $h;
                }
            }
            // Final window
            $end_h    = ( $prev + 1 ) % 24;
            $windows[] = [
                'value' => sprintf( '%02d:00-%02d:00', $start, $end_h ),
                'label' => sprintf( '%d:%02d %s – %d:%02d %s',
                    $start % 12 ?: 12, 0, $start < 12 ? 'AM' : 'PM',
                    $end_h  % 12 ?: 12, 0, $end_h  < 12 ? 'AM' : 'PM' ),
            ];
        }

        $js_locs[] = [
            'index'           => $i,
            'name'            => $loc['name'],
            'address'         => $loc['address'] ?? '',
            'disableWeekends' => ( $loc['disable_weekends'] ?? '1' ) === '1',
            'blackoutDates'   => $blackout,
            'timeSlots'       => array_values( $windows ),
        ];
    }

    wp_add_inline_script( 'wcps-flatpickr', 'window.wcpsConfig = ' . wp_json_encode([
        'minDate'   => $min_date->format('Y-m-d'),
        'maxDate'   => $max_date->format('Y-m-d'),
        'locations' => $js_locs,
        'minSlots'  => 2,
    ]) . ';', 'before' );

    // Enqueue checkout interaction JS properly so it runs after flatpickr
    wp_add_inline_script( 'wcps-flatpickr', <<<'WCPSINLINE'
(function($) {
        var cfg        = window.wcpsConfig || {};
        var slots      = [];
        var slotCount  = 0;
        var currentLoc = null;

        function populateLocationSelect() {
            var sel = document.getElementById('wcps_location');
            if (!sel || !cfg.locations || sel.options.length > 1) return;
            cfg.locations.forEach(function(loc) {
                var opt = document.createElement('option');
                opt.value = loc.index;
                opt.textContent = loc.name;
                sel.appendChild(opt);
            });
        }

        function isPickupSelected() {
            var checked = document.querySelector('input[name^="shipping_method"]:checked');
            if (checked) return checked.value.indexOf('local_pickup') !== -1;
            var hidden = document.querySelector('input[name^="shipping_method"][type="hidden"]');
            if (hidden) return hidden.value.indexOf('local_pickup') !== -1;
            return false;
        }

        function updateShippingVisibility() {
            var wrap = document.getElementById('wcps-wrap');
            if (!wrap) return;
            if (isPickupSelected()) {
                wrap.style.display = '';
            } else {
                wrap.style.display = 'none';
                // Reset location/slots when switching away from pickup
                var sel = document.getElementById('wcps_location');
                if (sel) sel.value = '';
                document.getElementById('wcps-slots-section').style.display = 'none';
                document.getElementById('wcps-location-address').style.display = 'none';
                slots.forEach(function(s) { try { s.fp.destroy(); } catch(e) {} });
                slots = [];
                slotCount = 0;
                currentLoc = null;
                document.getElementById('wcps-slots-wrap').innerHTML = '';
                syncHidden();
            }
        }

        function onLocationChange() {
            var sel     = document.getElementById('wcps_location');
            var addrEl  = document.getElementById('wcps-location-address');
            var slotSec = document.getElementById('wcps-slots-section');
            var val     = sel ? sel.value : '';

            if (val === '') {
                if (slotSec) slotSec.style.display = 'none';
                if (addrEl)  addrEl.style.display  = 'none';
                return;
            }

            var idx = parseInt(val, 10);
            var loc = (cfg.locations || []).find(function(l) { return l.index === idx; });
            if (!loc) return;

            if (addrEl && loc.address) {
                addrEl.textContent = loc.address;
                addrEl.style.display = '';
            } else if (addrEl) {
                addrEl.style.display = 'none';
            }

            if (currentLoc !== idx) {
                currentLoc = idx;
                initSlots(loc);
            }

            if (slotSec) slotSec.style.display = '';
        }

        function initSlots(loc) {
            var wrap = document.getElementById('wcps-slots-wrap');
            if (!wrap) return;
            slots.forEach(function(s) { try { s.fp.destroy(); } catch(e) {} });
            slots = [];
            slotCount = 0;
            wrap.innerHTML = '';
            for (var i = 0; i < cfg.minSlots; i++) buildSlotRow(loc);
        }

        function buildSlotRow(loc) {
            var idx      = slotCount++;
            var disabled = (loc.blackoutDates || []).slice();
            if (loc.disableWeekends) {
                disabled.push(function(date) { return date.getDay() === 0 || date.getDay() === 6; });
            }

            var row = document.createElement('div');
            row.className = 'wcps-slot-row';

            var dateInput = document.createElement('input');
            dateInput.type = 'text';
            dateInput.className = 'wcps-date-input';
            dateInput.placeholder = 'Select date...';
            dateInput.readOnly = true;
            row.appendChild(dateInput);

            var timeSel = document.createElement('select');
            timeSel.className = 'wcps-time-select';
            var defOpt = document.createElement('option');
            defOpt.value = '';
            defOpt.textContent = 'Select time...';
            timeSel.appendChild(defOpt);
            (loc.timeSlots || []).forEach(function(slot) {
                var opt = document.createElement('option');
                opt.value = slot.value;
                opt.textContent = slot.label;
                timeSel.appendChild(opt);
            });
            row.appendChild(timeSel);

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'wcps-remove-slot';
            removeBtn.textContent = 'Remove';
            removeBtn.addEventListener('click', function() {
                if (slots.length <= cfg.minSlots) return;
                var pos = slots.findIndex(function(s) { return s.idx === idx; });
                if (pos !== -1) {
                    try { slots[pos].fp.destroy(); } catch(e) {}
                    slots[pos].row.remove();
                    slots.splice(pos, 1);
                }
                updateRemoveButtons();
                syncHidden();
            });
            row.appendChild(removeBtn);

            document.getElementById('wcps-slots-wrap').appendChild(row);

            var fp = flatpickr(dateInput, {
                dateFormat: 'Y-m-d',
                minDate: cfg.minDate || 'today',
                maxDate: cfg.maxDate || null,
                disable: disabled,
                disableMobile: false,
                allowInput: false,
            });

            slots.push({ idx: idx, row: row, dateInput: dateInput, timeSel: timeSel, fp: fp });
            dateInput.addEventListener('change', syncHidden);
            timeSel.addEventListener('change', syncHidden);
            // Also sync on flatpickr's own onChange
            fp.config.onChange.push(syncHidden);
            updateRemoveButtons();
            syncHidden();
        }

        function updateRemoveButtons() {
            slots.forEach(function(s) {
                s.row.querySelector('.wcps-remove-slot').disabled = slots.length <= cfg.minSlots;
            });
        }

        function syncHidden() {
            var data = slots.map(function(s) {
                return { date: s.dateInput.value, time: s.timeSel.value };
            });
            var hidden = document.getElementById('wcps_slots_data');
            if (hidden) hidden.value = JSON.stringify(data);
        }

        function bindLocationSelect() {
            var sel = document.getElementById('wcps_location');
            if (sel && !sel._wcpsBound) {
                sel.addEventListener('change', onLocationChange);
                sel._wcpsBound = true;
            }
        }

        function bindAddSlot() {
            var btn = document.getElementById('wcps-add-slot');
            if (btn && !btn._wcpsBound) {
                btn.addEventListener('click', function() {
                    if (currentLoc === null) return;
                    var loc = (cfg.locations || []).find(function(l) { return l.index === currentLoc; });
                    if (loc) buildSlotRow(loc);
                });
                btn._wcpsBound = true;
            }
        }

        function init() {
            populateLocationSelect();
            bindLocationSelect();
            bindAddSlot();
            updateShippingVisibility();
        }

        document.addEventListener('DOMContentLoaded', init);

        $(document.body).on('updated_checkout wc_fragments_refreshed wc_fragment_refresh', function() {
            setTimeout(function() {
                populateLocationSelect();
                bindLocationSelect();
                bindAddSlot();
                updateShippingVisibility();
            }, 200);
        });

        $(document.body).on('change', 'input[name^="shipping_method"]', function() {
            setTimeout(updateShippingVisibility, 100);
        });

        // Move the collection details block to just after the order review table
        // (shipping row is inside <tfoot> so we can't insert a div there)
        function wcpsReposition() {
            var wrap = document.getElementById('wcps-wrap');
            if (!wrap) return;

            // Find the shipping row, walk up to the containing <table>, insert after it
            var shippingRow = document.querySelector('tr.woocommerce-shipping-totals');
            if (!shippingRow) return;

            var table = shippingRow.closest('table');
            if (!table) return;

            // Don't move if already in position
            if (table.nextSibling === wrap) return;

            table.parentNode.insertBefore(wrap, table.nextSibling);
            wrap.style.display = isPickupSelected() ? '' : 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(wcpsReposition, 300);
        });
        $(document.body).on('updated_checkout wc_fragments_refreshed', function() {
            setTimeout(function() { wcpsReposition(); updateShippingVisibility(); }, 400);
        });

        // Sync hidden field before any form submission — works with Elementor and classic checkout
        $(document).on('submit', 'form.checkout, form[name="checkout"], .woocommerce-checkout', function() {
            syncHidden();
        });
        // Also catch WC AJAX checkout
        $(document.body).on('checkout_place_order', function() { syncHidden(); return true; });
        // Belt-and-braces: sync on Place Order button click before validation fires
        $(document).on('click', '#place_order, [name="woocommerce_checkout_place_order"], .elementor-button[type="submit"]', function() {
            syncHidden();
        });

    })(jQuery);
WCPSINLINE
    );
});

// ─────────────────────────────────────────────
// FRONTEND: CHECKOUT FIELDS
// ─────────────────────────────────────────────

add_action( 'woocommerce_after_order_notes', 'wcps_render_checkout_fields' );

function wcps_render_checkout_fields() {
    ?>
    <div id="wcps-wrap" style="display:none;margin-bottom:24px;">
        <h3><?php esc_html_e( 'Collection Details', 'wcps' ); ?></h3>

        <p class="form-row form-row-wide">
            <label for="wcps_location">
                <?php esc_html_e( 'Pickup Location', 'wcps' ); ?> <span class="required">*</span>
            </label>
            <select id="wcps_location" name="wcps_location" class="select">
                <option value=""><?php esc_html_e( '— Select a pickup location —', 'wcps' ); ?></option>
            </select>
        </p>

        <p id="wcps-location-address" style="margin:-8px 0 16px;color:#555;font-size:0.9em;display:none;"></p>

        <div id="wcps-slots-section" style="display:none;">
            <p style="font-weight:600;margin-bottom:8px;">
                <?php esc_html_e( 'Preferred Collection Date & Time', 'wcps' ); ?>
                <span style="font-weight:normal;font-size:0.85em;color:#555;">
                    — <?php esc_html_e( 'please provide at least 2 options', 'wcps' ); ?>
                </span>
            </p>
            <div id="wcps-slots-wrap"></div>
            <button type="button" id="wcps-add-slot"
                style="margin-top:4px;background:none;border:none;color:#2271b1;cursor:pointer;padding:0;font-size:0.95em;text-decoration:underline;">
                <?php esc_html_e( '+ Add another preferred time', 'wcps' ); ?>
            </button>
            <p id="wcps-min-notice" style="color:#d63638;font-size:0.85em;display:none;margin-top:6px;">
                <?php esc_html_e( 'Please provide at least 2 preferred collection date/time slots.', 'wcps' ); ?>
            </p>
        </div>

        <input type="hidden" id="wcps_slots_data" name="wcps_slots_data" value="">
    </div>

    <style>
        .wcps-slot-row {
            display:flex; gap:10px; align-items:center; margin-bottom:10px; flex-wrap:wrap;
        }
        .wcps-slot-row .wcps-date-input,
        .wcps-slot-row .wcps-time-select {
            flex:1; min-width:140px; padding:8px 10px; border:1px solid #ccc;
            border-radius:4px; font-size:0.95em; box-sizing:border-box;
        }
        .wcps-remove-slot {
            background:none; border:none; color:#d63638; cursor:pointer;
            font-size:0.85em; white-space:nowrap; text-decoration:underline; padding:0;
        }
        .wcps-remove-slot:disabled { opacity:0.35; cursor:default; text-decoration:none; }
    </style>
    <?php

}

// ─────────────────────────────────────────────
// VALIDATION
// ─────────────────────────────────────────────

add_action( 'woocommerce_checkout_process', function() {
    // Check POST shipping methods directly — more reliable than session during checkout process
    $shipping_methods = $_POST['shipping_method'] ?? [];
    $is_pickup = false;
    foreach ( (array) $shipping_methods as $method ) {
        if ( strpos( $method, 'local_pickup' ) !== false ) { $is_pickup = true; break; }
    }
    // Also check session as fallback
    if ( ! $is_pickup ) $is_pickup = wcps_is_pickup_selected();
    if ( ! $is_pickup ) return;

    $loc_val = $_POST['wcps_location'] ?? '';
    if ( $loc_val === '' ) {
        wc_add_notice( __( 'Please select a pickup location.', 'wcps' ), 'error' );
    }

    $raw = sanitize_text_field( $_POST['wcps_slots_data'] ?? '' );
    if ( empty( $raw ) ) {
        wc_add_notice( __( 'Please provide at least 2 preferred collection date/time slots.', 'wcps' ), 'error' );
        return;
    }

    $slots = json_decode( stripslashes( $raw ), true );
    if ( ! is_array( $slots ) ) {
        wc_add_notice( __( 'Invalid slot data. Please try again.', 'wcps' ), 'error' );
        return;
    }

    $valid = 0;
    foreach ( $slots as $slot ) {
        $date = sanitize_text_field( $slot['date'] ?? '' );
        $time = sanitize_text_field( $slot['time'] ?? '' );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && preg_match( '/^\d{1,2}:\d{2}/', $time ) ) {
            $valid++;
        }
    }

    if ( $valid < 2 ) {
        wc_add_notice( __( 'Please provide at least 2 preferred collection slots with both a date and time selected.', 'wcps' ), 'error' );
    }
});

// ─────────────────────────────────────────────
// SAVE TO ORDER META
// ─────────────────────────────────────────────

function wcps_parse_slots( $raw ) {
    $slots  = json_decode( stripslashes( $raw ), true );
    $parsed = [];
    if ( ! is_array( $slots ) ) return $parsed;
    foreach ( $slots as $slot ) {
        $date = sanitize_text_field( $slot['date'] ?? '' );
        $time = sanitize_text_field( $slot['time'] ?? '' );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && preg_match( '/^\d{1,2}:\d{2}/', $time ) ) {
            // Format date nicely
            $dt = DateTime::createFromFormat( 'Y-m-d', $date );
            $formatted_date = $dt ? $dt->format( get_option('date_format') ) : $date;

            // Format time window nicely: 07:00-17:00 -> 7:00 AM - 5:00 PM
            $formatted_time = $time;
            if ( preg_match( '/^(\d{1,2}:\d{2})-(\d{1,2}:\d{2})$/', $time, $m ) ) {
                $t1 = DateTime::createFromFormat( 'H:i', $m[1] );
                $t2 = DateTime::createFromFormat( 'H:i', $m[2] );
                $formatted_time = ( $t1 ? $t1->format('g:i A') : $m[1] ) . ' – ' . ( $t2 ? $t2->format('g:i A') : $m[2] );
            } elseif ( preg_match( '/^(\d{1,2}:\d{2})$/', $time ) ) {
                $t1 = DateTime::createFromFormat( 'H:i', $time );
                $formatted_time = $t1 ? $t1->format('g:i A') : $time;
            }

            $parsed[] = [
                'date'      => $date,
                'time'      => $time,
                'formatted' => $formatted_date . ', ' . $formatted_time,
            ];
        }
    }
    return $parsed;
}

add_action( 'woocommerce_checkout_update_order_meta', function( $order_id ) {
    if ( isset( $_POST['wcps_location'] ) ) {
        $idx = intval( $_POST['wcps_location'] );
        update_post_meta( $order_id, '_wcps_location_index', $idx );
        update_post_meta( $order_id, '_wcps_location_name', wcps_get_location_name( $idx ) );
    }
    $raw = sanitize_text_field( $_POST['wcps_slots_data'] ?? '' );
    if ( $raw ) update_post_meta( $order_id, '_wcps_slots', wcps_parse_slots( $raw ) );
});

add_action( 'woocommerce_checkout_create_order', function( $order, $data ) {
    if ( isset( $_POST['wcps_location'] ) ) {
        $idx = intval( $_POST['wcps_location'] );
        $order->update_meta_data( '_wcps_location_index', $idx );
        $order->update_meta_data( '_wcps_location_name', wcps_get_location_name( $idx ) );
    }
    $raw = sanitize_text_field( $_POST['wcps_slots_data'] ?? '' );
    if ( $raw ) $order->update_meta_data( '_wcps_slots', wcps_parse_slots( $raw ) );
}, 10, 2 );

// ─────────────────────────────────────────────
// DISPLAY IN ADMIN / EMAIL / THANK YOU
// ─────────────────────────────────────────────

function wcps_render_order_slots( $order ) {
    $loc   = $order->get_meta( '_wcps_location_name' );
    $slots = $order->get_meta( '_wcps_slots' );
    if ( $loc ) {
        echo '<p><strong>' . __( 'Pickup Location', 'wcps' ) . ':</strong> ' . esc_html( $loc ) . '</p>';
    }
    if ( ! empty( $slots ) ) {
        echo '<p><strong>' . __( 'Preferred Collection Slots', 'wcps' ) . ':</strong></p><ol style="margin:4px 0 0 20px;padding:0;">';
        foreach ( $slots as $slot ) {
            echo '<li>' . esc_html( $slot['formatted'] ) . '</li>';
        }
        echo '</ol>';
    }
}

add_action( 'woocommerce_admin_order_data_after_billing_address', function( $order ) {
    wcps_render_order_slots( $order );
});

add_action( 'woocommerce_email_order_meta', function( $order, $sent_to_admin, $plain_text ) {
    $loc   = $order->get_meta( '_wcps_location_name' );
    $slots = $order->get_meta( '_wcps_slots' );
    if ( ! $loc && empty( $slots ) ) return;

    if ( $plain_text ) {
        if ( $loc ) echo "Pickup Location: " . $loc . "\n";
        if ( ! empty( $slots ) ) {
            echo "Preferred Collection Slots:\n";
            foreach ( $slots as $i => $s ) {
                echo ( $i + 1 ) . '. ' . $s['formatted'] . "\n";
            }
        }
        echo "\n";
    } else {
        echo '<div style="margin-bottom:16px;font-family:inherit;">';
        if ( $loc ) {
            echo '<p style="margin:0 0 8px;"><strong>' . __( 'Pickup Location', 'wcps' ) . ':</strong> ' . esc_html( $loc ) . '</p>';
        }
        if ( ! empty( $slots ) ) {
            echo '<p style="margin:0 0 4px;"><strong>' . __( 'Preferred Collection Slots', 'wcps' ) . ':</strong><br>';
            foreach ( $slots as $i => $s ) {
                echo '&nbsp;&nbsp;' . ( $i + 1 ) . '. ' . esc_html( $s['formatted'] ) . '<br>';
            }
            echo '</p>';
        }
        echo '</div>';
    }
}, 10, 3 );

add_action( 'woocommerce_order_details_after_order_table', function( $order ) {
    wcps_render_order_slots( $order );
});
