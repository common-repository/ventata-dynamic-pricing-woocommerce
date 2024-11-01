<?php
/*
Plugin Name: Ventata Dynamic pricing woocommerce
Plugin URI: http://wordpress.org/extend/plugins/ventata-connector/
Description: Ventata Dynamic Pricing Software.
Author: Luke Davis
Author URI: http://ventata.com/
Version: 0.1
Text Domain: ventata-connector
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

register_activation_hook(__FILE__,'ventata_connector_install'); 
register_deactivation_hook( __FILE__, 'ventata_connector_remove' );
//include('ventata-wordpress.php');


function ventata_connector_install() {
    add_option("ventata_api_key", '', '', 'yes');
    add_option("ventata_sent_order_product", 'false', '', 'yes');
}

function ventata_connector_remove() {
    delete_option('ventata_api_key');
    delete_option('ventata_sent_order_product');
}


function ventata_connector_admin() {
	include('ventata-connector-admin.php');
} 

function ventata_connector_admin_actions() {
    //add_options_page("Ventata Connector", "Ventata Connector", "manage_options", "ventata_connector", "ventata_connector_admin");
    //add_menu_page("Ventata", "Ventata", "manage_options", 'ventata', 'ventata-connector/ventata-connector-admin.php', '', '', 200);
    add_menu_page('Ventata', 'Ventata', 'manage_options', 'ventata-dynamic-pricing-woocommerce/ventata-connector-admin.php', '',   '', 400);
}

add_action('admin_menu', 'ventata_connector_admin_actions');


function plugin_myown_template() {
    include("ventata-wordpress.php");
    exit;
}

if($_GET['ventata_api'] == get_option('ventata_api_key')) {
    add_action('template_redirect', 'plugin_myown_template');
}

add_action( 'save_post', 'send_product_data_to_ventata', 10, 2 );
function send_product_data_to_ventata($post_id, $post)
{
    $apiKey = get_option('ventata_api_key');
    if($post->post_type == 'product') {
        if ($post->post_date == $post->post_modified) {
            $action = 'new';
        } else {
            $action = 'update';
        }
        
        $_product = &new woocommerce_product( $post->ID );
        if ( !wp_is_post_revision( $post_id ) ) {
            if($post->post_title != null) {
				
                if($action == 'new') {					
               		$ch = curl_init();
					$data = array(
						'Cost' => 0,
						'DateCreated' => '/Date(' . date('U', strtotime($post->post_date)) . '000)/',
						'Description' => $post->post_content,
						'MANUCODE' => '',
						'Name' => $post->post_title,
						'Price' => $_product->price,
						'SKU' => $_product->sku,
						'StoreCode' => $_product->id,
						'StoreId' => '00000000-0000-0000-0000-000000000000',
						'Strategy' => 'Off',        
					);
					$data_string = json_encode($data);
					
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
					
                } else {			
					//Patch product
					$storeCode = $post->id;
					
					$data = array(
						'ColumnName' => 'StoreCode',
						'ColumnValue' => $storeCode
						);					
					patch_product($storeCode, $data, $apiKey);
					
					$data = array(
						'ColumnName' => 'Description',
						'ColumnValue' => $post->post_content
						);
					patch_product($storeCode, $data, $apiKey);
					
					$data = array(
						'ColumnName' => 'Name',
						'ColumnValue' => $post->post_title
						);
					patch_product($storeCode, $data, $apiKey);
					
					$data = array(
						'ColumnName' => 'Price',
						'ColumnValue' => $post->price
						);
					patch_product($storeCode, $data, $apiKey);
					
					$data = array(
						'ColumnName' => 'SKU',
						'ColumnValue' => $post->sku
						);
					patch_product($storeCode, $data, $apiKey);					
                }
            }
        }
    }
}

function patch_product($id, $data, $apiKey)
{
	$ch = curl_init();
    $url = "https://api.ventata.com/product/patch/woocommerce?ApiKey=" . $apiKey . "&StoreCode=" . $id;                
    curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
	$result = curl_exec($ch);	
	curl_close($ch);
}

function send_order_data_to_ventata( $order_id )
{
    $post = get_post( $order_id, ARRAY_A );
    $_order = &new woocommerce_order( $order_id );
    $apiKey = get_option('ventata_api_key');
    
    $sent_data = get_option('ventata_sent_order_' . $order_id);
    if($sent_data == 'false') {
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
        
        delete_option('ventata_sent_order_' . $order_id);
    }
}

add_action( 'woocommerce_thankyou', 'send_order_data_to_ventata' );

function create_order_sent_check($order_id) {
    add_option("ventata_sent_order_" . $order_id, 'false', '', 'yes');
}
add_action( 'woocommerce_new_order', 'create_order_sent_check' );


?>