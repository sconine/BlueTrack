<html>
<head>BlueTrack</head>
<body>
Hi There!<br>
<?php
echo "hi Steve!";

// Load my configuration
$debug = true;
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

# The Scan API is paginated. Issue the Scan request multiple times.
do {
    echo "Scanning table $tableName" . PHP_EOL;
    $request = array(
        "TableName" => $tableName,
        "Limit" => 20
    );

    # Add the ExclusiveStartKey if we got one back in the previous response
    if(isset($response) && isset($response['LastEvaluatedKey'])) {
        $request['ExclusiveStartKey'] = $response['LastEvaluatedKey'];
    }
    $response = $client->scan($request);
    echo "new page<hr>";

    foreach ($response['Items'] as $key => $value) {
      //var_dump($value);
      echo "mac_id: " . $value['mac_id']["S"] . "<br>";
    }
} while(isset($response['LastEvaluatedKey'])); 
//If there is no LastEvaluatedKey in the response, there are no more items matching this Scan invocation

//'mac_id'      => array("S" => $mac),
//'collector_id'      => array("S" => $collector_id)
//"name" => array("SS" => $name),
//"clock_offset" =>  array("SS" => $clock_offset),
//"class" => array("SS" => $class),
//"inq_on" => array("NS" => $inq_on),
//"scan_on" => array("NS" => $scan_on),


echo 'Made it here!<br>';

?>



</body>
</html>
