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
        echo '<img src="'.get_bloginfo('wpurl').'/wp-content/plugins/faspay-woocommerce/assets/img/paywithfaspay.png">';
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
        if (get_option('faspay_merchant_env') == '1') {
            //production
            $url        = "https://xpress.faspay.co.id/v4/post";            
        }else{
            //sandbox
            $url        = "https://xpress-sandbox.faspay.co.id/v4/post";
        }

        $bytes          = random_bytes(10);
        $trx_identifier = bin2hex($bytes);
        $boi            = get_option('faspay_merchant_code');
        $password       = get_option('faspay_merchant_debit_password');
        $signature      = sha1(md5("bot".$boi.$password.$id.$total));
        $expired        = date('Y-m-d H:i:s', strtotime('+'.get_option('faspay_merchant_expired').' hours'));
        $tax            = $order->get_total_tax();
        $shipping       = $order->get_shipping_total();
        $disc           = $order->get_total_discount();
        $billgross      = 0;
        $billmiscfee    = $shipping + $tax - $disc;
        $srv            = get_bloginfo('wpurl');
        $return_url     = $srv."/checkout/order-received/";

        $post = array();
        $post['merchant_id'] = $boi;
        $post['merchant'] = get_option('faspay_merchant_name');
        $post['bill_no'] = $id;
        $post['bill_reff'] = $key;
        $post['bill_date'] = $date;
        $post['bill_expired'] = $expired;
        $post['bill_miscfee'] = "0";
        $post['bill_total'] = $total;
        $post['bill_tax'] = $order->get_total_tax();
        $post['bill_gross'] = $order->get_subtotal();
        $post['bill_desc'] = "Pembelian di ".get_option('faspay_merchant_name');
        if($customer == '' || $customer == ' '){
            $post['cust_name'] = 'Faspay';
        }else{
            $post['cust_name'] = $customer;
        }
        $post['cust_no'] = 'woocommerce';
        $post['return_url'] = $return_url;
        $post['msisdn'] = ($order->get_billing_phone()) ? $order->get_billing_phone() : '081234567890';
        $post['email'] = ($email) ? $email : 'test@test.test';
        $post['billing_address'] = ($bill_address) ? $bill_address : 'Jakarta';
        $post['billing_address_city'] = ($bill_city) ? $bill_city : 'Jakarta';
        $post['billing_address_region'] = ($bill_region) ? $bill_region : 'JK';
        $post['billing_address_state'] = ($bill_state) ? $bill_state : 'ID';
        $post['billing_address_poscode'] = ($bill_postcode) ? $bill_postcode : '12345';
        $post['billing_address_country_code'] = ($bill_state) ? $bill_state : 'ID';
        $post['receiver_name_for_shipping'] = ($customer) ? $customer : 'Faspay';
        $post['shipping_address'] = ($ship_address) ? $ship_address : 'Jakarta';
        $post['shipping_address_city'] = ($ship_city) ? $ship_city : 'Jakarta';
        $post['shipping_address_region'] = ($ship_region) ? $ship_region : 'JK';
        $post['shipping_address_state'] = ($ship_state) ? $ship_state : 'ID';
        $post['shipping_address_poscode'] = ($ship_postcode) ? $ship_postcode : '12345';
        $post['shipping_address_country_code'] = ($ship_state) ? $ship_state : 'ID';
        $post['signature'] = $signature;

        $post['item'][1]['product']  = "Checkout from ".get_option('faspay_merchant_name');
        $post['item'][1]['qty']      = "1";
        $post['item'][1]['amount']   = $order->get_total();

        $body       = json_encode($post);
        $response   = $this->curl($url, $body);
        $rst        = json_decode($response);


        if(!isset($rst->response_code)){
             wc_clear_notices();
             wc_add_notice('An error occurred while processing your request', 'notice');
             exit;
         }

        if($rst->response_code == "00"){
            // Mark the order as processed

            // Prepared statement untuk faspay_postdata
            $insert_trx = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$wpdb->prefix}faspay_postdata (order_id, date_trx, total_amount, post_data) VALUES (%s, %s, %s, %s)",
                    $id,
                    $date,
                    $total,
                    $body
                )
            );

            // Prepared statement untuk faspay_order
            $insert_trx2 = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$wpdb->prefix}faspay_order (order_id, date_trx, date_expire, total_amount, status) VALUES (%s, %s, %s, %s, %s)",
                    $id,
                    $date,
                    $expired,
                    $total,
                    '1'
                )
            );

            // Prepared statement untuk faspay_post
            $insert_trx3 = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$wpdb->prefix}faspay_post (order_id, date_trx, total_amount, post_data) VALUES (%s, %s, %s, %s)",
                    $id,
                    $date,
                    $total,
                    $body
                )
            );

            $redirect_success = $this->get_return_url( $order );

            // Redirect to the thank you page
            return array(
                'result'   => 'success',
                'redirect' => $rst->redirect_url,
            );
        }else{
            $errors['message'] = "<p>".$rst->response_desc."</p>";
            echo json_encode($errors);
            http_response_code(500);
            exit;
        }
    }
    
    // public function curl($url, $body){
    //     $c = curl_init ($url);
    //     curl_setopt ($c, CURLOPT_POST, true);
    //     curl_setopt ($c, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
    //     curl_setopt ($c, CURLOPT_POSTFIELDS, $body);
    //     curl_setopt ($c, CURLOPT_RETURNTRANSFER, true);
    //     curl_setopt($c, CURLOPT_SSL_VERIFYPEER, true);
    //     curl_setopt($c, CURLOPT_CAINFO, __DIR__ . '/faspay.crt');
    //     $rst = curl_exec ($c);
    //     curl_close ($c);

    //     return $rst;
    // }

    public function curl($url, $body) {
        $c = curl_init($url);
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
        curl_setopt($c, CURLOPT_POSTFIELDS, $body);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, true);
        // Ambil string SSL dari pengaturan
        $ssl_string = get_option('faspay_ssl_string');
        if ($ssl_string) {
            // Simpan string SSL ke file sementara
            $temp_file = sys_get_temp_dir() . '/faspay_cert.pem';
            file_put_contents($temp_file, $ssl_string);
    
            // Gunakan file sementara sebagai sertifikat CA
            curl_setopt($c, CURLOPT_CAINFO, $temp_file);
        } else {
            error_log('SSL string is empty or invalid.');
        }
    
        $rst = curl_exec($c);
    
        error_log('Data Response: ' . print_r($rst, true));
    
        curl_close($c);
    
        return $rst;
    }


    public function clean($string) {
       return preg_replace('/[^A-Za-z0-9\-]/', ' ', $string); // Removes special chars.
    }
}
?>