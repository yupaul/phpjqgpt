<?php

function call_api(array &$data, string $api_key) {
  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => array(
      'Authorization: Bearer ' . $api_key,
      'Content-Type: application/json'
    ),
  ));

  $response = curl_exec($curl);
  //echo $response;
  $out = json_decode($response, true);
  curl_close($curl);
  return ($out ? $out : $response);
}

function validate(string $k, $v) {
	switch($k) {
		case 'max_tokens':	
			if(!preg_match("/^\d+$/", $v)) return false;
			$v = (int) $v;
			if($v < 1 || $v > 2048) return false;
			break;
		case 'temperature':
		case 'top_p':
			if(!preg_match("/^\d+(?:\.\d+)?$/", $v)) return false;
			$v = round(floatval($v), 6);
			if($v <= 0 || $v >= 2) return false;
			break;
		default:
			break;
	}
	return $v;
}

function hs(string $s) : string {
	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
		
	
