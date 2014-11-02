<?php
include 'functions.php';
include 'index_include.php';

//Using bootstrap css and library from here: http://bootswatch.com/paper/

?>

<html>
<head><title>BlueTrack</title>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<script src="//code.jquery.com/ui/1.11.2/jquery-ui.js"></script>
<script src="http://code.highcharts.com/highcharts.js"></script>
<script src="http://code.highcharts.com/highcharts-more.js"></script>
<script src="http://code.highcharts.com/modules/exporting.js"></script>

<script src="../bootstrap/js/bootstrap.min.js"></script>
<link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="//code.jquery.com/ui/1.11.2/themes/smoothness/jquery-ui.css">

<meta charset="UTF-8"></head>
<body>
<div class="container">
	<div class="page-header">
		<h1>BlueTrack - Analyze</h1>
		<p class="lead">A system to analyze data we've collected with a Bluetooth scanner into a Dynamo DB</p>
	</div>
	
	<div class="row">
		<div class="col-md-4">
			<div class="well bs-component">
				<form method="GET" action="index.php" class="form-horizontal">
				<input type="hidden" name="bust" value="<?php echo time();?>"> 
				<fieldset>
					<legend>Filter Devices</legend>
					<div class="form-group">
						<div class="col-lg-12">
							<?php 
							$c = 1;
							$cl1 = '';
							$cl2 = '';
							foreach ($type_desc as $type => $desc) {
								$col = '<input type="checkbox" name="type[]" value="' . $type .
											 '" ' . ischecked($type, $type_f) .
											 '> ' . $type . ' = ' . $desc . '<br>';
								if ($c % 2 == 0) {$cl2 .= $col;}
								else {$cl1 .= $col;}
								$c++;
							}
							?>
							<div class="col-md-6"><?php echo $cl1;?></div>
							<div class="col-md-6"><?php echo $cl2;?></div>
						</div>
					</div>

					<div class="form-group">
						<div class="col-lg-12">
							Between: <input type="text" id="datepicker" name="start_day" size="8" value="<?php echo $day_count_f;?>"> 
							and <input type="text" id="datepicker" name="start_day" size="8" value="<?php echo $day_count_f;?>"> <br>
							Name Contains:&#160;&#160;&#160;&#160;&#160; <input type="text" name="name" size="18" value=""><br>
							Mac Info Contains: <input type="text" name="man_info" size="18" value=""><br>
							From Collector: &#160;&#160;&#160;&#160;&#160;&#160;<?php echo create_select('col_id', $col_select_list, $col_id_f, false, 0); ?><br>
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
$(function() {$( "#datepicker" ).datepicker();});

</script>
</body>
</html>

