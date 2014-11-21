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
$mac = '';
$all = array();

foreach ($f as $i => $line) {
  if (preg_match($pattern, $line) != 0) {
    // Process the last one we just found
    if ($row != 0) {
      var_dump($data);
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
      var_dump($all);
      exit;
    }
    
    $mac = preg_replace($pattern, $replacement, $line);
    echo "found $mac\n";
    unset($data);
    $data = array();
    $row = 0;
  } elseif ($mac != '') {
    if (trim($line) != '') {
      $thisline = strtoupper(preg_replace('/^\s+([a-zA-Z0-9]{1}.*)$/', '$1', $line));
    echo "thisline $thisline\n";
    echo "line $line\n";
    echo "row $row\n";

      if ($row == 0) {$data['company'] = strtoupper(substr($line, 24));}
      else {$data['address'][] = $thisline;}
      $row++;
    }
  }
}




?>
