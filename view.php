<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
  <meta http-equiv="content-type" content="text/html; charset=ascii">
  <title></title>
  <style type="text/css">
  	body { font-family: Tahoma, Arial, sans-serif; }
  	td { text-align: center; }
  	table.teams td em { font-size: 1.5em; font-weight: bold; font-style: normal; }
  	table.teams td.frags { font-size: 3em; }
  	table.teams td.teams { font-size: 4em; }
  	table.players thead td { background-color: #400; color: #fff; cursor: hand; }
  	table.players thead td:hover { background-color: #ddf; color: #000; }
  	table.players tbody tr.t1 { background-color: #eee; }
  </style>
  <script src="sorttable.js"></script>
  </head>
  <body>

<?php
	require "ktlogpsr.php";
	
	$file = $_REQUEST["log"];
	if (!preg_match("/^[a-z0-9]+$/",$file)) {
		echo "Invalid log specified.";
		die;
	}
	$file = "logs/{$file}.log";
	if (!file_exists($file)) {
		echo "Invalid log specified.";
		die;
	}
		
	$parser = new KTLogParser;
	$r = $parser->Parse($file);
	
	if (is_null($r)) {
		echo "Error: ".$parser->ErrorDesc();
	}
	else {
		$v = new KTLP_Visualizer;
		
		echo $v->GetHTML($r);
		//echo "<pre><code>";
		//print_r($r);
		//echo "</code></pre>";
	}
?>

  </body>
</html>
<?php

?>
