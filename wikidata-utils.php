<?php

require_once 'vendor/autoload.php';

//----------------------------------------------------------------------------------------
// Extract type of work from title
function types_from_title(&$w, $title)
{
	// errata
	if (preg_match('/^ERRATA\b/i', $title))
	{
		$w[] = array('P31' => 'Q1348305');	
		
		if (preg_match('/^ERRATA ET ADDENDA/i', $title))
		{
			$w[] = array('P31' => 'Q352858');	
		}
	}
}	

//----------------------------------------------------------------------------------------
function nice_strip_tags($str)
{
	$str = preg_replace('/</u', ' <', $str);
	$str = preg_replace('/>/u', '> ', $str);
	
	$str = strip_tags($str);
	
	$str = preg_replace('/&amp;/u', '&', $str);
	
	$str = preg_replace('/\s\s+/u', ' ', $str);
	
	$str = preg_replace('/^\s+/u', '', $str);
	$str = preg_replace('/\s+$/u', '', $str);
	
	return $str;
}

//----------------------------------------------------------------------------------------
// trim a string nicely
function nice_shorten($str, $length = 250) {
	if (mb_strlen($str) > $length)
	{
		$str = mb_substr($str, 0, $length - 1);
		
		$pos = mb_strrpos($str, ' ');
		if ($pos === false) {
		} else {
			$str = mb_substr($str, 0, $pos);		
		}
		
		$str .= '…';	
	}

	return $str;
}

//----------------------------------------------------------------------------------------
function get($url, $user_agent='', $content_type = '')
{	
	$data = null;

	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE,
	  
		CURLOPT_SSL_VERIFYHOST=> FALSE,
		CURLOPT_SSL_VERIFYPEER=> FALSE,
	  
	);

	if ($content_type != '')
	{
		$opts[CURLOPT_HTTPHEADER] = array(
			"Accept: " . $content_type, 
			"User-agent: Mozilla/5.0 (iPad; U; CPU OS 3_2_1 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Mobile/7B405" 
		);
	}
	
	$ch = curl_init();
	curl_setopt_array($ch, $opts);
	$data = curl_exec($ch);
	$info = curl_getinfo($ch); 
	curl_close($ch);
	
	return $data;
}

?>
