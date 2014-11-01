<?php
//
// A very simple PHP example that sends a HTTP POST to a remote site
//
$mac = '00-22-58';
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL,"http://standards.ieee.org/cgi-bin/ouisearch");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS,
            "x=" . urlencode($mac) . "&submit2=" . urlencode('Search!'));

// receive server response ...
curl_setopt($ch, CURLOPT_FAILONERROR, true); 
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,3);
curl_setopt($ch,CURLOPT_TIMEOUT, 20);
$server_output = curl_exec ($ch);
curl_close ($ch);


echo ' Hi Max!!!<br>';
echo htmlentities($server_output);
// further processing ....
//if ($server_output == "OK") { ... } else { ... }

?>


