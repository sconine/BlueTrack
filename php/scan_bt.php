<?php

// Load date from previous runs here
$file = "/home/pi/BlueTrack/my_macs.txt";
$f = file_get_contents($file);
$my_macs = json_decode($f, true);
$lp_cnt = 0;
$known_dev = array("D8:A2:5E:88:3C:68", "70:F1:A1:67:5B:10");


// Clean these out
//$my_macs[$conine]['scan_on'] = '';
//$my_macs[$conine]['inq_count'] = '1';
//$my_macs[$conine]['inq_on'] = '';


while (1 == 1) {
	//echo "Loop count $lp_cnt \n";
	//First run a scan and get names of BT devices
	exec("hcitool scan", $out);
	//var_dump($out);
	foreach ($out as $i => $v) {
		if ($i > 0) {
			$d = explode("\t", $v);
			// don't bother saving well known devices
			if (!(in_array($d[1], $known_dev))) {
				$my_macs[$d[1]]['name'] = str_replace("\u2019", "'", $d[2]);
				$my_macs[$d[1]]['scan_count']++;
				$my_macs[$d[1]]['scan_on'][time()] = 'y';
			} 
		}
	}
	$out = '';

	// Then run an inquire and get clock and class of BT devices
	exec("hcitool inq --flush --length=3", $out);
	//var_dump($out);
	foreach ($out as $i => $v) {
		if ($i > 0) {
			$d = explode("\t", $v);
			// don't bother saving well known devices
			if (!(in_array($d[1], $known_dev))) {
				$my_macs[$d[1]]['clock offset'] = str_replace("clock offset: ", "", $d[2]);
				$my_macs[$d[1]]['class'] = str_replace("class: ", "", $d[3]);
				$my_macs[$d[1]]['inq_count']++;
				$my_macs[$d[1]]['inq_on'][time()] = 'y';
			} 
		}
	}
	$out = '';

	// Write data out after each run in case we re-boot
	file_put_contents($file, json_encode($my_macs));
	//var_dump($my_macs);
	$lp_cnt++;
}





?>
