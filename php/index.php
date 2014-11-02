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
		<div class="col-md-4">
			<div class="well bs-component">
				<form method="GET" action="index.php" class="form-horizontal">
				<input type="hidden" name="bust" value="<?php echo time();?>"> 
				<fieldset>
					<legend>Filter Devices</legend>
					<div class="form-group">
						<div class="col-lg-10">
							<input type="checkbox" name="multi_day" value="d" <?php echo checkit($multi_day_f);?>> Show Devices Seen
							<input type="text" name="day_count" size="2" value="<?php echo $day_count_f;?>"> Days<br>
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
						</div>
					</div>
					<div class="form-group">
						<div class="col-lg-10 col-lg-offset-2">
							<button class="btn btn-default">Cancel</button>
							<button type="submit" class="btn btn-primary">Submit</button>
						</div>
					</div>					
				</fieldset>
				</form>
			</div>
		</div>
		<div class="col-md-4">
			<div class="well bs-component">
				<legend>Key Stats</legend>
				<p>
					Total Devices: <?php echo $count; ?><br>
					Total Scans: <?php echo $total_seen; ?><br>
					Total Date Range: <?php echo date("Y-m-d", $t_first_seen); ?> to
					<?php echo date("Y-m-d", $t_last_seen); ?><br>
				</p>
				<p>
					Unique Devies in Last Hr: <?php echo count($last_hour); ?><br>
					Displayed Devices: <?php echo $displayed_count; ?><br>
					Displayed Date Range: <?php echo date("Y-m-d", $t_first_disp); ?> to
					<?php echo date("Y-m-d", $t_last_disp); ?><br>
				</p>
			</div>
			<div class="well bs-component">
				<legend>Collector Stats</legend>
				<p>
					Total Seen: 60<br>
					Seen in last hour: 
				</p>
			</div>
		</div>
		<div class="col-md-4"><div id="byclass"></div></div>
	</div>
      
	<div class="row">
		<div id="bydevice" style="height: 400px;"></div>
	</div>
</div>

<?php
include 'graph_js.js';
?>


</body>
</html>

