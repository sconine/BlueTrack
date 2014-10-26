<html>
<head>BlueTrack
<meta charset="UTF-8"></head>
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

//echo "<table><tr><td>mac_id</td><td>collector_id</td><td>name</td><td>clock_offset</td><td>class</td><td>inq_on</td><td>scan_on</td></tr>";
$count = 0;
$top = array();
$names = array();
$show_minutes = array();
$seen_hours = array();
$seen_days = array();
date_default_timezone_set('UTC');

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
        $count++;
        $mac = $value['mac_id']["S"];
        $name[$mac] = implode(',', $value['name']["SS"]);
        
        // Manipulate the dates a bit
        $seen = array_merge($value['scan_on']["NS"], $value['inq_on']["NS"]);
        $seen_count = 0;
        foreach ($seen as $i => $v) {
          $seen_count++;
          if (isset($show_minutes[$mac][date("Y-m-d h:i a", $v - 14400)])) {$show_minutes[$mac][date("Y-m-d h:i a", $v - 14400)]++;}
          else {$show_minutes[$mac][date("Y-m-d h:i a", $v - 14400)] = 1;}
          if (isset($seen_hours[$mac][date("h a", $v - 14400)])) {$seen_hours[$mac][date("h a", $v - 14400)]++;}
          else {$seen_hours[$mac][date("h a", $v - 14400)] = 1;}
          if (isset($seen_days[$mac][date("Y-m-d", $v - 14400)])) {$seen_days[$mac][date("Y-m-d", $v - 14400)]++;}
          else {$seen_days[$mac][date("Y-m-d", $v - 14400)] = 1;}
        }  
        
        // create a vew arrays of data we care about
        $top[$mac] = $seen_count;
        
      //echo "<tr><td>" . $value['mac_id']["S"] . "</td>";
      //echo "<td>" . $value['collector_id']["S"] . "</td>";
      ////echo "<tr><td>" . implode(',', $value['name']["SS"]) . "</td></tr>";
      //echo "<td>" . implode(',', $value['clock_offset']["SS"]) . "</td>";
      //echo "<td>" . implode(',', $value['class']["SS"]) . "</td>";
      //$seen = array_merge($value['scan_on']["NS"], $value['inq_on']["NS"]);
      //foreach ($seen as $t => $v) {
       //   $show_seen[] = date("Y-m-d h:i a", $t - 14400);
      //}      
     // $show_seen = array_unique($show_seen);
      //echo "<td>" . implode('<br>', $show_seen) . "</td>";
      
    }
} while(isset($response['LastEvaluatedKey'])); 
//If there is no LastEvaluatedKey in the response, there are no more items matching this Scan invocation

echo "<table><tr><td>name</td><td>count</td><td>Days</td></tr>";
arsort($top);

foreach ($top as $mac => $count) {
    echo "<tr><td>$name[$mac]</td><td>$count</td><td><table><tr><td>Day</td><td>Count</td></tr>\n";
    foreach ($seen_days[$mac] as $d => $c) {
        echo "<tr><td>$d</td><td>$c</td></tr>\n";
    }
    echo "</table></td></tr>\n";
}

echo "</table><br> There are <b>$count</b> Total!<br>";

?>



</body>
</html>
