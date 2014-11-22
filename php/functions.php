<?php

function base_mac($mac) {
	return strtoupper(substr(str_replace(':', '-', $mac), 0, 8));
}

function shorten_time($in_time) {
	// shortens time by 100 seconds
	// 1415421466 becomes 14154214
	if (strlen($in_time) > 8) {
		return substr($in_time, 0, -2);
	} else {
		return $in_time;
	}
}

function lengthen_time($in_time) {
	if (strlen($in_time) > 6) {
		return ($in_time * 100);
	} else {
		return $in_time;
	}
}

function remove_short_time($in_time) {
	if (strlen($in_time) < 4) {
		return false;
	} else {
		return true;
	}
}
function format_mac_info($mac_info) {
    $mac_info = str_replace("\n", " ", $mac_info);
    $mac_info = str_replace("'", "\'", $mac_info);
    return $mac_info;
}
  
function update_mac_info(&$client, $mac, $collector_id, $mac_info) {
	$result = $client->updateItem(array(
			'TableName' => 'collector_data',
			'Key' => array(
				'mac_id'      => array("S" => $mac),
				'collector_id'      => array("S" => $collector_id)
			),
			"AttributeUpdates" => array(
			"mac_info" => array(
				"Value" => array("S" => $mac_info),
				"Action" => "PUT"
			)
		),
		'ReturnValues' => "NONE"
	));
	return true;
}


function update_type(&$client, $mac, $collector_id, $type) {
	$result = $client->updateItem(array(
		'TableName' => 'collector_data',
		'Key' => array(
			'mac_id'      => array("S" => $mac),
			'collector_id'      => array("S" => $collector_id)
		),
		"AttributeUpdates" => array(
			"type" => array(
				"Value" => array("S" => $type),
				"Action" => "PUT"
			)
		),
		'ReturnValues' => "NONE"
	));
	return true;
}


function create_select($name, $arr, $def, $multi, $size) {
	$to_ret = '<select name="' . htmlentities($name) . '[]"' ;
	if ($multi) {$to_ret .= ' multiple size="' . $size . '"';}
	$to_ret .= ">\n";
	$to_ret .= '<option value="">Select One</option>' . "\n";
	
	if (!is_array($def)) {
		$t = $def;
		unset($def);
		$def = array();
		$def[] = $t;
	}
	foreach ($arr as $i => $v) {
		$to_ret .= '<option value="' . htmlentities($i) . '"';
		if (in_array($v, $def)) {$to_ret .= ' selected ';}
		$to_ret .= ">" . htmlentities($v) . "</option>\n";
	}
	$to_ret .= "</select>\n";
	return $to_ret;
}



function ischecked($v, $c) {
    if (isset($c)) {
        if (is_array($c)) {
            foreach ($c as $i => $val) {
                if ($v == $val) {return ' checked ';}
            }
        }
    } else {
        if ($v == $c) {return ' checked ';}
    }
    return '';
}

function checkit($v) {
    if ($v) {
        return ' checked ';
    }
    return '';
}

// these come from http://standards.ieee.org/develop/regauth/oui/public.html
function get_mac_info($mac) {
    $mac = str_replace(':', '-', $mac);
    $mac = substr($mac, 0, 8);
    echo "retreiving mac = $mac <br>\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,"http://standards.ieee.org/cgi-bin/ouisearch");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,
                "x=" . urlencode($mac) . "&submit2=" . urlencode('Search!'));
    // receive server response ...
    curl_setopt($ch, CURLOPT_FAILONERROR, true); 
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,3);
    curl_setopt($ch,CURLOPT_TIMEOUT, 20);
    $server_output = curl_exec ($ch);
    curl_close ($ch);

    // Parse the output
    $start = strpos(strtolower($server_output), '<pre>');
    $end = strpos(strtolower($server_output), '</pre>', $start);
    $data = $server_output;
    
    if ($start > 10 && $end > $start) {
                $data = substr($server_output, $start + 5, $end - $start - 6);
                $start = strpos(strtolower($data), '</b>');
                if ($start > 1 && $end > $start) {
                            $data = substr($data, $start + 4);
                }
                $data = str_replace('(hex)', '', $data);
                $data = str_replace('(base 16)', '', $data);
                $cc = str_replace('-', '', $mac);
                $data = str_replace($cc, '', $data);
                $data = preg_replace("/[ \t]{2,}/", "", $data);
                return $data;
    } 
    return 'n/a';
}

// Function to turn a bluetooth class code into an english description
// not sure this is perfectly accurate, and up to date but works for my purposes
function get_bt_class_info($hex, &$mdcs) {
	$mdc = ''; 
	$mdcs = ''; 
	$mds_c = 0;
	$msc = array();
	$min_sc = array();
	$b = base_convert($hex, 16, 2);
	$b = str_pad($b, 24, "0", STR_PAD_LEFT);
	$bin_cd = str_split($b);
	
	//Major Service Class
	if ($bin_cd[10]) {$msc[] = 'Limited Discoverable Mode';}
	if ($bin_cd[7]) {$msc[] = 'Positioning (location identification';}
	if ($bin_cd[6]) {$msc[] = 'Networking (LAN, Ad hoc etc)';}
	if ($bin_cd[5]) {$msc[] = 'Rendering (printing, speaker etc)';}
	if ($bin_cd[4]) {$msc[] = 'Capturing (scanner, microphone etc)';}
	if ($bin_cd[3]) {$msc[] = 'Object Transfer (v-inbox, v-folder etc)';}
	if ($bin_cd[2]) {$msc[] = 'Audio (speaker, microphone, headset service etc)';}
	if ($bin_cd[1]) {$msc[] = 'Telephony (cordless telephony, modem, headset service etc)';}
	if ($bin_cd[0]) {$msc[] = 'Information (WEB-server, WAP-server etc)';}
	
	//Major Device Class
	if ($bin_cd[11] && $bin_cd[12] && $bin_cd[13] && $bin_cd[14] && $bin_cd[15]) {$mdcs = 'Uncategorized';$mdc = 'Uncategorized, specific device code not specified'; $mds_c = 8;}
	if ($bin_cd[13] && $bin_cd[14] && $bin_cd[15]) {$mdcs = 'Wearable';$mdc = 'Wearable'; $mds_c = 1;}
	if ($bin_cd[14] && $bin_cd[15]) {$mdcs = 'LAN';$mdc = 'LAN/Network Access point'; $mds_c = 3;}
	if ($bin_cd[13] && $bin_cd[15]) {$mdcs = 'Peripheral';$mdc = 'Peripheral (mouse, joystick, keyboards etc)'; $mds_c = 5;}
	if ($bin_cd[13] && $bin_cd[14]) {$mdcs = 'Imaging';$mdc = 'Imaging (printing, scanner, camera, display etc)'; $mds_c = 6;}
	if ($bin_cd[15]) {$mdcs = 'Computer';$mdc = 'Computer (desktop,notebook, PDA, organizers etc)'; $mds_c = 1;}
	if ($bin_cd[14]) {$mdcs = 'Phone';$mdc = 'Phone (cellular, cordless, payphone, modem)'; $mds_c = 2;}
	if ($bin_cd[13]) {$mdcs = 'Audio';$mdc = 'Audio/Video (headset, speaker, stereo, video display etc)'; $mds_c = 4;}
	if ($bin_cd[12]) {$mdcs = 'Toy';$mdc = 'Toy'; $mds_c = 7;}

	//Wearable
	if ($mds_c == 1) {
		if ($bin_cd[21] && $bin_cd[19]) {$min_sc[] = 'Palm sized PC/PDA';}
		elseif ($bin_cd[20] && $bin_cd[19]) {$min_sc[] = 'Wearable computer (watch sized)';}
		elseif ($bin_cd[21] && $bin_cd[20]) {$min_sc[] = 'Laptop';}
		elseif ($bin_cd[21]) {$min_sc[] = 'Desktop workstation';}
		elseif ($bin_cd[20]) {$min_sc[] = 'Server-class computer';}
		elseif ($bin_cd[19]) {$min_sc[] = 'Handheld PC/PDA (clam shell)';}
	}
	//Phone
	if ($mds_c == 2) {
		if ($bin_cd[21] && $bin_cd[20]) {$min_sc[] = 'Smart phone';}
		elseif ($bin_cd[21] && $bin_cd[19]) {$min_sc[] = 'Common ISDN Access';}
		elseif ($bin_cd[21]) {$min_sc[] = 'Cellular';}
		elseif ($bin_cd[20]) {$min_sc[] = 'Cordless';}
		elseif ($bin_cd[19]) {$min_sc[] = 'Wired modem or voice gateway';}
	}
	//LAN/Network Access point
	if ($mds_c == 3) {
		if ($bin_cd[18] && $bin_cd[17] & $bin_cd[16]) {$min_sc[] = 'No service available';}
		elseif ($bin_cd[18] && $bin_cd[17]) {$min_sc[] = '33 - 50% utilized';}
		elseif ($bin_cd[18] && $bin_cd[16]) {$min_sc[] = '67 - 83% utilized';}
		elseif ($bin_cd[17] && $bin_cd[16]) {$min_sc[] = '83 - 99% utilized';}
		elseif ($bin_cd[16]) {$min_sc[] = '50 - 67% utilized';}
		elseif ($bin_cd[18]) {$min_sc[] = '1 - 17% utilized';}
		elseif ($bin_cd[17]) {$min_sc[] = '17 - 33% utilized';}
	}
	//Audio/Video
	if ($mds_c == 4) {
		if ($bin_cd[21] && $bin_cd[20] && $bin_cd[19] && $bin_cd[18]) {$min_sc[] = 'Video Display and Loudspeaker';}
		elseif ($bin_cd[21] && $bin_cd[20] && $bin_cd[19]) {$min_sc[] = 'Portable Audio';}
		elseif ($bin_cd[21] && $bin_cd[20] && $bin_cd[18]) {$min_sc[] = 'VCR';}
		elseif ($bin_cd[21] && $bin_cd[19] && $bin_cd[18]) {$min_sc[] = 'Camcorder';}
		elseif ($bin_cd[20] && $bin_cd[19] && $bin_cd[18]) {$min_sc[] = 'Video Monitor';}
		elseif ($bin_cd[19] && $bin_cd[18]) {$min_sc[] = 'Video Camera';}
		elseif ($bin_cd[20] && $bin_cd[17]) {$min_sc[] = 'Gaming/Toy';}
		elseif ($bin_cd[21] && $bin_cd[19]) {$min_sc[] = 'Loudspeaker';}
		elseif ($bin_cd[20] && $bin_cd[19]) {$min_sc[] = 'Headphones';}
		elseif ($bin_cd[21] && $bin_cd[18]) {$min_sc[] = 'Set-top box';}
		elseif ($bin_cd[20] && $bin_cd[18]) {$min_sc[] = 'HiFi Audio Device';}
		elseif ($bin_cd[21]) {$min_sc[] = 'Wearable Headset Device';}
		elseif ($bin_cd[20]) {$min_sc[] = 'Hands-free Device';}
		elseif ($bin_cd[19]) {$min_sc[] = 'Microphone';}
		elseif ($bin_cd[18]) {$min_sc[] = 'Car audio';}
		elseif ($bin_cd[17]) {$min_sc[] = 'Video Conferencing';}
	}
	//Peripheral
	if ($mds_c == 5) {
		if ($bin_cd[21] && $bin_cd[19]) {$min_sc[] = 'Digitizer tablet';}
		elseif ($bin_cd[20] && $bin_cd[19]) {$min_sc[] = 'Card Reader (e.g. SIM Card Reader)';}
		elseif ($bin_cd[17] && $bin_cd[16]) {$min_sc[] = 'Combo keyboard/pointing device';}
		elseif ($bin_cd[21] && $bin_cd[20]) {$min_sc[] = 'Remote control';}
		elseif ($bin_cd[17]) {$min_sc[] = 'Keyboard';}
		elseif ($bin_cd[16]) {$min_sc[] = 'Pointing device';}
		elseif ($bin_cd[21]) {$min_sc[] = 'Joystick';}
		elseif ($bin_cd[20]) {$min_sc[] = 'Gamepad';}
		elseif ($bin_cd[19]) {$min_sc[] = 'Sensing device';}
	}
	//Imaging
	if ($mds_c == 6) {
		if ($bin_cd[19]) {$min_sc[] = 'Display';}
		elseif ($bin_cd[18]) {$min_sc[] = 'Camera';}
		elseif ($bin_cd[17]) {$min_sc[] = 'Scanner';}
		elseif ($bin_cd[16]) {$min_sc[] = 'Printer';}
	}
	//Toy
	if ($mds_c == 7) {
		if ($bin_cd[21] && $bin_cd[20]) {$min_sc[] = 'Jacket';}
		elseif ($bin_cd[21] && $bin_cd[19]) {$min_sc[] = 'Glasses';}
		elseif ($bin_cd[21]) {$min_sc[] = 'Wrist Watch';}
		elseif ($bin_cd[20]) {$min_sc[] = 'Pager';}
		elseif ($bin_cd[19]) {$min_sc[] = 'Helmet';}
	}
	//Uncategorized
	if ($mds_c == 8) {
		if ($bin_cd[21] && $bin_cd[20]) {$min_sc[] = 'Doll / Action Figure';}
		elseif ($bin_cd[21] && $bin_cd[19]) {$min_sc[] = 'Game';}
		elseif ($bin_cd[21]) {$min_sc[] = 'Robot';}
		elseif ($bin_cd[20]) {$min_sc[] = 'Vehicle';}
		elseif ($bin_cd[19]) {$min_sc[] = 'Controller';}
	}


	$to_ret = 'Device Class: ' . $mdc . '<br>Detail: ' . implode('<br>', $min_sc) . '<br><br>Services:<br>' . implode('<br>', $msc);
	return 	$to_ret;
}





?>
