<?php

class Newsletter2GoCheckoutSuccessExtender extends Newsletter2GoCheckoutSuccessExtender_parent
{

    public function proceed()
    {
        parent::proceed();

        $language = new LanguageCode(new StringType($_SESSION['language_code']));
        /** @var ProductReadServiceInterface $productRepository */
        $productRepository = StaticGXCoreLoader::getService('ProductRead');
        /** @var CategoryReadServiceInterface $categoryRepository */
        $categoryRepository = StaticGXCoreLoader::getService('CategoryRead');

        $query = "SELECT `configuration_value` FROM `" . TABLE_CONFIGURATION . "` WHERE `configuration_key` = 'NEWSLETTER2GO_TRACKING';";
        $trackingEnabled = xtc_db_fetch_array(xtc_db_query($query));
        $trackingEnabled = $trackingEnabled['configuration_value'] === 'TRUE';

        $query = "SELECT `configuration_value` FROM `" . TABLE_CONFIGURATION . "` WHERE `configuration_key` = 'NEWSLETTER2GO_COMPANYID';";
        $companyId = xtc_db_fetch_array(xtc_db_query($query));
        $companyId = $companyId['configuration_value'];

        if($trackingEnabled && isset($companyId)){
            $orderId = $this->v_data_array['orders_id'];

            $script = '<script id="n2g_script">
                !function(e,t,n,c,r,a,i){e.Newsletter2GoTrackingObject=r,e[r]=e[r]||function(){(e[r].q=e[r].q||[]).push(arguments)},e[r].l=1*new Date,a=t.createElement(n),i=t.getElementsByTagName(n)[0],a.async=1,a.src=c,i.parentNode.insertBefore(a,i)}(window,document,"script","//static-sandbox.newsletter2go.com/utils.js","n2g");
                n2g(\'create\', \'' . $companyId . '\');';

            $t_query = 'SELECT 
							products_id as id, 
							products_name as name,
							products_model as model,
							products_quantity as qty, 
							products_price as price, 
							final_price,
							products_tax as tax 
						FROM ' . TABLE_ORDERS_PRODUCTS . '
						WHERE orders_id = "' . $orderId . '"
						ORDER BY orders_products_id';
            $orderItemsQuery = xtc_db_query($t_query);

            $total = 0.0;
            $tax = 0.0;
            $shipping = isset($this->v_data_array['coo_order']->info['pp_shipping']) ? $this->v_data_array['coo_order']->info['pp_shipping'] : 0;
            $items = '';

            while($orderItem = xtc_db_fetch_array($orderItemsQuery))
            {
                $category = '';
                $categories = $productRepository->getProductLinks(new IdType($orderItem['id']));
                if (!$categories->isEmpty()) {
                    $category = $categoryRepository->getCategoryById($categories->getItem(0))->getName($language);
                }

                $total += $orderItem['final_price'];
                $tax += $orderItem['tax'];

                $json = json_encode(array(
                    'id' => $orderId,
                    'name' => $orderItem['name'],
                    'sku' => $orderItem['model'],
                    'category' => $category,
                    'price' => round($orderItem['price'], 2),
                    'quantity' => round($orderItem['qty'], 2),
                ));

                $items .= "n2g('ecommerce:addItem', $json);";
            }

            $orderInfoJson = json_encode(array(
                'id' => $orderId,
                'affiliation' => '',
                'revenue' => round($total, 2),
                'shipping' => round((float)$shipping, 2),
                'tax' => round((float)$tax, 2),
            ));

            $script .= "n2g('ecommerce:addTransaction', $orderInfoJson); $items n2g('ecommerce:send');</script>";

            $this->html_output_array[] = $script;
        }
    }
}