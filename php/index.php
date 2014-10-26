<html>
<head><title>BlueTrack</title>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
<script src="http://code.highcharts.com/highcharts.js"></script>
<script src="http://code.highcharts.com/highcharts-more.js"></script>
<script src="http://code.highcharts.com/modules/exporting.js"></script>
<meta charset="UTF-8"></head>
<body>

<div style="width: 100%; display: table;">
    <div style="display: table-row">
        <div id="byday" style="width: 400px;  height:400px; display: table-cell;"></div>
        <div id="byclass" style="width: 400px;  height:400px; display: table-cell;"></div>
    </div>
</div>
<div id="bydevice" style="width: 100%;  height:600px;"></div>

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
$by_day = array();
$by_class = array();
$last_seen = array();
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
        "Limit" => 500
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
            // put in EST
            $v = $v - 14400;
            
            // Keep track of ones we've seen in last hour
            if ($v > (time() - 3600)) {
                if (isset($last_hour[$mac])) {$last_hour[$mac]++;}
                else {$last_hour[$mac] = 1;}
            }
            
            $minute = strtotime(date("Y-m-d h:i a", $v));
            $hour = strtotime(date("1990-01-01 h:00 a", $v));
            $day = strtotime(date("Y-m-d", $v));
            $hourofday = date("H", $v);
            $dayofyear = date("z", $v);
            $dayofweek = date("w", $v);
            $dayofweek3 = date("w", $v);

            // Keep track of counts by day
            if (isset($by_day[$dayofweek][$mac])) {$by_day[$dayofweek][$mac]++;}
            else {$by_day[$dayofweek][$mac] = 1;}
            
            // Keep track of counts by class
            $t_class = implode(',', $value['class']["SS"]);
            if (isset($by_class[$t_class][$mac])) {$by_class[$t_class][$mac]++;}
            else {$by_class[$t_class][$mac] = 1;}
            
            // Build data for bubble chart
            if (isset($seen_dayofw[$mac][$dayofweek3])) {$seen_dayofw[$mac][$dayofweek3]++;}
            else {$seen_dayofw[$mac][$dayofweek3] = 1;}
            if (isset($seen_hours[$mac][$hourofday])) {$seen_hours[$mac][$hourofday]++;}
            else {$seen_hours[$mac][$hourofday] = 1;}

            // Last Seen
            if (isset($last_seen[$mac])) {
                if ($last_seen[$mac] < $v) {$last_seen[$mac] = $v;}
            } else {
                $last_seen[$mac] = $v;
            }

            // Stuff to show various tables
            /*
            if (isset($show_minutes[$mac][$minute])) {$show_minutes[$mac][$minute]++;}
            else {$show_minutes[$mac][$minute] = 1;}
            if (isset($seen_hourss[$mac][$hour])) {$seen_hourss[$mac][$hour]++;}
            else {$seen_hourss[$mac][$hour] = 1;}
            if (isset($seen_days[$mac][$day])) {$seen_days[$mac][$day]++;}
            else {$seen_days[$mac][$day] = 1;}
            */
        }  
        
        // create a vew arrays of data we care about
        $top[$mac] = $seen_count;
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
foreach ($top as $mac => $mct) {
    // For each device get the top day it's been seen and the top date
    $dowtot = 0;
    $doytot = 0;
    foreach ($seen_dayofw[$mac] as $dow => $dcnt) {$dowtot = $dowtot + ($dow * $dcnt);}
    foreach ($seen_hours[$mac] as $hrs => $dcnt) {
        $doytot = $doytot + ($hrs * $dcnt);
        //if ($mac == "5C:51:4F:4F:CC:BA") {echo "hrd = $hrs , count = $dcnt , totl = $doytot <br>\n";}
    }
    $avg_dayofweek = round($dowtot/$mct,2);
    $avg_hr = round($doytot/$mct,2);

    if ($b_data != '') {$b_data .= ", \n";}
    $b_data .= "{ showInLegend: false, name: '". str_replace("'", "\'", $name[$mac]) . "', data: [{m: '" . $mac . "', l: '" . date("Y-m-d h:i a", $last_seen[$mac]) . "', x: " . $avg_hr . ", y: " . $avg_dayofweek . ", z: " . $mct . "}]}";
    //$b_data .= "[1, 2, " . $count . "]";
}

?>

<script>
$(function () {
    $('#byday').highcharts({
        chart: {
            type: 'line'
        },
        title: {
            text: 'Daily Devices'
        },
        xAxis: {
            categories: ['Sun', 'Mon', 'Tues', 'Wed', 'Thurs', 'Fri', 'Sat']
        },
        yAxis: {
            title: {
                text: 'Counts'
            }
        },
        <?php
        echo $day_count;
        ?>
    });
});


$(function () {
    $('#byclass').highcharts({
        chart: {
            plotBackgroundColor: null,
            plotBorderWidth: 1,//null,
            plotShadow: false
        },
        title: {
            text: 'Device Classes Seen'
        },
        tooltip: {
            pointFormat: '{series.name}: <b>{point.percentage:.1f}%</b>'
        },
        plotOptions: {
            pie: {
                allowPointSelect: true,
                cursor: 'pointer',
                dataLabels: {
                    enabled: true,
                    format: '<b>{point.name}</b>: {point.percentage:.1f} %',
                    style: {
                        color: (Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black'
                    }
                }
            }
        },
        series: [{
            type: 'pie',
            name: 'Class Share',
            data: [
            <?php
            echo $class_data;
            ?>
            ]
        }]
    });
});

$(function () {
    $('#bydevice').highcharts({

        chart: {
            type: 'bubble',
            zoomType: 'xy'
        },

        title: {
            text: 'Devices'
        },
        plotOptions: {
            bubble: {
                tooltip: {
                    headerFormat: '<b>{series.name}</b><br>',
                    pointFormat: '{point.z} Total<br>{point.x} Avg Hour<br>{point.y} Avg Day<br>{point.m} MAC<br>{point.l} Last Seen<br>'
                }
            }
        },
        series: [<?php echo $b_data; ?>]
    });
});



</script>

<?php
//If there is no LastEvaluatedKey in the response, there are no more items matching this Scan invocation
echo "Key Facts:<table><tr><td>Total Seen</td><td>$count</td></tr>";
echo "<tr><td>Seen in Last Hour</td><td>" . count($last_hour) . "</td></tr>";
echo "</table><br>";

/*
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
    krsort($seen_hourss[$mac]);
    foreach ($seen_hourss[$mac] as $d => $c) {
        echo "<tr><td>" . date("h:00 a", $d) . "</td><td>$c</td></tr>\n";
    }
    echo "</table></td></tr>\n";
}
echo "</table><br> There are <b>$count</b> Total!<br>";
*/

?>


</body>
</html>
