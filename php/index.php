<?php
include 'functions.php';
include 'index_include.php';
?>

<html>
<head><title>BlueTrack</title>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<script src="http://code.highcharts.com/highcharts.js"></script>
<script src="http://code.highcharts.com/highcharts-more.js"></script>
<script src="http://code.highcharts.com/modules/exporting.js"></script>

<script src="../bootstrap/js/bootstrap.min.js"></script>
<link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">

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
					Total Devices: <strong><?php echo number_format($count); ?></strong><br>
					Total Scans: <strong><?php echo number_format($total_seen); ?></strong><br>
					Total Date Range: <strong><?php echo date("Y-m-d", $t_first_seen); ?></strong> to
					<strong><?php echo date("Y-m-d", $t_last_seen); ?></strong><br>
				</p>
				<p>
					Unique Devies in Last Hr: <strong><?php echo number_format(count($last_hour)); ?></strong><br>
					Displayed Devices: <strong><?php echo number_format($displayed_count); ?></strong><br>
					Displayed Date Range: <strong><?php echo date("Y-m-d", $t_first_disp); ?></strong> to
					<strong><?php echo date("Y-m-d", $t_last_disp); ?></strong><br>
				</p>
			</div>
			<div class="well bs-component">
				<legend>Collector Stats</legend>
				<p>
				<?php
					foreach ($collectors as $id => $v) {
						$tip = str_pad('Checkins: ' . number_format($v['collector_checkin_count']), 40, " ");
						$tip .= str_pad('Last Seen: ' . date("Y-m-d h:i a", $v['collector_last_checkin']), 40, " ");
						$tip .= str_pad('Region: ' . $v['collector_region_name'], 40, " ");
						$tip .= 'IP: ' . $v['collector_private_ip'];
						echo '<button type="button" class="btn btn-default" ';
						echo 'data-toggle="tooltip" data-placement="right" title="" ';
						echo 'data-original-title="';
						echo str_replace('  ', ' &#160;', $tip);
						echo '">' . $id . '</button><br>';
	
					}
				?>
				
				</p>
			</div>
		</div>
		<div class="col-md-4"><div id="byclass"></div></div>
	</div>
      
	<div class="row">
		<div id="bydevice" style="height: 600px;"></div>
	</div>
</div>

<?php
include 'graph_js.js';
?>

<script>
(function(){$('.bs-component [data-toggle="tooltip"]').tooltip();})();
</script>
</body>
</html>

