<?php echo '<'.'?'.'xml version="1.0" encoding="us-ascii"?'.'>
'; ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
  <meta http-equiv="content-type" content="text/html; charset=us-ascii" />
  <title>KT Log Parser: Match Stats</title>
  <style type="text/css">
  	body { font-family: Tahoma, Arial, sans-serif;
	  background-image: url('shamby.jpg');
	  background-repeat: no-repeat;
	  background-position: left top; }
  	td { text-align: center; }
	h1, h2	{ text-align: center; }

  	table.teams { margin-left: auto; margin-right: auto; }
  	table.teams td em { font-size: 1.5em; font-weight: bold; font-style: normal; }
  	table.teams td.frags { font-size: 3em; }
  	table.teams td.teams { font-size: 4em; }
	
  	table.players { margin-left: auto; margin-right: auto; }
  	table.players thead td { background-color: #400; color: #fff; cursor: hand; }
  	table.players thead td:hover { background-color: #ddf; color: #000; }
  	table.players tbody tr.t1 { background-color: #eee; }
  </style>
  <script src="sorttable.js" type="text/javascript"></script>
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
