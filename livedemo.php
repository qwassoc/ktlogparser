<?php
	require "ktlogpsr.php";
	
	if (!$_FILES["testfile"]["size"]) {
		echo "File upload failed";
		die;
	}
	
	$key = date("U").$_SERVER["REMOTE_ADDR"].rand(0,1000);
	$key = substr(sha1($key),0,10);
	$file = getcwd()."/logs/".$key.".log";
	move_uploaded_file($_FILES["testfile"]["tmp_name"], $file);
	
	header("Location: view.php?log={$key}");
	header("Connection: close");
	die;
?>
