<?php
// Get the media we have stored on S3 and load it into a dynamoDB
// don't want to print debug through web server in general
$debug = false; 
if (!isset($_SERVER['HTTP_HOST'])) {
    $debug = true; 
} else {
    if (isset($_REQUEST['debug'])) {$debug = true;}
}

// Load my configuration
$datastring = file_get_contents('/usr/www/html/BlueTrack/master_config.json');
$config = json_decode($datastring, true);
if ($debug) {echo "datastring: $datastring\n";}
if ($debug) {var_dump($config);}
//Use MY SQL - this include assumes that $config has been loaded 
include '/usr/www/html/BlueTrack/php/my_sql.php';
// You'll need to edit this with your config
require '/usr/www/html/BlueTrack/vendor/autoload.php';
use Aws\Common\Aws;
$aws = Aws::factory('/usr/www/html/BlueTrack/php/amazon_config.json');

// Build the devices table schema on the fly
$sql = 'CREATE TABLE IF NOT EXISTS devices ('
. ' id INTEGER AUTO_INCREMENT UNIQUE KEY, '
. ' mac_id varchar(32) NOT NULL, '
. ' mac_root varchar(32) NOT NULL, '
. ' class varchar(32) NOT NULL, '
. ' name varchar(512) NOT NULL, '
. ' type varchar(32) NOT NULL, '
. ' PRIMARY KEY (mac_id), '
. ' INDEX(id), INDEX(mac_root));';
if (!$mysqli->query($sql)) {die("Table creation failed: (" . $mysqli->errno . ") " . $mysqli->error);}
if ($debug) {echo 'devices table Exists'. "\n";}

// Build the devices table schema on the fly
$sql = 'CREATE TABLE IF NOT EXISTS device_scans ('
. ' id INTEGER AUTO_INCREMENT UNIQUE KEY, '
. ' mac_id varchar(32) NOT NULL, '
. ' device_id varchar(32) NOT NULL, '
. ' seen int NOT NULL, '
. ' PRIMARY KEY (mac_id, device_id, seen), '
. ' INDEX(seen), INDEX(device_id));';
if (!$mysqli->query($sql)) {die("Table creation failed: (" . $mysqli->errno . ") " . $mysqli->error);}
if ($debug) {echo 'device_scans table Exists'. "\n";}




// connect to S3 and get a list of files we are storeing
// Unknown: What is the pratical upper limit to # of files, hoping it is like 1M
$s3_client = $aws->get('s3');
// Set the bucket for where media is stored and retrive all objects
// this is what could get to be a big list
$bucket = $config['ec2_image_bucket'];
$media_iterator = $s3_client->getIterator('ListObjects', array(
    'Bucket' => $bucket
    //,'Prefix' => 'Dec-2005'  // this will filter to specific matches
));
// Loop through files and sync to our local index
// TODO: Would be awesome if we could store checksums on each file, then path would not matter...
//       might have to do that as we are sending files since we don't have them now, but something to look into
//TODO: See what is returned in the $s3_item collection
$time = time();
$cnt = 0;
foreach ($media_iterator as $s3_item) {
	// Don't load anything larger than 1GB
	if ($s3_item['Size'] < 1000000000) {
		$file_path = trim($s3_item['Key']);
		// don't bother storing folder names
		if (substr($file_path, -1) == '/') {$file_path = '';}
		
		if ($file_path != '') {
			$media_type = "";
			$f_ext = strtolower(substr($file_path, -3));
			$f_ext_4 = strtolower(substr($file_path, -4));
			if ($debug) {echo "Extension: " . $f_ext . "\n";}
			if ($f_ext == 'gif') {
				$media_type = "image/gif";
			} elseif ($f_ext == 'jpg' || $f_ext_4 == 'jpeg') {
				$media_type = "image/jpeg";
			} elseif ($f_ext == 'mov') {
				$media_type = "movie/quicktime";
			} elseif ($f_ext_4 == 'mpeg') {
				$media_type = "movie/mpeg";
			} elseif ($f_ext == 'mp4') {
				$media_type = "movie/mp4";
			} elseif ($f_ext == 'cmf') {
				$media_type = "application/screen.comopound.movie";
			} elseif ($f_ext == 'png') {
				$media_type = "image/png";
			}
			
			// only store the files we care about
			if ($media_type != '') {
				$sql = 'INSERT IGNORE INTO media_files (media_path, media_type, media_size, last_sync, rnd_id, shown) VALUES ('
					. sqlq($s3_item['Key'],0) . ','
					. sqlq($media_type,0) . ','
					. sqlq($s3_item['Size'],0) . ','
					. sqlq($time,1) . ','
					. '(FLOOR( 1 + RAND( ) *6000000 )), 0) ON DUPLICATE KEY UPDATE last_sync=' . sqlq($time,1) . ';';
				if ($debug) {echo "Running: $sql\n";}
				if (!$mysqli->query($sql)) {die("Insert Failed: (" . $mysqli->errno . ") " . $mysqli->error);}
				$cnt = $cnt + 1;
			}
		}
	} else {
		if ($debug) {echo "File > 1GB: " . $s3_item['Key'] . " Size: " . $s3_item['Size'] . "\n";}
		
	}
}
// Now cleanup provided we did find some files
if ($cnt > 100) {
	$sql = 'DELETE FROM media_files WHERE last_sync <> ' . sqlq($time,1) . ';';
	if ($debug) {echo "Running: $sql\n";}
	if (!$mysqli->query($sql)) {die("Delete Failed: (" . $mysqli->errno . ") " . $mysqli->error);}
}
?>
