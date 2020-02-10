<?php

/*
 * Plugin: Personal Best Checkpoints
 * ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
 * This Plugin is useful on Train-Servers and maybe RPG-Servers. It displays the
 * differences between your own Checkpoint-Times for each run.
 *
 * The first run on a Challenge are now preset with a local driven record, so you can
 * compare from the first run your Checkpoint times. This feature is only active if
 * less then 32 Players connected to the Server to prevent long loads on Database access.
 *
 * Currently it supports the Gamemodes: Rounds, TimeAttack, Team, Laps, Stunts and Cup
 *
 * ----------------------------------------------------------------------------------
 * Author:		undef.de
 * Version:		1.3.2
 * Date:		2011-10-23
 * Copyright:		2009 - 2011 by undef.de
 * Home:		http://www.undef.de/Trackmania/Plugins/
 * System:		XAseco/1.14+
 * Game:		Trackmania Forever (TMF) only
 * ----------------------------------------------------------------------------------
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * ----------------------------------------------------------------------------------
 *
 * Dependencies:	plugins/plugin.localdatabase.php
 */

/* The following manialink id's are used in this plugin (the 915 part of id can be changed on trouble):
 * 91500		id for manialinks
 * 91501		id for manialink for the Widget itself
 * 91502		id for manialink Checkpoint-Times and Differences
 * 91503		id for manialink for the Help
 * 91504		id for action to display Help
 * 91505		id for action to close Help
 */

Aseco::registerEvent('onSync',				'pbcps_onSync');
Aseco::registerEvent('onCheckpoint',			'pbcps_onCheckpoint');
Aseco::registerEvent('onNewChallenge2',			'pbcps_onNewChallenge2');
Aseco::registerEvent('onPlayerConnect',			'pbcps_onPlayerConnect');
Aseco::registerEvent('onPlayerInfoChanged',		'pbcps_onPlayerInfoChanged');
Aseco::registerEvent('onPlayerManialinkPageAnswer',	'pbcps_onPlayerManialinkPageAnswer');
Aseco::registerEvent('onPlayerFinish',			'pbcps_onPlayerFinish');
Aseco::registerEvent('onEndRace1',			'pbcps_onEndRace1');			// throw main 'end challenge' event
Aseco::registerEvent('onRestartChallenge',		'pbcps_onRestartChallenge');		// throw 'restarted challenge' event

global $pbcps_config;

/* Just a note for my editor -> utf-8 äöüß */

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbcps_onSync ($aseco) {
	global $pbcps_config;


	// Check for the right XAseco-Version
	$xaseco_min_version = 1.14;
	if ( defined('XASECO_VERSION') ) {
		if ( version_compare(XASECO_VERSION, $xaseco_min_version, '<') ) {
			trigger_error('[plugin.personal_best_checkpoints.php] Not supported XAseco version ('. XASECO_VERSION .')! Please update to min. version '. $xaseco_min_version .'!', E_USER_ERROR);
		}
	}
	else {
		trigger_error('[plugin.personal_best_checkpoints.php] Can not identify the System, "XASECO_VERSION" is unset! This plugin runs only with XAseco/'. $xaseco_min_version .'+', E_USER_ERROR);
	}

	if ($aseco->server->getGame() != 'TMF') {
		trigger_error('[plugin.personal_best_checkpoints.php] This plugin supports only TMF, can not start with a "'. $aseco->server->getGame() .'" Dedicated-Server!', E_USER_ERROR);
	}

	// Check for dependencies
	if ( !function_exists('ldb_loadSettings') ) {
		trigger_error('[plugin.personal_best_checkpoints.php] Missing dependent plugin, please activate "plugin.localdatabase.php" in "plugins.xml" and restart.', E_USER_ERROR);
	}

	// Set 'checkpoint_list' off, this place we need
	setCustomUIField('checkpoint_list', false);

	$pbcps_config['MANIALINK_ID'] = '915';
	$pbcps_config['VERSION'] = '1.3.2';

	// Register this to the global version pool (for up-to-date checks)
	$aseco->plugin_versions[] = array(
		'plugin'	=> 'plugin.personal_best_checkpoints.php',
		'author'	=> 'undef.de',
		'version'	=> $pbcps_config['VERSION']
	);

	$pbcps_config['WIDGET']['POSITION_X'] = 13;
	$pbcps_config['WIDGET']['POSITION_Y'] = -32.5;
	$pbcps_config['WIDGET']['TEXTSIZE'] = 1;
	$pbcps_config['WIDGET']['TEXTSCALE'] = 0.9;

	$pbcps_config['SHOW_MAX_CHECKPOINTS'] = 30;			// 30 is max.

	$pbcps_config['WIDGET']['FULLTIME'] = true;			// "+1.14" = false, "+0:01.14" = true

	// Set current state
	$pbcps_config['CURRENT_STATE'] = 1;
	$pbcps_config['CHALLENGE'] = array();
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbcps_onPlayerConnect ($aseco, $player) {
	global $pbcps_config;


	// Clean complete
	$player->data['BPCPS_TIMES'] = array();

	// Set PlayerId (from Database), cache the Id here for later use
	$player->data['BPCPS_DBPLAYERID'] = $aseco->getPlayerId( $player->login );

	// Need to do a trick, because on XASECO StartUp, there is no Challenge loaded
	// and XASECO can not give the number of Checkpoints. So just fill the maximum.
	// When the Challenge is loaded, then this get fixed to the correct number of
	// Checkpoints.
	// If a Player joins when XASECO runs a longer time, then there is the correct
	// number of Checkpoints, because the Challenge is already loaded.
	$pbcps_config['CHALLENGE']['NUM_CPS'] = (isset($pbcps_config['CHALLENGE']['NUM_CPS']) ? $pbcps_config['CHALLENGE']['NUM_CPS'] : $pbcps_config['SHOW_MAX_CHECKPOINTS']);

	// Preset the PBC-Array for current Player
	pbcps_resetPlayerArray($player);

	// Display the empty Widget
	pbcps_buildWidget($player->login);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onPlayerInfoChanged
function pbcps_onPlayerInfoChanged ($aseco, $info) {
	global $pbcps_config;


	// Nothing todo at Score
	if ($pbcps_config['CURRENT_STATE'] != 6) {

		// Get Player
		$player = $aseco->server->players->getPlayer($info['Login']);

		if ($info['SpectatorStatus'] > 0) {
			$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
			$xml .= '<manialinks id="'. $pbcps_config['MANIALINK_ID'] .'00">';
			$xml .= '<manialink id="'. $pbcps_config['MANIALINK_ID'] .'01"></manialink>';
			$xml .= '<manialink id="'. $pbcps_config['MANIALINK_ID'] .'02"></manialink>';
			$xml .= '</manialinks>';

			// Hide at Scoretable
			$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);
		}
		else {
			// Display the empty Widget
			pbcps_buildWidget($player->login);

			// Display the personal checkpoints time for Player
			pbcps_buildPersonalCheckpointsTimeInlay($player, -1);
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onPlayerManialinkPageAnswer
function pbcps_onPlayerManialinkPageAnswer ($aseco, $answer) {
	global $pbcps_config;


	// If id = 0, bail out immediately
	if ($answer[2] == 0) {
		return;
	}

	// Get Player
	$player = $aseco->server->players->getPlayer($answer[1]);

	if ($answer[2] == $pbcps_config['MANIALINK_ID'] .'04') {			// Display Help

		pbcps_buildHelpWindow($player->login, true);

	}
	else if ($answer[2] == $pbcps_config['MANIALINK_ID'] .'05') {			// Close Help

		pbcps_buildHelpWindow($player->login, false);

	}

}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onPlayerFinish
function pbcps_onPlayerFinish ($aseco, $finish_item) {
	global $pbcps_config;


	// Refresh the times without any highlite Checkpoint
	if ($finish_item->score == 0) {
		// Display the personal checkpoint time for Player
		pbcps_buildPersonalCheckpointsTimeInlay($finish_item->player, -1);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbcps_onNewChallenge2 ($aseco, $challenge_item) {
	global $pbcps_config;


	// Set current state
	$pbcps_config['CURRENT_STATE'] = 1;

	// Display the empty Widget
	pbcps_buildWidget(false);


	// Save the number of Checkpoints of this Map and if this Map is a Multilap
	$pbcps_config['CHALLENGE']['NUM_CPS'] = $challenge_item->nbchecks;
	$pbcps_config['CHALLENGE']['MULTILAP'] = $challenge_item->laprace;
	$pbcps_config['CHALLENGE']['DBID'] = $aseco->server->challenge->id;

	// Reset the PBC-Array for all connected Players
	pbcps_resetPlayerArray(false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbcps_onRestartChallenge ($aseco, $challenge_item) {
	global $pbcps_config;


	// Set current state
	$pbcps_config['CURRENT_STATE'] = 1;

	// Preset the PBC-Array for all Players
	pbcps_resetPlayerArray(false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// TMN: [0]=PlayerUid, [1]=Login, [2]=Time, [3]=Score, [4]=CheckpointIndex
// TMF: [0]=PlayerUid, [1]=Login, [2]=TimeScore, [3]=CurLap, [4]=CheckpointIndex
function pbcps_onCheckpoint ($aseco, $checkpt) {
	global $pbcps_config;


	// Set the author of this action
	$player = $aseco->server->players->getPlayer($checkpt[1]);
	$time = $checkpt[2];
	$round = $checkpt[3];
	$CheckpointId = $checkpt[4];

	// Bail out if not already initialized
	if ( !isset($player->data['BPCPS_TIMES'][$CheckpointId]->best_time) ) {
		return;
	}



//	$aseco->console('***B CP:'. sprintf("%02d", $CheckpointId) .' RND:'. $round .' TIME:'. $time);

	// Special work for Multilaps Maps in Gamemode 'Laps'
	if ( ($aseco->server->gameinfo->mode == 3) && ($pbcps_config['CHALLENGE']['MULTILAP'] == true) ) {

		// Correct CheckpointId in Multilaps Maps
		$finish = false;
		if ( ($CheckpointId+1) == ($pbcps_config['CHALLENGE']['NUM_CPS'] * $round) ) {
			$round -= 1;

			// Was a finish "Checkpoint"
			$finish = $player->data['BPCPS_LAST_LAP_TIME'];

			// Store the total time for this lap
			$player->data['BPCPS_LAST_LAP_TIME'] = $time;
		}
		if ($round > 0) {
			// The Checkpoints counts up, but i need the Id of the Checkpoint
			$cp = ($CheckpointId - ($pbcps_config['CHALLENGE']['NUM_CPS'] * $round));
			if ($cp >= 0) {
				$CheckpointId = $cp;
			}

			// Calculate the right time for this Checkpoint only if it is not the finish "Checkpoint" for this lap
			if ($finish == false) {
				$time -= $player->data['BPCPS_LAST_LAP_TIME'];
			}
			else {
				$time -= $finish;
			}
		}
	}

//	$aseco->console('***A CP:'. sprintf("%02d", $CheckpointId) .' RND:'. $round .' TIME:'. $time .LF);


	// Calculate the difference between personal Checkpoint time and personal best Checkpoint time
	if ( (!isset($player->data['BPCPS_TIMES'][$CheckpointId]->best_time)) || ($player->data['BPCPS_TIMES'][$CheckpointId]->best_time == -1) ) {
		$differences = 0;
	}
	else {
		$differences = ($time - $player->data['BPCPS_TIMES'][$CheckpointId]->best_time);
	}


	// Prepare the times and style for this Checkpoint
	if ($differences < 0) {

		$differences = abs($differences);
		if ($aseco->server->gameinfo->mode == 4) {
			// Player has a lower Score at this Checkpoint
			$player->data['BPCPS_TIMES'][$CheckpointId]->diff_styled = '$F00-'. $differences;
			$player->data['BPCPS_TIMES'][$CheckpointId]->bar_color = 'F00';
		}
		else {
			// Player has a better time at this Checkpoint
			$player->data['BPCPS_TIMES'][$CheckpointId]->diff_styled = '$0F0-'. (($pbcps_config['WIDGET']['FULLTIME'] == true) ? pbcps_formatTime($differences) : sprintf('%d.%02d', floor($differences/1000), (($differences - (floor($differences/1000) * 1000)) / 10)));
			$player->data['BPCPS_TIMES'][$CheckpointId]->bar_color = '0F0';
			$player->data['BPCPS_TIMES'][$CheckpointId]->best_time = $time;
		}

	}
	elseif ($differences == 0) {

		if ($player->data['BPCPS_TIMES'][$CheckpointId]->best_time == -1) {
			// Player has not driven this Checkpoint before
			if ($aseco->server->gameinfo->mode == 4) {
				$player->data['BPCPS_TIMES'][$CheckpointId]->diff_styled = '$FFF---';
			}
			else {
				$player->data['BPCPS_TIMES'][$CheckpointId]->diff_styled = (($pbcps_config['WIDGET']['FULLTIME'] == true) ? '$FFF-:--.--' : '$FFF-.--');
			}
			$player->data['BPCPS_TIMES'][$CheckpointId]->best_time = $time;
			$player->data['BPCPS_TIMES'][$CheckpointId]->bar_color = 'FFF';
		}
		else {
			if ($aseco->server->gameinfo->mode == 4) {
				// Player has same Score at this Checkpoint
				$player->data['BPCPS_TIMES'][$CheckpointId]->diff_styled = '$0DF±'. $differences;
			}
			else {
				// Player has same time at this Checkpoint
				$player->data['BPCPS_TIMES'][$CheckpointId]->diff_styled = '$0DF±'. (($pbcps_config['WIDGET']['FULLTIME'] == true) ? pbcps_formatTime($differences) : sprintf('%d.%02d', floor($differences/1000), (($differences - (floor($differences/1000) * 1000)) / 10)));
			}
			$player->data['BPCPS_TIMES'][$CheckpointId]->bar_color = '09F';
		}

	}
	else { // $differences > 0

		if ($aseco->server->gameinfo->mode == 4) {
			// Player has higher Score at this Checkpoint
			$player->data['BPCPS_TIMES'][$CheckpointId]->diff_styled = '$0F0+'. $differences;
			$player->data['BPCPS_TIMES'][$CheckpointId]->bar_color = '0F0';
			$player->data['BPCPS_TIMES'][$CheckpointId]->best_time = $time;
		}
		else {
			// Player has slower time at this Checkpoint
			$player->data['BPCPS_TIMES'][$CheckpointId]->diff_styled = '$F00+'. (($pbcps_config['WIDGET']['FULLTIME'] == true) ? pbcps_formatTime($differences) : sprintf('%d.%02d', floor($differences/1000), (($differences - (floor($differences/1000) * 1000)) / 10)));
			$player->data['BPCPS_TIMES'][$CheckpointId]->bar_color = 'F00';
		}

	}

	// Set the last Checkpoint
	$player->data['BPCPS_LASTCP'] = $CheckpointId;

	// Display the personal checkpoint time for Player
	pbcps_buildPersonalCheckpointsTimeInlay($player, $CheckpointId);

}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onEndRace1
function pbcps_onEndRace1 ($aseco, $race) {
	global $pbcps_config;


	// Set current state
	$pbcps_config['CURRENT_STATE'] = 6;

	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks id="'. $pbcps_config['MANIALINK_ID'] .'00">';
	$xml .= '<manialink id="'. $pbcps_config['MANIALINK_ID'] .'01"></manialink>';
	$xml .= '<manialink id="'. $pbcps_config['MANIALINK_ID'] .'02"></manialink>';
	$xml .= '</manialinks>';

	// Hide at Scoretable
	$aseco->client->query('SendDisplayManialinkPage', $xml, 0, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbcps_resetPlayerArray ($player = false) {
	global $aseco, $pbcps_config;


	// bail out immediately if no Player is connected
	if (count($aseco->server->players->player_list) == 0) {
		return;
	}

	if ( ($player == false) && (count($aseco->server->players->player_list) <= 32) ) {
		pbcps_playerArrayPersonal(false);
	}
	else if ( ($player != false) && (count($aseco->server->players->player_list) <= 32) ) {
		pbcps_playerArrayPersonal($player);
	}
	else {
		pbcps_playerArrayNice();
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbcps_playerArrayNice () {
	global $aseco, $pbcps_config;


	// Clean Players CP list (Nice-Mode)
	foreach ($aseco->server->players->player_list as $player) {

		// Clean complete
		$player->data['BPCPS_TIMES'] = array();

		// Preset all CPs with -1
		for ($i = 0 ; $i <= $pbcps_config['CHALLENGE']['NUM_CPS'] ; $i ++) {
			$player->data['BPCPS_TIMES'][$i]->best_time = -1;
			if ($aseco->server->gameinfo->mode == 4) {
				$player->data['BPCPS_TIMES'][$i]->diff_styled = '$FFF---';
			}
			else {
				$player->data['BPCPS_TIMES'][$i]->diff_styled = (($pbcps_config['WIDGET']['FULLTIME'] == true) ? '$FFF-:--.--' : '$FFF-.--');
			}
			$player->data['BPCPS_TIMES'][$i]->bar_color = 'FFF';
			$player->data['BPCPS_LASTCP'] = -1;
		}
		$player->data['BPCPS_LAST_LAP_TIME'] = 0;

		// Display the personal checkpoints time for Player (except Spectators)
		if ($player->isspectator != 1) {
			pbcps_buildPersonalCheckpointsTimeInlay($player, -1);
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbcps_playerArrayPersonal ($player = false) {
	global $aseco, $pbcps_config;


	// bail out immediately if no Challenge Database Id was set
	if ( !isset($pbcps_config['CHALLENGE']['DBID']) ) {
		return;
	}

	// Preset with local saved Checkpoint times (if any)
	if ($player != false) {
		$query = "SELECT `PlayerId`,`Checkpoints` FROM `records` WHERE `ChallengeId`='". $pbcps_config['CHALLENGE']['DBID'] ."' AND `PlayerId`='". $player->data['BPCPS_DBPLAYERID'] ."' LIMIT 1;";
	}
	else {
		$query = "SELECT `PlayerId`,`Checkpoints` FROM `records` WHERE `ChallengeId`='". $pbcps_config['CHALLENGE']['DBID'] ."' AND `PlayerId` IN (";
		foreach ($aseco->server->players->player_list as &$ply) {
			$query .= "'". $ply->data['BPCPS_DBPLAYERID'] ."',";

		}
		$query = substr($query, 0, strlen($query)-1);		// remove the last ,
		$query .= ');';
	}

	$res = mysql_query($query);
	if ($res != false) {

		if ($player != false) {
			// Clean only given Player
			$player->data['BPCPS_TIMES'] = array();
			$player->data['BPCPS_LASTCP'] = -1;
		}
		else {
			// Clean complete
			foreach ($aseco->server->players->player_list as &$ply) {
				$ply->data['BPCPS_TIMES'] = array();
				$ply->data['BPCPS_LASTCP'] = -1;
			}
		}

		// Fill with local record
		while ($row = mysql_fetch_object($res)) {
			foreach ($aseco->server->players->player_list as &$ply) {
				if ($ply->data['BPCPS_DBPLAYERID'] == $row->PlayerId) {

					$cps = explode(',', $row->Checkpoints);
					for ($i = 0 ; $i <= $pbcps_config['CHALLENGE']['NUM_CPS'] ; $i ++) {
						$ply->data['BPCPS_TIMES'][$i]->best_time = ((isset($cps[$i]) && $cps[$i]!='') ? $cps[$i] : -1);
						if ($aseco->server->gameinfo->mode == 4) {
							$ply->data['BPCPS_TIMES'][$i]->diff_styled = '$FFF---';
						}
						else {
							$ply->data['BPCPS_TIMES'][$i]->diff_styled = (($pbcps_config['WIDGET']['FULLTIME'] == true) ? '$FFF-:--.--' : '$FFF-.--');
						}
						$ply->data['BPCPS_TIMES'][$i]->bar_color = 'FFF';
						$ply->data['BPCPS_LASTCP'] = -1;
					}
					$ply->data['BPCPS_LAST_LAP_TIME'] = 0;

					// Display the personal checkpoints time for Player (except Spectators)
					if ($ply->isspectator != 1) {
						pbcps_buildPersonalCheckpointsTimeInlay($ply, -1);
					}
				}
			}
		}

		// Find Players without a local record and preset with empty time
		foreach ($aseco->server->players->player_list as &$ply) {
			if (count($ply->data['BPCPS_TIMES']) == 0) {
				// Preset all CPs with -1
				for ($i = 0 ; $i <= $pbcps_config['CHALLENGE']['NUM_CPS'] ; $i ++) {
					$ply->data['BPCPS_TIMES'][$i]->best_time = -1;
					if ($aseco->server->gameinfo->mode == 4) {
						$ply->data['BPCPS_TIMES'][$i]->diff_styled = '$FFF---';
					}
					else {
						$ply->data['BPCPS_TIMES'][$i]->diff_styled = (($pbcps_config['WIDGET']['FULLTIME'] == true) ? '$FFF-:--.--' : '$FFF-.--');
					}
					$ply->data['BPCPS_TIMES'][$i]->bar_color = 'FFF';
					$ply->data['BPCPS_LASTCP'] = -1;
				}
				$ply->data['BPCPS_LAST_LAP_TIME'] = 0;
			}
		}

	}
	else {
		$aseco->console('[plugin.personal_best_checkpoints.php] SQL error: '. mysql_error() .' [for statement "'. $query .'"]');
		pbcps_playerArrayNice();
	}
	mysql_free_result($res);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbcps_buildWidget ($login = false) {
	global $aseco, $pbcps_config;


	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks id="'. $pbcps_config['MANIALINK_ID'] .'00">';
	$xml .= '<manialink id="'. $pbcps_config['MANIALINK_ID'] .'02">';
	$xml .= '<frame posn="'. $pbcps_config['WIDGET']['POSITION_X'] .' '. $pbcps_config['WIDGET']['POSITION_Y'] .' 3">';
	$xml .= '<label posn="35.2 -0.5 0.12" sizen="2.85 19" action="'. $pbcps_config['MANIALINK_ID'] .'04" focusareacolor1="FFF9" focusareacolor2="FFFF" text=" "/>';
	$xml .= '</frame>';
	$xml .= '</manialink>';
	$xml .= '<manialink id="'. $pbcps_config['MANIALINK_ID'] .'01">';
	$xml .= '<frame posn="'. $pbcps_config['WIDGET']['POSITION_X'] .' '. $pbcps_config['WIDGET']['POSITION_Y'] .' 3">';
	$xml .= '<quad posn="-0.5 -0.5 0.11" sizen="38.6 18" style="BgsPlayerCard" substyle="ProgressBar"/>';
	$xml .= '<quad posn="11.5 -0.65 0.13" sizen="0.1 18" bgcolor="FFF5"/>';
	$xml .= '<quad posn="23.5 -0.65 0.13" sizen="0.1 18" bgcolor="FFF5"/>';
	$xml .= '<quad posn="35 1 0.13" sizen="3.3 3.3" action="'. $pbcps_config['MANIALINK_ID'] .'04" style="BgRaceScore2" substyle="ScoreLink"/>';
	$xml .= '<quad posn="35.6 -12.8 0.13" sizen="2 2" action="'. $pbcps_config['MANIALINK_ID'] .'04" style="Icons64x64_1" substyle="TrackInfo"/>';
	$xml .= '</frame>';
	$xml .= '</manialink>';
	$xml .= '</manialinks>';

	if ($login != false) {
		// Send to $login
		$aseco->client->query('SendDisplayManialinkPageToLogin', $login, $xml, 0, false);
	}
	else {
		// Send to all Players
		$aseco->client->query('SendDisplayManialinkPage', $xml, 0, false);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbcps_buildHelpWindow ($login, $display = true) {
	global $aseco, $pbcps_config;


	// Set the message
	if ($aseco->server->gameinfo->mode == 4) {
		$message = array(
			'With this Widget you can see the differences between your driven scores for each Checkpoint. If you have already a local record,',
			'then this record are inserted instead an empty entry.',
			'',
			'Detailed description of the scores:',
			'  $F00+0:00.28 $FF0 You have a lower score as the run before',
			'  $0DF±0:00.00 $FF0 You have exact the same score as the run before',
			'  $0F0-0:00.32  $FF0 You have a better score as the run before',
			'   $FFF---         $FF0 Checkpoint was first time reached',
			'',
			'The last reached Checkpoints blinks, so you can find quickly the last reached Checkpoint.',
			'',
			'The Bar at the right of this Widget changes the color in exact the same behavior as the Checkpoint-Scores, so you does not need',
			'to look at every Checkpoint to this Widget, you can see your differences just out of the corners of your eyes.'
		);
	}
	else {
		$timeformat = (($pbcps_config['WIDGET']['FULLTIME'] == true) ? '-:--.--     ' : '-.--        ');
		$message = array(
			'With this Widget you can see the differences between your driven times for each Checkpoint. If you have already a local record,',
			'then this record are inserted instead an empty entry.',
			'',
			'Detailed description of the times:',
			'  $F00+0:00.28 $FF0 You was slower as the run before',
			'  $0DF±0:00.00 $FF0 You was exact same fast as the run before',
			'  $0F0-0:00.32  $FF0 You was faster as the run before',
			'   $FFF'. $timeformat .'$FF0 Checkpoint was first time reached',
			'',
			'The last reached Checkpoints blinks, so you can find quickly the last reached Checkpoint.',
			'',
			'The Bar at the right of this Widget changes the color in exact the same behavior as the Checkpoint-Times, so you does not need',
			'to look at every Checkpoint to this Widget, you can see your differences just out of the corners of your eyes.'
		);
	}


	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks id="'. $pbcps_config['MANIALINK_ID'] .'00">';
	$xml .= '<manialink id="'. $pbcps_config['MANIALINK_ID'] .'03">';

	if ($display == true) {
		// Window
		$xml .= '<frame posn="-40.1 30.45 -3">';	// BEGIN: Window Frame
		$xml .= '<quad posn="0.8 -0.8 0.01" sizen="78.4 53.7" bgcolor="000B"/>';
		$xml .= '<quad posn="-0.2 0.2 0.09" sizen="80.4 55.7" style="Bgs1InRace" substyle="BgCard3"/>';

		// Header Line
		$xml .= '<quad posn="0.8 -1.3 0.02" sizen="78.4 3" bgcolor="09FC"/>';
		$xml .= '<quad posn="0.8 -4.3 0.03" sizen="78.4 0.1" bgcolor="FFF9"/>';
		$xml .= '<quad posn="1.8 -1.4 0.10" sizen="2.8 2.8" style="BgRaceScore2" substyle="ScoreLink"/>';
		$xml .= '<label posn="5.5 -1.8 0.10" sizen="74 0" halign="left" textsize="2" scale="0.9" textcolor="FFFF" text="Help for Personal Best Checkpoints"/>';

		$xml .= '<quad posn="2.7 -54.1 0.12" sizen="16 1" url="http://www.undef.de/Trackmania/Plugins/" bgcolor="0000"/>';
		$xml .= '<label posn="2.7 -54.1 0.12" sizen="30 1" halign="left" textsize="1" scale="0.7" textcolor="000F" text="PERSONAL-BEST-CHECKPOINTS/'. $pbcps_config['VERSION'] .'"/>';

		// Close Button
		$xml .= '<frame posn="77.4 1.3 0">';
		$xml .= '<quad posn="0 0 0.10" sizen="4 4" style="Icons64x64_1" substyle="ArrowDown"/>';
		$xml .= '<quad posn="1.1 -1.35 0.11" sizen="1.8 1.75" bgcolor="EEEF"/>';
		$xml .= '<quad posn="0.65 -0.7 0.12" sizen="2.6 2.6" action="'. $pbcps_config['MANIALINK_ID'] .'05" style="Icons64x64_1" substyle="Close"/>';
		$xml .= '</frame>';

		// Set the width of MessageWindow
		$width = 75;
		$line_height = 1.65;
		$position = 0;

		$xml .= '<frame posn="3 -6 0">';
		foreach ($message as $msg) {
			if ($msg) {
				$xml .= '<label posn="0 '. $position .' 0.05" sizen="'. ($width-2.6) .' 0" halign="left" textsize="1" textcolor="FFFF" text="'. $msg .'"/>';
			}
			$position -= $line_height;
		}
		$xml .= '</frame>';


		$xml .= '</frame>';	// Window
	}

	$xml .= '</manialink>';
	$xml .= '</manialinks>';

	// Send Help to given Player
	$aseco->client->query('SendDisplayManialinkPageToLogin', $login, $xml, 0, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function pbcps_buildPersonalCheckpointsTimeInlay ($player, $cpid = -1) {
	global $aseco, $pbcps_config;


	// Set actual Checkpoint
	$CheckpointId = (($cpid != -1) ? $cpid : 0);

	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks id="'. $pbcps_config['MANIALINK_ID'] .'00">';
	$xml .= '<manialink id="'. $pbcps_config['MANIALINK_ID'] .'02">';
	$xml .= '<frame posn="'. $pbcps_config['WIDGET']['POSITION_X'] .' '. $pbcps_config['WIDGET']['POSITION_Y'] .' 3">';
	$xml .= '<label posn="35.2 -0.5 0.12" sizen="2.85 19" action="'. $pbcps_config['MANIALINK_ID'] .'04" focusareacolor1="'. (($cpid == -1) ? 'FFF' : $player->data['BPCPS_TIMES'][$CheckpointId]->bar_color) .'9" focusareacolor2="FFFF" text=" "/>';

	$lines = 1;
	$posx = 0;
	$posy = 0;
	$offsety = 1.37;
	$CheckpointCount = 0;
	foreach ($player->data['BPCPS_TIMES'] as $struct) {

		// Show max. $pbcps_config['SHOW_MAX_CHECKPOINTS'] Checkpoints
		if (($CheckpointCount+1) > $pbcps_config['SHOW_MAX_CHECKPOINTS']) {
			break;
		}

//		// Hide not passed Checkpoints
//		if ($CheckpointCount > $CheckpointId) {
//			break;
//		}

		// Do not show Finish
		if (($CheckpointCount+1) == $pbcps_config['CHALLENGE']['NUM_CPS']) {
			break;
		}


		// Check for max. line count
		if ($lines == 11) {
			$lines = 1;
		}
		$posy = -($offsety * $lines);

		// Check for next block
		if ( ($CheckpointCount == 10) || ($CheckpointCount == 20) ) {
			$posx += 12;
		}


		if ( ($CheckpointCount == $CheckpointId) && ($cpid != -1) ) {
			// Highlight current Checkpoint
 			$xml .= '<format style="TextTitle2Blink"/>';
		}
		else {
			// No Highlight
 			$xml .= '<format style="TextStaticMedium"/>';
		}

		// Highlight last reached Checkpoint
		$prefix = '';
		if ( ($player->data['BPCPS_LASTCP'] == $CheckpointCount) && ($cpid == -1) ) {
 			$prefix = '$O';
		}

		$xml .= '<label posn="'. ($posx + 1.85) .' '. $posy .' 0.14" sizen="1.5 0" halign="right" textsize="'. $pbcps_config['WIDGET']['TEXTSIZE'] .'" scale="'. $pbcps_config['WIDGET']['TEXTSCALE'] .'" textcolor="FFFF" text="'. $prefix . ($CheckpointCount+1) .'."/>';
		$xml .= '<label posn="'. ($posx + 6.3) .' '. $posy .' 0.14" sizen="4.3 0" halign="right" textsize="'. $pbcps_config['WIDGET']['TEXTSIZE'] .'" scale="'. $pbcps_config['WIDGET']['TEXTSCALE'] .'" textcolor="FFFF" text="'. $prefix . (($aseco->server->gameinfo->mode == 4) ? $player->data['BPCPS_TIMES'][$CheckpointCount]->best_time : pbcps_formatTime($player->data['BPCPS_TIMES'][$CheckpointCount]->best_time)) .'"/>';
		$xml .= '<label posn="'. ($posx + 10.6) .' '. $posy .' 0.14" sizen="4.3 0" halign="right" textsize="'. $pbcps_config['WIDGET']['TEXTSIZE'] .'" scale="'. $pbcps_config['WIDGET']['TEXTSCALE'] .'" text="'. $prefix . $player->data['BPCPS_TIMES'][$CheckpointCount]->diff_styled .'"/>';

		$CheckpointCount++;
		$lines++;
	}

	$xml .= '</frame>';
	$xml .= '</manialink>';
	$xml .= '</manialinks>';

	$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// Stolen from basic.inc.php and adjusted
function pbcps_formatTime ($MwTime, $hsec = true) {
	global $aseco;

	if ($MwTime == -1) {
		return '???';
	}
	else {
		$hseconds = (($MwTime - (floor($MwTime/1000) * 1000)) / 10);
		$MwTime = floor($MwTime / 1000);
		$hours = floor($MwTime / 3600);
		$MwTime = $MwTime - ($hours * 3600);
		$minutes = floor($MwTime / 60);
		$MwTime = $MwTime - ($minutes * 60);
		$seconds = floor($MwTime);
		if ($hsec) {
			if ($hours) {
				return sprintf('%d:%02d:%02d.%02d', $hours, $minutes, $seconds, $hseconds);
			}
			else {
				return sprintf('%d:%02d.%02d', $minutes, $seconds, $hseconds);
			}
		}
		else {
			if ($hours) {
				return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
			}
			else {
				return sprintf('%d:%02d', $minutes, $seconds);
			}
		}
	}
}

?>
