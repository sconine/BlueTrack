<?php
include 'functions.php';
include 'index_include.php';
?>

<html>
<head><title>BlueTrack</title>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
<script src="http://code.highcharts.com/highcharts.js"></script>
<script src="http://code.highcharts.com/highcharts-more.js"></script>
<script src="http://code.highcharts.com/modules/exporting.js"></script>

<link rel="stylesheet" href="../css/bootstrap.min.css">
<meta charset="UTF-8"></head>
<body>
<div class="container">
	<div class="page-header">
		<h1>BlueTrack - Analyze</h1>
		<p class="lead">A system to analyse data we've collected with a Bluetooth scanner into a Dynamo DB</p>
	</div>
	
	<div class="row">
		<div class="col-md-4">Put for to filter graphs here</div>
		<div class="col-md-4">Put info about collectors here</div>
		<div class="col-md-4"><div id="byclass"></div></div>
	</div>
      
	<div class="row">
		<div id="bydevice" style="height: 400px;"></div>
	</div>
</div>

<?php
include 'graph_js.js';

//If there is no LastEvaluatedKey in the response, there are no more items matching this Scan invocation
echo "<hr><b>Key Facts:</b><table><tr><td>Total Seen</td><td>$count</td></tr>";
echo "<tr><td>Seen in Last Hour</td><td>" . count($last_hour) . "</td></tr>";
echo "</table><br>";
?>

<form method="GET" action="index.php">
<b>Device Type Key</b><br>
<input type="hidden" name="bust" value="<?php echo time();?>"> 
<input type="checkbox" name="multi_day" value="d" <?php echo checkit($multi_day_f);?>> Show Multi Day Devices Only
<input type="text" name="day_count" size="4" value="<?php echo $day_count_f;?>"> Min Days seen<br>
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




