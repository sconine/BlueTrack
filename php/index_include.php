
<?php
// Feature ideas:
//  edit types easier and assign in a bale based on filtering on device major class and a substring of the manufacturer
//  cache dynamodb data in a local file and retreive on demand


// Load my configuration
$debug = false;
$datastring = file_get_contents('/usr/www/html/BlueTrack/master_config.json');
if ($debug) {echo "datastring = $datastring <br>\n";}
$config = json_decode($datastring, true);
if ($debug) {var_dump($config);}

require '../vendor/autoload.php';
use Aws\Common\Aws;

// You'll need to edit this with your config file
// make sure you specify the correct region as dynamo is region specific
$aws = Aws::factory('/usr/www/html/BlueTrack/php/amazon_config.json');
$client = $aws->get('DynamoDb');
$tableName = "collector_data";

// Setup filters
$type_f = array();
$multi_day_f = false;
$day_count_f = 0;
$col_id_f = array();
$man_info_f = '';
$name_f = '';
$start_day_f = '';
$end_day_f = '';
if(!empty($_REQUEST['day_count'])) {$day_count_f = $_REQUEST['day_count'];}
if(!empty($_REQUEST['multi_day'])) {$multi_day_f = true;}
if(!empty($_REQUEST['type'])) {$type_f = $_REQUEST['type'];}
if(!empty($_REQUEST['col_id'])) {$col_id_f = $_REQUEST['col_id'];}
if(!empty($_REQUEST['man_info'])) {$man_info_f = $_REQUEST['man_info'];}
if(!empty($_REQUEST['name'])) {$name_f = $_REQUEST['name'];}
if(!empty($_REQUEST['start_day'])) {$start_day_f = $_REQUEST['start_day'];}
if(!empty($_REQUEST['end_day'])) {$end_day_f = $_REQUEST['end_day'];}

// Make sure they look safe
$pattern = '/^[a-zA-ZvV0-9,]+$/';
if (preg_match($pattern, implode(",", $type_f)) == 0) {$type_f = array();}
if (!is_numeric($day_count_f)) {$day_count_f = 0;}

// Setup the scan and filters
$request = array(
    "TableName" => $tableName,
    //"ConditionalOperator" => 'OR',
    "Limit" => 500
);

$scan_filters = array();
if (count($type_f) > 0) {
    $avl[] = array('NULL' => true);
    foreach ($type_f as $i => $t) {$avl[] = array('S' => $t);}
    $scan_filters['type'] = array('AttributeValueList' => $avl,'ComparisonOperator' => 'IN');
    // If we want to filter for X that's the only one we can do for type as far as I've figured out
    //if (in_array('X', $type_f)) {
    //    $scan_filters['type'] = array('ComparisonOperator' => 'NULL');
    //}
}
/*
if ($col_id_f != '') {
    $scan_filters['collector_id'] = array('AttributeValueList' => array(array('SS' => $col_id_f)),'ComparisonOperator' => 'CONTAINS');
}
if ($man_info_f != '') {
    $scan_filters['mac_info'] = array('AttributeValueList' => array(array('S' => $man_info_f)),'ComparisonOperator' => 'CONTAINS');
}
if ($name_f != '') {
    $scan_filters['name'] = array('AttributeValueList' => array(array('S' => $name_f)),'ComparisonOperator' => 'CONTAINS');
}          
if ($start_day_f != '') {
    $start_day_f = strtotime($start_day_f);
    $scan_filters['seen'] = array('AttributeValueList' => array(array('N' => $start_day_f)),'ComparisonOperator' => 'GT');
}   
if ($end_day_f != '') {
    $end_day_f = strtotime($end_day_f);
    $scan_filters['seen'] = array('AttributeValueList' => array(array('N' => $end_day_f)),'ComparisonOperator' => 'LT');
}   
*/

if (count($scan_filters) > 0) {
    $request['ScanFilter'] = $scan_filters;
}

$count = 0;
$displayed_count = 0;
$total_seen = 0;
$t_first_seen = 0;
$t_first_disp = 0;
$t_last_seen = 0;
$t_last_disp = 0;
$day_names = array("Mon", "Tues", "Wed", "Thurs", "Fri", "Sat", "Sun");
$last_hour = array();
$type_list = array();
// Set some default ones
$type_list['M'] = 1; // Mobile Phone
$type_desc['M'] = 'Mobile Phone'; // Mobile Phone
$type_list['H'] = 1; // Human
$type_desc['H'] = 'Human'; // Human
$type_list['V'] = 1; // Vehicle
$type_desc['V'] = 'Vehicle'; // Vehicle
$type_list['A'] = 1; // Apple Device
$type_desc['A'] = 'Apple Device'; // Apple Device
$type_list['C'] = 1; // Computer
$type_desc['C'] = 'Computer'; // Computer
$type_list['G'] = 1; // GPS
$type_desc['G'] = 'GPS'; // GPS
$type_list['T'] = 1; // TV Device
$type_desc['T'] = 'TV Device'; // TV Device
$type_list['P'] = 1; // Toy (play)
$type_desc['P'] = 'Toy (play)'; // Toy (play)
$type_list['S'] = 1; // Music Device
$type_desc['S'] = 'Music Device'; // Music Device
$type_list['U'] = 1; // Unknown
$type_desc['U'] = 'Unknown'; // Unknown
$type_list['X'] = 1; // Not Set
$type_desc['X'] = 'Not Set'; // Not Set
$by_day = array();
$by_class = array();
$last_seen = array();
$first_seen = array();
$top = array();
$names = array();
$collectors = array();
$classes = array();
$dev_type = array();
$show_minutes = array();
$seen_hours = array();
$seen_days = array();
$gc = 0;
date_default_timezone_set('UTC');

// The Scan API is paginated. Issue the Scan request multiple times.
do {
    // Add the ExclusiveStartKey if we got one back in the previous response
    if(isset($response) && isset($response['LastEvaluatedKey'])) {
        $request['ExclusiveStartKey'] = $response['LastEvaluatedKey'];
    }
    $response = $client->scan($request);

    foreach ($response['Items'] as $key => $value) {
        $count++;
        $mac = $value['mac_id']["S"];
        $collector_id = $value['collector_id']["S"];
        $collectors[] = $collector_id;
        $name[$mac] = implode(',', $value['name']["SS"]);
        $dev_type[$mac] = isset($value['type']["S"]) ? $value['type']["S"] : 'X';
        $type_list[$dev_type[$mac]] = 1;
        if (!isset($type_desc[$dev_type[$mac]])) {$type_desc[$dev_type[$mac]] = 'Not Defined';}
        $last_seen[$mac] = 0;
        $first_seen[$mac] = 0;
        
        // Do we have mac registrant info if not get it and store it
        if (! isset($value['mac_info']["S"])) {
            $mac_info = get_mac_info($mac);
            $gc++;
            
            if ($mac_info != '') {
                $result = $client->updateItem(array(
                	'TableName' => 'collector_data',
                	'Key' => array(
                		'mac_id'      => array("S" => $mac),
                		'collector_id'      => array("S" => $collector_id)
                	),
                	"AttributeUpdates" => array(
                		"mac_info" => array(
                			"Value" => array("S" => $mac_info),
                			"Action" => "PUT"
                		)
                	),
                	'ReturnValues' => "NONE"
                ));
                
                $value['mac_info']["S"] = $mac_info;
            } else {
                $value['mac_info']["S"] ='uknown';
            }
        }
        $my_mac_info[$mac] = str_replace("\n", " ", $value['mac_info']["S"]);
        $my_mac_info[$mac] = str_replace("'", "\'", $my_mac_info[$mac]);

        // Keep track of counts by class
        $mdc = '';
        $t_class = '';
        foreach ($value['class']["SS"] as $cli => $cl) {
        	if ($cl != 'n/a') {
        		get_bt_class_info($cl, $mdc);
        		if ($t_class != '') {$t_class .= ',';}
        		$t_class .= $mdc;
        	}
        }
        if ($t_class == '') {$t_class = 'Not Sent';}
        if (isset($by_class[$t_class][$mac])) {$by_class[$t_class][$mac]++;}
        else {$by_class[$t_class][$mac] = 1;}
        
        // Just pick the first class listed of this device for the pie chart
        $classes[$mac] = $value['class']["SS"][0];
                
        // Manipulate the dates a bit
        $seen = array_merge($value['scan_on']["NS"], $value['inq_on']["NS"]);
        $seen_count = 0;
        foreach ($seen as $i => $v) {
            $seen_count++;
            if ($v > 1) {
                // Keep track of ones we've seen in last hour
                if ($v > (time() - 3600)) {
                    if (isset($last_hour[$mac])) {$last_hour[$mac]++;}
                    else {$last_hour[$mac] = 1;}
                }
                
                // put in EST
                $v = $v - (3600 * 5);
    
                $minute = strtotime(date("Y-m-d h:i a", $v));
                $hour = strtotime(date("1990-01-01 h:00 a", $v));
                $day = strtotime(date("Y-m-d", $v));
                $hourofday = date("H", $v);
                $dayofyear = date("z", $v);
                $dayofweek = date("N", $v);
                $dayofweek3 = date("N", $v);
    
                // Keep track of counts by day
                if (isset($by_day[$dayofweek][$mac])) {$by_day[$dayofweek][$mac]++;}
                else {$by_day[$dayofweek][$mac] = 1;}
            
                // Build data for bubble chart
                if (isset($seen_dayofw[$mac][$dayofweek3])) {$seen_dayofw[$mac][$dayofweek3]++;}
                else {$seen_dayofw[$mac][$dayofweek3] = 1;}
                if (isset($seen_hours[$mac][$hourofday])) {$seen_hours[$mac][$hourofday]++;}
                else {$seen_hours[$mac][$hourofday] = 1;}
    
                // Last Seen
                if ($last_seen[$mac] < $v || $last_seen[$mac] == 0) {$last_seen[$mac] = strtotime(date("Y-m-d", $v));}

                // First Seen
                if ($first_seen[$mac] > $v || $first_seen[$mac] == 0) {$first_seen[$mac] = strtotime(date("Y-m-d", $v));}
            }  
        }
        
        // create an array to use in the bubble chart if not filters
        if ((in_array($dev_type[$mac], $type_f)) || empty($type_f)) {
            // Do we want only multi day CODE HERE!!!
            if (($multi_day_f && $first_seen[$mac] != $last_seen[$mac]) || !$multi_day_f) {
                if (($last_seen[$mac] - $first_seen[$mac]) >= ($day_count_f * 3600 * 24) || !$multi_day_f) {
                    $top[$mac] = $seen_count;
                    $displayed_count++;
                    if ($t_first_disp > $first_seen[$mac] || $t_first_disp == 0) {$t_first_disp = strtotime(date("Y-m-d", $first_seen[$mac]));}
                    if ($t_last_disp < $last_seen[$mac] || $t_last_disp == 0) {$t_last_disp = strtotime(date("Y-m-d", $last_seen[$mac]));}
                }
            }
        }
        if ($t_first_seen > $first_seen[$mac] || $t_first_seen == 0) {$t_first_seen = strtotime(date("Y-m-d", $first_seen[$mac]));}
        if ($t_last_seen < $last_seen[$mac] || $t_last_seen == 0) {$t_last_seen = strtotime(date("Y-m-d", $last_seen[$mac]));}
        $total_seen = $total_seen + $seen_count;
    }
} while(isset($response['LastEvaluatedKey'])); 

// Data for device count by day
asort($by_day);
$day_count = "series: [{name: 'Devices',data: [";
$data = '';
foreach ($by_day as $day => $mac) {
    if ($data != '') {$data .= ',';}
    $data .= count($by_day[$day]);
}
$day_count .= $data . "]}]";

// Data for class share pie chart
$class_data = '';
foreach ($by_class as $class => $mac) {
    if ($class_data != '') {$class_data .= ", \n";}
    $class_data .= "['" . $class . "', " . (count($by_class[$class])/$count) . "]";
}

// Data for bubble chart
$b_data = '';
$series = array();
foreach ($top as $mac => $mct) {
    // For each device get the top day it's been seen and the top date
    $dowtot = 0;
    $doytot = 0;
    if (isset($seen_dayofw[$mac])) {foreach ($seen_dayofw[$mac] as $dow => $dcnt) {$dowtot = $dowtot + ($dow * $dcnt);}}
    if (isset($seen_hours[$mac])) {foreach ($seen_hours[$mac] as $hrs => $dcnt) {$doytot = $doytot + ($hrs * $dcnt);}}
    $avg_dayofweek = round($dowtot/$mct,2);
    $avg_hr = round($doytot/$mct,2);
    if ($avg_dayofweek == 0) {$avg_dayofweek = 1;}
    if ($avg_hr == 0) {$avg_hr = 1;}
    
    $disp_hr = date("h:i a", round($avg_hr * 60 * 60));
    
    // Name series based on how recently these were seen
    $lsn = 0;
    if (isset($last_seen[$mac])) {if ($last_seen[$mac] > (time() - (3600*24*7))) {$lsn = strtotime(date("m/d/Y", $last_seen[$mac]));}}
    if (isset($series[$lsn])) { $series[$lsn] .= ", \n";} else {$series[$lsn] = '';}
    // Set an upper limit on the circle size
    $mctd = $mct;
    if ($mctd > 300) {$mctd = 300;}
    
    // Get infor about this class of device
    $class_det = 'Unknown';
    if ($classes[$mac] != 'n/a') {
        $hex = str_replace("0x", "", $classes[$mac]);
        $class_det = str_replace("'", "\'", get_bt_class_info($hex, $mdc));
    }

    $series[$lsn] .= "{n: '". str_replace("'", "\'", $name[$mac]) 
            . "', m: '" . $mac 
            . "', l: '" . date("m/d/Y h:i a", $last_seen[$mac]) 
            . "', f: '" . date("m/d/Y h:i a", $first_seen[$mac]) 
            . "', d: '" . $day_names[(round($avg_dayofweek) - 1)]
            . "', h: '" . $disp_hr 
            . "', c: '" . $class_det
            . "', i: '" . $my_mac_info[$mac] 
            . "', type: '" . $dev_type[$mac] 
            . "', t: " . $mct 
            . ", x: " . $avg_hr 
            . ", y: " . $avg_dayofweek
            . ", z: " . $mctd . "}";
}
// Build the type list for ajax setting
$b_types = '';
foreach ($type_list as $type => $val) {
    if ($b_types == '') { $b_types = "\t\t\t\t'(";} else {$b_types .= " | ' + \n \t\t\t\t'";}
    $b_types .= "<a onclick=\"set_type(\'" . $type . "\', \'' + this.point.m + '\');\">" . $type . "</a>";
}
$b_types .= ")' + \n";

// Build the series
krsort($series);
foreach ($series as $lsn => $lsn_data) {
    if ($b_data != '') {$b_data .= ", \n";}
    if ($lsn == 0) {$lsn = "More than 7 Days Ago";} else {$lsn = date("m/d/Y", $lsn);}
    $b_data .= "{ showInLegend: true, name: '". $lsn . "', data: [" . $lsn_data . "]}";
}

// Get details about devices
// The Scan API is paginated. Issue the Scan request multiple times.
$collectors = array();
$col_select_list = array();
unset($response);
do {
    $request = array(
        "TableName" => 'collectors',
        "Limit" => 60
    );
    // Add the ExclusiveStartKey if we got one back in the previous response
    if(isset($response) && isset($response['LastEvaluatedKey'])) {
        $request['ExclusiveStartKey'] = $response['LastEvaluatedKey'];
    }
    $response = $client->scan($request);
    foreach ($response['Items'] as $key => $value) {
        $id = $value['collector_id']["S"];
        $col_select_list[$id] = $id; // If we give these a name change value to that for this select list array
        $collectors[$id]['collector_active'] = $value['collector_active']["N"];
        $collectors[$id]['collector_region_name'] = $value['collector_region_name']["S"];
        $collectors[$id]['collector_checkin_count'] = $value['collector_checkin_count']["N"];
        $collectors[$id]['collector_last_checkin'] = $value['collector_last_checkin']["N"];
        $collectors[$id]['collector_private_ip'] = $value['collector_private_ip']["S"];
        $collectors[$id]['collector_public_ip'] = $value['collector_public_ip']["S"];
        $collectors[$id]['collector_storage'] = $value['collector_storage']["N"];
    }

} while(isset($response['LastEvaluatedKey']) && 0 == 1); 




?>
