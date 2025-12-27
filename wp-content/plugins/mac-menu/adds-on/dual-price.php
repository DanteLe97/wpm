<?php
    add_action('mac_menu_add_on_additional_settings_row_dual_price', 'mac_menu_dual_price_addon_setting_row');
    function mac_menu_dual_price_addon_setting_row() {
        
        if (false === get_option('mac_menu_dp')) {
            add_option('mac_menu_dp', '0');
        }
        if (false === get_option('mac_menu_dp_sw')) {
            add_option('mac_menu_dp_sw', '0');
        }
        if (false === get_option('mac_menu_dp_value')) {
            add_option('mac_menu_dp_value', '0');
        }
        
        $dualPrice = get_option('mac_menu_dp') ?: "0";
        $swDualPrice = get_option('mac_menu_dp_sw') ?: "0";
        $valueDualPrice = get_option('mac_menu_dp_value') ?: "1";
        ?>
        <tr>
            <td><h2 style="font-size: 18px; font-weight:500;">Dual Price</h2></td>
        </tr>
        <tr class="option-cash">
            <td>On/Off Card</td>
            <td style="display:flex; gap: 20px">
                <?php $dualPrice = !empty(get_option('mac_menu_dp')) ? get_option('mac_menu_dp') : "0" ; ?>
                <div class="mac-switcher-wrap mac-switcher-btn <?php if($dualPrice == "1") echo 'active'; ?>">
                    <span class="mac-switcher-true">On</span>
                    <span class="mac-switcher-false">Off</span>
                    <input type="text" name="mac-menu-dp" value="<?= $dualPrice ?>" readonly/>
                </div>
                <div class="option-cash-drop" style="display:flex; gap: 10px">
                    <?php $swDualPrice = !empty(get_option('mac_menu_dp_sw')) ? get_option('mac_menu_dp_sw') : "0" ; ?>
                    <select class="mac-selection-dual-price" name="mac-menu-dp-sw">
                        <option value="0" <?= ($swDualPrice == 0) ? "selected" : ""  ?>>&#37;</option>
                        <option value="1" <?= ($swDualPrice == 1) ? "selected" : ""  ?>>&#36;</option>
                    </select>
                    <input type="number" step="0.001" name="mac-menu-dp-value" value="<?= $valueDualPrice ?>"/>
                </div>
                
            </td>
        </tr>  
        <?php
    }
