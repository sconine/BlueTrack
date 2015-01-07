<?php
// Connect to local MySQL database
$mysqli = new mysqli($config['mysql']['host'], $config['mysql']['user'], $config['mysql']['password'], $config['mysql']['database']);
if ($mysqli->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
	die;
}
if ($debug) {
	echo $mysqli->host_info . "\n";
	echo 'Connected to MySQL'. "\n";
}

// We'll make sure all the tables we are expecting exist
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

// Build the class_description table schema on the fly
// stores the description of what a class ID stands for in english
$sql = 'CREATE TABLE IF NOT EXISTS class_description ('
. ' id INTEGER AUTO_INCREMENT UNIQUE KEY, '
. ' class varchar(32) NOT NULL, '
. ' short varchar(64) NOT NULL, '
. ' long varchar(512) NOT NULL, '
. ' PRIMARY KEY (class);';
if (!$mysqli->query($sql)) {die("Table creation failed: (" . $mysqli->errno . ") " . $mysqli->error);}
if ($debug) {echo 'class_description table Exists'. "\n";}

// Build the device_scans table schema on the fly
$sql = 'CREATE TABLE IF NOT EXISTS device_scans ('
. ' id INTEGER AUTO_INCREMENT UNIQUE KEY, '
. ' mac_id varchar(32) NOT NULL, '
. ' device_id varchar(32) NOT NULL, '
. ' seen int NOT NULL, '
. ' PRIMARY KEY (mac_id, device_id, seen), '
. ' INDEX(seen), INDEX(device_id));';
if (!$mysqli->query($sql)) {die("Table creation failed: (" . $mysqli->errno . ") " . $mysqli->error);}
if ($debug) {echo 'device_scans table Exists'. "\n";}

// Build the manufacturers table schema on the fly
$sql = 'CREATE TABLE IF NOT EXISTS manufacturers ('
. ' manu_id INTEGER AUTO_INCREMENT UNIQUE KEY, '
. ' company_name varchar(255) NULL, '
. ' address varchar(255) NULL, '
. ' city varchar(64) NULL, '
. ' country varchar(128) NULL, '
. ' state varchar(64) NULL, '
. ' zip varchar(32) NULL, '
. ' PRIMARY KEY (manu_id));';
if (!$mysqli->query($sql)) {die("Table creation failed: (" . $mysqli->errno . ") " . $mysqli->error);}
if ($debug) {echo 'manufacturers table Exists'. "\n";}

// Build the mac_roots table schema on the fly
$sql = 'CREATE TABLE IF NOT EXISTS mac_roots ('
. ' manu_id INTEGER NOT NULL, '
. ' mac_root varchar(32) NOT NULL, '
. ' PRIMARY KEY (manu_id, mac_root);';
if (!$mysqli->query($sql)) {die("Table creation failed: (" . $mysqli->errno . ") " . $mysqli->error);}
if ($debug) {echo 'mac_roots table Exists'. "\n";}



// Query helper functions - TODO: pull these into a common function file
function sqlq($var, $var_type) {
  if ($var_type == 1) {
    if (is_numeric($var) && !empty($var)) {
      return $var;
    } 
  } else {
    if (!empty($var)) {
      $var = str_replace("'", "''", $var);
      return "'" . $var . "'";
    }
  }
  return 'NULL';
}
function query_to_array($sql, &$mysqli) {
  global $debug;
  $to_ret = array();
  if ($debug) {echo "Running: $sql \n";}
  $result = $mysqli->query($sql);
  while ($row = $result->fetch_assoc()) {
      $to_ret[] = $row;
  }
  return $to_ret;
}
?>
