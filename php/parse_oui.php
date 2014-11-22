<?php
// First run wget http://www.ieee.org/netstorage/standards/oui.txt to get the latest Bluetooth 
// mac address license file
$debug = false;
$datastring = file_get_contents('/usr/www/html/BlueTrack/master_config.json');
$config = json_decode($datastring, true);
require '/usr/www/html/BlueTrack/vendor/autoload.php';
use Aws\Common\Aws;
$aws = Aws::factory('/usr/www/html/BlueTrack/php/amazon_config.json');
$client = $aws->get('DynamoDb');
// Make sure the dynamo tables exists assumes $client is defined
include 'dynamo_tables.php';
	
$f = file("/usr/www/html/BlueTrack/data/oui.txt");
$pattern = '/^\s{0,2}([a-zA-Z0-9\-]{8})\s+.*$/';
$statesp = '/^([A-Z ]+)\s+([A-Z]{2})\s+([0-9-]{2,10})$/';
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
        if (preg_match($statesp, $st, $matches) == 1) {
          $all[$mac]['city'] = $matches[1];
          $all[$mac]['state'] = $matches[2];
          $all[$mac]['zip'] = $matches[3];
          $addr_rows = $addr_rows - 1;
        }
      }
      for ($i = 0; $i < $addr_rows ; $i++) {$all[$mac]['address'][] = $data['address'][$i];}
      save_mac_data($mac, $all[$mac]);
      echo "Saving: $mac - $all[$mac]['company'] \n";
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

function save_mac_data($mac, $sd) {
  $sd['company'] = isset($sd['company']) ? $sd['company'] : '';
  $sd['country'] = isset($sd['country']) ? $sd['country'] : '';
  $sd['address'] = isset($sd['address']) ? implode("\n", $sd['address']) : '';
  $sd['city'] = isset($sd['city']) ? $sd['city'] : '';
  $sd['state'] = isset($sd['state']) ? $sd['state'] : '';
  $sd['zip'] = isset($sd['zip']) ? $sd['zip'] : '';
  
	$to_update = array(
		'TableName' => 'mac_data',
		'Key' => array(
			'company_name'      => array("S" => $sd['company'])
		),
		"AttributeUpdates" => array(
			"macs" => array(
				"Value" => array("SS" => $mac),
				"Action" => "ADD"
			),
			"country" => array(
				"Value" => array("S" => $sd['country']),
				"Action" => "ADD"
			),
			"address" => array(
				"Value" => array("S" => $sd['address']),
				"Action" => "ADD"
			),
			"city" => array(
				"Value" => array("S" => $sd['city']),
				"Action" => "ADD"
			),
			"state" => array(
				"Value" => array("S" => $sd['state']),
				"Action" => "ADD"
			),
			"zip" => array(
				"Value" => array("S" => $sd['zip']),
				"Action" => "ADD"
			)
		),
		'ReturnValues' => "NONE"
	);
	$result = $client->updateItem($to_update);
  
}


?>
