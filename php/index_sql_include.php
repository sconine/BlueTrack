<?php
// Get the media we have stored in dynamoDB and load into a MySQL structure
// don't want to print debug through web server in general
$debug = false; 

// Load my configuration
$datastring = file_get_contents('/usr/www/html/BlueTrack/master_config.json');
$config = json_decode($datastring, true);
if ($debug) {echo "datastring: $datastring\n";}
if ($debug) {var_dump($config);}
//Use MY SQL - this include assumes that $config has been loaded 
//All the table creation is done in this include
include '/usr/www/html/BlueTrack/php/my_sql.php';
date_default_timezone_set('UTC');
// You'll need to edit this with your config
require '../vendor/autoload.php';
use Aws\Common\Aws;
// Loop through the dynamo tables and load the data into MySQL
$aws = Aws::factory('/usr/www/html/BlueTrack/php/amazon_config.json');
$client = $aws->get('DynamoDb');

// Setup filters
$type_f = array();
$start_day_f = '';
$end_day_f = '';
$name_f = '';
$total_count_h_f = 0;
$total_count_l_f = 0;
$company_name_f = array();
$col_id_f = array();

if(!empty($_REQUEST['type'])) {$type_f = $_REQUEST['type'];}
if(!empty($_REQUEST['start_day'])) {$start_day_f = $_REQUEST['start_day'];}
if(!empty($_REQUEST['end_day'])) {$end_day_f = $_REQUEST['end_day'];}
if(!empty($_REQUEST['name'])) {$name_f = $_REQUEST['name'];}
if(!empty($_REQUEST['company_name'])) {$company_name_f = $_REQUEST['company_name'];}
if(!empty($_REQUEST['col_id'])) {$col_id_f = $_REQUEST['col_id'];}

$pattern = '/^[a-zA-ZvV0-9,]+$/';
if (preg_match($pattern, implode(",", $type_f)) == 0) {$type_f = array();}
// Put in GMT since our times are stored in GMT
if ($start_day_f != '') {$start_day_f = strtotime($start_day_f) - (3600 * 5);}
if ($end_day_f != '') {$end_day_f = strtotime($end_day_f) - (3600 * 5);}

// Pull data we care about
// (for date format see: http://www.epochconverter.com/programming/mysql-from-unixtime.php) 
$sql = 'SELECT mac_id, collector_id, name, major_type, device_type, service_class, company_name, manu_id, class, '.
        ' seen_hour, hour_count FROM device_scans_hour ';

// Setup filters
$filters = '';

// Filter by Class Type
if (!empty($type_f)) {
  $class_types = quote_list($type_f, 0);
  if ($class_types != '') {
    if ($filters != '') {$filters .= ' AND ';}
    $filters .= ' class IN (' . $class_types . ')';
  }
}

// Date range filters
if ($start_day_f != '') {
    if ($filters != '') {$filters .= ' AND ';}
    $filters .= ' seen_hour >= ' . sqlq($start_day_f,1);
}
if ($end_day_f != '') {
    if ($filters != '') {$filters .= ' AND ';}
    $filters .= ' seen_hour <= ' . sqlq($end_day_f,1);
}

// Device Name filter
if ($name_f != '') {
    if ($filters != '') {$filters .= ' AND ';}
    $filters .= ' name like ' . sqlq('%' . $name_f . '%' ,0);
}

// Company Name filter
if (!empty($company_name_f)) {
  $company_names = quote_list($company_name_f, 1);
  if ($company_names != '' AND $company_names != 'NULL') {
    if ($filters != '') {$filters .= ' AND ';}
    $filters .= ' manu_id IN (' . $company_names . ')';
  }
}

// Collector filter
if (!empty($col_id_f)) {
  $colids = quote_list($col_id_f, 0);
  if ($colids != '' AND $colids != 'NULL') {
    if ($filters != '') {$filters .= ' AND ';}
    $filters .= ' collector_id IN (' . $colids . ')';
  }
}
if ($filters != '') {$filters = ' WHERE ' . $filters;}
$sql .= $filters;
//$sql .= ' LIMIT 10; ';

//echo $sql;
$data = query_to_array($sql, $mysqli);
$series = array();
$min_date = 0;

// Data for Heat Map
foreach ($data as $i => $v) {
    // put in EST since stored in GMT
    $seen_hour =  $v['seen_hour'] + (3600 * 5)
    $key = date("Y-m-d,G", $seen_hour);
    if (!isset($series[$key])) {$series[$key] = 1;} else {$series[$key]++;}
    if ($min_date == 0 || $seen_hour < $min_date) {$min_date = $seen_hour];}
/*
    $mctd = $v['hour_count'];
    $series[$lsn] .= "{n: '". str_replace("'", "\'", $v['name']) 
            . "', m: '" . $v['mac_id']
            . "', l: '" . date("m/d/Y h:i a", $v['seen_hour']) 
            . "', c: '" . "Type: " . $v['major_type'] 
            . "', w: '" . $v['collector_id'] 
            . "', i: '" . $v['company_name'] 
            . "', type: '" . 'X' 
            . "', t: " . $mctd
            . ", x: " . date("G", round($v['seen_hour'])) 
            . ", y: " . date("z", round($v['seen_hour'])) 
            . ", z: " . $mctd . "}";
*/

}


// Build a year's worth of data from the min date seen forward
$heat_data = '';
$start_year = date("Y", $min_date);
$start_month = date("m", $min_date) - 1;
$s_data = array();
for ($i = $min_date; $i <= ($min_date + 60*60*24*265); $i = $i + (60*60)) {
    $key = date("Y-m-d,G", $i);
    if (!isset($series[$key])) {$s_data[$key] = 0;} else {$s_data[$key] = $series[$key];}
}

foreach ($s_data as $key => $v) {
   $heat_data .= $key . ',' . $v . "\n";
}









// List of collectors to pick from
$col_select_list= array();
$collectors= array();
$sql = 'select collector_id, region_name, checkin_count, last_checkin, private_ip from collectors order by collector_id;';
$coll_ar = $type_ar = query_to_array($sql, $mysqli);
if (count($coll_ar) > 0) {
  foreach ($coll_ar as $i => $v) {
    $col_select_list[$v['collector_id']] = $v['collector_id'];
    $collectors[$v['collector_id']]['collector_checkin_count'] = $v['checkin_count'];
    $collectors[$v['collector_id']]['collector_last_checkin'] = $v['last_checkin'];
    $collectors[$v['collector_id']]['collector_region_name'] = $v['region_name'];
    $collectors[$v['collector_id']]['collector_private_ip'] = $v['private_ip'];
  }
} 

// List of companies to choose from
$company_name_select_list = array();
$sql = 'SELECT manu_id, company_name, SUM(DevCount) as DeviceCount FROM (select a.manu_id, company_name, (SELECT count(1) FROM devices c WHERE c.mac_root=b.mac_root) as DevCount FROM manufacturers a INNER JOIN mac_roots b on a.manu_id=b.manu_id) as tbl GROUP BY manu_id, company_name HAVING DeviceCount > 0 ORDER BY DeviceCount DESC;';
$comp_ar = $type_ar = query_to_array($sql, $mysqli);
if (count($comp_ar) > 0) {
  foreach ($comp_ar as $i => $v) {
    $company_name_select_list[$v['manu_id']] = substr($v['company_name'], 0, 32) . ' (' . $v['DeviceCount'] . ')';
  }
} 

// List of type filters from class table
$type_desc = array();
$sql = 'SELECT class, short_major_type, device_type FROM class_description WHERE  short_major_type IS NOT NULL ORDER BY  short_major_type, device_type;';
$type_ar = query_to_array($sql, $mysqli);
if (count($type_ar) > 0) {
  $class_array = array();
  foreach ($type_ar as $i => $v) {
    $dt = json_decode($v['device_type']);
    $desc = $v['short_major_type'];
    if (count($dt) == 0) {$desc .= ' - Other';}
    else {$desc .= ' - ' . $dt[0];}
    if (isset($class_array[$desc])) {$class_array[$desc] .= ',' . $v['class'];}
    else {$class_array[$desc] = $v['class'];}
  }
  foreach ($class_array as $i => $v) {$type_desc[$v] = $i;}
}





?>
