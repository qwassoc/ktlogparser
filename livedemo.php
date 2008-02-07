<?php
	require "ktlogpsr.php";
	
	if (!$_FILES["testfile"]["size"]) {
		echo "File upload failed";
		die;
	}
	
	$parser = new KTLogParser;
	$r = $parser->Parse($_FILES["testfile"]["tmp_name"]);
	
	if (is_null($r)) {
		echo "Error: ".$parser->ErrorDesc();
	}
	else {
		echo "<pre><code>";
		print_r($r);
		echo "</code></pre>";
	}
?>
