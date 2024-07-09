<?php
class Faspay_Gateway extends WC_Payment_Gateway {
  
    // Constructor method
    public function __construct() {
        $this->id                 = 'faspay_gateway';
        $this->method_title       = __('Pay with Faspay', 'faspay-gateway');
        $this->method_description = __('Accept payments through Faspay Payment Gateway', 'faspay-gateway');

        $this->enabled          = $this->get_option( 'enabled' );
        $this->title            = "Faspay Payment Gateway";
        $this->description      = "Faspay Payment Gateway";
        
        // Other initialization code goes here
        
        $this->init_form_fields();
        $this->init_settings();
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }
  
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'faspay-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable Faspay Payment Gateway', 'faspay-gateway'),
                'default' => 'yes',
            ),
            // Add more settings fields as needed
        );
    }

    // View payment
    public function payment_fields() {
        
    }
  
    // Process the payment
    public function process_payment($order_id) {
        global $wpdb;

        //Order
        $order          = wc_get_order($order_id);
        $id             = $order->get_id();
        $key            = $order->get_order_key();
        $total          = intval($order->get_total());
        $date           = date('Y-m-d H:i:s');

        //Customer
        $customer       = $this->clean($order->get_billing_first_name()." ".$order->get_billing_last_name());
        $phone          = $order->get_billing_phone();
        $email          = $order->get_billing_email();

        //Billing
        $bill_address   = $this->clean($order->get_billing_address_1());
        $bill_city      = $this->clean($order->get_billing_city());
        $bill_region    = $this->clean($order->get_billing_state());
        $bill_state     = $this->clean($order->get_billing_country());
        $bill_postcode  = $this->clean($order->get_billing_postcode());

        //Shipping
        $ship_address   = $this->clean($order->get_shipping_address_1());
        $ship_city      = $this->clean($order->get_shipping_city());
        $ship_region    = $this->clean($order->get_shipping_state());
        $ship_state     = $this->clean($order->get_shipping_country());
        $ship_postcode  = $this->clean($order->get_shipping_postcode());
      
        // Implement your payment processing logic here
        if (get_option('faspay_merchant_env') == '1') { //production
            $url        = "https://xpress.faspay.co.id/v4/post";            
        }else{ //sandbox
            $url        = "https://xpress-sandbox.faspay.co.id/v4/post";
        }

        $bytes          = random_bytes(10);
        $trx_identifier = bin2hex($bytes);
        $boi            = get_option('faspay_merchant_code');
        $password       = get_option('faspay_merchant_debit_password');
        $signature      = sha1(md5("bot".$boi.$password.$id.$total));
        $expired        = date('Y-m-d H:i:s', strtotime('+'.get_option('faspay_merchant_expired').' hours'));
        $tax            = $order->get_total_tax();
        $disc           = $order->get_total_discount();
        $billgross      = 0;
        $billmiscfee    = $order->get_shipping_total();
        $additional_fee = $billmiscfee + $tax - $disc;

        if($order->get_user_id() != 0 || $order->get_user_id() != NULL){
            $userid = $order->get_user_id();
        }else{
            $userid = 1;
        }

        if($order->get_billing_phone() != NULL || $order->get_billing_phone() != ""){
            $phone = $order->get_billing_phone();
        }else{
            $phone = "081234567890";
        }

        $srv            = get_bloginfo('wpurl');
        $return_url     = $srv."/wp-content/plugins/faspay-woocommerce/thanks.php";

        $post = array();
        $post['merchant_id'] = $boi;
        $post['merchant'] = get_option('faspay_merchant_name');
        $post['bill_no'] = $id;
        $post['bill_reff'] = $key;
        $post['bill_date'] = $date;
        $post['bill_expired'] = $expired;
        $post['bill_miscfee'] = $billmiscfee;
        $post['bill_total'] = $total;
        $post['bill_tax'] = $order->get_total_tax();
        $post['bill_gross'] = $order->get_subtotal();
        $post['bill_desc'] = "Pembelian di ".get_option('faspay_merchant_name');
        $post['cust_name'] = $customer;
        $post['cust_no'] = $userid;
        $post['return_url'] = $return_url;
        $post['msisdn'] = $phone;
        $post['email'] = $email;
        $post['billing_address'] = $bill_adress;
        $post['billing_address_city'] = $bill_city;
        $post['billing_address_region'] = $bill_region;
        $post['billing_address_state'] = $bill_state;
        $post['billing_address_poscode'] = $bill_postcode;
        $post['billing_address_country_code'] = $bill_state;
        $post['receiver_name_for_shipping'] = $customer;
        $post['shipping_address'] = $ship_address;
        $post['shipping_address_city'] = $ship_city;
        $post['shipping_address_region'] = $ship_region;
        $post['shipping_address_state'] = $ship_state;
        $post['shipping_address_poscode'] = $ship_postcode;
        $post['shipping_address_country_code'] = $ship_state;
        $post['signature'] = $signature;

        $no = 0;
        foreach ( $order->get_items() as $item ) {
            $no ++;
            //$billgross += ($item->get_subtotal() / $item->get_quantity()) - $disc;
            $post['item'][$no]['product']    = $this->clean($item->get_name());
            $post['item'][$no]['qty']        = $item->get_quantity();
            $post['item'][$no]['amount']     = $item->get_subtotal() / $item->get_quantity();
        }

        if($billmiscfee > 0){
            $ship_no                            = $no + 1;
            $post['item'][$ship_no]['product']  = "Shipping Fee";
            $post['item'][$ship_no]['qty']      = "1";
            $post['item'][$ship_no]['amount']   = $billmiscfee;
        }

        if($tax > 0){
            $tax_no                             = $no + 2;
            $post['item'][$tax_no]['product']   = "Tax Fee";
            $post['item'][$tax_no]['qty']       = "1";
            $post['item'][$tax_no]['amount']    = $tax;
        }

        if($disc > 0){
            $disc_no                            = $no + 3;
            $post['item'][$disc_no]['product']  = "Discount";
            $post['item'][$disc_no]['qty']      = "1";
            $post['item'][$disc_no]['amount']   = "-".$disc;
        }
        
        $body       = json_encode($post);
        $response   = $this->curl($url, $body);
        $rst        = json_decode($response);

        if($rst->response_code == "00"){
            // Mark the order as processed
            $order->payment_complete();

            $insert_trx = $wpdb->query("insert into ". $wpdb->prefix ."faspay_postdata (order_id, date_trx, total_amount, post_data) values ('".$id."', '".$date."', '".$total."', '".$body."')");
            $insert_trx2 = $wpdb->query("insert into ". $wpdb->prefix ."faspay_order (order_id, date_trx, date_expire, total_amount, status) values ('".$id."', '".$date."', '".$expired."', '".$total."', '1')");
            $insert_trx3 = $wpdb->query("insert into ". $wpdb->prefix ."faspay_post (order_id, date_trx, total_amount, post_data) values ('$id', '".$date."', '".$total."', '".$body."')");

            $redirect_success = $this->get_return_url( $order );

            // Redirect to the thank you page
            return array(
                'result'   => 'success',
                'redirect' => $rst->redirect_url,
            );
        }
    }

    public function curl($url, $body){
        $c = curl_init ($url);
        curl_setopt ($c, CURLOPT_POST, true);
        curl_setopt ($c, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
        curl_setopt ($c, CURLOPT_POSTFIELDS, $body);
        curl_setopt ($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt ($c, CURLOPT_SSL_VERIFYPEER, false);
        $rst = curl_exec ($c);
        curl_close ($c);

        return $rst;
    }

    public function clean($string) {
       return preg_replace('/[^A-Za-z0-9\-]/', ' ', $string); // Removes special chars.
    }
}
?>