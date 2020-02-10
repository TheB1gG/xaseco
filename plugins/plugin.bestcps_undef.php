<?php

/*
 * Plugin Best Checkpoint Times
 * This Plugin is similar with the "Best Checkpoints Widget" from afisse
 * (see http://www.tm-forum.com/viewtopic.php?t=22232), but it looks like
 * my "Personal Best Checkpoints" Plugin (see http://www.tm-forum.com/viewtopic.php?f=127&t=25976).
 *
 * This Plugin works only with TMF!
 * ----------------------------------------------------------------------------------
 * Author:		undef.de
 * Version:		1.0.2
 * Date:		2011-11-10
 * Copyright:		2011 by undef.de
 * Home:		http://www.undef.de/Trackmania/Plugins/
 * System:		XAseco/1.13+
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
 * Dependencies:	none
 */

/* The following manialink id's are used in this plugin (the 915 part of id can be changed on trouble):
 * 92001		id for manialink for the Widget itself
 * 92002		id for manialink Checkpoint-Times and Differences
 * 92003		id for manialink for the Help
 * 92004		id for action to display Help
 * 92005		id for action to close Help
 */

Aseco::registerEvent('onSync',				'bct_onSync');
Aseco::registerEvent('onCheckpoint',			'bct_onCheckpoint');
Aseco::registerEvent('onNewChallenge2',			'bct_onNewChallenge2');
Aseco::registerEvent('onPlayerConnect',			'bct_onPlayerConnect');
Aseco::registerEvent('onPlayerInfoChanged',		'bct_onPlayerInfoChanged');
Aseco::registerEvent('onPlayerManialinkPageAnswer',	'bct_onPlayerManialinkPageAnswer');
Aseco::registerEvent('onEndRace1',			'bct_onEndRace1');
Aseco::registerEvent('onRestartChallenge',		'bct_onRestartChallenge');

global $bct_config;

/* Just a note for my editor -> utf-8 äöüß */

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function bct_onSync ($aseco) {
	global $bct_config;


	// Check for the right XAseco-Version
	if ( defined('XASECO_VERSION') ) {
		if ( version_compare(XASECO_VERSION, 1.13, '<') ) {
			trigger_error('[plugin.best_checkpoint_times.php] Not supported XAseco version ('. XASECO_VERSION .')! Please update to min. version 1.13!', E_USER_ERROR);
		}
	}
	else {
		trigger_error('[plugin.best_checkpoint_times.php] Can not identify the System, "XASECO_VERSION" is unset! This plugin runs only with XAseco/1.13 and up.', E_USER_ERROR);
	}

	if ($aseco->server->getGame() != 'TMF') {
		trigger_error('[plugin.best_checkpoint_times.php] This plugin supports only TMF, can not start with a '. $aseco->server->getGame() .' Dedicated-Server!', E_USER_ERROR);
	}

	// Set 'checkpoint_list' off, this place we need
	//setCustomUIField('checkpoint_list', false);

	$bct_config['MANIALINK_ID'] = '920';
	$bct_config['VERSION'] = '1.0.2';

	// Register this to the global version pool (for up-to-date checks)
	$aseco->plugin_versions[] = array(
		'plugin'	=> 'plugin.best_checkpoint_times.php',
		'author'	=> 'undef.de',
		'version'	=> $bct_config['VERSION']
	);

	$bct_config['WIDGET']['POSITION_X'] = -64.3;
	$bct_config['WIDGET']['POSITION_Y'] = 49;
	$bct_config['WIDGET']['TEXTSIZE'] = 1;
	$bct_config['WIDGET']['TEXTSCALE'] = 0.9;
	$bct_config['SHOW_MAX_CHECKPOINTS'] = 20;			// 20 is max.

	// Set current state
	$bct_config['CURRENT_STATE'] = 1;
	$bct_config['CHALLENGE'] = array();
	$bct_config['CHECKPOINT_TIMES'] = array();
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function bct_onPlayerConnect ($aseco, $player) {
	global $bct_config;


	// Need to do a trick, because on XAseco StartUp, there is no Challenge loaded
	// and XAseco can not give the number of Checkpoints. So just fill the maximum.
	// When the Challenge is loaded, then this get fixed to the correct number of
	// Checkpoints.
	// If a Player joins when XAseco runs a longer time, then there is the correct
	// number of Checkpoints, because the Challenge is already loaded.
	$bct_config['CHALLENGE']['NUM_CPS'] = (isset($bct_config['CHALLENGE']['NUM_CPS']) ? $bct_config['CHALLENGE']['NUM_CPS'] : $bct_config['SHOW_MAX_CHECKPOINTS']);

	// Display the empty Widget
	bct_buildWidget($player->login);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onPlayerInfoChanged
function bct_onPlayerInfoChanged ($aseco, $info) {
	global $bct_config;


	// Nothing todo at Score
	if ($bct_config['CURRENT_STATE'] != 6) {

		// Get Player
		$player = $aseco->server->players->getPlayer($info['Login']);

		if ($info['SpectatorStatus'] > 0) {
			$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
			$xml .= '<manialinks>';
			$xml .= '<manialink id="'. $bct_config['MANIALINK_ID'] .'01"></manialink>';
			$xml .= '<manialink id="'. $bct_config['MANIALINK_ID'] .'02"></manialink>';
			$xml .= '</manialinks>';

			// Setup state
			$player->data['BestCheckpointTimesHideWidget'] = true;

			// Hide at Scoretable
			$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);
		}
		else {
			// Remove state
			unset($player->data['BestCheckpointTimesHideWidget']);

			// Display the empty Widget
			bct_buildWidget($player->login);

			// Display the Checkpoints Time for Player
			bct_buildCheckpointsTimeInlay();
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onPlayerManialinkPageAnswer
function bct_onPlayerManialinkPageAnswer ($aseco, $answer) {
	global $bct_config;


	// If id = 0, bail out immediately
	if ($answer[2] == 0) {
		return;
	}

	// Get Player
	$player = $aseco->server->players->getPlayer($answer[1]);

	if ($answer[2] == $bct_config['MANIALINK_ID'] .'04') {			// Display Help

		bct_buildHelpWindow($player->login, true);

	}
	else if ($answer[2] == $bct_config['MANIALINK_ID'] .'05') {		// Close Help

		bct_buildHelpWindow($player->login, false);

	}

}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function bct_onNewChallenge2 ($aseco, $challenge_item) {
	global $bct_config;


	// Set current state
	$bct_config['CURRENT_STATE'] = 1;

	// Display the empty Widget to all Players)
	bct_buildWidget(false);

	// Save the number of Checkpoints of this Map and if this Map is a Multilap
	$bct_config['CHALLENGE']['NUM_CPS'] = $challenge_item->nbchecks;
	$bct_config['CHALLENGE']['MULTILAP'] = $challenge_item->laprace;

	// Setup the Array of CheckpointTimes with empty entries for all Checkpoints
	for ($cp = 0; $cp < $bct_config['CHALLENGE']['NUM_CPS']; $cp ++) {
		$bct_config['CHECKPOINT_TIMES'][$cp]['Score'] = 0;
		$bct_config['CHECKPOINT_TIMES'][$cp]['Nickname'] = '---';
	}

	bct_buildCheckpointsTimeInlay();
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function bct_onRestartChallenge ($aseco, $challenge_item) {
	global $bct_config;


	// Set current state
	$bct_config['CURRENT_STATE'] = 1;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// TMN: [0]=PlayerUid, [1]=Login, [2]=Time, [3]=Score, [4]=CheckpointIndex
// TMF: [0]=PlayerUid, [1]=Login, [2]=TimeScore, [3]=CurLap, [4]=CheckpointIndex
function bct_onCheckpoint ($aseco, $checkpt) {
	global $bct_config;


	// Set the author of this action
	$player = $aseco->server->players->getPlayer($checkpt[1]);
	$score = $checkpt[2];
	$round = $checkpt[3];
	$CheckpointId = $checkpt[4];


	// Bail out if no setup was done at bct_onNewChallenge2(),
	// this is a problem at a running Server with Players on.
	if ( !isset($bct_config['CHECKPOINT_TIMES'][$CheckpointId]) ) {
		return;
	}


//	$aseco->console('***B CP:'. sprintf("%02d", $CheckpointId) .' RND:'. $round .' TIME:'. $score);

	// Special work for Multilaps Maps in Gamemode 'Laps'
	if ( ($aseco->server->gameinfo->mode == 3) && ($bct_config['CHALLENGE']['MULTILAP'] == true) ) {

		// Correct CheckpointId in Multilaps Maps
		$finish = false;
		if ( ($CheckpointId+1) == ($bct_config['CHALLENGE']['NUM_CPS'] * $round) ) {
			$round -= 1;
		}
		if ($round > 0) {
			// The Checkpoints counts up, but i need the Id of the Checkpoint
			$cp = ($CheckpointId - ($bct_config['CHALLENGE']['NUM_CPS'] * $round));
			if ($cp >= 0) {
				$CheckpointId = $cp;
			}
		}
	}

//	$aseco->console('***A CP:'. sprintf("%02d", $CheckpointId) .' RND:'. $round .' TIME:'. $score .LF);


	// Check if the actual Player has a better Score/Time
	$refresh = false;
	if ($bct_config['CHECKPOINT_TIMES'][$CheckpointId]['Score'] > 0) {

		if ( ($aseco->server->gameinfo->mode == 4) && ($score > $bct_config['CHECKPOINT_TIMES'][$CheckpointId]['Score']) ) {
			// Stunt: Higher = Better
			$bct_config['CHECKPOINT_TIMES'][$CheckpointId]['Score'] = $score;
			$bct_config['CHECKPOINT_TIMES'][$CheckpointId]['Nickname'] = bct_handleSpecialChars($player->nickname);
			$refresh = true;
		}
		else if ($score < $bct_config['CHECKPOINT_TIMES'][$CheckpointId]['Score']) {
			// All other: Lower = Better
			$bct_config['CHECKPOINT_TIMES'][$CheckpointId]['Score'] = $score;
			$bct_config['CHECKPOINT_TIMES'][$CheckpointId]['Nickname'] = bct_handleSpecialChars($player->nickname);
			$refresh = true;
		}
	}
	else {
		$bct_config['CHECKPOINT_TIMES'][$CheckpointId]['Score'] = $score;
		$bct_config['CHECKPOINT_TIMES'][$CheckpointId]['Nickname'] = bct_handleSpecialChars($player->nickname);
		$refresh = true;
	}

	if ($refresh == true) {
		// Display the Inlay to all Players
		bct_buildCheckpointsTimeInlay($CheckpointId);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onEndRace1
function bct_onEndRace1 ($aseco, $race) {
	global $bct_config;


	// Set current state
	$bct_config['CURRENT_STATE'] = 6;

	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $bct_config['MANIALINK_ID'] .'01"></manialink>';
	$xml .= '<manialink id="'. $bct_config['MANIALINK_ID'] .'02"></manialink>';
	$xml .= '</manialinks>';

	// Reset the Array of CheckpointTimes
	$bct_config['CHECKPOINT_TIMES'] = array();

	// Hide at Scoretable
	$aseco->client->query('SendDisplayManialinkPage', $xml, 0, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function bct_buildWidget ($login = false) {
	global $aseco, $bct_config;


	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $bct_config['MANIALINK_ID'] .'01">';
	$xml .= '<frame posn="'. $bct_config['WIDGET']['POSITION_X'] .' '. $bct_config['WIDGET']['POSITION_Y'] .' 3">';
	$xml .= '<quad posn="-0.5 -0.5 0.11" sizen="38.6 18" />'; //style="BgsPlayerCard" substyle="BgRacePlayerLine"
	$xml .= '<quad posn="17.5 -0.65 0.13" sizen="0.1 18" />';
	$xml .= '<quad posn="35 1 0.13" sizen="3.3 3.3" action="'. $bct_config['MANIALINK_ID'] .'04" />';
	$xml .= '<quad posn="35.6 -12.8 0.13" sizen="2 2" action="'. $bct_config['MANIALINK_ID'] .'04" />';
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

function bct_buildHelpWindow ($login, $display = true) {
	global $aseco, $bct_config;


	$message = array(
		'With this Widget you can see who has the fastest Time/Score at the related Checkpoint. The last fastest Time/Score blinks,',
		'so you can easy find the latest beaten Checkpoint.',
		'',
		'If nobody has a fastest Time/Score at some Checkpoint, then the Widget displays empty times. After someone drives through',
		'a Checkpoint, this time is indicated in the Widget.'
	);

	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $bct_config['MANIALINK_ID'] .'03">';

	if ($display == true) {
		// Window
		$xml .= '<frame posn="-40.1 30.45 -3">';	// BEGIN: Window Frame
		$xml .= '<quad posn="0.8 -0.8 0.01" sizen="78.4 53.7" bgcolor="000B"/>';
		$xml .= '<quad posn="-0.2 0.2 0.09" sizen="80.4 55.7" style="Bgs1InRace" substyle="BgCard3"/>';

		// Header Line
		$xml .= '<quad posn="0.8 -1.3 0.02" sizen="78.4 3" bgcolor="09FC"/>';
		$xml .= '<quad posn="0.8 -4.3 0.03" sizen="78.4 0.1" bgcolor="FFF9"/>';
		$xml .= '<quad posn="1.8 -1.4 0.10" sizen="2.8 2.8" style="BgRaceScore2" substyle="ScoreLink"/>';
		$xml .= '<label posn="5.5 -1.8 0.10" sizen="74 0" halign="left" textsize="2" scale="0.9" textcolor="FFFF" text="Help for Best Checkpoint Times"/>';

		$xml .= '<quad posn="2.7 -54.1 0.12" sizen="16 1" url="http://www.undef.de/Trackmania/Plugins/" bgcolor="0000"/>';
		$xml .= '<label posn="2.7 -54.1 0.12" sizen="30 1" halign="left" textsize="1" scale="0.7" textcolor="000F" text="BEST-CHECKPOINT-TIMES/'. $bct_config['VERSION'] .'"/>';

		// Close Button
		$xml .= '<frame posn="77.4 1.3 0">';
		$xml .= '<quad posn="0 0 0.10" sizen="4 4" style="Icons64x64_1" substyle="ArrowDown"/>';
		$xml .= '<quad posn="1.1 -1.35 0.11" sizen="1.8 1.75" bgcolor="EEEF"/>';
		$xml .= '<quad posn="0.65 -0.7 0.12" sizen="2.6 2.6" action="'. $bct_config['MANIALINK_ID'] .'05" style="Icons64x64_1" substyle="Close"/>';
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

function bct_buildCheckpointsTimeInlay ($cpid = -1) {
	global $aseco, $bct_config;


	// Set actual Checkpoint
	$CheckpointId = (($cpid != -1) ? $cpid : 0);

	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $bct_config['MANIALINK_ID'] .'02">';
	$xml .= '<frame posn="'. $bct_config['WIDGET']['POSITION_X'] .' '. $bct_config['WIDGET']['POSITION_Y'] .' 3">';

	$lines = 1;
	$posx = 0;
	$posy = 0;
	$offsety = 1.37;
	$CheckpointCount = 0;
	for ($cp = 0; $cp < $bct_config['CHALLENGE']['NUM_CPS']; $cp ++) {

		// Show max. $bct_config['SHOW_MAX_CHECKPOINTS'] Checkpoints
		if (($cp+1) > $bct_config['SHOW_MAX_CHECKPOINTS']) {
			break;
		}

		// Do not show Finish
		if (($CheckpointCount+1) == $bct_config['CHALLENGE']['NUM_CPS']) {
			break;
		}

		// Break if Checkpoint is not set
		if ( !isset($bct_config['CHECKPOINT_TIMES'][$cp]) ) {
			break;
		}


		// Check for max. line count
		if ($lines == 11) {
			$lines = 1;
		}
		$posy = -($offsety * $lines);

		// Check for next block
		if ( ($CheckpointCount == 10) || ($CheckpointCount == 20) ) {
			$posx += 18;
		}

		if ($bct_config['CHECKPOINT_TIMES'][$cp]['Score'] > 0) {
         // Highlight last reached Checkpoint
         if ( ($CheckpointCount == $CheckpointId) && ($cpid != -1) ) {
            // Highlight current Checkpoint
             $xml .= '<format style="TextTitle2Blink"/>';
         }
         else {
            // No Highlight
             $xml .= '<format style="TextStaticMedium"/>';
         }

         $xml .= '<label posn="'. ($posx + 1.85) .' '. $posy .' 0.14" sizen="1.5 0" halign="right" textsize="'. $bct_config['WIDGET']['TEXTSIZE'] .'" scale="'. $bct_config['WIDGET']['TEXTSCALE'] .'" text="$FFF'. ($cp+1) .'."/>';
         $xml .= '<label posn="'. ($posx + 6.3) .' '. $posy .' 0.14" sizen="4.3 0" halign="right" textsize="'. $bct_config['WIDGET']['TEXTSIZE'] .'" scale="'. $bct_config['WIDGET']['TEXTSCALE'] .'" text="$FFF'. (($aseco->server->gameinfo->mode == 4) ? $bct_config['CHECKPOINT_TIMES'][$cp]['Score'] : bct_formatTime($bct_config['CHECKPOINT_TIMES'][$cp]['Score'])) .'"/>';
         $xml .= '<label posn="'. ($posx + 6.8) .' '. $posy .' 0.14" sizen="11 0" textsize="'. $bct_config['WIDGET']['TEXTSIZE'] .'" scale="'. $bct_config['WIDGET']['TEXTSCALE'] .'" text="$FFF'. $bct_config['CHECKPOINT_TIMES'][$cp]['Nickname'] .'"/>';
      	}

		$CheckpointCount++;
		$lines++;
	}

	$xml .= '</frame>';
	$xml .= '</manialink>';
	$xml .= '</manialinks>';

	// Build the list of Logins to send the inlay to
	$login_list = '';
	foreach ($aseco->server->players->player_list as &$player) {
		if ( !isset($player->data['BestCheckpointTimesHideWidget']) ) {
			$login_list .= $player->login .',';
		}
	}
	unset($player);
	$login_list = substr($login_list, 0, strlen($login_list)-1);		// remove trailing ,

	$aseco->client->query('SendDisplayManialinkPageToLogin', $login_list, $xml, 0, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// Stolen from basic.inc.php and adjusted
function bct_formatTime ($MwTime, $hsec = true) {
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

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function bct_handleSpecialChars ($string) {
	global $re_config;


	// Remove links, e.g. "$(L|H|P)[...]...$(L|H|P)"
	$string = preg_replace('/\${1}(L|H|P)\[.*?\](.*?)\$(L|H|P)/i', '$2', $string);
	$string = preg_replace('/\${1}(L|H|P)\[.*?\](.*?)/i', '$2', $string);
	$string = preg_replace('/\${1}(L|H|P)(.*?)/i', '$2', $string);

	// Remove $S (shadow)
	// Remove $H (manialink)
	// Remove $W (wide)
	// Remove $I (italic)
	// Remove $L (link)
	// Remove $O (bold)
	// Remove $N (narrow)
	$string = preg_replace('/\${1}[SHWILON]/i', '', $string);


	// Convert &
	// Convert "
	// Convert '
	// Convert >
	// Convert <
	$string = str_replace(
			array(
				'&',
				'"',
				"'",
				'>',
				'<'
			),
			array(
				'&amp;',
				'&quot;',
				'&apos;',
				'&gt;',
				'&lt;'
			),
			$string
	);
	$string = stripNewlines($string);	// stripNewlines() from basic.inc.php

	return validateUTF8String($string);	// validateUTF8String() from basic.inc.php
}

?>