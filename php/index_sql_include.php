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
if(!empty($_REQUEST['company_name'])) {$company_name_f = $_REQUEST['company_name'][0];}
if(!empty($_REQUEST['col_id'])) {$col_id_f = $_REQUEST['col_id'];}

$pattern = '/^[a-zA-ZvV0-9,]+$/';
if (preg_match($pattern, implode(",", $type_f)) == 0) {$type_f = array();}
if ($start_day_f != '') {$start_day_f = strtotime($start_day_f);}
if ($end_day_f != '') {$end_day_f = strtotime($end_day_f);}
if (!is_numeric($total_count_h_f)) {$total_count_h_f = 0;}
if (!is_numeric($total_count_l_f)) {$total_count_l_f = 0;}












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
    $company_name_select_list[$v['manu_id']] = $v['company_name'] . ' (' . $v['DeviceCount'] . ')';
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
