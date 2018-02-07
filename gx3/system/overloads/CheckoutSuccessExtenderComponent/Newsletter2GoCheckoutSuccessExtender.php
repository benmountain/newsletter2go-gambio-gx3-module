<?php

class Newsletter2GoCheckoutSuccessExtender extends Newsletter2GoCheckoutSuccessExtender_parent
{
    public function proceed()
    {
        parent::proceed();

        $query = "SELECT `configuration_value` FROM `" . TABLE_CONFIGURATION . "` WHERE `configuration_key` = 'NEWSLETTER2GO_TRACKING';";
        $trackingEnabled = xtc_db_fetch_array(xtc_db_query($query));
        $trackingEnabled = $trackingEnabled['configuration_value'];

        $query = "SELECT `configuration_value` FROM `" . TABLE_CONFIGURATION . "` WHERE `configuration_key` = 'NEWSLETTER2GO_COMPANYID';";
        $companyId = xtc_db_fetch_array(xtc_db_query($query));
        $companyId = $companyId['configuration_value'];

        if(!empty($trackingEnabled) && $trackingEnabled && isset($companyId)){

            $script = '<script id="n2g_script">
                !function(e,t,n,c,r,a,i){e.Newsletter2GoTrackingObject=r,e[r]=e[r]||function(){(e[r].q=e[r].q||[]).push(arguments)},e[r].l=1*new Date,a=t.createElement(n),i=t.getElementsByTagName(n)[0],a.async=1,a.src=c,i.parentNode.insertBefore(a,i)}(window,document,"script","//static-sandbox.newsletter2go.com/utils.js","n2g");
                n2g(\'create\', \'' . $companyId . '\');';


            $t_query = 'SELECT 
							products_id, 
							products_name,
							products_quantity, 
							products_price, 
							final_price,
							products_tax 
						FROM ' . TABLE_ORDERS_PRODUCTS . '
						WHERE orders_id = "' . $this->v_data_array['orders_id'] . '"
						ORDER BY orders_products_id';
            $t_last_orders_products_query = xtc_db_query($t_query);

            $total = 0.0;

            while($t_last_orders_products_array = xtc_db_fetch_array($t_last_orders_products_query))
            {
                $total += $t_last_orders_products_array['final_price'];

                $script .= 'n2g(\'ecommerce:addItem\', {
                    \'id\': \'' . $t_last_orders_products_array['products_id'] . '\',            // Transaction ID. Required.
                    \'name\': \'' . $t_last_orders_products_array['products_name'] . '\',        // Product name. Required.
                    \'sku\': \'\',                                                               // SKU/code.
                    \'category\': \'\',                                                          // Category or variation.
                    \'price\': \'' . $t_last_orders_products_array['products_price'] . '\',      // Unit price.
                    \'quantity\': \'' . $t_last_orders_products_array['products_quantity'] . '\' // Quantity.
                });';
            }

            $tax = $t_last_orders_products_array['products_tax'];

            $script .= 'n2g(\'ecommerce:addTransaction\', {
                \'id\': \'' . $this->v_data_array['orders_id'] . '\',    // Transaction ID. Required.
                \'affiliation\': \'\',                                   // Affiliation or store name or website
                \'revenue\': \'' . $total . '\',                         // Grand Total.
                \'shipping\': \'\',                                      // Shipping.
                \'tax\': \'' . $tax . '\'                                // Tax.
            });';

            $script .= 'n2g(\'ecommerce:send\');
                </script>';

            $this->html_output_array[] = $script;
        }
    }
}