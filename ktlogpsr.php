<?php

/// \file
///
/// \brief
/// KT* Log Parser
///
/// Parses logs generated by QuakeWorld server mods:
/// - Kombat Teams
/// - KTPro
/// - KTX

// set this to 0 to turn off debug messages, set to 1 to allow basic debug messages,
// higher value for more verbose debug messages
define (KTLP_DEBUG, 0);

define (KTLP_ERR_OK, 0);
define (KTLP_ERR_FILEOPEN, -1);

define (KTLP_ST_PREGAME, 0);
define (KTLP_ST_GAME, 1);
define (KTLP_ST_PLAYERS, 2);
define (KTLP_ST_MATCH, 3);
define (KTLP_ST_TEAMS, 4);
define (KTLP_ST_AFTERGAME, 5);

//$KTLP_OLD_ERROR_LVL = error_reporting(E_ALL+E_STRICT);

function KTLP_ChatLine($line) 
{
	return preg_match("/^\S+:/",trim($line));
}

function KTLP_ParseMultipleLine($line, $pattern)
{
	$matches = array();
	$val = array();
	
	if (preg_match_all($pattern,$line,$matches)) {
		for ($i=0; $i < count($matches[0]); $i++) {
			$val[$matches[1][$i]] = $matches[2][$i];
		}
		return $val;
	}
	else return NULL;
}

// parses line with format like this:
// rl62.3% sg35.6% ssg35.7%
function KTLP_ParseWpLine($line)
{
	$pattern = "/\s*(\D+)([0-9\.\%]+)\s*/";
	return KTLP_ParseMultipleLine($line, $pattern);
}

// parses line with format like this:
// ad:61.5 dh:15
function KTLP_ParseGeneralStatsLine($line)
{
	$pattern = "/\s*(\D+):(\S+)\s*/";
	return KTLP_ParseMultipleLine($line, $pattern);
}

function KTLP_SafeElementName($name)
{
	return strtr($name,"& !","-__");
}

// oonverts PHP array structure into JSON format
function PHPArrayToJSON($array)
{
	$out = "";
	if (is_array($array)) {
		$out .= "{";
		foreach ($array as $k => $v) {
			$out .= '"'.$k.'":';
			$out .= PHPArrayToJSON($v);
			$out .= ", ";
		}	
		$out .= "}";
	}
	else {
		$out = '"'.$array.'"';
	}
	
	return $out;
}

// converts PHP array structure into XML format
function PHPArrayToXML($array)
{
	$out = "";
	if (is_array($array)) {
		foreach ($array as $k => $v) {
			$eln = KTLP_SafeElementName($k);
			$out .= "<$eln>".PHPArrayToXML($v)."\n</$eln>\n";
		}
	}
	else {
		$out = $array;
	}
	
	return $out;
}

// base implementation of debugging, output buffer, lines count storage 
class KTLP_BasePartParser
{
	var $debug;
	var $result;
	var $lines;

	function DPrint($lev,$str) {
		if ($this->debug >= $lev) {
			echo "<p><code>".htmlspecialchars($str)."</code></p>";
		}
	}
	
	function KTLP_BasePartParser($dbg) {
		$this->result = array();
		$this->debug = $dbg;
		$this->lines = 0;
	}

	function GetResult()
	{
		return $this->result;
	}
}

class KTLP_TeamScoresParser extends KTLP_BasePartParser
{
	var $over;
	
	function KTLP_TeamScoresParser($dbg)
	{
		$this->KTLP_BasePartParser($dbg);
		$this->over = false;
	}
	
	function EatLine($line)
	{
		if ($this->over) return false;
		$this->lines++;
		$this->DPrint(1,"teamscores eating line {$this->lines}");
		
		$matches = array();
		
		if (preg_match("/^_+$/", $line)) {
			if ($this->lines != 1) {
				$this->over = true;
				return false;
			}
			else return true;
		}
		else if (preg_match("/^\[(.*)\]: (\S+) . (\S+)$/",$line,$matches)
			||   preg_match("/^\[(.*)\]: \S+ \+ \(.*\) = (\S+) . (\S+)$/",$line,$matches)) {
			$team = $matches[1];
			$frags = $matches[2];
			$percentage = $matches[3];
			$this->result[$team] = array ( "frags" => $frags, "percentage" => $percentage );
		}
		
		return true;
	}
}

class KTLP_MatchStatsParser extends KTLP_BasePartParser
{
	var $section;
	var $curteam;
	
	function KTLP_MatchStatsParser($dbg)
	{
		$this->KTLP_BasePartParser($dbg);
		$this->section = 0;
		$this->curteam = "";
	}
	
	function EatLine($line)
	{
		$this->lines++;
		$this->DPrint(1,"Match stats eating line {$this->lines}, section is {$this->section}");
		if (preg_match("/^_+$/",$line)) {
			$this->DPrint(1,"Matched separator");
			if ($this->section == 1) {
				$this->section = 2;
			}
			else if ($this->section == 0) {
			}
			else if ($this->section == 2) {
				$this->section = 0;	// end of detailed match statistics reached
				return false;
			}
		}
		else if (preg_match("/^$/", $line)) {
			$this->DPrint(1,"Matched blank line");
		}
		else if (preg_match("/^.*weapons.*powerups.*armors.*damage.*$/",$line)) {
			$this->section = 1;
			$this->DPrint(1,"Matched WPAD");
		}
		else if ($this->section == 2) {	// main content of match stats is in this section
			if (preg_match("/^\[(.*)\]: Wp:(.*)$/", $line, $matches)) {
				$this->curteam = $matches[1];
				$this->result[$this->curteam] = array();
				$this->result[$this->curteam]["wp"] = KTLP_ParseWpLine($matches[2]);
			}
			else if (preg_match("/^\s*(\S+):(.*)$/",$line,$matches)) {
				$this->result[$this->curteam][strtolower($matches[1])] = KTLP_ParseGeneralStatsLine($matches[2]);
			}
		}
		$this->DPrint(1,"Eating line done, section is {$this->section}");
		return true;
	}
}

class KTLP_PlayerStatsParser extends KTLP_BasePartParser
{
	var $curplayer;
	var $curteam;

	function KTLP_PlayerStatsParser($dbg)
	{
		$this->KTLP_BasePartParser($dbg);
		$this->curplayer = "";
		$this->curteam = "";
	}
	
	// Player stats
	function EatLine($line)
	{
		$this->lines++;
		$this->DPrint(1,"Player stats line: $line");
		if (preg_match("/^Frags \(rank\) (\S*) ?\. efficiency$/",$line)) { // the 3rd word here in teamplay matches is friendkills
			$this->DPrint(1,"Player stats intro line matched");
		}
		else {
			$matches = array();
			if (preg_match("/^Team \[(.*)\]:$/",$line,$matches)) {
				$this->DPrint(1,"Team $matches[1] matched");
				$this->curteam = $matches[1];
			}
			else if (preg_match("/^_+$/",$line)) {
				$this->curplayer = "";
			}
			else if (preg_match("/^_ (.*):$/",$line,$matches)) {
				$this->DPrint(1,"Player $matches[1] matched");
				$this->curplayer = $matches[1];
				$this->result[$this->curplayer] = array();
				$this->result[$this->curplayer]["team"] = $this->curteam;
			}
			else if (preg_match("/^$/",$line)) {
				// skip blank line
			}
			else if ($this->curplayer) {
				if (preg_match("/^(.*): (.*)$/",$line,$matches)) {
					$key = strtolower(trim($matches[1]));
					$val = trim($matches[2]);
					if ($key == "wp") {
						if ($t = KTLP_ParseWpLine($val)) {
							$val = $t;
						}
					}
					else if ($key == "spawnfrags") {
						if (preg_match("/(\d+)/",$line,$matches)) {
							$val = $matches[1];
						}
					}
					else {
						if ($t = KTLP_ParseGeneralStatsLine($val)) {
							$val = $t;
						}
					}
					$this->result[$this->curplayer][$key] = $val;
				}
				// 51 (5) 2 52.6%
				else if (preg_match("/^(\d+) \((\S+)\) (\S+) (.*)%$/",$line,$matches)) {
					$this->result[$this->curplayer]["frags"] = $matches[1];
					$this->result[$this->curplayer]["rank"] = $matches[2];
					$this->result[$this->curplayer]["friendkills"] = $matches[3];
					$this->result[$this->curplayer]["efficiency"] = $matches[4];
				}
				else {
					$this->result[$this->curplayer][] = $line;
				}
			}
		}
	}

}

class KTLP_Parser
{
	var $parsestate;
	var $output;
	var $err;
	var $debug;
	var $PlayerStatsParser;
	var $TeamScoresParser;
	var $MatchStatsParser;
	
	function KTLP_Parser($dbg)
	{
		$this->parsestate = KTLP_ST_PREGAME;
		$this->err = KTLP_ERR_OK;
		$this->output = array();
		$this->output["general"] = array();
		$this->output["chat"] = array( "pre-game" => "", "after-game" => "" );
		$this->debug = $dbg;
		$this->PlayerStatsParser = new KTLP_PlayerStatsParser($dbg);
		$this->TeamScoresParser = new KTLP_TeamScoresParser($dbg);
		$this->MatchStatsParser = new KTLP_MatchStatsParser($dbg);
	}
	
	function DPrint($lev,$str) {
		if ($this->debug >= $lev) {
			echo "<p><code>".htmlspecialchars($str)."</code></p>";
		}
	}
	
	function EatLinePreGame($line) {
		if (KTLP_ChatLine($line)) {
			$this->output["chat"]["pre-game"] .= $line . "\n";
		}
	} 
	
	function EatLineAfterGame($line) {
		if (KTLP_ChatLine($line)) {
			$this->output["chat"]["after-game"] .= $line ."\n";
		}
	}
	
	// not implemented,
	// add parsing of frag messages, counting of mm2 messages, ... what else?
	function EatLineGame($line) {}
		
	// not implemented,
	function EatLineMatch($line) {}
	
	// not implemented
	function EatLineTeams($line) {
	
	}
	
	function EatLine($line)
	{
		$matches = array();
		
		$this->DPrint(1,"Eating line ".$line);
		if (preg_match("/^The (\S+) has begun!$/",$line)) {
			$this->parsestate = KTLP_ST_GAME;
			$this->DPrint(1,"Match begin matched");
		}
		else if (preg_match("/^Player statistics:$/",$line)) {
			$this->parsestate = KTLP_ST_PLAYERS;
			$this->DPrint(1,"Player statistics matched");
		}
		else if (preg_match("/^\[(.*)\] vs \[(.*)\] match statistics:$/",$line)) {
			$this->parsestate = KTLP_ST_MATCH;
			$this->DPrint(1,"Match statistics matched");
		}
		else if (preg_match("/^Team scores:/",$line)) {
			$this->parsestate = KTLP_ST_TEAMS;
			$this->DPrint(1,"Team scores matched");
		}
		else if (preg_match("/^The match is over/",$line)) {
			$this->parsestate = KTLP_ST_PREGAME;
			$this->DPrint(1,"Match end matched");		
		}
		else if (preg_match("/^matchdate: (.*)$/", $line, $matches)
				|| preg_match("/^matchkey: (.*)$/", $line, $matches)) {
			$this->output["general"]["date"] = $matches[1];
			$this->DPrint(1,"Matchdate matched");
		}
		else if (preg_match("/^\[(.*)\] top scorer?s:$/",$line, $matches)) {
			$this->output["general"]["map"] = $matches[1];
			$this->DPrint(1,"Map matched");
		}
		else {
			switch ($this->parsestate) {
			case KTLP_ST_PREGAME: $this->EatLinePreGame($line); break;
			case KTLP_ST_GAME: $this->EatLineGame($line); break;
			case KTLP_ST_PLAYERS: $this->PlayerStatsParser->EatLine($line); break;
			case KTLP_ST_MATCH: $this->MatchStatsParser->EatLine($line); break;
			case KTLP_ST_TEAMS: 
				$parse_result = $this->TeamScoresParser->EatLine($line);
				if (!$parse_result) {
					$this->parsestate = KTLP_ST_AFTERGAME;
				}
				break;
				
			case KTLP_ST_AFTERGAME: $this->EatLineAfterGame($line); break;
			default:
			DPrint(1,"Unknown state cannot be handled!");
			}
		}
		return true;
	}
	
	function Result() {
		$t_scores = $this->TeamScoresParser->GetResult();
		$t_stats = $this->MatchStatsParser->GetResult();
		$this->output["teams"] = array_merge_recursive(
			$t_scores,
			$t_stats
		);
		$this->output["players"] = $this->PlayerStatsParser->GetResult();
		return $this->output;
	}
	function Error() { return $this->err; }
}

class KTLP_ReadableConverter
{
	var $readable;
	var $orig;
	
	// used ezQuake 1.9 implementation of Con_CreateReadableChars
	function KTLP_ReadableConverter()
	{
		$this->readable = array(
			'.', '_' , '_' , '_' , '_' , '.' , '_' , '_' , '_' , '_' , "\n" ,
			'_' , "\n" , "\t" , '.' , '.', '[', ']',
			'0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
			'.', '_', '_', '_');
			
		$this->orig = array();
		
		 
		for ($i = 32; $i < 127; $i++) {
			$this->readable[$i] = $this->readable[128 + $i] = chr($i);
		}
		
		$this->readable[127] = $this->readable[128 + 127] = '_';
	
		for ($i = 0; $i < 32; $i++) {
			$this->readable[128 + $i] = $this->readable[$i];
		}
		
		$this->readable[128] = '_';
		$this->readable[10 + 128] = '_';
		$this->readable[12 + 128] = '_';
		
		for ($i = 0; $i < 256; $i++) {
			$this->orig[$i] = chr($i);
		}

		ksort($this->readable);
		ksort($this->orig);
	}

	function Convert($str)
	{
		return str_replace($this->orig, $this->readable, $str);
	}
}

class KTLogParser
{
	var $err;
	var $readableConverter;
	var $parser;
	
	function KTLogParser()
	{
		$this->err = KTLP_ERR_OK;
		$this->readableConverter = new KTLP_ReadableConverter();
	}
	
	// returns NULL on error
	function Parse($file)
	{
		$this->err = KTLP_ERR_OK;
		$f = fopen($file,"rb");
		if (!$f) {
			$this->err = KTLP_ERR_FILEOPEN; 
			return NULL;
		}
		
		$this->parser = new KTLP_Parser(KTLP_DEBUG);
		
		while (!feof($f)) {
			$l = fgets($f);	// reads one line
			$l = $this->readableConverter->Convert($l);
			$l = trim($l);
			if (!$this->parser->EatLine($l)) {
				$this->err = $this->parser->Error();
				return NULL;
			}
		}
		
		fclose($f);
		
		return $this->parser->Result();
	}
	
	function GetJSON()
	{
		return PHPArrayToJSON($this->parser->Result());
	}
	
	function GetArray() {
		return $this->parser->Result();
	}
	
	function GetXML() {
		return PHPArrayToXML($this->parser->Result());
	}
	
	function ErrorDesc()
	{
		switch ($this->err) {
		case KTLP_ERR_FILEOPEN: return "File Open Error";
		case KTLP_ERR_OK: return "Success (no errors)";
		default: return "Unknown Error";
		}
	}
}

class KTLP_Visualizer
{
	function KTLP_Visualizer() {
	}
	
	function TR3($a,$b,$c,$class = "") {
		$nb = (int) $b;
		$nc = (int) $c;
		if ($nb || $nc) {
			if ($nb >= $nc)
				$b = "<em>{$b}</em>";
			else if ($nc >= $nb)
				$c = "<em>{$c}</em>";
		}
		if ($class)
			return "<tr><td>{$a}</td><td class='{$class}'>{$b}</td><td class='{$class}'>{$c}</td></tr>\n";
		else
			return "<tr><td>{$a}</td><td>{$b}</td><td>{$c}</td></tr>\n";
	}
	
	function TR3NZ($a,$b,$c,$class = "")
	{
		if ($b || $c)
		return $this->TR3($a,$b,$c,$class);
	}
	
	function GetTeamsTable($arr) {
		$ret = "";
		$teams = array_keys($arr["teams"]);
		$t1 = &$arr["teams"][$teams[0]];
		$t2 = &$arr["teams"][$teams[1]];

		$ret .= "<table class='teams'>\n";
		$ret .= $this->TR3("Teams",$teams[0],$teams[1],"teams");
		$ret .= $this->TR3("Frags",$t1["frags"],$t2["frags"],"frags");
		$ret .= "<tr><td>Summary</td><td colspan='2'>\n";
		$ret .= "  <p class='map'>Map: <strong>".$arr["general"]["map"]."</strong></p>\n";
		$ret .= "  <p class='date'>Date: ".$arr["general"]["date"]."</p>\n";
		$ret .= "</td></tr>\n";

		$ret .= $this->TR3("Quads",$t1["powerups"]["Q"],$t2["powerups"]["Q"]);
		$ret .= $this->TR3NZ("Red Armors",$t1["armr&mhs"]["ra"],$t2["armr&mhs"]["ra"]);
		$ret .= $this->TR3NZ("Yellow Armors",$t1["armr&mhs"]["ya"],$t2["armr&mhs"]["ya"]);
		
		$ret .= $this->TR3NZ("Pentagrams",$t1["powerups"]["P"],$t2["powerups"]["P"]);
		$ret .= $this->TR3NZ("Taken RLs",$t1["rl"]["Took"],$t2["rl"]["Took"]);
		$ret .= $this->TR3NZ("Killed RLs",$t1["rl"]["Killed"],$t2["rl"]["Killed"]);
		$ret .= $this->TR3NZ("Dropped RLs",$t1["rl"]["Dropped"],$t2["rl"]["Dropped"]);
		$ret .= $this->TR3NZ("Given Damage",$t1["damage"]["Gvn"],$t2["damage"]["Gvn"]);
		$ret .= "</table>\n\n";
		return $ret;
	}
	
	function Flatenize($arr) {
		$newa = array();
		foreach($arr as $k1 => $v1) {
			if (is_array($v1)) {
				foreach($v1 as $k2 => $v2) {
					$newa[$k1."-".$k2] = $v2;
				}
			}
			else {
				$newa[$k1] = $v1;			
			}
		}
		return $newa;
	}
	
	// tells in which stats category given stat type belongs 
	function KeyCategory($key) {
		if (preg_match("/^wp-/",$key) || preg_match("/^rl skill/",$key) || preg_match("/^damage-/",$key))
			return 2;
		else if (preg_match("/^armr/",$key) || preg_match("/^powerups-/",$key) || preg_match("/^rl-Took/",$key))
			return 3;
		else
			return 1;
	}
	
	function CategoryName($catnum) {
		$catnum = (int) $catnum;
		if ($catnum == 1) return "General stats";
		if ($catnum == 2) return "Damage stats";
		if ($catnum == 3) return "Item stats";
	}
	
	function GetPlayersTable($arr) {
		$plrs = array();
		
		foreach ($arr["players"] as $pname => $parr) {
			$parr = $this->Flatenize($parr);
			$plr = array();
			
			$plr["name"] = $pname;	// add name
			$plr += $parr;	// add other stats
			$plrs[] = $plr;
		}
		
		$keys = array_keys($plrs[0]);
		$team1 = $plrs[0]["team"];

		$ret = "";
		
		for ($category = 1; $category <= 3; $category++) {
			$ret .= "<h2>".$this->CategoryName($category)."</h2>\n";
			$ret .= "<table class='players sortable' cellspacing='2'>\n";
			$ret .= "<thead>\n";
			$ret .= "<tr>\n";
			foreach($keys as $k) {
				if ($k != "name" && $this->KeyCategory($k) != $category) continue;
				$class = KTLP_SafeElementName($k);
				$ret .= "<td class='{$class}'>".htmlspecialchars($k)."</td>\n";
			}
			$ret .= "</tr>\n";
			
			$ret .= "</thead>\n";
			
			$ret .= "<tbody>\n";
				
			foreach($plrs as $p) {
				$class = $p["team"] == $team1 ? "t1" : "t2";
				$ret .= "<tr class='{$class}'>\n";
				foreach($keys as $k) {
					if ($k != "name" && $this->KeyCategory($k) != $category) continue;
					$class = KTLP_SafeElementName($k);
					if (array_key_exists($k,$p)) 
					$val = $p[$k];
					else $val = "";
					$ret .= "<td class='{$class}'>{$val}</td>\n";
				}
				$ret .= "</tr>\n";
			}
			
			$ret .= "</tbody>\n";
			$ret .= "</table>\n\n";
		}
		return $ret;
	}
	
	function GetHtml($arr) {
		$ret = "<h1>Match stats</h1>\n";
		if (count($arr["players"]) < 2 || count($arr["teams"]) != 2) {
			return "<p>Error: Not enough players/teams found in the log</p>";
		}
		
		$ret .= $this->GetTeamsTable($arr);
		
		$ret .= $this->GetPlayersTable($arr);
		
		return $ret;
	}
}

?>
