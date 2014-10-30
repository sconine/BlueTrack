<html>
<head><title>BlueTrack</title>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
<script src="http://code.highcharts.com/highcharts.js"></script>
<script src="http://code.highcharts.com/highcharts-more.js"></script>
<script src="http://code.highcharts.com/modules/exporting.js"></script>
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

// Setup filters (could take an array, but initial plan if for just one at a time)
$mac = '';
if(!empty($_REQUEST['mac'])) {$mac = $_REQUEST['mac'];}
if (isset($_REQUEST['col'])) {$collector_id = $_REQUEST['col'];} else {$collector_id = 'b8:27:eb:3a:0b:aa';}

// Make sure they look safe
$pattern = '/^[a-zA-ZvV0-9,:]+$/';
if (preg_match($pattern, $mac) == 0) {$mac = '00:11:B1:08:97:3D';}
if (preg_match($pattern, $collector_id) == 0) {$collector_id = 'b8:27:eb:3a:0b:aa';}

$agg = array();
date_default_timezone_set('UTC');

$result = $client->getItem(array(
    'ConsistentRead' => true,
    'TableName' => 'collector_data',
    'Key'       => array(
        'mac_id'   => array('S' => $mac),
        'collector_id'   => array('S' => $collector_id)
    )
));
if ($debug) {var_dump($result); echo '<br>';}
if (!isset($result['Item']['collector_id']['S'])) {echo 'No device found'; exit;}

$c_name = 'Device detail: ' . $result['Item']['mac_id']["S"] . ' known as: <b>';
$c_name .= str_replace("'", "\'", implode(',', $result['Item']['name']["SS"])) . '</b> type: ';
$c_name .= isset($result['Item']['type']["S"]) ? $result['Item']['type']["S"] : 'X';

// Manipulate the dates a bit
$seen = array();
if (is_array($result['Item']['scan_on']["NS"]) && is_array($result['Item']['inq_on']["NS"])) {
    $seen = array_merge($result['Item']['scan_on']["NS"], $result['Item']['inq_on']["NS"]);
} elseif (is_array($result['Item']['scan_on']["NS"])) {
    $seen = $result['Item']['scan_on']["NS"];
} elseif (is_array($result['Item']['inq_on']["NS"])) {
    $seen = $result['Item']['scan_on']["NS"];
}

$min_day = 0;
$max_day = 0;
foreach ($seen as $i => $v) {
    if ($v == 1) {next;}
    
    // put in EST
    $v = $v - (3600 * 5);
    $day = strtotime(date("Y-m-d", $v));
    $hourofday = date("H", $v);

    // Aggregate by hour and day
    if (isset($agg[$day][$hourofday])) {$agg[$day][$hourofday]++;} else {$agg[$day][$hourofday] = 1;}
    //if ($min_day ==0 || $min_day > $day) {$min_day = $day;}
    //if ($max_day ==0 || $max_day < $day) {$max_day = $day;}
}

// Look at min and max days and fill in missing days
//for ($i = 1; $i <= 10; $i = $i + (60*60*24)) {if (! isset($agg[$i])) {$agg[$i] = 1;}}

// Now put in highcharts format
$data_set = '';
foreach ($agg as $day => $hour_a) {
    
    if (is_array($hour_a)) {
        foreach ($hour_a as $hour => $count) {
            if ($data_set != '') { $data_set .= ',';}
            $data_set .= "{"
                    . "x: " . $day
                    . ", y: " . $hour
                    . ", z: " . $count . "}";
        }
    }
}
$b_data = "{ showInLegend: false, data: [" . $data_set . "]}";

?>

<div id="byday" style="width: 100%;  height:600px;"></div>

<script>

$(function () {
    $('#byday').highcharts({
        chart: {
            type: 'bubble',
            zoomType: 'xy'
        },
        title: {
            text: '<?php echo $c_name; ?>'
        },
        tooltip: {
            useHTML: true, 
            formatter: function() {
                return 'Seen: ' + this.point.z + ' times<br>' +
                        'At: ' + Highcharts.dateFormat('%m/%d/%y %l:%M%P', this.point.x * 1000);
            }
        },
        plotOptions: {
            bubble: {
                minSize:15,
                maxSize:100
            }
        },
        xAxis: {        
            type: 'datetime',
            labels: {
                rotation: -45,
                style: {
                    fontSize: '13px',
                    fontFamily: 'Verdana, sans-serif'
                },
                formatter: function() {
                    return Highcharts.dateFormat('%m/%d/%y %l:%M%P', this.value * 1000);
                }
            }
        },
        yAxis: {        
            labels: {
                formatter: function() {
                    return Highcharts.dateFormat('%l:%M%P', this.value * 60*60*1000);
                }
            }
        },
        series: [<?php echo $b_data; ?>]
    });
});

</script>



</body>
</html>
