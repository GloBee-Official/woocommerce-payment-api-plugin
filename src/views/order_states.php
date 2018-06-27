<tr valign="top">
    <th scope="row" class="titledesc">Order States:</th>
    <td class="forminp" id="globee_order_states">
        <table cellspacing="0" cellpadding="0" style="padding:0">
            <?php foreach ($globeeStatuses as $globeeState => $globeeName) { ?>
                <tr>
                    <th><?php echo $globeeName; ?></th>
                    <td>
                        <select name="globee_woocommerce_order_states[<?php echo $globeeState; ?>]">
                            <?php
                            $orderStates = get_option('globee_woocommerce_order_states');
                            foreach ($wcStatuses as $wcState => $wcName) {
                                $currentOption = $orderStates[$globeeState];
                                if (true === empty($currentOption)) {
                                    $currentOption = $statuses[$globeeState];
                                }
                                echo "<option value='$wcState'";
                                if ($currentOption === $wcState) {
                                    echo "selected";
                                }
                                echo ">$wcName</option>";
                            } ?>
                        </select>
                    </td>
                </tr>
            <?php } ?>
        </table>
    </td>
</tr>