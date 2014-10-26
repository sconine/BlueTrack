<html>
<head>BlueTrack</head>
<body>
<?php

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

echo "<table><tr><td>mac_id</td><td>collector_id</td><td>name</td><td>clock_offset</td><td>class</td><td>inq_on</td><td>scan_on</td></tr>";

// The Scan API is paginated. Issue the Scan request multiple times.
do {
    $request = array(
        "TableName" => $tableName,
        "Limit" => 20
    );

    // Add the ExclusiveStartKey if we got one back in the previous response
    if(isset($response) && isset($response['LastEvaluatedKey'])) {
        $request['ExclusiveStartKey'] = $response['LastEvaluatedKey'];
    }
    $response = $client->scan($request);

    foreach ($response['Items'] as $key => $value) {
      echo "<tr><td>" . $value['mac_id']["S"] . "</td>";
      echo "<td>" . $value['collector_id']["S"] . "</td>";
      echo "<td>" . $value['name']["SS"] . "</td>";
      echo "<td>" . $value['clock_offset']["SS"] . "</td>";
      echo "<td>" . $value['class']["SS"] . "</td>";
      echo "<td>" . $value['inq_on']["NS"] . "</td>";
      echo "<td>" . $value['scan_on']["NS"] . "</td></tr>";
    }
} while(isset($response['LastEvaluatedKey'])); 
//If there is no LastEvaluatedKey in the response, there are no more items matching this Scan invocation



echo '</table>Made it here!<br>';

?>



</body>
</html>
