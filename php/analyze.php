<?php


$file = "/home/pi/BlueTrack/my_macs.txt";
$f = file_get_contents($file);
$my_macs = json_decode($f, true);
$total = 0;


foreach ($my_macs as $addr => $v) {
	$total++;
	unset($t);
	echo "$addr \tname: \e[0;35m" . str_pad(@$v['name'],32," ") . " \e[0mclock offset: " . str_pad(@$v['clock offset'],10," ") . " class: " . @$v['class'] . "\n";
	echo "\t\t\tscanned: " . str_pad(@$v['scan_count'],29," ") . " inquired: " . @$v['inq_count'] . "\n";
	if (strpos(strtolower(@$v['name']), 'conine') === false) {
		if (isset($v['scan_on'])) {
			foreach ($v['scan_on'] as $tm => $v) {
				@@$t[date("Y-m-d h:i a", $tm - 14400)]++;
			}
		}

		if (isset($v['inq_on'])) {
			foreach ($v['inq_on'] as $tm => $v) {
				@@$t[date("Y-m-d h:i a", $tm - 14400)]++;
			}
		}
		echo "\t\t\tseen on:\n";
		if (isset($t)) {
			foreach ($t as $time => $cnt) {
				echo "\t\t\t\t$time\n";
			}
		}
	}
	echo "\n";
}

echo "Total Unique Devices: \e[1;33m $total \e[0m \n";





?>
