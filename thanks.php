<?php
require_once '../../../wp-config.php';
require_once '../../../wp-settings.php';

global $woocommerce;
global $wpdb;

$ch 		='';
$order_id 	='';
$status   	= '';
$trxid 		= '';
$woocommerce->cart->empty_cart();

if (isset($_GET['bill_no']) || isset($_GET['status'])) {
	$order_id = $_GET['bill_no'];
	$status   = $_GET['status'];
}

if (isset($_GET['ch']) || isset($_GET['trxid'])) {
	$ch  = $_GET['ch'];
	$trxid = $_GET['trxid'];
}

if (isset($_GET['trx_id'])) {
	$trxidbca = $_GET['trx_id'];
}else{
	$trxidbca = NULL;
}

$rawdata 	= file_get_contents("php://input");

if ($rawdata != null || $rawdata != '') {
	$data 		= explode("&",$rawdata);
	$response = array();

	foreach($data as $string){
		$body = explode("=",$string);
		$key = $body[0];
		$value = $body[1];
		$response[$key] = $value;
	}

	$rwdatetrx 	 = str_replace('%3A', ':', @$response['TRANDATE']);
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
	$order 		= wc_get_order((int)$order_id);
}

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
	curl_setopt($ch, CURLOPT_URL, "https://fpgdev.faspay.co.id/payment/api");
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

$icon_success 	= '<svg xmlns="http://www.w3.org/2000/svg" class="text-success" width="75" height="75"
			            fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
			            <path
			                d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z" />
			        </svg>';

$icon_failed 	= '<svg xmlns="http://www.w3.org/2000/svg" class="text-danger" width="75" height="75" fill="currentColor" class="bi bi-x-circle" viewBox="0 0 16 16">
					  <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
					  <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708"/>
					</svg>';

$icon_process 	= '<svg xmlns="http://www.w3.org/2000/svg" class="text-primary" width="75" height="75" fill="currentColor" class="bi bi-clock-fill" viewBox="0 0 16 16">
					  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71z"/>
					</svg>';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order</title>
    <link href="<?= get_bloginfo('wpurl') ?>/wp-content/plugins/faspay-woocommerce/assets/bootstrap-5.2.0.min.css" rel="stylesheet">
</head>

<body>
    <div class="vh-100 d-flex justify-content-center align-items-center">
        <div>

		<?php
		// DEBIT
		if(!isset($response)){
			if ($status == '2') {
				switch ($ch) {
					case '405':
						$woocommerce->cart->empty_cart();
						$getid = $wpdb->get_results("select order_id,payment_reff from ".$wpdb->prefix."faspay_order where trx_id = '".$trxid."' limit 1",ARRAY_A);
						$order = wc_get_order((int)$getid[0]['order_id']);
						
						if ($getid[0]['payment_reff'] == '2') {
							echo '<div class="mb-4 text-center">
					                '.$icon_success.'
					            </div>
					            <div class="text-center">
					                <h1>Thank You !</h1>
					                <p>Your order #'.$getid[0]['order_id'].' has been succeed.</p>
					                <a href="'.home_url().'" class="btn btn-primary">Back Home</a>
					            </div>';
						}else{
							echo '<div class="mb-4 text-center">
			            		   '.$icon_failed.'
			            		</div>
			            		<div class="text-center">
			            		    <h1>Failed</h1>
			            		    <p>Your order #'.$getid[0]['order_id'].' has been cancelled.</p>
			            		    <a href="'.home_url().'" class="btn btn-primary">Back Home</a>
			            		</div>';
						}
					break;
				
					default:
						echo '<div class="mb-4 text-center">
				                '.$icon_success.'
				            </div>
				            <div class="text-center">
				                <h1>Thank You !</h1>
				                <p>Your order #'.$order_id.' has been succeed.</p>
				                <a href="'.home_url().'" class="btn btn-primary">Back Home</a>
				            </div>';
					break;
				}
			}elseif($status == '1'){
				echo '<div class="mb-4 text-center">
				        '.$icon_process.'
				    </div>
				    <div class="text-center">
				        <h1>Info</h1>
				        <p>Your order #'.$order_id.' is still on process, please contact your merchant for further assistance.</p>
				        <a href="'.home_url().'" class="btn btn-primary">Back Home</a>
				    </div>';
			}else{
				echo '<div class="mb-4 text-center">
				        '.$icon_failed.'
				    </div>
				    <div class="text-center">
				        <h1>Failed</h1>
				        <p>Your payment order has been failed. Please order again.</p>
				        <a href="'.home_url().'" class="btn btn-primary">Back Home</a>
				    </div>';
			}
		// CREDIT CARD
		}else if(isset($response)){
			if ($response['TXN_STATUS'] == 'A' && $response['SIGNATURE'] == ceksig($mid_mrc,$pass_mrc,$response['MERCHANT_TRANID'], $response['AMOUNT'], $response['TXN_STATUS'])) {
				$payment = wc_get_payment_gateway_by_order( $order );
				$envcc = get_option('faspay_merchant_env');
				$capture = requeryCapture($response,$envcc,$pass_mrc);
		
				if ($capture['TXN_STATUS'] == 'C') {
					echo '<div class="mb-4 text-center">
			                '.$icon_success.'
			            </div>
			            <div class="text-center">
			                <h1>Thank You !</h1>
			                <p>Your order #'.$response['MERCHANT_TRANID'].' has been succeed.</p>
			                <a href="'.home_url().'" class="btn btn-primary">Back Home</a>
			            </div>';
				}elseif ($capture['TXN_STATUS'] == 'E') {
					echo '<div class="mb-4 text-center">
					        '.$icon_failed.'
					    </div>
					    <div class="text-center">
					        <h1>Failed</h1>
					        <p>Your payment for order #'.$response['MERCHANT_TRANID'].' has been failed. Please order again.</p>
					        <a href="'.home_url().'" class="btn btn-primary">Back Home</a>
					    </div>';
				}else{
					echo '<div class="mb-4 text-center">
					        '.$icon_process.'
					    </div>
					    <div class="text-center">
					        <h1>Info</h1>
					        <p>Your order #'.$response['MERCHANT_TRANID'].' is still on process, please contact your merchant for further assistance.</p>
					        <a href="'.home_url().'" class="btn btn-primary">Back Home</a>
					    </div>';
				}
			}elseif (($response['TXN_STATUS']=='C' || $response['TXN_STATUS']=='S') && $response['SIGNATURE'] == ceksig($mid_mrc,$pass_mrc,$response['MERCHANT_TRANID'], $response['AMOUNT'], $response['TXN_STATUS'])) {
				if ($response['EXCEED_HIGH_RISK']=='No') {
					echo '<div class="mb-4 text-center">
					        '.$icon_success.'
					    </div>
					    <div class="text-center">
					        <h1>Success</h1>
					        <p>Your order #'.$response['MERCHANT_TRANID'].' has been succeed.</p>
					        <a href="'.home_url().'" class="btn btn-primary">Back Home</a>
					    </div>';
				}elseif ($response['EXCEED_HIGH_RISK']=='Yes') {
					$voidRes = autoThings($response['TRANSACTIONID'],(int)$response['MERCHANT_TRANID'],'V');
					if ($voidRes['TXN_STATUS'] == "V") {
						echo '<div class="mb-4 text-center">
						        '.$icon_failed.'
						    </div>
						    <div class="text-center">
						        <h1>Cancelled</h1>
						        <p>Your payment for order #'.$response['MERCHANT_TRANID'].' has been failed, please try again or contact your merchant if still facing same difficulties.</p>
						        <a href="'.home_url().'" class="btn btn-primary">Back Home</a>
						    </div>';
					}
				}
			}elseif ($response['TXN_STATUS']=="CF" && $response['SIGNATURE'] == ceksig($mid_mrc,$pass_mrc,$response['MERCHANT_TRANID'], $response['AMOUNT'], $response['TXN_STATUS'])) {
				echo '<div class="mb-4 text-center">
					    '.$icon_process.'
					</div>
					<div class="text-center">
					    <h1>Info</h1>
					    <p>Your order #'.$response['MERCHANT_TRANID'].' is still on process, please contact your merchant for further assistance.</p>
					    <a href="'.home_url().'" class="btn btn-primary">Back Home</a>
					</div>';
			}elseif ($response['TXN_STATUS']=="P" && $response['SIGNATURE'] == ceksig($mid_mrc,$pass_mrc,$response['MERCHANT_TRANID'], $response['AMOUNT'], $response['TXN_STATUS'])) {
				echo '<div class="mb-4 text-center">
					    '.$icon_process.'
					</div>
					<div class="text-center">
					    <h1>Info</h1>
					    <p>Your order #'.$response['MERCHANT_TRANID'].' is still on process, please contact your merchant for further assistance.</p>
					    <a href="'.home_url().'" class="btn btn-primary">Back Home</a>
					</div>';
			}elseif ($response['TXN_STATUS'] == 'F') {
				echo '<div class="mb-4 text-center">
					    '.$icon_failed.'
					</div>
					<div class="text-center">
					    <h1>Info</h1>
					    <p>Your payment for order #'.$response['MERCHANT_TRANID'].' has been failed. Please order again.</p>
					    <a href="'.home_url().'" class="btn btn-primary">Back Home</a>
					</div>';
			}else{
				echo '<div class="mb-4 text-center">
					    '.$icon_failed.'
					</div>
					<div class="text-center">
					    <h1>Failed</h1>
					    <p>Your payment for order #'.$order->get_id().' has been failed. Please order again.</p>
					    <a href="'.home_url().'" class="btn btn-primary">Back Home</a>
					</div>';
			}
		}

		if (isset($response) && $response['TXN_STATUS']=="A") {
			if ($response['EXCEED_HIGH_RISK'] == "No") {
				autoThings($response['TRANSACTIONID'],$response['MERCHANT_TRANID'],"A");
			}
		}

		if($trxidbca != null || $trxidbca != ''){
			$woocommerce->cart->empty_cart();
			echo '<div class="primary">
					<div class="col-md-12">
					</div>
				</div>
				<br>';
		}
		?>
	</div>
</body>
</html>