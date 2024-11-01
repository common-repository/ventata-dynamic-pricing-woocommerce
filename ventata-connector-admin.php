<?php if(isset($_POST['submit'])) : ?>
    <?php
    $apiKey = $_POST['ventata_api_key'];  
	$oldKey = get_option('ventata_api_key');
	
	if ($apiKey != $oldKey)
	{
		update_option('ventata_api_key', $apiKey);  
		
		if(get_option('ventata_sent_order_product') == 'false') {
			get_all_products(get_option('ventata_api_key'));
			get_all_orders(get_option('ventata_api_key'));
			update_option('ventata_sent_order_product', 'true');
		}
	}
    ?>  
    <div class="updated"><p><strong><?php _e('Options saved.' ); ?></strong></p></div>  
<?php endif; ?> 

<?php 
function get_all_orders($apiKey) 
{
    $orders = new WP_Query(array('post_type' => 'shop_order', 'posts_per_page' => -1 ));
    while ($orders->have_posts()) {
        $orders->the_post();
        send_order_to_ventata( get_the_ID(), $apiKey ); 
    }
}

function send_order_to_ventata($order_id, $apiKey)
{    
    $post = get_post($order_id, ARRAY_A);
    $_order = &new woocommerce_order($order_id);
        
    $data = array();
	$data['DateCreated'] = "/Date(" . date('U', strtotime($_order->order_date)) . "000)/";
    $data['ExternalOrderId'] = $_order->id;
    $data['ShippingCost'] = $_order->get_shipping();
    $data['Taxes'] = $_order->order_tax;
    $data['TotalPrice'] = $_order->order_total;
    $data['OrderDetails'] = array();
    
    $subtotal = 0;
    foreach ( $_order->get_items() as $products ) {
        $_product = &new woocommerce_product( $products['id'] );
        $subtotal += $products['line_subtotal'];
        $tempData = array(
            'CostPerItem' => ($_product->price) ? $_product->price : 0,
            'ManuCode' => '',
            'Quantity' => $products['qty'],
            'PricePaid' => $products['line_total'],
            'StoreCode' => $products['id'],
        );
        array_push($data['OrderDetails'], $tempData);
    }	
    $data['SubTotal'] = $subtotal;

    $data_string = json_encode($data);
    $ch = curl_init();
    
    $url = "https://api.ventata.com/orders?ApiKey=" . $apiKey;

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    curl_close($ch);
}


function get_all_products($apiKey) 
{
    $searchProducts = new WP_Query(array('post_type' => 'product', 'posts_per_page' => -1 ));
    while ($searchProducts->have_posts()) {
        $searchProducts->the_post();
        send_products_to_ventata( get_the_ID(), $apiKey ); 
    }
}


function send_products_to_ventata($product_id, $apiKey)
{   
    $post = get_post( $product_id, ARRAY_A );
    $_product = &new woocommerce_product( get_the_ID() );

    $data = array(
        'Cost' => 0,
        'DateCreated' => '/Date(' . date('U', strtotime($post['post_date'])) . '000)/',
        'Description' => $post['post_content'],
        'MANUCODE' => '',
        'Name' => $post['post_title'],
        'Price' => $_product->price,
        'SKU' => $_product->sku,
        'StoreCode' => $_product->id,
        'StoreId' => '00000000-0000-0000-0000-000000000000',
        'Strategy' => 'Off',        
    );
    
    $data_string = json_encode($data);
    $ch = curl_init();
    $url = "https://api.ventata.com/product?ApiKey=" . $apiKey;
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);	
    curl_close($ch);
}
?>
<div>
    <h2>Ventata Connector Settings</h2>
    <form method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
        <?php wp_nonce_field('update-options'); ?>
        <table width="510">
            <tr valign="top">
                <th width="92" scope="row">Store API Key</th>
                <td width="406">
                    <input style="width: 300px" name="ventata_api_key" type="text" id="ventata_api_key" value="<?php echo get_option('ventata_api_key'); ?>" />
                </td>
            </tr>
            <tr valign="top" style="text-align: right;">
                <th width="92" scope="row">
                <p>
                    <input name="submit" type="submit" value="<?php _e('Save') ?>" />
                </p>
                </th>
            </tr>
            <tr valign="top" style="text-align: left;">
                <th width="498" scope="row" colspan="2">
                <ol>
	                Installation instructions:
                    <br/>
                    <br/>
                    <li>Sign up for a <a href="https://ventata.com/Ecommerce/Pricing" target="_blank">free trial on at Ventata.com</a>.</li>
                    <li>Go to <a href="https://manage.ventata.com" target="_blank">your Ventata mangement page</a>.</li>
                    <li>Login with the email address and password you used during sign up.</li>
                    <li>Then go to <i>Stores</i> -> <i>Add Store</i>.</li>
                    <li>Select <i>WooCommerce</i> under the "Your Ecommerce Provider" drop down list</li>
                    <li>Tell us your store's URL and the name of the store and hit <i>Next</i>.</li>
                    <li>Copy the Store API Key we give you into the above "Store API Key" field and you’re done.</li>
                </ol>                
</th>
            </tr>
            <tr valign="top" style="display: none">
                <th width="92" scope="row">Sent Orders and Products to ventata</th>
                <td width="406">
                    <input disabled="disabled" name="ventata_sent_order_product" type="text" id="ventata_sent_order_product" value="<?php echo get_option('ventata_sent_order_product'); ?>" />
                </td>
            </tr>
        </table>
        <input type="hidden" name="action" value="update" />
        <input type="hidden" name="page_options" value="ventata_api_key" />
    </form>
</div>
