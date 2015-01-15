<?php
include 'functions.php';
include 'index_sql_include.php';
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
		<p class="lead">A system to analyze data from Bluetooth scans</p>
	</div>
      
	<div class="row">
		<div id="bydevice" style="height: 1600px;"></div>
	</div>
	
	<div class="row">
		<div class="col-lg-8">
			<div class="well bs-component">
				<form method="GET" action="index_sql.php" class="form-horizontal">
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
											 '> ' . $desc . '<br>';
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
							<div class="col-md-6">
								Between: <input type="text" id="datepickers" name="start_day" size="8" value="<?php echo $start_day_f;?>"> 
								and <input type="text" id="datepickere" name="end_day" size="8" value="<?php echo $end_day_f;?>">
							</div>
							<div class="col-md-6">
								Name Contains: <input type="text" name="name" size="18" value="<?php echo htmlentities($name_f);?>">
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-lg-12">
							<div class="col-md-6">
								<?php echo create_select('company_name', $company_name_select_list, $company_name_f, true, 4, "All Companies"); ?>
							</div>
							<div class="col-md-6">
								<?php echo create_select('col_id', $col_select_list, $col_id_f, true, 4, "All Collectors"); ?>
							</div>
						</div>
					</div>
					
					<div class="form-group">
						<div class="col-lg-12 col-lg-offset-2">
							<button type="submit" class="btn btn-primary">Submit</button>
						</div>
					</div>					
				</fieldset>
				</form>
			</div>
		</div>
		<div class="col-lg-4">
			<div class="well bs-component">
				<legend>Key Stats</legend>
				<p>
				</p>
				<p>
					Unique Devies in Last Hr: <strong><?php echo number_format(count(99999)); ?></strong><br>
					Displayed Date Range: <strong><?php echo date("m-d-Y", 456456); ?></strong> to
					<strong><?php echo date("m-d-Y", 345345); ?></strong><br>
				</p>
			</div>
			<div id="byclass"></div>
			<div class="well bs-component">
				<legend>Collector Stats</legend>
				<p>
				<?php
					foreach ($collectors as $id => $v) {
						$tip = str_pad('Checkins: ' . number_format($v['collector_checkin_count']), 40, " ");
						$tip .= str_pad('Last Seen: ' . date("m-d-Y h:i a", $v['collector_last_checkin']), 40, " ");
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
	</div>

</div>

<script>
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
                var det = '   <a href="analyze_one.php?mac=' + encodeURIComponent(this.point.m) + 
                          '&col=' + encodeURIComponent(this.point.w) + '" target="_n">details</a>';
                return '<b>' + this.point.n + '</b><br>Seen: ' + this.point.t + ' times' +
                    '<br>' + this.point.c + 
                    '<br>MAC: ' + this.point.m + 
                    '<br>MAC info: ' + this.point.i + 
                    '<br>Collectors: ' + this.point.w + 
                    '</b><br>Seen: <b>' + this.point.l + '</b>' + det;
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
                maxSize:15
                
            }
        },
        series: [<?php echo $b_data; ?>]
    });
});

</script>


<script>
(function(){$('.bs-component [data-toggle="tooltip"]').tooltip();})();
$(function() {$( "#datepickers" ).datepicker();});
$(function() {$( "#datepickere" ).datepicker();});
</script>
</body>
</html>
