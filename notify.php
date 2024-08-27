<?php
require_once '../../../wp-config.php';
require_once '../../../wp-settings.php';

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