<?php
// Get the media we have stored on S3 and load it into a dynamoDB
// don't want to print debug through web server in general
$debug = false; 
if (!isset($_SERVER['HTTP_HOST'])) {
    $debug = true; 
} else {
    if (isset($_REQUEST['debug'])) {$debug = true;}
}

// Load my configuration
$datastring = file_get_contents('/usr/www/html/BlueTrack/master_config.json');
$config = json_decode($datastring, true);
if ($debug) {echo "datastring: $datastring\n";}
if ($debug) {var_dump($config);}
//Use MY SQL - this include assumes that $config has been loaded 
//All the table creation is done in this include
include '/usr/www/html/BlueTrack/php/my_sql.php';

// You'll need to edit this with your config
require '../vendor/autoload.php';
use Aws\Common\Aws;

// Loop through the dynamo tables and load the data into MySQL
$aws = Aws::factory('/usr/www/html/BlueTrack/php/amazon_config.json');
$client = $aws->get('DynamoDb');
$tableName = "collector_data";

// Setup to run through a table 100 pages at a time
$request = array(
    "TableName" => $tableName,
    //"ConditionalOperator" => 'OR',
    "Limit" => 100
);

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

        foreach ($seen as $i => $v) {    
            $sql .= 'REPLACE INTO device_scans (mac_id, collector_id, seen)';
            $sql .= ' VALUES (' . sqlq($mac,0) . ',' .
            $sql .= ' VALUES (' . sqlq($collector_id,0) . ',' .
            $sql .= ' VALUES (' . sqlq($v,1) . '); ';
        }
        
        if ($class != 'n/a') {
            $hex = str_replace("0x", "", $class);
            $class_det = get_bt_class_info($hex, $mdcs, $mdc, $msc, $min_sc);
    
            $sql .= 'REPLACE INTO varchar (class, short_major_type, major_type, service_class, device_type)';
            $sql .= ' VALUES (' . sqlq($class,0) . ',' .
            $sql .= ' VALUES (' . sqlq($mdcs,0) . ',' .
            $sql .= ' VALUES (' . sqlq($mdc,0) . ',' .
            $sql .= ' VALUES (' . sqlq(json_encode($msc),0) . ',' .
            $sql .= ' VALUES (' . sqlq(json_encode($min_sc),0) . '); ';
        }

    }
} while(isset($response['LastEvaluatedKey'])); 

// This comes from mac_data table
        //$full_data[$mac][$collector_id]['mac_info'] = isset($value['mac_info']["S"]) ? $value['mac_info']["S"] : 'n/a';


?>
