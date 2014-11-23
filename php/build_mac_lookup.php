<?php
// Script to dump data from dynamodb into a json file so php can quickly lookup
// details about a particular mac address

$debug = false;
$datastring = file_get_contents('/usr/www/html/BlueTrack/master_config.json');
$config = json_decode($datastring, true);
require '/usr/www/html/BlueTrack/vendor/autoload.php';
$file = '/usr/www/html/BlueTrack/data/mac_data.json';
use Aws\Common\Aws;
$aws = Aws::factory('/usr/www/html/BlueTrack/php/amazon_config.json');
$client = $aws->get('DynamoDb');
// Make sure the dynamo tables exists assumes $client is defined
include 'dynamo_tables.php';

// Setup the scan with filters
$request = array(
    "TableName" => 'mac_data',
    "Limit" => 100
);

do {
    // Add the ExclusiveStartKey if we got one back in the previous response
    if(isset($response) && isset($response['LastEvaluatedKey'])) {
        $request['ExclusiveStartKey'] = $response['LastEvaluatedKey'];
    }
    $response = $client->scan($request);
    
    foreach ($response['Items'] as $key => $value) {
    	foreach ($value['macs']["SS"] as $i => $mac) {
    		unset($d);
    		$mac = trim($mac);
    		$d['company_name'] = $value['company_name']["S"];
    		$d['country'] = $value['country']["S"];
    		$d['address'] = $value['address']["S"];
    		$d['city'] = $value['city']["S"];
    		$d['state'] = $value['state']["S"];
    		$d['zip'] = $value['zip']["S"];
    		
    		// Save when we see one with a real name
    		if (isset($dt[$mac])) {
    		    if ($dt[$mac]['company_name'] == 'n/a') {
    		        echo "Saving new mac  ($mac) for " . $d['company_name'] . "\n";
    		        $dt[$mac] = $d;
    		    }
    		} else {
        		$dt[$mac] = $d;
    		}
    	}
    	
    }
} while(isset($response['LastEvaluatedKey'])); 
file_put_contents($file, json_encode($dt));



?>
