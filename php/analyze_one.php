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

// Setup filters (could take an array, but initial plan if for just one at a time)
$mac = array();
if(!empty($_REQUEST['mac'])) {
    $mac = $_REQUEST['mac'];
}
if (isset($_REQUEST['col'])) {$collector_id = $_REQUEST['col'];} else {$collector_id = 'b8:27:eb:3a:0b:aa';}

// Make sure they look safe
$pattern = '/^[a-zA-ZvV0-9,]+$/';
if (preg_match($pattern, implode(",", $mac)) == 0) {echo 'no mac'; exit;}
if (preg_match($pattern, $collector_id) == 0) {$collector_id = 'b8:27:eb:3a:0b:aa';}


$day_names = array("Mon", "Tues", "Wed", "Thurs", "Fri", "Sat", "Sun");
$top = array();
$show_minutes = array();
$seen_hours = array();
$seen_days = array();
date_default_timezone_set('UTC');

$result = $client->getItem(array(
    'ConsistentRead' => true,
    'TableName' => 'collector_data',
    'Key'       => array(
        'mac_id'   => array('S' => $mac),
        'collector_id'   => array('S' => $region_name)
    )
));
if ($debug) {var_dump($result); echo '<br>';}
exit;


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

?>


<div style="width: 100%; display: table;">
    <div style="display: table-row">
        <div id="byday" style="width: 400px;  height:400px; display: table-cell;"></div>
        <div id="byclass" style="width: 400px;  height:400px; display: table-cell;"></div>
    </div>
</div>
<div id="bydevice" style="width: 100%;  height:600px;"></div>



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
            categories: ['Mon', 'Tues', 'Wed', 'Thurs', 'Fri', 'Sat', 'Sun']
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
        yAxis: {
            labels: {
                enabled: true
            },
            categories: [' ', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun', ' ']
        },
        title: {
            text: 'Devices'
        },
        tooltip: {
            useHTML: true, 
            formatter: function() {
                return '<b>' + this.point.n + '</b><br>Seen: ' + this.point.t + ' times' +
                    <?php
                        echo($b_types);
                    ?>
                    ' <b>' + this.point.type + '</b>' +
                    '<br>Avg Hour: ' + this.point.h + ', Avg Day: ' + this.point.d +
                    '<br>MAC: ' + this.point.m + 
                    '<br>First Seen: <b>' + this.point.f +
                    '</b><br>Last Seen: <b>' + this.point.l + '</b>';
            }
        },
        plotOptions: {
            bubble: {
                dataLabels: {
                    enabled: true,
                    style: { textShadow: 'none', color: '#000000' },
                    formatter: function() {
                        if (this.point.n == 'n/a') {
                            return this.point.type;
                        } else {
                            return '<b>(' + this.point.type + ')</b>';
                        }
                    }
                },
            
                minSize:15,
                maxSize:100
                //minSize:'2%',
                //maxSize:'50%'
                
            }
        },
        series: [<?php echo $b_data; ?>]
    });
});

</script>



</body>
</html>
