<?php
//
// A very simple PHP example that sends a HTTP POST to a remote site
//
$mac = 'C8-F7-33';
if(!empty($_REQUEST['mac'])) {$mac = $_REQUEST['mac'];}
// Make sure they look safe
$pattern = '/^[a-zA-ZvV0-9,:]+$/';
if (preg_match($pattern, $mac) == 0) {$mac = 'C8-F7-33';}
else {
            $mac = str_replace(':', '-', $mac);
            $mac = substr(mac, 0, 8);
}
echo "mac = $mac <br>\n";

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

$start = strpos(strtolower($server_output), '<pre>');
$end = strpos(strtolower($server_output), '</pre>', $start);
$data = $server_output;

if ($start > 10 && $end > $start) {
            $data = substr($server_output, $start + 5, $end - $start - 6);
            $start = strpos(strtolower($data), '</b>');
            if ($start > 1 && $end > $start) {
                        $data = substr($data, $start + 4);
            }
            $data = str_replace('(hex)', '', $data);
            $data = str_replace('(base 16)', '', $data);
            $cc = str_replace('-', '', $mac);
            $data = str_replace($cc, '', $data);
            $data = preg_replace("/[ \t]{2,}/", "", $data);
} 

echo "start: $start <br>\n";
echo "end: $end <br><hr><hr>\n";
//echo "server_output: $server_output <hr><hr><hr>\n";
echo '<pre>' . $data . '</pre>';
// further processing ....
//if ($server_output == "OK") { ... } else { ... }

?>


