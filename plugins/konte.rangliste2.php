<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
* Eine Art „Punkteranking“ für jeden Spieler
* Created by Konte
*
* Dependencies: /plugins/12h/teams.txt
*/

Aseco::registerEvent('onStartup', "rangliste_init");
Aseco::registerEvent('onPlayerConnect', "rangliste_PlayerConnect");
Aseco::registerEvent('onPlayerManialinkPageAnswer', "rangliste_MLAnswer");
// xaseco1
Aseco::registerEvent('onEndRace', "rangliste_EndRace");
Aseco::registerEvent('onNewChallenge', "rangliste_NewChallenge");
// xaseco2
//Aseco::registerEvent('onEndMap', "rangliste_EndRace");
//Aseco::registerEvent('onBeginMap', "rangliste_NewChallenge");

Aseco::addChatCommand('resetscore', 'resets the secondary score of the ranking');
Aseco::addChatCommand('score', 'Shows the current score of the ranking');

function rangliste_init($aseco) {
	global $rangliste_cfg, $rangliste_data;
	$rangliste_cfg = $rangliste_data = array();

	// Config -->
	$rangliste_cfg["pos_game1"]  = "48 17 1";
	$rangliste_cfg["pos_game2"]  = "48 -6 1";
	$rangliste_cfg["pos_score1"] = "-64 17 1";
	$rangliste_cfg["pos_score2"] = "-64 -6 1";
	$rangliste_cfg["admins"] = array("konte", "kripke");
	// <--

	$result = mysql_query("
		CREATE TABLE IF NOT EXISTS `rangliste` (
			`login` varchar(50) COLLATE utf8_bin NOT NULL,
			`total` mediumint(3) unsigned NOT NULL,
			`sekundaer` mediumint(3) unsigned NOT NULL,
			PRIMARY KEY (`login`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
	");
	if (!$result) {
		$aseco->console('[konte.rangliste.php] Error on creating the table `rangliste`: '. mysql_error());
	}
	rangliste_data();
}

function rangliste_data($limit = 10) {
	global $rangliste_data, $rangliste_temp;
	$spalten = array("total", "sekundaer");
	foreach ($spalten as $spalte) {
		$rangliste_data[$spalte] = array();
		$query = "
			SELECT
				`rangliste`.`login`,
				`total`,
				`sekundaer`,
				`players`.`NickName`
			FROM `rangliste`
			LEFT JOIN `players` ON `players`.`Login` = `rangliste`.`login`
			ORDER BY `$spalte` DESC
			LIMIT $limit
		";
		$result = mysql_query($query);
		if ($result AND mysql_num_rows($result) > 0) {
			while ($row = mysql_fetch_array($result)) {
				$row[$spalte] += $rangliste_temp[$spalte][$row["login"]];
				$rangliste_data[$spalte][] = $row;

			}
			mysql_free_result($result);
		}
	}
}

function rangliste_EndRace($aseco, $rankings) {
	global $rangliste_cfg, $rangliste_temp;
	static $i;
	$p_arr = $rangliste_temp;
	if (!$p_arr) {
		$p_arr = array();
		$p_arr["total"] = $p_arr["sekundaer"] = array();
	}

	$rankings = $rankings[0];
	$gesamt = 0;
	foreach ($rankings as $ranking) {
		if (!$aseco->server->players->getPlayer($ranking["Login"])->isspectator) {
			$gesamt++;
		}
	}
	
	rangliste_Anzeige("total", 95701, "", "Total", $rangliste_cfg["pos_score1"]);
	rangliste_Anzeige("sekundaer", 95702, "", "Secondary", $rangliste_cfg["pos_score2"]);
	foreach ($rankings as $ranking) {
		$login = $ranking["Login"];
		if ($aseco->server->players->getPlayer($login)->isspectator OR $ranking["BestTime"] == -1) {
			continue;
		}
		$punkte = $gesamt - $ranking["Rank"];
		if (!array_key_exists($login, $p_arr["total"])) {
			$p_arr["total"][$login] = $punkte;
			$p_arr["sekundaer"][$login] = $punkte;
		} elseif (!array_key_exists($login, $p_arr["sekundaer"])) {
			$p_arr["total"][$login] += $punkte;
			$p_arr["sekundaer"][$login] = $punkte;
		} else {
			$p_arr["total"][$login] += $punkte;
			$p_arr["sekundaer"][$login] += $punkte;
		}
	}
	
	rangliste_data();

	rangliste_Anzeige("total", 95701, "", "Total", $rangliste_cfg["pos_score1"]);
	rangliste_Anzeige("sekundaer", 95702, "", "Secondary", $rangliste_cfg["pos_score2"]);
	
	if ($i == 10) {
		foreach ($p_arr["total"] as $login => $total_punkte) {
			$sek_punkte = $p_arr["sekundaer"][$login];
			mysql_query("
				INSERT INTO `rangliste` (
					`login`, `total`, `sekundaer`
				)
				VALUES (
					'$login', $total_punkte, $sek_punkte
				)
				ON DUPLICATE KEY UPDATE
					`total` = `total` + $total_punkte,
					`sekundaer` = `sekundaer` + $sek_punkte;
			");
		}
		$i = 0;
	}
	@$i++;
}

function rangliste_NewChallenge($aseco) {
	global $rangliste_cfg;
	rangliste_Anzeige("total", 95701, "", "Total", $rangliste_cfg["pos_game1"]);
	rangliste_Anzeige("sekundaer", 95702, "", "Secondary", $rangliste_cfg["pos_game2"]);
}

function rangliste_PlayerConnect($aseco, $player) {
	global $rangliste_cfg;
	rangliste_Anzeige("total", 95701, $player->login, "Total", $rangliste_cfg["pos_game1"]);
	rangliste_Anzeige("sekundaer", 95702, $player->login, "Secondary", $rangliste_cfg["pos_game2"]);
}

function rangliste_Anzeige($spalte, $id, $login, $text, $pos, $breite = 16, $textsize = 1, $max = 10) {
	global $aseco, $rangliste_data;

	$ml  = '<?xml version="1.0" encoding="UTF-8"?>';
	$ml .= '<manialinks><manialink id="'.$id.'"><format textsize="'.$textsize.'" textcolor="FFF" />';
	$ml .= '<frame posn="'.$pos.'">';
	$ml .= '<label posn="'.($breite/2).' -0.5" halign="center" text="$o'.$text.'" />';
	$posy = -2.5;
	$i = 1;
	$j = 0;
	$vorher = 0;

	foreach ($rangliste_data[$spalte] as $row) {
		if ($vorher == $row[$spalte]) {
			$j++;
		} else {
			$j = 0;
		}
		$ml .= '<label posn="1 '.$posy.' 1" sizen="'.($breite-4).'" text="'.($i-$j).'. '.htmlspecialchars($row['NickName']).'"/>';
		$ml .= '<label posn="'.($breite-1).' '.$posy.' 1" halign="right" text="'.$row[$spalte].'"/>';

		$vorher = $row[$spalte];
		$posy -= $textsize * 0.63 + 1.26;
		$i++;
	}
	$ml .= '<quad sizen="'.$breite.' '.(-$posy+0.5).'" style="BgsPlayerCard" substyle="BgCardSystem" />';
	$ml .= '</frame></manialink>';
	$ml .= '</manialinks>';
	if (!$login) 
		$aseco->client->query('SendDisplayManialinkPage', $ml, 0, false);
	else
		$aseco->client->query('SendDisplayManialinkPageToLogin', $login, $ml, 0, false);
}

function chat_resetscore($aseco, $command) {
	global $rangliste_cfg;
	$login = $command["author"]->login;
	if(!in_array($login, $rangliste_cfg["admins"])) {
		return;
	}
	if (mysql_query("UPDATE `rangliste` SET `sekundaer` = 0")) {
		$aseco->client->query('ChatSendServerMessageToLogin', 'Success!', $login);
	} else {
		$aseco->client->query('ChatSendServerMessageToLogin', 'Error!', $login);
	}
	rangliste_data();
	rangliste_NewChallenge($aseco);
}

function chat_score($aseco, $command) {
	if ($command["params"] == "now") {
		rangliste_score($aseco, $command["author"]->login, "sekundaer");
	} else {
		rangliste_score($aseco, $command["author"]->login);
	}
}
function rangliste_score($aseco, $login, $spalte = "total", $seite = 0) {
	global $rangliste_temp;
	if (!in_array($spalte, array("total", "sekundaer"))) {
		$spalte = "total";
	}
	$id = 95705;
	$diff = $seite * 20;
	switch ($spalte) {
		case "total": $text = "Total"; break;
		case "sekundaer": $text = "Secondary"; break;
	}
	$ml  = '<?xml version="1.0" encoding="UTF-8"?>';
	$ml .= '<manialinks><manialink id="'.$id.'"><format textsize="2" textcolor="FFF" />';
	$ml .= '<frame posn="-30 30 1">';
	$ml .= '<label posn="30 -1" halign="center" textsize="3" text="$oRanking – '.$text.'" />';
	$ml .= '<quad sizen="60 35" style="BgsPlayerCard" substyle="BgActivePlayerName" />';
	$ml .= '<quad sizen="60 35" style="BgsPlayerCard" substyle="BgActivePlayerName" />';
	$ml .= '<quad posn="56 -1 2" sizen="3 3" style="Icons64x64_1" substyle="Close" action="95704" />';

	$query = "
		SELECT
			`rangliste`.`login`,
			`$spalte`,
			`sekundaer`,
			`players`.`NickName`
		FROM `rangliste`
		LEFT JOIN `players` ON `players`.`Login` = `rangliste`.`login`
		ORDER BY `$spalte` DESC
		LIMIT $diff, 21
	";

	$result = mysql_query($query);
	if ($result) {
		if (mysql_num_rows($result) > 0) {
			$posy = -5;
			$posx = 1;
			$i = 1 + $diff;
			$j = 0;
			$vorher = 0;
			while ($row = mysql_fetch_array($result)) {
				if ($vorher == $row[$spalte]) {
					$j++;
				} else {
					$j = 0;
				}
				$row[$spalte] += $rangliste_temp[$spalte][$row["login"]];
				$ml .= '<label posn="'.$posx.' '.$posy.' 1" sizen="25" text="'.($i-$j).'. '.htmlspecialchars($row['NickName']).'"/>';
				$ml .= '<label posn="'.($posx+27).' '.$posy.' 1" halign="right" text="'.$row[$spalte].'"/>';

				$vorher = $row[$spalte];
				$posy -= 2.5;
				$i++;
				if ($i == 11 + $diff) {
					$posy = -5;
					$posx = 31;
				} elseif ($i == 21 + $diff) {
					break;
				}
			}
			$seite = $id+$seite+($spalte == "sekundaer" ? 10 : 0);
			if (mysql_num_rows($result) == 21 AND $i <= 100) {
				$ml .= '<quad posn="31 -31 1" sizen="3 3" style="Icons64x64_1" substyle="ArrowNext" action="'.($seite+1).'" />';
			}
			if ($diff) {
				$ml .= '<quad posn="26 -31 1" sizen="3 3" style="Icons64x64_1" substyle="ArrowPrev" action="'.($seite-1).'" />';
			}
			
		}
		mysql_free_result($result);
	}
	$ml .= '</frame></manialink>';
	$ml .= '</manialinks>';
	$aseco->client->query('SendDisplayManialinkPageToLogin', $login, $ml, 0, false);
}
function rangliste_MLAnswer($aseco, $answer) {
	if ($answer[2] == 95704) {
		$aseco->client->query('SendDisplayManialinkPageToLogin', $answer[1], '<manialink id="95705"></manialink>', 0, false);
	} elseif ($answer[2] > 95704 && $answer[2] < 95710) {
		rangliste_score($aseco, $answer[1], "total", $answer[2] - 95705);
	} elseif ($answer[2] > 95714 && $answer[2] < 95720) {
		rangliste_score($aseco, $answer[1], "sekundaer", $answer[2] - 95715);
	}
}
?>