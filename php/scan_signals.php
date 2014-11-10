<?php
// A script that uses hcitool to scan bluetooth signals
// and load results into a dynamoDB

// Load my configuration
$debug = false;
$datastring = file_get_contents('/home/pi/BlueTrack/master_config.json');
if ($debug) {echo "datastring = $datastring \n";}
$config = json_decode($datastring, true);
if ($debug) {var_dump($config);}
$file = $config['log_folder'] . "my_macs.txt";
if ($debug) {echo "Opening: $file\n";}
$f = file_get_contents($file);
$my_macs = json_decode($f, true);
$lp_cnt = 0;
$region_name = isset($config['region'] ) ? $config['region']  : 'Default';
$collector_id = gethostmacaddr();
$collector_private_ip = gethostbyname(trim(`hostname --all-ip-addresses`));
$collector_public_ip = isset($config['public_ip'] ) ? $config['public_ip']  : '9.9.9.9';
$collector_storage = 0;
$time = time();

// TODO: move known devices to the config file
$known_dev = array("D8:A2:5E:88:3C:68", "70:F1:A1:67:5B:10");
require '/home/pi/BlueTrack/vendor/autoload.php';
use Aws\Common\Aws;

// This might be running disconnected fom the internet 
$online = true;
try {
	// You'll need to edit this with your config file
	// make sure you specify the correct region as dynamo is region specific
	$aws = Aws::factory('/home/pi/BlueTrack/php/amazon_config.json');
	$client = $aws->get('DynamoDb');
	
	// Make sure the dynamo tables exists assumes $client is defined
	include 'dynamo_tables.php';
	
	// ok we've got tables, see what we were sent
	if ($debug) {echo "Current Tables Exist<br>\n";}
	$created_region = false;
	
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
	            'region_collector_list'   => array($collector_id)
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
	                'region_name'   => array('S' => $region_name)
	            ),
	            'AttributeUpdates' => array(
	                'region_collector_list'   => array('Action' => 'ADD', 'Value' => array('SS' => array($collector_id)))
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
	            'collector_id'   => array('S' => $collector_id),
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
} catch (Exception $e) {
	echo 'Caught exception: ',  $e->getMessage(), "\n";
	$online = false;
}	
	

// Now start running hci tool in a loop and loading data it sees to Dynamo

while (1 == 1) {
	//First run a scan and get names of BT devices
	exec("hcitool scan", $out);
        if ($debug) {var_dump($out);}
	foreach ($out as $i => $v) {
		if ($i > 0) {
			$d = explode("\t", $v);
			// don't bother saving well known devices
			if (!(in_array($d[1], $known_dev))) {
				$my_macs[$d[1]]['status'] = 'dirty';
				if (! isset($d[2])) {$d[2] = 'n/a';}
				$my_macs[$d[1]]['name'] = str_replace("\u2019", "'", $d[2]);
				if (isset($my_macs[$d[1]]['scan_count'])) {$my_macs[$d[1]]['scan_count']++;} 
				else {$my_macs[$d[1]]['scan_count'] = 1;}
				$my_macs[$d[1]]['scan_on'][time()] = 'y';
			} 
		}
	}
	$out = '';

	// Then run an inquire and get clock and class of BT devices
	exec("hcitool inq --flush --length=3", $out);
        if ($debug) {var_dump($out);}
	foreach ($out as $i => $v) {
		if ($i > 0) {
			$d = explode("\t", $v);
			// don't bother saving well known devices
			if (!(in_array($d[1], $known_dev))) {
				$my_macs[$d[1]]['status'] = 'dirty';
				$my_macs[$d[1]]['clock offset'] = str_replace("clock offset: ", "", $d[2]);
				$my_macs[$d[1]]['class'] = str_replace("class: ", "", $d[3]);
				if (isset($my_macs[$d[1]]['inq_count'])) {$my_macs[$d[1]]['inq_count']++;} 
				else {$my_macs[$d[1]]['inq_count'] = 1;}
				$my_macs[$d[1]]['inq_on'][time()] = 'y';
			} 
		}
	}
	$out = '';
	
	// Still buggy, but in the future might want to run a low energy device scan too via:
	// sudo hcitool lescan & sleep 4;sudo kill $!

	// Write data out after each run in case we re-boot
	file_put_contents($file, json_encode($my_macs));
	
	// every 800th run connect to EC2 and save our data
	if ($lp_cnt % 800 == 0 && $lp_cnt > 0 && $online) {
        	if ($debug) {echo "$lp_cnt loops - dumping data to Dynamo\n";}
		foreach ($my_macs as $mac => $farray) {
			// See if data has changed since we saved it last
			$fstatus = isset($farray['status']) ? $farray['status'] : 'dirty';
			if ($fstatus == 'dirty') {
				if ($debug) {echo "farray ------\n"; var_dump($farray);}
				$name = array(!empty($farray['name']) ? $farray['name'] : 'n/a');
				$clock_offset = array(!empty($farray['clock offset']) ? $farray['clock offset'] : 'n/a');
				$class = array(!empty($farray['class']) ? $farray['class'] : 'n/a');
				$inq_on = array(1);
				$scan_on = array(1);
				if (isset($farray['inq_on'])) {if (is_array($farray['inq_on'])) {$inq_on = array_keys($farray['inq_on']);}}
				if (isset($farray['scan_on'])) {if (is_array($farray['scan_on'])) {$scan_on = array_keys($farray['scan_on']);}}
				if ($debug) {echo "mac = $mac \n";}
				if ($debug) {echo "name \n"; var_dump($name);}
				if ($debug) {echo "clock_offset \n"; var_dump($clock_offset);}
				if ($debug) {echo "class \n"; var_dump($class);}
				if ($debug) {var_dump($inq_on);}
				if ($debug) {var_dump($scan_on);}
				$ec2_save = true;
				try {
					$to_update = array(
						'TableName' => 'collector_data',
						'Key' => array(
							'mac_id'      => array("S" => $mac),
							'collector_id'      => array("S" => $collector_id)
						),
						"AttributeUpdates" => array(
							"name" => array(
								"Value" => array("SS" => $name),
								"Action" => "ADD"
							),
							"clock_offset" => array(
								"Value" => array("SS" => $clock_offset),
								"Action" => "ADD"
							),
							"class" => array(
								"Value" => array("SS" => $class),
								"Action" => "ADD"
							),
							"inq_on" => array(
								"Value" => array("NS" => $inq_on),
								"Action" => "ADD"
							),
							"scan_on" => array(
								"Value" => array("NS" => $scan_on),
								"Action" => "ADD"
							)
						),
						'ReturnValues' => "NONE"
					);
					$result = $client->updateItem($to_update);
				} catch (Exception $e) {
					echo 'Caught exception: ',  $e->getMessage(), "\n";
					var_dump($to_update);
					$ec2_save = false;
				}	
				
				if ($ec2_save) {
					//Now that we've stored these values reset the counters so that we don't store again
					//In the future might want to just unset($my_macs) since that way we'll 
					//never run out of space or slow the process over time.  
					$my_macs[$mac]['status'] = 'clean';
					$my_macs[$mac]['inq_count'] = 0;
					$my_macs[$mac]['scan_count'] = 0;
					unset($my_macs[$mac]['inq_on']);
					unset($my_macs[$mac]['scan_on']);
				}

			}
		}
		
		// update that this collector has called in
		if ($online) {
			try {
				$result = $client->updateItem(array(
				        'TableName' => 'collectors',
				        'Key'       => array(
				            'collector_id'   => array('S' => $collector_id),
				            'collector_region_name'   => array('S' => $region_name)
				        ),
				        'AttributeUpdates' => array(
				            'collector_last_checkin'    =>  array('Action' => 'PUT', 'Value' => array('N' => time())),
				            'collector_checkin_count'    =>  array('Action' => 'ADD', 'Value' => array('N' => 1))
				        )
				));    
				if ($debug) {echo "$collector_id in $region_name updated<br>\n";}
			} catch (Exception $e) {
				echo 'Caught exception: ',  $e->getMessage(), "\n";
			}
		}
	}

	$lp_cnt++;
}

// Function to get the mac address of the onboard ethernet card (which should not change)
function gethostmacaddr() {
	$mm = "unknown";
	exec("ifconfig", $out);
	foreach ($out as $i => $v) {
		if (substr($v, 0, 4) == 'eth0') {
			$mm = substr($v, strpos($v, 'HWaddr') + 7, 17);
		}
	}
	return $mm;
}



?>
