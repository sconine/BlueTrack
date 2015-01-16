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
<script src="http://code.highcharts.com/modules/data.js"></script>
<script src="http://code.highcharts.com/modules/heatmap.js"></script>

<script src="../bootstrap/js/bootstrap.min.js"></script>
<link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="//code.jquery.com/ui/1.11.2/themes/smoothness/jquery-ui.css">
<style type="text/css">
.highcharts-tooltip>span {
	background: rgba(255,255,255,0.85);
	border: 1px solid silver;
	border-radius: 3px;
	box-shadow: 1px 1px 2px #888;
	padding: 8px;
	z-index: 2;
}
</style>
<meta charset="UTF-8"></head>
<body>
<div class="container">
	<div class="page-header">
		<h1>BlueTrack - Analyze</h1>
		<p class="lead">A system to analyze data from Bluetooth scans</p>
	</div>

	<div class="row">
		<div id="bydevice" style="height: 320px; width: 1000px; margin: 0 auto"></div>
	</div>
<pre id="csv" style="display: none">Date,Time,Temperature
2013-01-01,0,1.3
2013-01-01,1,1.4
2013-01-01,2,1.6
2013-01-01,3,2.0
2013-01-01,4,2.4
2013-01-01,5,2.9
2013-01-01,6,3.1
</pre>

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

    /**
     * This plugin extends Highcharts in two ways:
     * - Use HTML5 canvas instead of SVG for rendering of the heatmap squares. Canvas
     *   outperforms SVG when it comes to thousands of single shapes.
     * - Add a K-D-tree to find the nearest point on mouse move. Since we no longer have SVG shapes
     *   to capture mouseovers, we need another way of detecting hover points for the tooltip.
     */
    (function (H) {
        var wrap = H.wrap,
            seriesTypes = H.seriesTypes;

        /**
         * Recursively builds a K-D-tree
         */
        function KDTree(points, depth) {
            var axis, median, length = points && points.length;

            if (length) {

                // alternate between the axis
                axis = ['plotX', 'plotY'][depth % 2];

                // sort point array
                points.sort(function (a, b) {
                    return a[axis] - b[axis];
                });

                median = Math.floor(length / 2);

                // build and return node
                return {
                    point: points[median],
                    left: KDTree(points.slice(0, median), depth + 1),
                    right: KDTree(points.slice(median + 1), depth + 1)
                };

            }
        }

        /**
         * Recursively searches for the nearest neighbour using the given K-D-tree
         */
        function nearest(search, tree, depth) {
            var point = tree.point,
                axis = ['plotX', 'plotY'][depth % 2],
                tdist,
                sideA,
                sideB,
                ret = point,
                nPoint1,
                nPoint2;

            // Get distance
            point.dist = Math.pow(search.plotX - point.plotX, 2) +
                Math.pow(search.plotY - point.plotY, 2);

            // Pick side based on distance to splitting point
            tdist = search[axis] - point[axis];
            sideA = tdist < 0 ? 'left' : 'right';

            // End of tree
            if (tree[sideA]) {
                nPoint1 = nearest(search, tree[sideA], depth + 1);

                ret = (nPoint1.dist < ret.dist ? nPoint1 : point);

                sideB = tdist < 0 ? 'right' : 'left';
                if (tree[sideB]) {
                    // compare distance to current best to splitting point to decide wether to check side B or not
                    if (Math.abs(tdist) < ret.dist) {
                        nPoint2 = nearest(search, tree[sideB], depth + 1);
                        ret = (nPoint2.dist < ret.dist ? nPoint2 : ret);
                    }
                }
            }
            return ret;
        }

        // Extend the heatmap to use the K-D-tree to search for nearest points
        H.seriesTypes.heatmap.prototype.setTooltipPoints = function () {
            var series = this;

            this.tree = null;
            setTimeout(function () {
                series.tree = KDTree(series.points, 0);
            });
        };
        H.seriesTypes.heatmap.prototype.getNearest = function (search) {
            if (this.tree) {
                return nearest(search, this.tree, 0);
            }
        };

        H.wrap(H.Pointer.prototype, 'runPointActions', function (proceed, e) {
            var chart = this.chart;
            proceed.call(this, e);

            // Draw independent tooltips
            H.each(chart.series, function (series) {
                var point;
                if (series.getNearest) {
                    point = series.getNearest({
                        plotX: e.chartX - chart.plotLeft,
                        plotY: e.chartY - chart.plotTop
                    });
                    if (point) {
                        point.onMouseOver(e);
                    }
                }
            })
        });

        /**
         * Get the canvas context for a series
         */
        H.Series.prototype.getContext = function () {
            var canvas;
            if (!this.ctx) {
                canvas = document.createElement('canvas');
                canvas.setAttribute('width', this.chart.plotWidth);
                canvas.setAttribute('height', this.chart.plotHeight);
                canvas.style.position = 'absolute';
                canvas.style.left = this.group.translateX + 'px';
                canvas.style.top = this.group.translateY + 'px';
                canvas.style.zIndex = 0;
                canvas.style.cursor = 'crosshair';
                this.chart.container.appendChild(canvas);
                if (canvas.getContext) {
                    this.ctx = canvas.getContext('2d');
                }
            }
            return this.ctx;
        }

        /**
         * Wrap the drawPoints method to draw the points in canvas instead of the slower SVG,
         * that requires one shape each point.
         */
        H.wrap(H.seriesTypes.heatmap.prototype, 'drawPoints', function (proceed) {

            var ctx;
            if (this.chart.renderer.forExport) {
                // Run SVG shapes
                proceed.call(this);

            } else {

                if (ctx = this.getContext()) {

                    // draw the columns
                    H.each(this.points, function (point) {
                        var plotY = point.plotY,
                            shapeArgs;

                        if (plotY !== undefined && !isNaN(plotY) && point.y !== null) {
                            shapeArgs = point.shapeArgs;

                            ctx.fillStyle = point.pointAttr[''].fill;
                            ctx.fillRect(shapeArgs.x, shapeArgs.y, shapeArgs.width, shapeArgs.height);
                        }
                    });

                } else {
                    this.chart.showLoading("Your browser doesn't support HTML5 canvas, <br>please use a modern browser");

                    // Uncomment this to provide low-level (slow) support in oldIE. It will cause script errors on
                    // charts with more than a few thousand points.
                    //proceed.call(this);
                }
            }
        });
    }(Highcharts));


    var start;
    $('#bydevice').highcharts({

        data: {
            csv: document.getElementById('csv').innerHTML,
            parsed: function () {
                start = +new Date();
            }
        },

        chart: {
            type: 'heatmap',
            margin: [60, 10, 80, 50]
        },


        title: {
            text: 'Highcharts extended heat map',
            align: 'left',
            x: 40
        },

        subtitle: {
            text: 'Temperature variation by day and hour through 2013',
            align: 'left',
            x: 40
        },

        tooltip: {
            backgroundColor: null,
            borderWidth: 0,
            distance: 10,
            shadow: false,
            useHTML: true,
            style: {
                padding: 0,
                color: 'black'
            }
        },

        xAxis: {
            min: Date.UTC(2013, 0, 1),
            max: Date.UTC(2014, 0, 1),
            labels: {
                align: 'left',
                x: 5,
                format: '{value:%B}' // long month
            },
            showLastLabel: false,
            tickLength: 16
        },

        yAxis: {
            title: {
                text: null
            },
            labels: {
                format: '{value}:00'
            },
            minPadding: 0,
            maxPadding: 0,
            startOnTick: false,
            endOnTick: false,
            tickPositions: [0, 6, 12, 18, 24],
            tickWidth: 1,
            min: 0,
            max: 23,
            reversed: true
        },

        colorAxis: {
            stops: [
                [0, '#3060cf'],
                [0.5, '#fffbbc'],
                [0.9, '#c4463a'],
                [1, '#c4463a']
            ],
            min: -15,
            max: 25,
            startOnTick: false,
            endOnTick: false,
            labels: {
                format: '{value}℃'
            }
        },

        series: [{
            borderWidth: 0,
            nullColor: '#EFEFEF',
            colsize: 24 * 36e5, // one day
            tooltip: {
                headerFormat: 'Temperature<br/>',
                pointFormat: '{point.x:%e %b, %Y} {point.y}:00: <b>{point.value} ℃</b>'
            },
            turboThreshold: Number.MAX_VALUE // #3404, remove after 4.0.5 release
        }]

    });
    console.log('Rendered in ' + (new Date() - start) + ' ms');

});



</script>


<script>
(function(){$('.bs-component [data-toggle="tooltip"]').tooltip();})();
$(function() {$( "#datepickers" ).datepicker();});
$(function() {$( "#datepickere" ).datepicker();});
</script>
</body>
</html>
