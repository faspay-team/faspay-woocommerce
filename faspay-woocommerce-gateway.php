<?php
/*
Plugin Name: Faspay Payment Gateway
Description: Faspay Payment Gateway Version: 4.0.0
Version: 4.0.0
Author: Faspay Development Team Author
Plugin URI: https://www.faspay.co.id
*/

include_once( plugin_dir_path( __FILE__ ) .'faspay-install.php' );
register_activation_hook( __FILE__ , 'faspay_activation_process' );
register_deactivation_hook( __FILE__ , 'faspay_uninstallation_process' );

add_action('plugins_loaded', 'woocommerce_faspay', 0);
function woocommerce_faspay(){
    if (!class_exists('WC_Payment_Gateway'))
        return; // if the WC payment gateway class 

    include_once(plugin_dir_path(__FILE__) . 'faspay-settings.php');
    include(plugin_dir_path(__FILE__) . 'class-gateway.php');
}

add_filter('woocommerce_payment_gateways', 'add_faspay_gateway');

function add_faspay_gateway($gateways) {
  $gateways[] = 'Faspay_Gateway';
  return $gateways;
}

/**
 * Custom function to declare compatibility with cart_checkout_blocks feature 
*/
function declare_cart_checkout_blocks_compatibility() {
    // Check if the required class exists
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        // Declare compatibility for 'cart_checkout_blocks'
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}
// Hook the custom function to the 'before_woocommerce_init' action
add_action('before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility');

add_action( 'woocommerce_thankyou_order_received_text', 'thanks', 20, 2);
function thanks(){
    global $woocommerce;
    global $wpdb;

    $ch 		= '';
    $order_id 	= '';
    $status   	= '';
    $trxid 		= '';
    $woocommerce->cart->empty_cart();

    if (isset($_GET['bill_no']) || isset($_GET['status'])) {
        $order_id = $_GET['bill_no'];
        $status   = $_GET['status'];
    }

    if (isset($_GET['ch']) || isset($_GET['trxid'])) {
        $ch     = $_GET['ch'];
        $trxid  = $_GET['trxid'];
    }

    if (isset($_GET['trx_id'])) {
        $trxidbca = $_GET['trx_id'];
    }else{
        $trxidbca = NULL;
    }

    $rawdata 	= file_get_contents("php://input");

    if ($rawdata != null || $rawdata != '') {
        $data       = explode("&",$rawdata);
        $response   = array();

        foreach($data as $string){
            $body = explode("=",$string);
            $key = $body[0];
            $value = $body[1];
            $response[$key] = $value;
        }

        $rwdatetrx 	= str_replace('%3A', ':', @$response['TRANDATE']);
        $datetrx1 	= str_replace('+', ' ', $rwdatetrx);
        $datetrx 	= date('Y-m-d H:i:s',strtotime($datetrx1));
        $trxcc 		= $response['MERCHANT_TRANID'].date('ymd').@$response['AUTH_ID']; //cc
        $query 		= $wpdb->get_results("select post_data from ".$wpdb->prefix."faspay_postdata where order_id = '".(int)$response['MERCHANT_TRANID']."'",ARRAY_A);
        $query2 	= $wpdb->get_results("select post_data from ".$wpdb->prefix."faspay_post where order_id = '".(int)$response['MERCHANT_TRANID']."'",ARRAY_A);

        if(!empty($query)){
            $datacc		= str_replace ('\"','"', $query[0]['post_data']);
            $hapuscc 	= str_replace(array('{','}', '"'), '', $datacc);
            $balikcc 	= explode(',', $hapuscc);
            $envcc 		= $query2[0]['post_data'];

            foreach ($balikcc as $key => $value) {
                $pecahcc = explode(':', $value);
                if (in_array($pecahcc[0] , array('RETURN_URL','style_image_url','callback','transactionDate','bill_date','bill_expired'))) {
                    if ($pecahcc[0]=='transactionDate' || $pecahcc[0]=='bill_date' || $pecahcc[0]=='bill_expired') {
                        $postcc[$pecahcc[0]] = $pecahcc[1].":".$pecahcc[2].":".$pecahcc[3];	
                    }else{
                        $post[$pecahcc[0]] = $pecahcc[1].":".$pecahcc[2];
                    }
                }else{
                    $postcc[$pecahcc[0]]  = $pecahcc[1];
                }
            }

            $mid_mrc 	= $response['MERCHANTID'];
            $pass_mrc 	= get_option('faspay_merchant_credit_password');
            $order 		= wc_get_order((int)$response['MERCHANT_TRANID']);
        }
    }else{
        $order = wc_get_order((int)$order_id);
    }

	//DEBIT
    if(!isset($response)){
        if ($status == '2') {
            switch ($ch) {
                case '405':
                    $woocommerce->cart->empty_cart();
                    $getid = $wpdb->get_results("select order_id,payment_reff from ".$wpdb->prefix."faspay_order where trx_id = '".$trxid."' limit 1",ARRAY_A);
                    $order = wc_get_order((int)$getid[0]['order_id']);
                    
                    if ($getid[0]['payment_reff'] == '2') {
                        $error = "Your order #".$getid[0]['order_id']." has been succeed.";
                        return $error;
                    }else{
                        $error = "Your order #".$getid[0]['order_id']." has been cancelled.";
                        return $error;
                    }
                break;

                default:
                    $error = "Your order #".$order_id." has been succeed.";
                    return $error;
                break;
            }
        }elseif($status == '1'){
            $error = "Your order #".$order_id." is still on process, please contact your merchant for further assistance.";
            return $error;
        }else{
            $error = "Your payment order has been failed. Please order again.";
            return $error;
        }
	}else if(isset($response)){
		if ($response['TXN_STATUS'] == 'A' && $response['SIGNATURE'] == ceksig($mid_mrc,$pass_mrc,$response['MERCHANT_TRANID'], $response['AMOUNT'], $response['TXN_STATUS'])) {
			$payment = wc_get_payment_gateway_by_order( $order );
			$envcc = get_option('faspay_merchant_env');
			$capture = requeryCapture($response,$envcc,$pass_mrc);

			if ($capture['TXN_STATUS'] == 'C') {
				$error = "Your order #".$response['MERCHANT_TRANID']." has been succeed.";
				return $error;
			}elseif ($capture['TXN_STATUS'] == 'E') {
				$error = "Your payment for order #".$response['MERCHANT_TRANID']." has been failed. Please order again.";
				return $error;
			}else{
				$error = "Your order #".$response['MERCHANT_TRANID']." is still on process, please contact your merchant for further assistance.";
				return $error;
			}
		}elseif (($response['TXN_STATUS']=='C' || $response['TXN_STATUS']=='S') && $response['SIGNATURE'] == ceksig($mid_mrc,$pass_mrc,$response['MERCHANT_TRANID'], $response['AMOUNT'], $response['TXN_STATUS'])) {
			if ($response['EXCEED_HIGH_RISK']=='No') {
				$error = "Your order #".$response['MERCHANT_TRANID']." has been succeed.";
				return $error;
			}elseif ($response['EXCEED_HIGH_RISK']=='Yes') {
				$error = "Your payment for order #".$response['MERCHANT_TRANID']." has been failed, please try again or contact your merchant if still facing same difficulties.";
				return $error;
			}
		}elseif ($response['TXN_STATUS']=="CF" && $response['SIGNATURE'] == ceksig($mid_mrc,$pass_mrc,$response['MERCHANT_TRANID'], $response['AMOUNT'], $response['TXN_STATUS'])) {
			$error = "Your order #".$response['MERCHANT_TRANID']." is still on process, please contact your merchant for further assistance.";
			return $error;
		}elseif ($response['TXN_STATUS']=="P" && $response['SIGNATURE'] == ceksig($mid_mrc,$pass_mrc,$response['MERCHANT_TRANID'], $response['AMOUNT'], $response['TXN_STATUS'])) {
			$error = "Your order #".$response['MERCHANT_TRANID']." is still on process, please contact your merchant for further assistance.";
			return $error;
		}elseif ($response['TXN_STATUS'] == 'F') {
			$error = "Your payment for order #".$response['MERCHANT_TRANID']." has been failed. Please order again.";
			return $error;
		}else{
			$error = ceksig($mid_mrc,$pass_mrc,$response['MERCHANT_TRANID'], $response['AMOUNT'], $response['TXN_STATUS']);
			return $error;
		}
	}
	// CREDIT CARD

	if (isset($response) && $response['TXN_STATUS']=="A") {
		if ($response['EXCEED_HIGH_RISK'] == "No") {
			autoThings($response['TRANSACTIONID'],$response['MERCHANT_TRANID'],"A");
		}
	}

	if($trxidbca != null || $trxidbca != ''){
		$woocommerce->cart->empty_cart();
	}
}

//NOTIFICATION
add_action( 'woocommerce_api_notify', 'notify' );
function notify() {
	include_once(plugin_dir_path(__FILE__) . 'faspay-settings.php');

	global $wpdb;
	global $woocommerce;
	
	$rawdata 	= file_get_contents("php://input");
	$response 	= array();
	
	if(isXml($rawdata) === FALSE){ //CREDIT CARD
		$datacc = explode("&",$rawdata);
		foreach($datacc as $string){
			$body = explode("=",$string);
			$key = isset($body[0]) ? $body[0] : null ;
			$value = isset($body[1]) ? $body[1] : null ;
			$response[$key] = $value;
		}
	
		if (isset($response['MERCHANT_TRANID'])){
			$orderidcc = (int)$response['MERCHANT_TRANID'];
		}
	
		$query 	= $wpdb->get_results("select post_data from ".$wpdb->prefix."faspay_postdata where order_id = '".$orderidcc."' limit 1",ARRAY_A);

		if (isset($query[0])) {
			$datq		= str_replace ('\"','"', $query[0]['post_data']);
		}
	
		if(isset($datq)){
			$hapus 		= str_replace(array('{','}', '"'), '', $datq);
		}
	
		if(isset($hapus)){
			$balik 		= explode(',', $hapus);
		}
	
		if(isset($balik)){
			foreach ($balik as $key => $value) {
				$pecah = explode(':', $value);
				if (in_array($pecah[0] , array('RETURN_URL','style_image_url','callback','transactionDate','bill_date','bill_expired'))) {
					if ($pecah[0]=='transactionDate' || $pecah[0]=='bill_date' || $pecah[0]=='bill_expired') {
						$post[$pecah[0]] = $pecah[1].":".$pecah[2].":".$pecah[3];	
					}else{
						$post[$pecah[0]] = $pecah[1].":".$pecah[2];
					}
				}else{
					$post[$pecah[0]]  = $pecah[1];
				}
			}
		}
	
		$mid_mrc  	= isset($response['MERCHANTID']) ? $response['MERCHANTID'] : get_option('faspay_merchant_code');
		$pass_mrc 	= get_option('faspay_merchant_credit_password');
		$ordercc  	= wc_get_order($orderidcc);
		$trxcc 	  	= $response['MERCHANT_TRANID'].date('ymd').$response['AUTH_ID']; //cc
		$rwdatetrx 	= str_replace('%3A', ':', $response['TRANDATE']);
		$datetrx1 	= str_replace('+', ' ', $rwdatetrx);
		$datetrx 	= date('Y-m-d H:i:s',strtotime($datetrx1));
		//print_r(sigcc($mid_mrc,$pass_mrc,$response['MERCHANT_TRANID'], $response['AMOUNT'], $response['TXN_STATUS']));exit;
	
		if (isset($ordercc)){
			if ($response['SIGNATURE'] == sigcc($mid_mrc,$pass_mrc,$response['MERCHANT_TRANID'], $response['AMOUNT'], $response['TXN_STATUS'])) {
				switch ($response['TXN_STATUS']) {
					case 'A':
						$update = $wpdb->query("update ". $wpdb->prefix ."faspay_order set trx_id = '".$trxcc."', trx_id_cc = '".$response['TRANSACTIONID']."', date_trx = '".$datetrx."', total_amount = '".str_replace('.00', '', $response['AMOUNT'])."', channel = '500', payment_reff = '".$response['BANK_REFERENCE']."', status = '1' where order_id = '".$orderidcc."'");
						$ordercc->update_status('wc-pending', __( 'Payment Processing.', 'woocommerce' ));
						$ordercc->add_order_note(__('Your order #'.$response['MERCHANT_TRANID'].'is still on process, please contact your merchant for further assistance.', 'woocommerce'));
						echo "Payment Processing";
					break;
	
					case 'CF':
						$update = $wpdb->query("update ". $wpdb->prefix ."faspay_order set trx_id = '".$trxcc."', trx_id_cc = '".$response['TRANSACTIONID']."', date_trx = '".$datetrx."', total_amount = '".str_replace('.00', '', $response['AMOUNT'])."', channel = '500', payment_reff = '".$response['BANK_REFERENCE']."', status = '3' where order_id = '".$orderidcc."'");
						$ordercc->update_status('wc-failed', __( 'Payment Failed.', 'woocommerce' ));
						$ordercc->add_order_note(__('Pembayaran tidak berhasil.', 'woocommerce'));
						echo "Payment Failed";
					break;
	
					case 'P':
						$update = $wpdb->query("update ". $wpdb->prefix ."faspay_order set trx_id = '".$trxcc."', trx_id_cc = '".$response['TRANSACTIONID']."', date_trx = '".$datetrx."', total_amount = '".str_replace('.00', '', $response['AMOUNT'])."', channel = '500', payment_reff = '".$response['BANK_REFERENCE']."', status = '1' where order_id = '".$orderidcc."'");
						$ordercc->update_status('wc-pending', __( 'Payment Processing.', 'woocommerce' ));
						$ordercc->add_order_note(__('Your order #'.$response['MERCHANT_TRANID'].'is still on process, please contact your merchant for further assistance.', 'woocommerce'));
						echo "Payment Processing";
					break;
		
					case 'C':
						$update = $wpdb->query("update ". $wpdb->prefix ."faspay_order set trx_id = '".$trxcc."', trx_id_cc = '".$response['TRANSACTIONID']."', date_trx = '".$datetrx."', total_amount = '".str_replace('.00', '', $response['AMOUNT'])."', channel = '500', payment_reff = '".$response['BANK_REFERENCE']."', status = '2', date_payment = '".date('Y-m-d H:i:s')."' where order_id = '".$orderidcc."'");
						$ordercc->update_status('wc-processing', __( 'Payment Success.', 'woocommerce' ));
						$ordercc->add_order_note(__('Pembayaran telah dilakukan melalui creditcard melalui Faspay dengan id '.$response['MERCHANT_TRANID']. '. Status: Success('.$response['TXN_STATUS'].')', 'woocommerce'));
						echo "Payment Success";
					break;
	
					case 'S':
						$update = $wpdb->query("update ". $wpdb->prefix ."faspay_order set trx_id = '".$trxcc."', trx_id_cc = '".$response['TRANSACTIONID']."', date_trx = '".$datetrx."', total_amount = '".str_replace('.00', '', $response['AMOUNT'])."', channel = '500', payment_reff = '".$response['BANK_REFERENCE']."', status = '2', date_payment = '".date('Y-m-d H:i:s')."' where order_id = '".$orderidcc."'");
						$ordercc->update_status('wc-processing', __( 'Payment Success.', 'woocommerce' ));
						$ordercc->add_order_note(__('Pembayaran telah dilakukan melalui creditcard melalui Faspay dengan id '.$response['MERCHANT_TRANID']. '. Status: Success('.$response['TXN_STATUS'].')', 'woocommerce'));
						echo "Payment Success";
					break;
	
					case 'V':
						$update = $wpdb->query("update ". $wpdb->prefix ."faspay_order set trx_id = '".$trxcc."', trx_id_cc = '".$response['TRANSACTIONID']."', date_trx = '".$datetrx."', total_amount = '".str_replace('.00', '', $response['AMOUNT'])."', channel = '500', payment_reff = '".$response['BANK_REFERENCE']."', status = '3' where order_id = '".$orderidcc."'");
						$ordercc->update_status('wc-cancelled', __( 'Payment Void.', 'woocommerce' ));
						$ordercc->add_order_note(__('Pembayaran tidak berhasil.', 'woocommerce'));
						echo "Payment Void";
					break;
	
					case 'F':
						$update = $wpdb->query("update ". $wpdb->prefix ."faspay_order set trx_id = '".$trxcc."', trx_id_cc = '".$response['TRANSACTIONID']."', date_trx = '".$datetrx."', total_amount = '".str_replace('.00', '', $response['AMOUNT'])."', channel = '500', payment_reff = '".$response['BANK_REFERENCE']."', status = '3' where order_id = '".$orderidcc."'");
						$ordercc->update_status('wc-failed', __( 'Payment Void.', 'woocommerce' ));
						$ordercc->add_order_note(__('Pembayaran tidak berhasil.', 'woocommerce'));
						echo "Payment Failed";
					break;
				}
	
				$woocommerce->cart->empty_cart();
			}else{
				echo "Invalid Signature";
			}
		}else{
			echo "Transaction not found";
		}
	}else{ //DEBIT
		$xml_post 	= preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $rawdata);
		$data  		= simplexml_load_string($xml_post);
		$attr 		= xml2array($data);
		$orderid 	= $attr['bill_no'];
		$trxid 		= $attr['trx_id'];
		$boi 		= $attr['merchant_id'];
		$codes 		= $attr['payment_status_code'];
		$query 		= $wpdb->get_results("select post_data from ".$wpdb->prefix."faspay_postdata where order_id = '".$orderid."' limit 1",ARRAY_A);
	
		if (isset($query[0])) {
			$datq		= str_replace ('\"','"', $query[0]['post_data']);
		}
	
		if(isset($datq)){
			$hapus 		= str_replace(array('{','}', '"'), '', $datq);
		}
	
		if(isset($hapus)){
			$balik 		= explode(',', $hapus);
		}
	
		if(isset($balik)){
			foreach ($balik as $key => $value) {
				$pecah = explode(':', $value);
				if (in_array($pecah[0] , array('RETURN_URL','style_image_url','callback','transactionDate','bill_date','bill_expired'))) {
					if ($pecah[0]=='transactionDate' || $pecah[0]=='bill_date' || $pecah[0]=='bill_expired') {
						$post[$pecah[0]] = $pecah[1].":".$pecah[2].":".$pecah[3];	
					}else{
						$post[$pecah[0]] = $pecah[1].":".$pecah[2];
					}
				}else{
					$post[$pecah[0]]  = $pecah[1];
				}
			}
		}
	
		$order 			= wc_get_order($orderid);
		//$order->update_status('wc-completed', __( 'Payment Success.', 'woocommerce' ));
		$iduser 		= isset($post['merchant_id']) ? $post['merchant_id'] : get_option('faspay_merchant_code');
		$pass 	 		= get_option('faspay_merchant_debit_password');
		$sig 			= sha1(md5('bot'.$iduser.$pass.$orderid.$codes));
		$paymentdate 	= $attr['payment_date'];
		$response_date 	= date('Y-m-d H:i:s');
		$status 		= $order->get_status();
		
		if ($status != 'completed') {
			switch ($codes) {
				case '2':
					if ($sig == $attr['signature']) {
						$order->add_order_note(__('Pembayaran telah dilakukan melalui faspay dengan id '.$orderid. ' dan trxid '.$trxid .' pada tanggal '.$paymentdate.'.', 'woocommerce'));
						$order->update_status('wc-processing', __( 'Payment Success.', 'woocommerce' ));
						$updatefp = $wpdb->query("update ". $wpdb->prefix ."faspay_order SET trx_id = '".$trxid."', status = '2', payment_reff = '".$attr['payment_reff']."', channel = '".$attr['payment_channel_uid']."', date_payment = '".$paymentdate."' WHERE order_id = '".$orderid."'");
						$xml ="<faspay>";
						   $xml.="<response>Payment Notification</response>";
						   $xml.="<trx_id>".$trxid."</trx_id>";
						   $xml.="<merchant_id>".$boi."</merchant_id>";
						   $xml.="<bill_no>".$orderid."</bill_no>";
						   $xml.="<response_code>00</response_code>";
						   $xml.="<response_desc>Sukses</response_desc>";
						   $xml.="<response_date>".$response_date."</response_date>";
						   $xml.="</faspay>";
						echo "$xml";
	
						$woocommerce->cart->empty_cart();
					}else{
						echo "Signature Not Match";
					}
				break;
				case '3':
					echo "Transaction Failed";
					$order->update_status('wc-cancelled', __( 'Payment Failed.', 'woocommerce' ));
					$updatefp = $wpdb->query("update ". $wpdb->prefix ."faspay_order SET trx_id = '".$trxid."', status = '3', payment_reff = '".$attr['payment_reff']."', date_payment = '".$paymentdate."' WHERE order_id = '".$orderid."'");
					$order->add_order_note(__('Pembayaran tidak berhasil.', 'woocommerce'));
				break;
				case '1':
					echo "Transaction in process";
				break;
			}
		}else{
			$xml ="<faspay>";
			$xml.="<response>Payment Notification</response>";
			$xml.="<trx_id>".$trxid."</trx_id>";
			$xml.="<merchant_id>".$boi."</merchant_id>";
			$xml.="<bill_no>".$orderid."</bill_no>";
			$xml.="<response_code>14</response_code>";
			$xml.="<response_desc>Transaction Already Paid</response_desc>";
			$xml.="<response_date>".$response_date."</response_date>";
			$xml.="</faspay>";
			
			echo "$xml";
		}
	}
	exit;
}

// Hook the custom function to the 'woocommerce_blocks_loaded' action
add_action( 'woocommerce_blocks_loaded', 'oawoo_register_order_approval_payment_method_type' );

/**
 * Custom function to register a payment method type

 */
function oawoo_register_order_approval_payment_method_type() {
    // Check if the required class exists
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }

    // Include the custom Blocks Checkout class
    require_once plugin_dir_path(__FILE__) . 'class-block.php';

    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            // Register an instance of My_Custom_Gateway_Blocks
            $payment_method_registry->register( new Faspay_Gateway_Blocks );
        }
    );
}

/* THANKS PAGE */
function ceksig($mid_mrc,$pass_mrc,$merchant_trandid, $amount, $txn_status){
	$signature = sha1(strtoupper('##'.$mid_mrc.'##'.$pass_mrc.'##'.$merchant_trandid.'##'.$amount.'##'.$txn_status.'##'));
	return strtoupper($signature);
}

function requeryCapture($data,$server,$pass){
	$sigcapture	= sha1('##'.strtoupper($data["MERCHANTID"]).'##'.strtoupper($pass).'##'.$data["MERCHANT_TRANID"].'##'.$data["AMOUNT"].'##'.$data["TRANSACTIONID"].'##');
	$post = array(
		"PAYMENT_METHOD"		=> '1',
		"TRANSACTIONTYPE"		=> '2',
		"MERCHANTID"			=> $data["MERCHANTID"],
		"MERCHANT_TRANID"		=> $data["MERCHANT_TRANID"],
		"TRANSACTIONID"			=> $data["TRANSACTIONID"],
		"AMOUNT"				=> $data["AMOUNT"],
		"RESPONSE_TYPE"			=> '3',
		"SIGNATURE"				=> $sigcapture
	);
	$a	= inquiryCapture($post,$server);
	return $a;
}

function inquiryCapture($post,$server){
	$url 	= $server == "1" ? "https://fpg.faspay.co.id/payment/api" : 
	"https://fpg-sandbox.faspay.co.id/payment/api";

	foreach($post as $key => $value){
		$post_items[] = $key . '=' . $value;
	}

	$postData = implode ('&', $post_items);
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$result	= curl_exec($ch);
	curl_close($ch);
	$lines	= explode(';',$result);
	$result = array();

	foreach($lines as $line){
		list($key,$value) = array_pad(explode('=', $line, 2), 2, null);
		$result[trim($key)] = trim($value);			
	}

	return $result;
}

function autoThings($transId,$orderId,$stat){
	global $wpdb;

	$orderr 	= wc_get_order($orderId);
	$param 		= $wpdb->get_results("select post_data from ".$wpdb->prefix."faspay_postdata where order_id = '".$orderId."'",ARRAY_A);
	$data		= str_replace ('\"','"', $param[0]['post_data']);
	$hapus 		= str_replace(array('{','}', '"'), '', $data);
	$balik 		= explode(',', $hapus);

	foreach ($balik as $key => $value) {
		$pecah = explode(':', $value);
		if (in_array($pecah[0] , array('RETURN_URL','style_image_url'))) {
			$post[$pecah[0]] = $pecah[1].":".$pecah[2];
		}else{
			$post[$pecah[0]]  = $pecah[1];
		}
	}

	$merchant_id = $post['MERCHANTID'];
	$password 	 = get_option('faspay_merchant_credit_password');
	$amount 	 = $post['AMOUNT'].'.00';
	$signature = strtoupper(sha1(strtoupper('##'.$merchant_id.'##'.$password.'##'.$orderId.'##'.$amount.'##'.$transId.'##')));
	$trxtype = "";

	if ($stat=="A") {
		$trxtype = '2';
	}elseif ($stat=="V") {
		$trxtype = '10';
	}

	$post = array(
		"PAYMENT_METHOD"	=> '1',
		"TRANSACTIONTYPE"	=> $trxtype,
		"MERCHANTID"		=> $merchant_id,
		"MERCHANT_TRANID"	=> $orderId,
		"TRANSACTIONID" 	=> $transId,
		"AMOUNT"			=> $amount,
		"RESPONSE_TYPE"		=> '3',
		"SIGNATURE"			=> $signature,
	);

	$data 	= http_build_query($post);
	$ch 	= curl_init();

	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($ch, CURLOPT_URL, "https://fpg.faspay.co.id/payment/api");
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$result = curl_exec($ch);
	curl_close($ch);
	$array = explode(";", $result);
	$response = array();

	foreach ($array as $string) {
		$body = explode("=", $string);
		$key  = $body[0];
		if ($body[1]) {
			$value = $body[1];
		}else{
			$value = "NULL";
		}
		$response[$key] = "NULL";
	}

	return $response;
}
/* THANKS PAGE */

/* NOTIFY */
function xml2array($xmlObject, $out = [])
{
	foreach((array) $xmlObject as $index => $node)
	{
		$out[$index] = (is_object($node)) ? xml2array($node) : $node;
	}

	return $out;
}

function isXml(string $value): bool{
    $prev = libxml_use_internal_errors(true);

    $doc = simplexml_load_string($value);
    $errors = libxml_get_errors();

    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    return false !== $doc && empty($errors);
}

function sigcc($mid_mrc,$pass_mrc,$merchant_trandid, $amount, $txn_status){
	$signature = sha1(strtoupper('##'.$mid_mrc.'##'.$pass_mrc.'##'.$merchant_trandid.'##'.$amount.'##'.$txn_status.'##'));
 	return strtoupper($signature);
}
/* NOTIFY */
?>