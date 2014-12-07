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
