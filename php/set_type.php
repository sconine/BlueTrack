<?php
// Load my configuration
$debug = false;
$datastring = file_get_contents('/usr/www/html/BlueTrack/master_config.json');
if ($debug) {echo "datastring = $datastring <br>\n";}
$config = json_decode($datastring, true);
if ($debug) {var_dump($config);}

if (isset($_REQUEST['type'])) {$type = $_REQUEST['type'];} else {echo 'No Type'; exit;}
if (isset($_REQUEST['mac'])) {$mac = $_REQUEST['mac'];} else {echo 'No Mac'; exit;}

$pattern = '/^[a-zA-Z0-9:]+$/';
if (preg_match($pattern, $type) == 0) {echo 'Bad Type'; exit;}
if (preg_match($pattern, $mac) == 0) {echo 'Bad Mac'; exit;}

require '../vendor/autoload.php';
use Aws\Common\Aws;

// You'll need to edit this with your config file
// make sure you specify the correct region as dynamo is region specific
$aws = Aws::factory('/usr/www/html/BlueTrack/php/amazon_config.json');
$client = $aws->get('DynamoDb');

$result = $client->updateItem(array(
	'TableName' => 'collector_data',
	'Key' => array(
		'mac_id'      => array("S" => $mac)
	),
	"AttributeUpdates" => array(
		"type" => array(
			"Value" => array("S" => $type),
			"Action" => "PUT"
		)
	),
	'ReturnValues' => "NONE"
));


echo 'Done, ';
?>
