<?php
	require "../src/ktlogpsr.php";
	
	if (!$_FILES["testfile"]["size"]) {
		echo "File upload failed";
		die;
	}

	$file = $_FILES["testfile"]["tmp_name"];
	$type = $_POST['type'];

	$parser = new KTLogParser();
	$data = $parser->Parse($file, "../src/fragfile.dat");

	switch ($type) {
		case "html":
			header("Content-type: text/html");
			include "view.htm";
			$visualiser = new KTLP_Visualizer();
			echo $visualiser->GetHtml($data);
			break;

		case "php":
			header("Content-type: text/plain");
			var_export($data);
			break;

		case "xml":
			header("Content-type: application/xml");
			echo $parser->GetXML();
			break;

		case "json":
			header("Content-type: application/json");
			echo $parser->GetJSON();
			break;

		default:
			echo "Invalid type";
			break;
	}
?>
