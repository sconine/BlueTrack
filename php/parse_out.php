<?php
// First run wget http://www.ieee.org/netstorage/standards/oui.txt to get the latest Bluetooth 
// mac address license file

$f = file("/usr/www/html/BlueTrack/data/oui.txt")
$pattern = '/^\s*([a-zA-Z0-9\-]{8})\s+.*$/';
$replacement = '$1';

foreach ($f as $i => $line) {
  if (preg_match($pattern, $line) != 0) {
    $mac = preg_replace($pattern, $replacement, $string);
    }



}










?>
