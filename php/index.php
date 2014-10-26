<html>
<head>BlueTrack</head>
<body>
Hi There!<br>
<?php
echo "hi Steve!";

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
$aws = Aws::factory('/home/pi/BlueTrack/php/amazon_config.json');
$client = $aws->get('DynamoDb');



echo 'Made it here!<br>';

?>



</body>
</html>
