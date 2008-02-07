<?php
	require "ktlogpsr.php";
	
	$parser = new KTLogParser;
	$r = $parser->Parse("a.log");
	
	if (is_null($r)) {
		echo "Error: ".$parser->ErrorDesc();
	}
	else {
		echo "<pre><code>";
		print_r($r);
		echo "</code></pre>";
	}
?>
