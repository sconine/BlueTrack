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

// Setup filters
$type_f = array();
if(!empty($_REQUEST['type'])) {$type_f = $_REQUEST['type'];}
$pattern = '/^[a-zA-ZvV0-9,]+$/';
if (preg_match($pattern, implode(",", $type_f)) == 0) {$type_f = array();}

// List of type filters from class table
$sql = 'SELECT class, short_major_type, device_type FROM class_description WHERE  short_major_type IS NOT NULL ORDER BY  short_major_type, device_type;';
$type_ar = query_to_array($sql, $mysqli);
if (count($type_ar) == 0) {
  $type_desc = array();
} else {
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
