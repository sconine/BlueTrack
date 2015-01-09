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
if(!empty($_REQUEST['total_coun_h'])) {$total_count_h_f = $_REQUEST['total_count_h'];}
if(!empty($_REQUEST['total_count_l'])) {$total_count_l_f = $_REQUEST['total_count_l'];}
if(!empty($_REQUEST['company_name'])) {$company_name_f = $_REQUEST['company_name'];}
if(!empty($_REQUEST['col_id'])) {$col_id_f = $_REQUEST['col_id'];}

$pattern = '/^[a-zA-ZvV0-9,]+$/';
if (preg_match($pattern, implode(",", $type_f)) == 0) {$type_f = array();}
if ($start_day_f != '') {$start_day_f = strtotime($start_day_f);}
if ($end_day_f != '') {$end_day_f = strtotime($end_day_f);}
if (!is_numeric($total_count_h_f)) {$total_count_h_f = 0;}
if (!is_numeric($total_count_l_f)) {$total_count_l_f = 0;}

// Pull data we care about
$sql = 'SELECT a. mac_id, collector_id, seen, name, major_type, device_type, service_class, company_name, b.class'.
        'FROM device_scans a INNER JOIN devices b ON a.mac_id=b.mac_id '.
        'INNER JOIN class_description c ON c.class=b.class '.
        'LEFT OUTER JOIN mac_roots d ON d.mac_root=b.mac_root '.
        'LEFT OUTER JOIN manufacturers e ON d.manu_id=e.manu_id '.

// Setup filters
$filters = '';

// Filter by Class Type
if (!empty($type_f)) {
  $class_types = '';
  foreach ($type_f as $i => $v) {
    foreach (explode(',', $v)) {
      if ($class_types != '') {$class_types .= ',';}
      $class_types .= sqlq($v,0);
    }
  if ($class_types != '') {
    if ($filters != '') {$filters .= ' AND ';}
    $filters = 'b.class IN (' . $class_types . ')';
  }
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
    $series[$lsn] .= "{n: '". str_replace("'", "\'", $names[$mac]) 
            . "', m: '" . $mac 
            . "', l: '" . date("m/d/Y h:i a", $last_seen[$mac]) 
            . "', f: '" . date("m/d/Y h:i a", $first_seen[$mac]) 
            . "', d: '" . $day_names[(round($avg_dayofweek) - 1)]
            . "', h: '" . $disp_hr 
            . "', c: '" . $class_det
            . "', w: '" . $my_collectors[$mac]
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
    $b_types .= "<a onclick=\"set_type(\'" . $type . "\', \'' + this.point.m + '\', \'' + this.point.w + '\');\">" . $type . "</a>";
}
$b_types .= ")' + \n";
// Build the series
krsort($series);
foreach ($series as $lsn => $lsn_data) {
    if ($b_data != '') {$b_data .= ", \n";}
    if ($lsn == 0) {$lsn = "More than 7 Days Ago";} else {$lsn = date("m/d/Y", $lsn);}
    $b_data .= "{ showInLegend: true, name: '". $lsn . "', data: [" . $lsn_data . "]}";
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
