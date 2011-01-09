<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

define ("TESTS_DIR", dirname(__FILE__)."/");
define ("PROJECT_ROOT", TESTS_DIR."../");
define ("DATA_DIR", PROJECT_ROOT."logs/");
define ("SRC_DIR", PROJECT_ROOT."src/");
require_once SRC_DIR."ktlogpsr.php";

class StopWatch {
	private $measuredTime;
	private $startTime;
	private $endTime;
	private $measurementOver;

	public function __construct() {
		$this->startTime = microtime(true);
		$this->measurementOver = false;
	}

	public function stop() {
		$this->endTime = microtime(true);
		$this->measuredTime = $this->endTime - $this->startTime;
		$this->measurementOver = true;
	}

	public function getTime() {
		if (!$this->measurementOver) {
			throw new Exception("Invalid state of the clock, measurement not finished yet.");
		}
		else {
			return $this->measuredTime;
		}
	}
}

function getFiles($directory)
{
	$dirReader = dir($directory);
	$res = array();

	while (false !== ($entry = $dirReader->read())) {
		if (strlen($entry) > 3 && $entry[0] != "." && substr($entry, -4) == ".log") {
			$res[] = $entry;
		}
	}

	return $res;
}

function main() {
	$files = getFiles(DATA_DIR);
	$f = fopen(TESTS_DIR."index.php", "wt");
	if ($f) {
		fwrite($f, "<ul>");
	}

	foreach ($files as $file) {
		$parser = new KTLogParser;

		echo "Parsing $file\n";
		$watch = new StopWatch;
		$parseData = $parser->Parse(DATA_DIR.$file, SRC_DIR."fragfile.dat");
		$watch->stop();
		echo "Finished in ".$watch->getTime()."\n";

		$visualiser = new KTLP_Visualizer();

		file_put_contents(DATA_DIR.$file.".parsed", var_export($parseData, true));
		file_put_contents(DATA_DIR.$file.".html", $visualiser->GetHtml($parseData));
		file_put_contents(DATA_DIR.$file.".xml", $parser->GetXML());
		file_put_contents(DATA_DIR.$file.".json", $parser->GetJSON());

		if ($f) {
			fwrite($f, "<li><ul>");
			fwrite($f, "<li><a href='../logs/{$file}'>{$file}</a> ");
			fwrite($f, "<li><a href='../logs/{$file}.parsed'>{$file}.parsed</a> ");
			fwrite($f, "<li><a href='../logs/{$file}.html'>{$file}.html</a> ");
			fwrite($f, "<li><a href='../logs/{$file}.xml'>{$file}.xml</a> ");
			fwrite($f, "<li><a href='../logs/{$file}.json'>{$file}.json</a> ");
			fwrite($f, "</ul>");
		}

		echo "Found ".count($parseData["frags"])." frags\n";
	}

	if ($f) {
		fwrite($f, "</ul>");
		fclose($f);
	}
}

main();

?>
