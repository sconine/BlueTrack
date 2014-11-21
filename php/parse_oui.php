<?php
// First run wget http://www.ieee.org/netstorage/standards/oui.txt to get the latest Bluetooth 
// mac address license file

$f = file("/usr/www/html/BlueTrack/data/oui.txt");
$pattern = '/^\s*([a-zA-Z0-9\-]{8})\s+.*$/';
$statesp = '/^([A-Z ]+)\s+([A-Z]{2})\s+([0-9-]{5,10})$/';
$replacement = '$1';
$data = array();
$thisline = '';
$row = 0;
$companies = 0;
$mac = '';
$all = array();

foreach ($f as $i => $line) {
  if (preg_match($pattern, $line) != 0) {
    // Process the last one we just found
    if ($row != 0) {
      $addr_rows = count($data['address']) - 1;
      $all[$mac]['company'] = $data['company'];
      $all[$mac]['country'] = $data['address'][$addr_rows];
      if ($all[$mac]['country'] == 'UNITED STATES') {
        $st = $data['address'][$addr_rows - 1];
        if (preg_match($pattern, $st, $matches) != 0) {
          $all[$mac]['city'] = $matches[1];
          $all[$mac]['state'] = $matches[2];
          $all[$mac]['zip'] = $matches[3];
          $addr_rows = $addr_rows - 1;
        }
      }
      for ($i = 0; $i < $addr_rows ; $i++) {$all[$mac]['address'][] = $data['address'][$i];}
      echo "############################## \n";
      var_dump($data);
      echo "############################## \n";
      var_dump($all[$mac]);  
      if ($companies > 10) {exit;}
      
      $companies++;
    }
    
    $mac = preg_replace($pattern, $replacement, $line);
    unset($data);
    $data = array();
    $row = 0;
  } elseif ($mac != '') {
    if (trim($line) != '') {
      $thisline = trim(strtoupper(preg_replace('/^\s+([a-zA-Z0-9]{1}.*)$/', '$1', $line)));
      if ($row == 0) {$data['company'] = trim(strtoupper(substr($line, 24)));}
      else {$data['address'][] = $thisline;}
      $row++;
    }
  }
}




?>
