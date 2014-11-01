
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
                    '<br>' + this.point.c + 
                    '<br>MAC: ' + this.point.m + 
                    '<br>MAC info: ' + this.point.i + 
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
