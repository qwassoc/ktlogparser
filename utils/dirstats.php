<?php
	/**
	 * \file
	 * 
	 * \brief
	 * Stats dir scanner.
	 * 
	 * This is a utility built on the KT Log Parser. It will scan all logs in
	 *  ./data dir and return a large table with all stats in it.
	 *  I used it for producing a large table with global stats from a LAN party.	 
	 *  
	 * \author
	 * johnnycz
	 * 	 
	**/	 	 	 	 

	require_once "ktlogpsr.php";
	
	define ("DATA_DIR", "./data");

	function getFiles($directory)
	{
		$dirReader = dir($directory);
		$res = array();
		
		while (false !== ($entry = $dirReader->read())) {
			if ($entry != "." && $entry != "..") {
				$res[] = $entry;
			}
		}
		
		return $res;
	}

	function getAllTeams($data_total)
	{
		$teams = array();
		
		foreach ($data_total as $match) {
			$teams += array_keys($match["teams"]);
		}
		
		return $teams;
	}
	
	function getAllPlayers($data_total)
	{
		$players = array();
		
		foreach ($data_total as $match) {
			$players += array_keys($match["players"]);
		}
		
		return $players;
	}
	
	function getAllTeamStats($data_total)
	{
		$stats = array();
		
		foreach ($data_total as $match) {
			foreach ($match["teams"] as $team) {
				$stats += array_keys($team);
			}
		}
		
		return $stats;
	}
	
	function getAllPlayerStats($data_total)
	{
		$stats = array();
		
		foreach ($data_total as $match) {
			foreach ($match["players"] as $player) {
				$stats += array_keys($player);
			}
		}
		
		return $stats;		
	}

	function fix_comma($txt)
	{
		return strtr($txt, ".", ",");
	}

	function main()
	{
		$files = getFiles(DATA_DIR);
		
		echo "<pre>\n";
		
		$data_total = array();
		$flatenizer = new KTLP_Visualizer();
		foreach ($files as $file) {
			$parser = new KTLogParser;
			$parser->Parse(DATA_DIR."/".$file);
			$data = $parser->GetArray();
			
			foreach ($data["teams"] as $team_key => $team_data) {
				$data["teams"][$team_key] = $flatenizer->Flatenize($team_data);
			}

			foreach ($data["players"] as $plr_key => $plr_data) {
				$data["players"][$plr_key] = $flatenizer->Flatenize($plr_data);
			}
			
			$data_total[] = $data;
			//break;
		}
		
		$teams = getAllTeams($data_total);
		$players = getAllPlayers($data_total);
		$teamstats = getAllTeamStats($data_total);
		$playerstats = getAllPlayerStats($data_total);
		
		echo "date\tmap\t";
		
		foreach ($teams as $team) {
			foreach ($teamstats as $teamstat) {
				echo "$team: $teamstat\t";
			}
		}

		foreach ($players as $player) {
			foreach ($playerstats as $playerstat) {
				echo "$player: $playerstat\t";
			}
		}
		
		echo "\n";
		
		foreach ($data_total as $match) {
			echo "{$match['general']['date']}\t{$match['general']['map']}\t";
			
			foreach ($teams as $team) {
				foreach ($teamstats as $teamstat) {
					if (isset($match['teams'][$team][$teamstat])) {
						echo fix_comma($match['teams'][$team][$teamstat])."\t";
					}
					else echo "\t";
				}
			}
	
			foreach ($players as $player) {
				foreach ($playerstats as $playerstat) {
					if (isset($match['players'][$player][$playerstat])) {
						echo fix_comma($match['players'][$player][$playerstat])."\t";
					}
					else echo "\t";
				}
			}
			
			echo "\n";
		}
	}
	
	main();

?>
