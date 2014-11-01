<?php
//
// A very simple PHP example that sends a HTTP POST to a remote site
//
$mac = '00:22:58';
$ch = curl_init();

<form action="/cgi-bin/ouisearch" class="bodycopy" method="post"><p><strong>Search the Public MA-L Listing</strong><br>
              Search for:
              <input name="x" size="30" type="text" value="">
              <input name="submit2" type="submit" value="Search!">
              <input name="reset" type="reset" value="clear field">
              </p></form>
              

curl_setopt($ch, CURLOPT_URL,"http://http://standards.ieee.org/cgi-bin/ouisearch");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS,
            "x=" . urlencode($mac) . "&submit2=" . urlencode('Search!'));

// receive server response ...
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,3);
curl_setopt($ch,CURLOPT_TIMEOUT, 20);
$server_output = curl_exec ($ch);
curl_close ($ch);

echo $server_output;
// further processing ....
//if ($server_output == "OK") { ... } else { ... }

?>


