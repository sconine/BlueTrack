<?php
// A script that uses hcitool to scan bluetooth signals
// and load results into a dynamoDB

// Load my configuration
$datastring = file_get_contents('/home/pi/BlueTrack/master_config.json');
$config = json_decode($datastring, true);
$debug = true;
$file = $config['log_folder'] . "my_macs.txt";
$f = file_get_contents($file);
$my_macs = json_decode($f, true);
$lp_cnt = 0;

// TODO: move known devices to the config file
$known_dev = array("D8:A2:5E:88:3C:68", "70:F1:A1:67:5B:10");

require '../vendor/autoload.php';

// don't want to print debug through web server in general
$debug = false; 
if (!isset($_SERVER['HTTP_HOST'])) {
    $debug = true; 
} else {
    if (isset($_REQUEST['debug'])) {$debug = true;}
}

use Aws\Common\Aws;

// You'll need to edit this with your config file
// make sure you specify the correct region as dynamo is region specific
$aws = Aws::factory('/home/pi/BlueTrack/php/amazon_config.json');
$client = $aws->get('DynamoDb');

// Make sure the dynamo tables exists assumes $client is defined
include 'dynamo_tables.php';

// ok we've got tables, see what we were sent
if ($debug) {echo "Current Tables Exist<br>\n";}
$created_region = false;
$region_name = isset($config['region'] ) ? $config['region']  : '';
$collector_id = isset($config['$collector_id'] ) ? $config['$collector_id']  : '';
$collector_private_ip = gethostbyname(trim(`hostname --all-ip-addresses`));
$collector_public_ip = isset($config['public_ip'] ) ? $config['public_ip']  : '';
$collector_storage = 0;
$time = time();

// have we seen this region
if ($debug) {echo "Looking up region: $region_name<br>\n";}
$result = $client->getItem(array(
    'ConsistentRead' => true,
    'TableName' => 'collector_regions',
    'Key'       => array(
        'region_name'   => array('S' => $region_name)
    )
));
if ($debug) {var_dump($result); echo '<br>';}

if (!isset($result['Item']['region_name']['S'])) {
    // Add this region
    if ($debug) {echo "$region_name not found, adding region now<br>\n";}
    $result = $client->putItem(array(
        'TableName' => 'collector_regions',
        'Item' => $client->formatAttributes(array(
            'region_name'      => $region_name,
            'region_active'    => true,
            'region_collector_list'   => array($screen_id)
        )),
        'ReturnConsumedCapacity' => 'TOTAL'
    ));
    $created_region = true;
    if ($debug) {echo "$region_name added<br>\n";}
} else {
    if ($debug) {echo "$region_name found!<br>\n";}
}

// have we seen this collector
if ($debug) {echo "Looking up collector: $collector_id in $region_name<br>\n";}
$result = $client->getItem(array(
    'ConsistentRead' => true,
    'TableName' => 'collectors',
    'Key'       => array(
        'collector_id'   => array('S' => $collector_id),
        'collector_region_name'   => array('S' => $region_name)
    )
));
if ($debug) {var_dump($result); echo '<br>';}

if (!isset($result['Item']['collector_id']['S'])) {
    // Add this collector
    if ($debug) {echo "$collector_id in $region_name not found, adding collector now<br>\n";}
    $result = $client->putItem(array(
        'TableName' => 'collectors',
        'Item' => $client->formatAttributes(array(
            'collector_id'      => $collector_id,
            'collector_region_name'    => $region_name,
            'collector_private_ip'    => $collector_private_ip,
            'collector_public_ip'    => $collector_public_ip,
            'collector_last_checkin'    => $time,
            'collector_active'    => 1,
            'collector_storage' => $collector_storage
        )),
        'ReturnConsumedCapacity' => 'TOTAL'
    ));
     if ($debug) {echo "$collector_id in $region_name added<br>\n";}
   
    // Make sure to push this collector onto the region collector list if we didn't just create the region
    if (!$created_region) {
        if ($debug) {echo "$collector_id in $region_name adding to region list<br>\n";}
        $result = $client->updateItem(array(
            'TableName' => 'collector_regions',
            'Key'       => array(
                'collector_name'   => array('S' => $collector_name)
            ),
            'AttributeUpdates' => array(
                'region_collector_list'   => array('Action' => 'ADD', 'Value' => array('SS' => array($screen_id)))
            )
        ));
        if ($debug) {echo "$collector_id in $region_name pushed onto region list<br>\n";}
    }

} else {
    // Update the collector_last_checkin and IP values for this collector
    if ($debug) {echo "$collector_id in $region_name found!<br>\n";}
    $result = $client->updateItem(array(
        'TableName' => 'collectors',
        'Key'       => array(
            'collector_id'   => array('S' => $screen_id),
            'collector_region_name'   => array('S' => $region_name)
        ),
        'AttributeUpdates' => array(
            'collector_private_ip'    =>  array('Action' => 'PUT', 'Value' => array('S' => $collector_private_ip)),
            'collector_public_ip'    =>  array('Action' => 'PUT', 'Value' => array('S' => $collector_public_ip)),
            'collector_last_checkin'    =>  array('Action' => 'PUT', 'Value' => array('N' => $time)),
            'collector_storage'    =>  array('Action' => 'PUT', 'Value' => array('N' => $collector_storage))
        )
    ));    
    if ($debug) {echo "$collector_id in $region_name updated<br>\n";}

}


// Now start running hci tool in a loop and loading data it sees to Dynamo

while (1 == 1) {
	//First run a scan and get names of BT devices
	exec("hcitool scan", $out);
	//var_dump($out);
	foreach ($out as $i => $v) {
		if ($i > 0) {
			$d = explode("\t", $v);
			// don't bother saving well known devices
			if (!(in_array($d[1], $known_dev))) {
				$my_macs[$d[1]]['status'] = 'dirty';
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
				$my_macs[$d[1]]['status'] = 'dirty';
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
    
    // every 60th run connect to EC2 and save our data
    if ($lp_cnt % 60 = 0) {
        foreach ($my_macs as $mac => $farray) {
            // See if data has changed since we saved it last
            if ($farray['status'] = 'dirty') {
				$my_macs[$d[1]]['name'] = str_replace("\u2019", "'", $d[2]);
				$my_macs[$d[1]]['scan_count']++;
				$my_macs[$d[1]]['scan_on'][time()] = 'y';
				$my_macs[$d[1]]['clock offset'] = str_replace("clock offset: ", "", $d[2]);
				$my_macs[$d[1]]['class'] = str_replace("class: ", "", $d[3]);
				$my_macs[$d[1]]['inq_count']++;
				$my_macs[$d[1]]['inq_on'][time()] = 'y';
                
                $result = $client->putItem(array(
                  'TableName' => 'collector_data',
                  'Item' => $client->formatAttributes(array(
                      'collector_id'      => $collector_id,
                      'time'    => time(),
                      'data'    => $out
                  )),
                  'ReturnConsumedCapacity' => 'TOTAL'
                ));
                
            }
        }
        
    }
   
	$lp_cnt++;
}






?>
