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

// Setup filters
$type_f = array();
//var_dump($_REQUEST);
if(!empty($_REQUEST['type'])) {
    $type_f = $_REQUEST['type'];
}
//var_dump($type_f);

// Make sure they look safe
$pattern = '/^[a-zA-ZvV0-9,]+$/';
if (preg_match($pattern, implode(",", $type_f)) == 0) {$type_f = array();}
//var_dump($type_f);

//echo "<table><tr><td>mac_id</td><td>collector_id</td><td>name</td><td>clock_offset</td><td>class</td><td>inq_on</td><td>scan_on</td></tr>";
$count = 0;
$day_names = array("Mon", "Tues", "Wed", "Thurs", "Fri", "Sat", "Sun");
$last_hour = array();
$type_list = array();
// Set some default ones
$type_list['M'] = 1; // Mobile Phone
$type_list['H'] = 1; // Human
$type_list['V'] = 1; // Vehicle
$type_list['A'] = 1; // Apple Device
$type_list['C'] = 1; // Computer
$type_list['G'] = 1; // GPS
$type_list['T'] = 1; // TV Device
$type_list['S'] = 1; // Music Device
$type_list['U'] = 1; // Unknown
$type_list['X'] = 1; // Not Set
$by_day = array();
$by_class = array();
$last_seen = array();
$first_seen = array();
$top = array();
$names = array();
$dev_type = array();
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
        $dev_type[$mac] = isset($value['type']["S"]) ? $value['type']["S"] : 'X';
        $type_list[$dev_type[$mac]] = 1;
        $last_seen[$mac] = 0;
        $first_seen[$mac] = 0;
                
                
        // Manipulate the dates a bit
        $seen = array_merge($value['scan_on']["NS"], $value['inq_on']["NS"]);
        $seen_count = 0;
        foreach ($seen as $i => $v) {
            $seen_count++;
            if ($v > 1) {
                
                // Keep track of ones we've seen in last hour
                if ($v > (time() - 3600)) {
                    if (isset($last_hour[$mac])) {$last_hour[$mac]++;}
                    else {$last_hour[$mac] = 1;}
                }
                
                // put in EST
                $v = $v - (3600 * 5);
    
                $minute = strtotime(date("Y-m-d h:i a", $v));
                $hour = strtotime(date("1990-01-01 h:00 a", $v));
                $day = strtotime(date("Y-m-d", $v));
                $hourofday = date("H", $v);
                $dayofyear = date("z", $v);
                $dayofweek = date("N", $v);
                $dayofweek3 = date("N", $v);
    
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
                if ($last_seen[$mac] < $v || $last_seen[$mac] == 0) {$last_seen[$mac] = $v;}

                // First Seen
                if ($first_seen[$mac] > $v || $first_seen[$mac] == 0) {$first_seen[$mac] = $v;}

            }  
            
            // create an array to use in the bubble chart if not filters
            if ((in_array($dev_type[$mac], $type_f)) || empty($type_f)) {
                $top[$mac] = $seen_count;
            }
        }
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
$series = array();
foreach ($top as $mac => $mct) {
    // For each device get the top day it's been seen and the top date
    $dowtot = 0;
    $doytot = 0;
    if (isset($seen_dayofw[$mac])) {foreach ($seen_dayofw[$mac] as $dow => $dcnt) {$dowtot = $dowtot + ($dow * $dcnt);}}
    if (isset($seen_hours[$mac])) {foreach ($seen_hours[$mac] as $hrs => $dcnt) {$doytot = $doytot + ($hrs * $dcnt);}}
    $avg_dayofweek = round($dowtot/$mct,2);
    $avg_hr = round($doytot/$mct,2);
    if ($avg_dayofweek == 0) {$avg_dayofweek = 1;}
    if ($avg_hr == 0) {$avg_hr = 1;}
    
    $disp_hr = date("h:i a", round($avg_hr * 60 * 60));
    
    // Name series based on how recently these were seen
    $lsn = 0;
    if (isset($last_seen[$mac])) {if ($last_seen[$mac] > (time() - (3600*24*7))) {$lsn = strtotime(date("m/d/Y", $last_seen[$mac]));}}
    if (isset($series[$lsn])) { $series[$lsn] .= ", \n";} else {$series[$lsn] = '';}
    // Set an upper limit on the circle size
    $mctd = $mct;
    if ($mctd > 300) {$mctd = 300;}

    $series[$lsn] .= "{n: '". str_replace("'", "\'", $name[$mac]) 
            . "', m: '" . $mac 
            . "', l: '" . date("m/d/Y h:i a", $last_seen[$mac]) 
            . "', f: '" . date("m/d/Y h:i a", $first_seen[$mac]) 
            . "', d: '" . $day_names[(round($avg_dayofweek) - 1)]
            . "', h: '" . $disp_hr 
            . "', type: '" . $dev_type[$mac] 
            . "', t: " . $mct 
            . ", x: " . $avg_hr 
            . ", y: " . $avg_dayofweek
            . ", z: " . $mctd . "}";
}
// Build the type list for ajax setting
$b_types = '';
foreach ($type_list as $type => $val) {
    if ($b_types == '') { $b_types = "\t\t\t\t'(";} else {$b_types .= " | ' + \n \t\t\t\t'";}
    $b_types .= "<a onclick=\"set_type(\'" . $type . "\', \'' + this.point.m + '\');\">" . $type . "</a>";
}
$b_types .= ")' + \n";

// Build the series
krsort($series);
foreach ($series as $lsn => $lsn_data) {
    if ($b_data != '') {$b_data .= ", \n";}
    if ($lsn == 0) {$lsn = "More than 7 Days Ago";} else {$lsn = date("m/d/Y", $lsn);}
    $b_data .= "{ showInLegend: true, name: '". $lsn . "', data: [" . $lsn_data . "]}";
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
                var det = '   <a href="analyze_one.php?mac=' + encodeURIComponent(this.point.m) + '" target="_n">details</a>';
                return '<b>' + this.point.n + '</b><br>Seen: ' + this.point.t + ' times' +
                    <?php
                        echo($b_types);
                    ?>
                    ' <b>' + this.point.type + '</b>' +
                    '<br>Avg Hour: ' + this.point.h + ', Avg Day: ' + this.point.d +
                    '<br>MAC: ' + this.point.m + 
                    '<br>First Seen: <b>' + this.point.f +
                    '</b><br>Last Seen: <b>' + this.point.l + '</b>' + det;
            }
        },
        plotOptions: {
            bubble: {
                dataLabels: {
                    enabled: true,
                    style: { textShadow: 'none', color: '#000000' },
                    formatter: function() {
                        if (this.point.n == 'n/a' || this.point.type != 'U') {
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

function set_type(type, mac) {
    //alert('set type: ' + type + ' for mac: ' + mac);
    var url = "set_type.php?type=" + encodeURIComponent(type) + "&mac=" + encodeURIComponent(mac);
    var jqxhr = $.ajax( url )
      .done(function(data) {
        alert( data + 'set type: ' + type + ' for mac: ' + mac);
      })
      .fail(function() {
        //alert( "error" );
      })
      .always(function() {
        //alert( "complete" );
      });    
      
    return false;
    
}

</script>

<?php
//If there is no LastEvaluatedKey in the response, there are no more items matching this Scan invocation
echo "<hr><b>Key Facts:</b><table><tr><td>Total Seen</td><td>$count</td></tr>";
echo "<tr><td>Seen in Last Hour</td><td>" . count($last_hour) . "</td></tr>";
echo "</table><br>";


function ischecked($v, $c) {
    if (isset($c)) {
        if (is_array($c)) {
            foreach ($c as $i => $val) {
                if ($v == $val) {return ' checked ';}
            }
        }
    } else {
        if ($v == $c) {return ' checked ';}
    }
    return '';
}


?>
<form method="GET" action="index.php">
<b>Device Type Key</b><br>
<input type="hidden" name="bust" value="<?php echo time();?>"> 
<input type="checkbox" name="type[]" value="M" <?php echo ischecked('M', $type_f);?>> M = Mobile Phone<br>
<input type="checkbox" name="type[]" value="H" <?php echo ischecked('H', $type_f); ?>> H = Human<br>
<input type="checkbox" name="type[]" value="V" <?php echo ischecked('V', $type_f); ?>> V = Vehicle<br>
<input type="checkbox" name="type[]" value="A" <?php echo ischecked('A', $type_f); ?>> A = Apple Device<br>
<input type="checkbox" name="type[]" value="C" <?php echo ischecked('C', $type_f); ?>> C = Computer<br>
<input type="checkbox" name="type[]" value="G" <?php echo ischecked('G', $type_f); ?>> G = GPS<br>
<input type="checkbox" name="type[]" value="T" <?php echo ischecked('T', $type_f); ?>> T = TV Device<br>
<input type="checkbox" name="type[]" value="S" <?php echo ischecked('S', $type_f); ?>> S = Music Device<br>
<input type="checkbox" name="type[]" value="U" <?php echo ischecked('U', $type_f); ?>> U = Unknown<br>
<input type="checkbox" name="type[]" value="X" <?php echo ischecked('X', $type_f); ?>> X = Not Set<br>
<input type="submit" name="update" value="update">
</form>
</body>
</html>
