<html>
<head><title>BlueTrack</title>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
<script src="http://code.highcharts.com/highcharts.js"></script>
<meta charset="UTF-8"></head>
<body>

<div id="container" style="width:100%; height:400px;"></div>
<script>
$(function () {
    $('#container').highcharts({
        chart: {
            type: 'line'
        },
        title: {
            text: 'Fruit Consumption'
        },
        xAxis: {
            categories: ['Apples', 'Bananas', 'Oranges']
        },
        yAxis: {
            title: {
                text: 'Fruit eaten'
            }
        },
        series: [{
            name: 'Jane',
            data: [[1,2], [0,3], [4,4]]
        }, {
            name: 'John',
            data: [[5,2], [7,3], [3,4]]
        }],
    });
});
</script>


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
$last_hour = array();
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
          
          // Keep track of ones we've seen in last hour
          if ($v > (time() - 3600)) {
            if (isset($last_hour[$mac])) {$last_hour[$mac]++;}
            else {$last_hour[$mac] = 1;}
          }
          
          $minute = strtotime(date("Y-m-d h:i a", $v - 14400));
          $hour = strtotime(date("1990-01-01 h:00 a", $v - 14400));
          $day = strtotime(date("Y-m-d", $v - 14400));
          
          if (isset($show_minutes[$mac][$minute])) {$show_minutes[$mac][$minute]++;}
          else {$show_minutes[$mac][$minute] = 1;}
          if (isset($seen_hours[$mac][$hour])) {$seen_hours[$mac][$hour]++;}
          else {$seen_hours[$mac][$hour] = 1;}
          if (isset($seen_days[$mac][$day])) {$seen_days[$mac][$day]++;}
          else {$seen_days[$mac][$day] = 1;}
        }  
        
        // create a vew arrays of data we care about
        $top[$mac] = $seen_count;
    }
} while(isset($response['LastEvaluatedKey'])); 
//If there is no LastEvaluatedKey in the response, there are no more items matching this Scan invocation

echo "Key Facts:<table><tr><td>Total Seen</td><td>$count</td></tr>";
echo "<tr><td>Seen in Last Hour</td><td>" . count($last_hour) . "</td></tr>";
echo "</table><hr>Seen in Last Hour:<br>";

echo "<table><tr><td>name</td><td>count</td></tr>";
arsort($last_hour);

foreach ($last_hour as $mac => $count) {
    echo "<tr><td>$name[$mac]</td><td>$count</td></tr>\n";
}
echo "</table><hr>Total List:<br>";


echo "<table><tr><td>name</td><td>count</td><td>Days</td></tr>";
arsort($top);

foreach ($top as $mac => $count) {
    echo "<tr><td>$name[$mac]</td><td>$count</td><td><table><tr><td>Day</td><td>Count</td></tr>\n";
    krsort($seen_days[$mac]);
    foreach ($seen_days[$mac] as $d => $c) {
        echo "<tr><td>" . date("Y-m-d", $d) . "</td><td>$c</td></tr>\n";
    }
    echo "</table></td></tr>\n";
}

echo "</table><br> There are <b>$count</b> Total!<br>";




?>



</body>
</html>
