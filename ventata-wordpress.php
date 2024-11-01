<?php
$apiKey = get_option('ventata_api_key');

if($_GET["ventata_api"] == $apiKey) {
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        $post_id = $_POST['WPProductId'];       
        $price = $_POST['Price'];
        update_post_meta( $post_id, '_price', $price );
        update_post_meta( $post_id, '_regular_price', $price );
    } 
} else {
    echo "Not Authorized";
}
?>