<?php
// Get the media we have stored in dynamoDB and load into a MySQL structure
// don't want to print debug through web server in general
$debug = true; 

// Load my configuration
$datastring = file_get_contents('/usr/www/html/BlueTrack/master_config.json');
$config = json_decode($datastring, true);
if ($debug) {echo "datastring: $datastring\n";}
if ($debug) {var_dump($config);}
//Use MY SQL - this include assumes that $config has been loaded 
//All the table creation is done in this include
include '/usr/www/html/BlueTrack/php/my_sql.php';
include '/usr/www/html/BlueTrack/php/functions.php';

// You'll need to edit this with your config
require '../vendor/autoload.php';
use Aws\Common\Aws;

// Loop through the dynamo tables and load the data into MySQL
$aws = Aws::factory('/usr/www/html/BlueTrack/php/amazon_config.json');
$client = $aws->get('DynamoDb');

// Setup to run through a table 100 pages at a time
$request = array("TableName" => "collector_data","Limit" => 100);

do {
    // Add the ExclusiveStartKey if we got one back in the previous response
    if(isset($response) && isset($response['LastEvaluatedKey'])) {
        $request['ExclusiveStartKey'] = $response['LastEvaluatedKey'];
    }
    $response = $client->scan($request);
    
    foreach ($response['Items'] as $key => $value) {
        $mac = $value['mac_id']["S"];
        $collector_id = $value['collector_id']["S"];
        $name = isset($value['name']["SS"]) ? $value['name']["SS"][0] : 'n/a';
        $class = isset($value['class']["SS"]) ? $value['class']["SS"][0] : 'n/a';
        $type = isset($value['type']["S"]) ? $value['type']["S"] : 'X';
        $seen = array_map("lengthen_time", isset($value['seen_on']["NS"]) ? $value['seen_on']["NS"] : array());

        $sql = 'REPLACE INTO devices (mac_id, mac_root, class, name, type)';
        $sql .= ' VALUES (' . sqlq($mac,0) . ',' .
                sqlq(base_mac($mac),0) . ',' .
                sqlq($class,0) . ',' .
                sqlq($name,0) . ',' .
                sqlq($type,0) . '); ';
    	if ($debug) {echo "Running: $sql\n";}
    	if (!$mysqli->query($sql)) {die("Insert Failed: (" . $mysqli->errno . ") " . $mysqli->error);}
	$sql = '';
	
        foreach ($seen as $i => $v) {    
            $sql .= 'REPLACE INTO device_scans (mac_id, collector_id, seen)';
            $sql .= ' VALUES (' . sqlq($mac,0) . ',' .
                    sqlq($collector_id,0) . ',' .
                    sqlq($v,1) . '); ' . "\n" ;
	    	if ($debug) {echo "Running: $sql\n";}
	    	if (!$mysqli->query($sql)) {die("Insert Failed: (" . $mysqli->errno . ") " . $mysqli->error);}
		$sql = '';
        }
        
        if ($class != 'n/a') {
            $hex = str_replace("0x", "", $class);
            $class_det = get_bt_class_info($hex, $mdcs, $mdc, $msc, $min_sc);
    
            $sql .= 'REPLACE INTO class_description  (class, short_major_type, major_type, service_class, device_type)';
            $sql .= ' VALUES (' . sqlq($class,0) . ',' .
                    sqlq($mdcs,0) . ',' .
                    sqlq($mdc,0) . ',' .
                    sqlq(json_encode($msc),0) . ',' .
                    sqlq(json_encode($min_sc),0) . '); ' . "\n";
                    
                if ($debug) {echo "Running: $sql\n";}
	    	if (!$mysqli->query($sql)) {die("Insert Failed: (" . $mysqli->errno . ") " . $mysqli->error);}
		$sql = '';

        }
    }
} while(isset($response['LastEvaluatedKey'])); 

// Setup to run through a table 100 pages at a time
$request = array("TableName" => "mac_data","Limit" => 100);
do {
    // Add the ExclusiveStartKey if we got one back in the previous response
    if(isset($response) && isset($response['LastEvaluatedKey'])) {
        $request['ExclusiveStartKey'] = $response['LastEvaluatedKey'];
    }
    $response = $client->scan($request);
    $sql = '';
    
    foreach ($response['Items'] as $key => $value) {
        $company_name = $value['company_name']["S"];
        $address = isset($value['address']["S"]) ? $value['address']["S"] : 'n/a';
        $city = isset($value['city']["S"]) ? $value['city']["S"] : 'n/a';
        $country = isset($value['country']["S"]) ? $value['country']["S"] : 'n/a';
        $state = isset($value['state']["S"]) ? $value['state']["S"] : 'n/a';
        $zip = isset($value['zip']["S"]) ? $value['zip']["S"] : 'n/a';
        $macs = isset($value['macs']["SS"]) ? $value['macs']["SS"] : array();
        $manu_id = get_manu_id($company_name, $mysqli);

        if (count($manu_id) == 0) {
            $msql = 'INSERT INTO manufacturers (company_name, address, city, country, state, zip)';
            $msql .= ' VALUES (' . sqlq($company_name,0) . ',' .
                    sqlq($address,0) . ',' .
                    sqlq($city,0) . ',' .
                    sqlq($country,0) . ',' .
                    sqlq($state,0) . ',' .
                    sqlq($zip,0) . '); ';
        	if ($debug) {echo "Running: $sql\n";}
        	if (!$mysqli->query($msql)) {die("Insert Failed: (" . $mysqli->errno . ") " . $mysqli->error);}
        	
            // Get the ID we just created (yea there's probably a way to do this in 1 step...)
            $manu_id = get_manu_id($company_name, $mysqli);
        }
        
        // Store Macs this manu is assocaited with
        foreach ($macs as $i => $mac_root) {    
            $sql .= 'INSERT INTO mac_roots (manu_id, mac_root)' .
        	     ' VALUES (' . sqlq($manu_id,1) . ',' . sqlq($mac_root,0) . '); ';
                if ($debug) {echo "Running: $sql\n";}
	    	if (!$mysqli->query($sql)) {die("Insert Failed: (" . $mysqli->errno . ") " . $mysqli->error);}
		$sql = '';
        }
    }
} while(isset($response['LastEvaluatedKey'])); 

echo 'Done!';

function get_manu_id($company_name, &$mysqli) {
    // See if we've created this company before
    $sql = 'SELECT manu_id FROM manufacturers WHERE company_name=' . sqlq($company_name,0) . ';';
	$manu_id = query_to_array($sql, $mysqli);
	if (count($manu_id) == 0) {
	    return 0;
	} else {
	    return $manu_id[0];
	}
}
?>
