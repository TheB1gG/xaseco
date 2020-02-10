<?php

/*
 * Plugin Alternate-Scoretable
 * This plugin is an alternative Scoretable, it's looks nicer (that's my opinion) and
 * shows more nicer information of each Player:
 *  - Player Position
 *  - Player Avatar
 *  - Player Nickname
 *  - The current best time/score for this Track
 *  - The personal best time/score for this Track
 *  - Total-Score at this Server and this Session
 *  - Amount how often this Track was finished or other related counts/scores (depends on GameMode)
 *  - The current Ladder-Rank
 *  - The current Ladder-Score
 *  - The Online-Time since connect to this Server
 *
 * Just take a look at the Screenshots at http://www.tm-forum.com/viewtopic.php?f=127&t=26138
 *
 * This Plugin works only with TMF and all the Gamemodes: Rounds, Team, TimeAttack,
 * Stunts, Laps and Cup. The Layout of the Scoretable in Team-Mode is just a little
 * bit different as the other one.
 *
 * Hardcoded limit of Players in Scoretable in the Gamemodes Rounds, TimeAttack, Stunts,
 * Laps and Cup are 300 and 40 Players in Gamemode Team (20 Players for each Team).
 *
 * Known errors or bad behavior:
 *  - The Total Ladder-Score are display delayed for one Track, because the 'LadderScore'
 *    in the ListMethod GetCurrentRanking() from the dedicated Server are not refreshed
 *    within the Score. It takes some time the 'LadderScore' is refreshed.
 *
 * ----------------------------------------------------------------------------------
 * Author:		undef.de
 * Version:		0.9.0
 * Date:		2011-11-20
 * Copyright:		2009 - 2011 by undef.de
 * Home:		http://www.undef.de/Trackmania/Plugins/
 * System:		XAseco/1.12+
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
 * The Bar-Images included in this Plugin are licensed by the Author under a
 * Creative Commons Attribution-Share Alike 3.0 Germany License.
 * See the following links for details:
 * German:	http://creativecommons.org/licenses/by-sa/3.0/de/legalcode
 * English:	http://creativecommons.org/licenses/by-sa/3.0/legalcode
 *
 * ----------------------------------------------------------------------------------
 *
 * Dependencies:	plugins/plugin.panels.php
 */

/* The following manialink id's are used in this plugin (the 916 part of id can be changed on trouble):
 * 91600		id for (reserved for later use)
 * 91601		id for manialink Scoretable
 * 91602		id for action display Scoretable
 * 91603		id for action close Scoretable
 * 91604		id for manialink Legend
 * 91605		id for action display Legend
 * 91606		id for action close Legend
 * 91607		id for manialink of Quicklink-Button
 * 91608		id for manialink of PlayerWidget
 * 91609		id for action close PlayerWidget
 * 91610		id for manialink PlayerPlaceWidget
 * 91611-20		id for (reserved for later use)
 * 91620-91635		id for action previous page in Scoretable (max. 15 pages)
 * 91640-91655		id for action next page in Scoretable (max. 15 pages)
 * 91660-9169999999	id for identify selected player for PlayerWidget
 *
 */

Aseco::registerEvent('onSync',				'ast_onSync');
Aseco::registerEvent('onStatusChangeTo3',		'ast_onStatusChangeTo3');
Aseco::registerEvent('onStatusChangeTo5',		'ast_onStatusChangeTo5');
Aseco::registerEvent('onPlayerConnect',			'ast_onPlayerConnect');
Aseco::registerEvent('onPlayerFinish',			'ast_onPlayerFinish');
Aseco::registerEvent('onPlayerInfoChanged',		'ast_onPlayerInfoChanged');
Aseco::registerEvent('onPlayerDisconnect',		'ast_onPlayerDisconnect');
Aseco::registerEvent('onPlayerManialinkPageAnswer',	'ast_onPlayerManialinkPageAnswer');
Aseco::registerEvent('onNewChallenge',			'ast_onNewChallenge');
Aseco::registerEvent('onNewChallenge2',			'ast_onNewChallenge2');
Aseco::registerEvent('onRestartChallenge2',		'ast_onRestartChallenge2');
Aseco::registerEvent('onCheckpoint',			'ast_onCheckpoint');
Aseco::registerEvent("onBeginRound", 			'ast_onBeginRound');
Aseco::registerEvent("onEndRound", 			'ast_onEndRound');
Aseco::registerEvent('onEndRace1',			'ast_onEndRace1');
Aseco::registerEvent('onEverySecond',			'ast_onEverySecond');
Aseco::registerEvent('onShutdown',			'ast_onShutdown');

Aseco::addChatCommand('scoretable',			'Display the alternate Scoretable (see: /scoretable help)');

global $ast_config, $ast_todo, $ast_ranks;
$ast_todo = array();

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onSync
function ast_onSync ($aseco) {
	global $ast_config, $ast_ranks;


	// Check for the right XASECO-Version
	if ( defined('XASECO_VERSION') ) {
		if ( version_compare(XASECO_VERSION, 1.12, '<') ) {
			trigger_error('[plugin.alternate_scoretable.php] Not supported xaseco version ('. XASECO_VERSION .')! Please update to min. version 1.12!', E_USER_ERROR);
		}
	}
	else {
		trigger_error('[plugin.alternate_scoretable.php] Can not identify the System, "XASECO_VERSION" is unset! This plugin runs only with XASECO/1.12 and up.', E_USER_ERROR);
	}


	// Check for dependencies
	if ( !function_exists('panels_default') ) {
		trigger_error('[plugin.alternate_scoretable.php] Missing dependent plugin, please activate "plugin.panels.php" in "plugins.xml" and restart.', E_USER_ERROR);
	}

	if ($aseco->server->getGame() != 'TMF') {
		trigger_error('[plugin.alternate_scoretable.php] This plugin supports only TMF, can not start with a '. $aseco->server->getGame() .' Dedicated-Server!', E_USER_ERROR);
	}


	if (!$ast_config = $aseco->xml_parser->parseXML('alternate_scoretable.xml')) {
		trigger_error('[plugin.alternate_scoretable.php] Could not read/parse config file "alternate_scoretable.xml"!', E_USER_ERROR);
	}

	$ast_config = $ast_config['ALTERNATE_SCORETABLE'];


	$ast_config['MANIALINK_ID'] = '916';
	$ast_config['VERSION'] = '0.9.0';
	$ast_config['PLAYERS'] = array();
	$ast_config['WarmUpPhase'] = false;

	// Register this to the global version pool (for up-to-date checks)
	$aseco->plugin_versions[] = array(
		'plugin'	=> 'plugin.alternate_scoretable.php',
		'author'	=> 'undef.de',
		'version'	=> $ast_config['VERSION']
	);


	// Set current State
	$ast_config['CURRENT_STATE'] = 'race';

	// Set Score-Display-Time
	if ($ast_config['SCORE_DISPLAY_TIME'][0] < 15) {
		$ast_config['SCORE_DISPLAY_TIME'][0] = 15;
	}
	$aseco->client->query('SetChatTime', (intval($ast_config['SCORE_DISPLAY_TIME'][0] / 1.3333) * 1000));		// Milliseconds


	$ast_config['PLAYER_PLACES_WIDGET'][0]['ENABLED'][0] = ((strtoupper($ast_config['PLAYER_PLACES_WIDGET'][0]['ENABLED'][0]) == 'TRUE') ? true : false);
	// Check for the display timeout of the PlayerPlaceWidget
	if ( !isset($ast_config['PLAYER_PLACES_WIDGET'][0]['TIMEOUT'][0]) ) {
		$ast_config['PLAYER_PLACES_WIDGET'][0]['TIMEOUT'][0] = 5;
	}

	// Check for the textcolor of the PlayerPlaceWidget
	if ( !isset($ast_config['PLAYER_PLACES_WIDGET'][0]['TEXTCOLOR'][0]['DEFAULT'][0]) ) {
		$ast_config['PLAYER_PLACES_WIDGET'][0]['TEXTCOLOR'][0]['DEFAULT'][0] = '0B3';
	}
	if ( !isset($ast_config['PLAYER_PLACES_WIDGET'][0]['TEXTCOLOR'][0]['SECURED'][0]) ) {
		$ast_config['PLAYER_PLACES_WIDGET'][0]['TEXTCOLOR'][0]['SECURED'][0] = '0BF';
	}
	if ( !isset($ast_config['PLAYER_PLACES_WIDGET'][0]['TEXTCOLOR'][0]['HIGHLITE'][0]) ) {
		$ast_config['PLAYER_PLACES_WIDGET'][0]['TEXTCOLOR'][0]['HIGHLITE'][0] = 'FFF';
	}

	// Check for the textsize of the PlayerPlaceWidget
	if ( !isset($ast_config['PLAYER_PLACES_WIDGET'][0]['TEXTSIZE'][0]) ) {
		$ast_config['PLAYER_PLACES_WIDGET'][0]['TEXTSIZE'][0] = 2;
	}

	// Check for the position of the PlayerPlaceWidget
	if ( !isset($ast_config['PLAYER_PLACES_WIDGET'][0]['POS_X'][0]) ) {
		$ast_config['PLAYER_PLACES_WIDGET'][0]['POS_X'][0] = -43;
	}
	if ( !isset($ast_config['PLAYER_PLACES_WIDGET'][0]['POS_Y'][0]) ) {
		$ast_config['PLAYER_PLACES_WIDGET'][0]['POS_Y'][0] = -28;
	}


	// What layout?
	if ( !isset($ast_config['GRAPHIC_MODE'][0]) ) {
		$ast_config['GRAPHIC_MODE'][0] = 'external';
	}
	else {
		$ast_config['GRAPHIC_MODE'][0] = strtolower($ast_config['GRAPHIC_MODE'][0]);
		if ( (!$ast_config['GRAPHIC_MODE'][0] == 'external') || (!$ast_config['GRAPHIC_MODE'][0] == 'internal') || (!$ast_config['GRAPHIC_MODE'][0] == 'minimal') ) {
			// Setup default
			$ast_config['GRAPHIC_MODE'][0] = 'external';
		}
	}

	// Transform 'TRUE' or 'FALSE' from string to boolean
	$ast_config['SHOW_AT_FINISH'][0]			= ((strtoupper($ast_config['SHOW_AT_FINISH'][0]) == 'TRUE')			? true : false);
	$ast_config['QUICKLINK_BUTTON'][0]['ENABLED'][0]	= ((strtoupper($ast_config['QUICKLINK_BUTTON'][0]['ENABLED'][0]) == 'TRUE')	? true : false);

	// Show the Quicklink button?
	if ($ast_config['QUICKLINK_BUTTON'][0]['ENABLED'][0] == true) {
		// $caller, $display
		ast_widgetQuicklink(false, true);
	}

	// Remove the function scorepanel_on() and scorepanel_off() from manialinks.inc.php at the event 'onEndRace' / 'onNewChallenge'
	// to prevent displaying Automatic Scoretable instead Alternate Scoretable
	$array_pos = 0;
	foreach ($aseco->events['onEndRace'] as $func_name) {
		if ($func_name == 'scorepanel_on') {
			unset($aseco->events['onEndRace'][$array_pos]);
			break;
		}
		$array_pos ++;
	}
	$array_pos = 0;
	foreach ($aseco->events['onNewChallenge'] as $func_name) {
		if ($func_name == 'scorepanel_off') {
			unset($aseco->events['onNewChallenge'][$array_pos]);
			break;
		}
		$array_pos ++;
	}

	// Disable automatic Scoretable
	setCustomUIField('scoretable', false);

	if ($ast_config['PLAYER_PLACES_WIDGET'][0]['ENABLED'][0] == true) {
		setCustomUIField('notice', false);
	}

	// Reset Ranking
	$ast_ranks = array();
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onShutdown
function ast_onShutdown ($aseco) {


	// Make sure the Dedicated-Server have the control
	$aseco->client->query('ManualFlowControlEnable', false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onEverySecond
function ast_onEverySecond ($aseco) {
	global $ast_config, $ast_todo;


	// Do Player based delayed calls
	foreach ($ast_config['PLAYERS'] as $login => $struct) {
		if ($ast_config['PLAYERS'][$login]['delayed_call'] != false) {

			// Start the job if wait is over
			if (time() >= $ast_config['PLAYERS'][$login]['delayed_call']['delay']) {
				eval($ast_config['PLAYERS'][$login]['delayed_call']['func']);
				$ast_config['PLAYERS'][$login]['delayed_call'] = false;
			}
		}
	}

	// Bail out if nothing to do for players(global) delayed calls
	if (count($ast_todo) == 0) {
		return;
	}

	// Start the jobs at given times
	foreach ($ast_todo as &$job) {

		// Skip job if already done
		if ($job['delay'] == -1) {
			continue;
		}

		// Start the job if wait is over
		if (time() >= $job['delay']) {
			eval($job['func']);
			$job['delay'] = -1;
			$job['func'] = false;
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function chat_scoretable ($aseco, $command) {
	global $ast_config;


	// Check optional parameter
	if (strtoupper($command['params']) == 'HELP') {

		ast_widgetLegend($command['author'], true);

	}
	else {

		// $aseco, $caller, $timeout, $display_close, $page
		ast_showScoretable($aseco, $command['author']->login, 0, true, 0);

	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ast_showScoretable ($aseco, $caller = false, $timeout = 0, $display_close = true, $page = 0) {
	global $ast_config, $ast_ranks;


	// Refresh Ranking
	if ($aseco->server->gameinfo->mode == 3) {
		// 3 = Laps
		ast_getCurrentRanking();
	}

	// Refresh online time and Personal Best of each (connected) Player
	foreach ($aseco->server->players->player_list as &$player) {

		// Get actual connect time
		$ast_config['PLAYERS'][$player->login]['online_since'] = ast_formatTime($player->getTimeOnline() * 1000, false);

		// Get personal best driven time for current map
		$ast_config['PLAYERS'][$player->login]['personal_best'] = $player->panels['pb'];

		// Get Spectator status
		$ast_config['PLAYERS'][$player->login]['isspectator'] = $player->isspectator;
	}


	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $ast_config['MANIALINK_ID'] .'01">';

	// Window
	$xml .= '<frame posn="'. $ast_config['WINDOW'][0][strtoupper($ast_config['CURRENT_STATE'])][0]['POS_X'][0] .' '. $ast_config['WINDOW'][0][strtoupper($ast_config['CURRENT_STATE'])][0]['POS_Y'][0] .' -3">';	// BEGIN: Window Frame
	$xml .= '<quad posn="0.8 -0.8 0.10" sizen="78.4 53.7" bgcolor="'. $ast_config['COLORS'][0]['BACKGROUND'][0] .'"/>';
	$xml .= '<quad posn="-0.2 0.2 0.11" sizen="80.4 55.7" style="Bgs1InRace" substyle="BgCard3"/>';

	// Header Icon
	$xml .= '<quad posn="-1.7 2 0.20" sizen="8 5" style="Icons64x64_1" substyle="RestartRace"/>';

	// Link and About
	$xml .= '<quad posn="2.7 -54.1 0.12" sizen="14.5 1" url="http://www.undef.de/Trackmania/Plugins/" bgcolor="0000"/>';
	$xml .= '<label posn="2.7 -54.1 0.12" sizen="30 1" halign="left" textsize="1" scale="0.7" textcolor="000F" text="ALTERNATE SCORETABLE/'. $ast_config['VERSION'] .'"/>';

	if ( ($display_close == true) && ($ast_config['CURRENT_STATE'] != 'score') ) {
		// Close Button
		$xml .= '<frame posn="77.4 1.3 0">';
		$xml .= '<quad posn="0 0 0.12" sizen="4 4" style="Icons64x64_1" substyle="ArrowDown"/>';
		$xml .= '<quad posn="1.1 -1.35 0.13" sizen="1.8 1.75" bgcolor="EEEF"/>';
		$xml .= '<quad posn="0.65 -0.7 0.14" sizen="2.6 2.6" action="'. $ast_config['MANIALINK_ID'] .'03" style="Icons64x64_1" substyle="Close"/>';
		$xml .= '</frame>';
	}

	// Frame for Legend/Previous/Next Buttons
	$xml .= '<frame posn="67.05 -53.2 0">';

	// AddFavorite button
	$xml .= '<quad posn="-4.95 0 0.12" sizen="3.2 3.2" manialink="addfavorite?action=add&amp;game='. $aseco->server->getGame() .'&amp;server='. rawurlencode($aseco->server->serverlogin) .'&amp;name='. rawurlencode($aseco->server->name) .'&amp;zone='. rawurlencode($aseco->server->zone) .'" addplayerid="1" style="Icons64x64_1" substyle="ArrowDown"/>';
	$xml .= '<quad posn="-4.1 -1.1 0.13" sizen="1.5 1.4" bgcolor="EEEF"/>';
	$xml .= '<quad posn="-4.3 -0.6 0.14" sizen="1.9 1.9" style="Icons128x128_Blink" substyle="ServersFavorites"/>';

	// Legend button
	$xml .= '<quad posn="-1.65 0 0.12" sizen="3.2 3.2" action="'. $ast_config['MANIALINK_ID'] .'05" style="Icons64x64_1" substyle="ArrowDown"/>';
	$xml .= '<quad posn="-0.8 -1.1 0.13" sizen="1.5 1.4" bgcolor="EEEF"/>';
	$xml .= '<quad posn="-1 -0.7 0.14" sizen="1.8 1.8" style="Icons64x64_1" substyle="TrackInfo"/>';

	// Display Previous/Next-Buttons, but not in Team-Mode (2 = Team)
	if ($aseco->server->gameinfo->mode != 2) {
		// Reload button, display not at Score
		if ($ast_config['CURRENT_STATE'] != 'score') {
			$xml .= '<quad posn="1.65 0 0.12" sizen="3.2 3.2" action="'. $ast_config['MANIALINK_ID'] . ($page + 40) .'" style="Icons64x64_1" substyle="Refresh"/>';
		}
		else {
			$xml .= '<quad posn="1.65 0 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
			$xml .= '<quad posn="1.65 0 0.13" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
		}

		// Previous button
		if ($page > 0) {
			$xml .= '<quad posn="4.95 0 0.12" sizen="3.2 3.2" action="'. $ast_config['MANIALINK_ID'] . ($page + 19) .'" style="Icons64x64_1" substyle="ArrowPrev"/>';
		}
		else {
			$xml .= '<quad posn="4.95 0 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
			$xml .= '<quad posn="4.95 0 0.13" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
		}

		// Next button (display only if more pages to display)
		if ( ($page < 15) && (count($ast_ranks) > 20) && (($page+1) < (ceil(count($ast_ranks)/20))) ) {
			$xml .= '<quad posn="8.25 0 0.12" sizen="3.2 3.2" action="'. $ast_config['MANIALINK_ID'] . ($page + 41) .'" style="Icons64x64_1" substyle="ArrowNext"/>';
		}
		else {
			$xml .= '<quad posn="8.25 0 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
			$xml .= '<quad posn="8.25 0 0.13" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
		}
	}
	else {
		// Placeholder Reload
		$xml .= '<quad posn="1.65 0 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
		$xml .= '<quad posn="1.65 0 0.13" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';

		// Placeholder Previous
		$xml .= '<quad posn="4.95 0 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
		$xml .= '<quad posn="4.95 0 0.13" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';

		// Placeholder Next
		$xml .= '<quad posn="8.25 0 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
		$xml .= '<quad posn="8.25 0 0.13" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
	}

	$xml .= '</frame>';



	// BEGIN: Header Frame
	$xml .= '<frame posn="0 0 0">';

	if ($aseco->server->gameinfo->mode == 2) {
		// Team has different Layout

		// BLUE: Avatar and Nickname of Player
		$xml .= '<quad posn="8.3 1.2 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="Buddy"/>';
		$xml .= '<label posn="11.2 -0.15 0.12" sizen="15 3" halign="left" textsize="1" scale="0.7" text="$000PLAYERS TEAM BLUE"/>';

		// BLUE: Besttime (current) of Player
		$xml .= '<quad posn="29.6 0.8 0.12" sizen="2.3 2.3" style="Icons128x32_1" substyle="RT_TimeAttack"/>';
		$xml .= '<label posn="31.9 -0.15 0.12" sizen="5 3" halign="left" textsize="1" scale="0.7" text="$000TIME"/>';

		// BLUE: Personal Best of Player
		$xml .= '<quad posn="34.8 0.6 0.12" sizen="2.2 2.2" style="BgRaceScore2" substyle="ScoreLink"/>';
		$xml .= '<label posn="37.3 -0.15 0.12" sizen="5 3" halign="left" textsize="1" scale="0.7" text="$000BEST"/>';

		// RED: Avatar and Nickname of Player
		$xml .= '<quad posn="46.3 1.2 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="Buddy"/>';
		$xml .= '<label posn="49.2 -0.15 0.12" sizen="15 3" halign="left" textsize="1" scale="0.7" text="$000PLAYERS TEAM RED"/>';

		// RED: Besttime (current) of Player
		$xml .= '<quad posn="67.6 0.8 0.12" sizen="2.3 2.3" style="Icons128x32_1" substyle="RT_TimeAttack"/>';
		$xml .= '<label posn="69.9 -0.15 0.12" sizen="5 3" halign="left" textsize="1" scale="0.7" text="$000TIME"/>';

		// RED: Personal Best of Player
		$xml .= '<quad posn="72.6 0.6 0.12" sizen="2.2 2.2" style="BgRaceScore2" substyle="ScoreLink"/>';
		$xml .= '<label posn="75.1 -0.15 0.12" sizen="5 3" halign="left" textsize="1" scale="0.7" text="$000BEST"/>';

	}
	else {
		// Default Layout

		// Avatar and Nickname of Player
		$xml .= '<quad posn="8.8 1.2 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="Buddy"/>';
		$xml .= '<label posn="11.8 -0.15 0.12" sizen="5 3" halign="left" textsize="1" scale="0.7" text="$000PLAYERS"/>';

		// Times how often the finish has reached
		$xml .= '<quad posn="29.4 0.8 0.12" sizen="2.5 2.5" style="Icons128x128_1" substyle="Race"/>';

		if ( ($aseco->server->gameinfo->mode == 0) || ($aseco->server->gameinfo->mode == 3) || ($aseco->server->gameinfo->mode == 4) || ($aseco->server->gameinfo->mode == 5) ) {
			// 0 = Rounds
			// 3 = Laps
			// 4 = Stunts
			// 5 = Cup
			// Score (current) of Player
			$xml .= '<quad posn="32.5 1.3 0.12" sizen="2.8 3.1" style="Icons128x128_1" substyle="Launch"/>';
			$xml .= '<label posn="35.4 -0.15 0.12" sizen="5 3" halign="left" textsize="1" scale="0.7" text="$000SCORE"/>';
		}
		else {
			// Besttime (current) of Player
			$xml .= '<quad posn="33.9 0.8 0.12" sizen="2.3 2.3" style="Icons128x32_1" substyle="RT_TimeAttack"/>';
			$xml .= '<label posn="36.3 -0.15 0.12" sizen="5 3" halign="left" textsize="1" scale="0.7" text="$000TIME"/>';
		}

		// Personal Best of Player
		$xml .= '<quad posn="40 0.6 0.12" sizen="2.2 2.2" style="BgRaceScore2" substyle="ScoreLink"/>';
		$xml .= '<label posn="42.5 -0.15 0.12" sizen="5 3" halign="left" textsize="1" scale="0.7" text="$000BEST"/>';

		// Last Score of Player
		$xml .= '<quad posn="46.1 0.7 0.12" sizen="1.5 2.5" style="BgRaceScore2" substyle="Points"/>';
		$xml .= '<label posn="48 -0.15 0.12" sizen="7 3" halign="left" textsize="1" scale="0.7" text="$000LAST"/>';

		// Total Score of Player
		$xml .= '<quad posn="51.2 0.7 0.12" sizen="1.5 2.5" style="BgRaceScore2" substyle="Points"/>';
		$xml .= '<label posn="53.1 -0.15 0.12" sizen="7 3" halign="left" textsize="1" scale="0.7" text="$000TOTAL"/>';

		// Ladderrank of Player
		$xml .= '<quad posn="57 0.8 0.12" sizen="2 2.5" style="Icons128x128_1" substyle="MedalCount"/>';
		$xml .= '<label posn="59.3 -0.15 0.12" sizen="7 3" halign="left" textsize="1" scale="0.7" text="$000POINTS"/>';

		// Ladderscore of Player
		$xml .= '<quad posn="64.1 0.7 0.12" sizen="2.4 2.4" style="BgRaceScore2" substyle="LadderRank"/>';
		$xml .= '<label posn="66.7 -0.15 0.12" sizen="7 3" halign="left" textsize="1" scale="0.7" text="$000RANK"/>';

		// Online Since
		$xml .= '<quad posn="70.2 0.6 0.12" sizen="2.2 2.2" style="Icons64x64_1" substyle="StateFavourite"/>';
		$xml .= '<label posn="72.5 -0.15 0.12" sizen="7 3" halign="left" textsize="1" scale="0.7" text="$000ON"/>';
	}

	// END: Header Frame
	$xml .= '</frame>';

	$seperator_color = (($ast_config['GRAPHIC_MODE'][0] == 'minimal') ? 'FFF3' : '0003');

	$xml .= '<frame posn="2 -2 0">';	// BEGIN: Player lines
	if ($aseco->server->gameinfo->mode == 2) {
		// Team has different Layout
		$xml .= '<quad posn="3.5 0 0.20" sizen="0.1 51.26" bgcolor="'. $seperator_color .'"/>';		// BLUE: Avatar and Nickname of Player
		$xml .= '<quad posn="27 0 0.20" sizen="0.1 51.26" bgcolor="'. $seperator_color .'"/>';		// BLUE: Besttime of Player
		$xml .= '<quad posn="32.4 0 0.20" sizen="0.1 51.26" bgcolor="'. $seperator_color .'"/>';	// BLUE: Personal Best of Player
		$xml .= '<quad posn="38 0 0.20" sizen="0.1 51.26" bgcolor="'. $seperator_color .'"/>';		// Team seperator
		$xml .= '<quad posn="41.5 0 0.20" sizen="0.1 51.26" bgcolor="'. $seperator_color .'"/>';	// RED: Avatar and Nickname of Player
		$xml .= '<quad posn="65 0 0.20" sizen="0.1 51.26" bgcolor="'. $seperator_color .'"/>';		// RED: Besttime of Player
		$xml .= '<quad posn="70.4 0 0.20" sizen="0.1 51.26" bgcolor="'. $seperator_color .'"/>';	// RED: Personal Best of Player

		// Build the Table content
		$xml .= ast_scoretableTeam($caller);
	}
	else {
		// Default Layout
		$xml .= '<quad posn="3.9 0 0.20" sizen="0.1 51.26" bgcolor="'. $seperator_color .'"/>';		// Avatar and Nickname of Player
		$xml .= '<quad posn="27.2 0 0.20" sizen="0.1 51.26" bgcolor="'. $seperator_color .'"/>';	// Times how often the finish has reached
		$xml .= '<quad posn="30.3 0 0.20" sizen="0.1 51.26" bgcolor="'. $seperator_color .'"/>';	// Besttime of Player
		$xml .= '<quad posn="36.7 0 0.20" sizen="0.1 51.26" bgcolor="'. $seperator_color .'"/>';	// Personal Best of Player
		$xml .= '<quad posn="42.9 0 0.20" sizen="0.1 51.26" bgcolor="'. $seperator_color .'"/>';	// Last Score of Player
		$xml .= '<quad posn="48.7 0 0.20" sizen="0.1 51.26" bgcolor="'. $seperator_color .'"/>';	// Total Score of Player
		$xml .= '<quad posn="54.5 0 0.20" sizen="0.1 51.26" bgcolor="'. $seperator_color .'"/>';	// Ladderscore of Player
		$xml .= '<quad posn="61 0 0.20" sizen="0.1 51.26" bgcolor="'. $seperator_color .'"/>';		// Ladderrank of Player
		$xml .= '<quad posn="67.7 0 0.20" sizen="0.1 51.26" bgcolor="'. $seperator_color .'"/>';	// Online Since
		$xml .= '<quad posn="72.5 0 0.20" sizen="0.1 51.26" bgcolor="'. $seperator_color .'"/>';	// Flag of Player

		// Build the Table content
		$xml .= ast_scoretableDefault($caller, $page);
	}
	$xml .= '</frame>';			// END: Player lines


	// END: Window Frame
	$xml .= '</frame>';

	$xml .= '</manialink>';
	$xml .= '</manialinks>';


	if ($caller == false) {
		// Send Scoretable to all connected Players
		$aseco->client->query('SendDisplayManialinkPage',$xml, ($timeout * 1000), false);

		// Set display status for all players
		foreach ($ast_config['PLAYERS'] as $login => $struct) {
			$ast_config['PLAYERS'][$login]['widget_status'] = true;
		}
	}
	else {
		// Send Scoretable to given Player ($caller = $player->login)
		$aseco->client->query('SendDisplayManialinkPageToLogin', $caller, $xml, ($timeout * 1000), false);

		// Set display status for this player
		$ast_config['PLAYERS'][$caller]['widget_status'] = true;
	}

}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ast_scoretableTeam ($caller = false) {
	global $aseco, $ast_config;


	// Set line height for Player lines
	$line_height = 2.5;

	// Init $xml
	$xml = '';

	// Get the current Ranking from Server
	// The first parameter specifies the maximum number of infos to be returned, and the second one the starting index in the ranking
	$aseco->client->resetError();
	$aseco->client->query('GetCurrentRanking', 2,0);
	$ranks = $aseco->client->getResponse();

	if ( !$aseco->client->isError() ) {

		$xml .= '<frame posn="0 -3.5 0">';

		// Team Flag Blue
		$xml .= '<quad posn="-6 0 0.05" sizen="4.8 5.8" style="Bgs1InRace" substyle="BgCard1"/>';
		$xml .= '<quad posn="-5.6 -0.15 0.06" sizen="4.4 5.48" bgcolor="03DF"/>';
		$xml .= '<label posn="-3.8 -1.3 0.07" sizen="3.5 3.5" halign="center" textsize="2" scale="1" text="$FFF'. $ranks[0]['Score'] .'"/>';
		$xml .= '<label posn="-3.8 -3.3 0.07" sizen="3.5 3.5" halign="center" textsize="1" scale="0.8" text="$FFF'. (($ranks[0]['Score'] == 1) ? 'Point' : 'Points') .'"/>';

		// Team Flag Red
		$xml .= '<quad posn="77.2 0 0.05" sizen="4.8 5.8" style="Bgs1InRace" substyle="BgCard1"/>';
		$xml .= '<quad posn="77.1 -0.15 0.06" sizen="4.4 5.48" bgcolor="D30F"/>';
		$xml .= '<label posn="79.8 -1.3 0.07" sizen="3.5 3.5" halign="center" textsize="2" scale="1" text="$FFF'. $ranks[1]['Score'] .'"/>';
		$xml .= '<label posn="79.8 -3.3 0.07" sizen="3.5 3.5" halign="center" textsize="1" scale="0.8" text="$FFF'. (($ranks[1]['Score'] == 1) ? 'Point' : 'Points') .'"/>';

		$xml .= '</frame>';



		// Build the background lines to have full 20 team lines
		$position = 0;
		for ($i = 0; $i < 20; $i++) {
			if ($ast_config['GRAPHIC_MODE'][0] == 'external') {
				$xml .= '<quad posn="0 '. $position .' 0.12" sizen="76 '. $line_height .'" image="'. $ast_config['URLS'][0]['BAR_TEAM'][0] .'"/>';
			}
			else if ($ast_config['GRAPHIC_MODE'][0] == 'internal') {
				$xml .= '<quad posn="0 '. $position .' 0.12" sizen="38 '. $line_height .'" bgcolor="'. $ast_config['COLORS'][0]['BAR_TEAM_BLUE'][0] .'"/>';
				$xml .= '<quad posn="38 '. $position .' 0.12" sizen="38 '. $line_height .'" bgcolor="'. $ast_config['COLORS'][0]['BAR_TEAM_RED'][0] .'"/>';
			}
			else {
				if ($i > 0) {
					$xml .= '<quad posn="0 '. $position .' 0.12" sizen="76 0.1" bgcolor="FFF3"/>';
				}
			}
			$position -= ($line_height + 0.07);
		}

		$ranks = array();
		foreach ($ast_config['PLAYERS'] as $login => $struct) {
			if ($ast_config['PLAYERS'][$login]['finish_time'] == 0) {
				$ranks[$login] = 999;			// For asort() need to != 0
			}
			else {
				$ranks[$login] = $ast_config['PLAYERS'][$login]['finish_time'];
			}
		}
		asort($ranks, SORT_NUMERIC);


		$rank_count = 0;
		$team_offset = 0;
		for ($team = 0; $team <= 1; $team++) {

			// If Red Team, change offset
			if ($team == 1) {
				$team_offset = 38;
			}

			$position = 0;
			foreach ($ranks as $login => $finish_time) {

				// Work only on one team
				if ($ast_config['PLAYERS'][$login]['team'] != $team) {
					continue;
				}

				// Set Textcolor for connected/disconnected Players
				if ($ast_config['PLAYERS'][$login]['disconnected'] == true) {
					$text_color	= 'FFF9';
				}
				else {
					$text_color	= 'FFFF';
				}


				// Flag of Player
				$xml .= '<quad posn="'. ($team_offset + 1.8) .' '. $position .' 0.14" sizen="2.4 2.4" halign="center" image="tmtp://Skins/Avatars/Flags/'. (isset($ast_config['PLAYERS'][$login]['nation']) ? $ast_config['PLAYERS'][$login]['nation'] : 'other') .'.dds"/>';

				// Avatar of Player (hide default TMN nation flags)
				if ( (isset($ast_config['PLAYERS'][$login]['avatar'])) && (!preg_match('/Skins\/Avatars\/Flags\/(\w{3}|other)\.dds$/i', $ast_config['PLAYERS'][$login]['avatar'])) ) {
					$xml .= '<quad posn="'. ($team_offset + 3.6) .' '. $position .' 0.14" sizen="2.5 2.5" bgcolor="000F" />';
					$xml .= '<quad posn="'. ($team_offset + 3.6) .' '. $position .' 0.15" sizen="2.5 2.5" image="tmtp://'. $ast_config['PLAYERS'][$login]['avatar'] .'"/>';
				}
				else {
					if ($ast_config['PLAYERS'][$login]->game_rights == 'United') {
						// Orange Player
						$xml .= '<quad posn="'. ($team_offset + 3.9) .' '. ($position - 0.25) .' 0.14" sizen="2.1 2.1" style="Icons128x128_1" substyle="ChallengeAuthor"/>';
					}
					else {
						// Green Player
						$xml .= '<quad posn="'. ($team_offset + 3.9) .' '. ($position - 0.25) .' 0.14" sizen="2.1 2.1" style="Icons128x128_1" substyle="Rankinks"/>';
					}
				}

				// Nickname of Player
				if ($ast_config['PLAYERS'][$login]['disconnected'] != true) {
					// Link to PlayerWidget
					$xml .= '<label posn="'. $team_offset .' '. $position .' 0.13" sizen="38 2.4" action="'. $ast_config['MANIALINK_ID'] . (60 + $ast_config['PLAYERS'][$login]['playerid']) .'" focusareacolor1="FFF0" focusareacolor2="FFF3" text=" "/>';	// Transparent background =D
				}
				$xml .= '<label posn="'. ($team_offset + 6.5) .' '. ($position - 0.3) .' 0.14" sizen="17.5 2.4" halign="left" textsize="1" scale="1" textcolor="'. $text_color .'" text="'. (isset($ast_config['PLAYERS'][$login]['nickname']) ? $ast_config['PLAYERS'][$login]['nickname'] : '???') .'"/>';

				// Mark Player as Specator, if Player is Spectator
				if ( ($ast_config['PLAYERS'][$login]['isspectator'] == true) && ($ast_config['PLAYERS'][$login]['disconnected'] != true) ) {
					$xml .= '<quad posn="'. ($team_offset + 24.6) .' '. ($position - 0.3) .' 0.14" sizen="2 2" style="Icons64x64_1" substyle="Camera"/>';
				}

				// Besttime of Player ($caller = $player->login)
				if ( ($caller != false) && ($caller == $login) && ($ast_config['PLAYERS'][$login]['finish_time'] > 0) ) {
					$xml .= '<label posn="'. ($team_offset + 32) .' '. ($position - 0.6) .' 0.14" sizen="9 0" halign="right" style="TextTitle2Blink" textsize="1" scale="0.5" textcolor="'. $text_color .'" text="$W'. ast_formatTime($ast_config['PLAYERS'][$login]['finish_time']) .'"/>';
				}
				else {
					$xml .= '<label posn="'. ($team_offset + 31.9) .' '. ($position - 0.6) .' 0.14" sizen="8 0" halign="right" textsize="1" scale="0.9" textcolor="'. $text_color .'" text="'. ast_formatTime($ast_config['PLAYERS'][$login]['finish_time']) .'"/>';
				}

				// Personal Best of Player
				$xml .= '<label posn="'. ($team_offset + 37.5) .' '. ($position - 0.6) .' 0.14" sizen="5 2.4" halign="right" textsize="1" scale="0.9" textcolor="'. $text_color .'" text="'. (($ast_config['PLAYERS'][$login]['personal_best'] > 0) ? ast_formatTime($ast_config['PLAYERS'][$login]['personal_best']) : '--.--') .'"/>';

				$position -= ($line_height + 0.07);

				$rank_count += 1;

				// Not more then 20 entries per Page/Team
				if ($rank_count >= 20) {
					continue 1;
				}
			}
		}

		return $xml;
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ast_scoretableDefault ($caller = false, $page = 0) {
	global $aseco, $ast_config, $ast_ranks;


	// Init $xml
	$xml = '';

	// Set line height for Player lines
	$line_height = 2.5;
	$position = 0;

	if (count($ast_ranks) > 0) {

		// Create the Players lines with content
		$rank_count = 0;
		for ($i = ($page * 20); $i < (($page * 20) + 20); $i ++) {

			// Is there an entry?
			if ( !isset($ast_ranks[$i]) ) {
				break;
			}
			$pos = $ast_ranks[$i];


			// Set Textcolor for connected/disconnected Players
			if ($ast_config['PLAYERS'][$pos['Login']]['disconnected'] == true) {
				$text_color	= 'FFF9';
				$score_color	= 'FFA9';
				$ast_config['PLAYERS'][$pos['Login']]['nickname'] = stripColors($ast_config['PLAYERS'][$pos['Login']]['nickname']);
			}
			else {
				$text_color	= 'FFFF';
				$score_color	= 'FFAF';
			}

			if ( ($pos['BestTime'] > 0) || ($pos['Score'] > 0) ) {
				// Position of Player
				switch ($pos['Rank']) {
					case 1:
						if ($ast_config['GRAPHIC_MODE'][0] == 'external') {
							$xml .= '<quad posn="0 '. $position .' 0.12" sizen="76 '. $line_height .'" image="'. $ast_config['URLS'][0]['BAR_GOLD'][0] .'"/>';
						}
						else if ($ast_config['GRAPHIC_MODE'][0] == 'internal') {
							$xml .= '<quad posn="0 '. $position .' 0.12" sizen="76 '. $line_height .'" bgcolor="'. $ast_config['COLORS'][0]['BAR_GOLD'][0] .'"/>';
						}
						$xml .= '<quad posn="0.85 '. ($position - 0.1) .' 0.15" sizen="2.3 2.2" style="Icons64x64_1" substyle="First"/>';
						break;
					case 2:
						if ($ast_config['GRAPHIC_MODE'][0] == 'external') {
							$xml .= '<quad posn="0 '. $position .' 0.12" sizen="76 '. $line_height .'" image="'. $ast_config['URLS'][0]['BAR_SILVER'][0] .'"/>';
						}
						else if ($ast_config['GRAPHIC_MODE'][0] == 'internal') {
							$xml .= '<quad posn="0 '. $position .' 0.12" sizen="76 '. $line_height .'" bgcolor="'. $ast_config['COLORS'][0]['BAR_SILVER'][0] .'"/>';
						}
						$xml .= '<quad posn="0.85 '. ($position - 0.1) .' 0.15" sizen="2.3 2.2" style="Icons64x64_1" substyle="Second"/>';
						break;
					case 3:
						if ($ast_config['GRAPHIC_MODE'][0] == 'external') {
							$xml .= '<quad posn="0 '. $position .' 0.12" sizen="76 '. $line_height .'" image="'. $ast_config['URLS'][0]['BAR_BRONZE'][0] .'"/>';
						}
						else if ($ast_config['GRAPHIC_MODE'][0] == 'internal') {
							$xml .= '<quad posn="0 '. $position .' 0.12" sizen="76 '. $line_height .'" bgcolor="'. $ast_config['COLORS'][0]['BAR_BRONZE'][0] .'"/>';
						}
						$xml .= '<quad posn="0.87 '. ($position - 0.1) .' 0.15" sizen="2.3 2.2" style="Icons64x64_1" substyle="Third"/>';
						break;
					default:
						if ($ast_config['GRAPHIC_MODE'][0] == 'external') {
							$xml .= '<quad posn="0 '. $position .' 0.12" sizen="76 '. $line_height .'" image="'. $ast_config['URLS'][0]['BAR_DEFAULT'][0] .'"/>';
						}
						else {
							$xml .= '<quad posn="0 '. $position .' 0.12" sizen="76 '. $line_height .'" bgcolor="'. $ast_config['COLORS'][0]['BAR_DEFAULT'][0] .'"/>';
						}
						$xml .= '<label posn="2 '. ($position - 0.5) .' 0.15" sizen="3 2.4" halign="center" textsize="1" scale="1" textcolor="'. $text_color .'" text="'. $pos['Rank'] .'"/>';
				}
			}
			else {
				if ($ast_config['GRAPHIC_MODE'][0] == 'external') {
					$xml .= '<quad posn="0 '. $position .' 0.12" sizen="76 '. $line_height .'" image="'. $ast_config['URLS'][0]['BAR_NORANK'][0] .'"/>';
				}
				else if ($ast_config['GRAPHIC_MODE'][0] == 'internal') {
					$xml .= '<quad posn="0 '. $position .' 0.12" sizen="76 '. $line_height .'" bgcolor="'. $ast_config['COLORS'][0]['BAR_NORANK'][0] .'"/>';
				}
				$xml .= '<label posn="2 '. ($position - 0.5) .' 0.15" sizen="3 2.4" halign="center" textsize="1" scale="0.9" textcolor="'. $text_color .'" text="---"/>';
			}

			// Avatar of Player (hide default TMN nation flags)
			if ( (isset($ast_config['PLAYERS'][$pos['Login']]['avatar'])) && (!preg_match('/Skins\/Avatars\/Flags\/(\w{3}|other)\.dds$/i', $ast_config['PLAYERS'][$pos['Login']]['avatar'])) ) {
				$xml .= '<quad posn="4 '. $position .' 0.14" sizen="2.5 2.5" bgcolor="000F" />';
				$xml .= '<quad posn="4 '. $position .' 0.15" sizen="2.5 2.5" image="tmtp://'. $ast_config['PLAYERS'][$pos['Login']]['avatar'] .'"/>';
			}
			else {
				if ($ast_config['PLAYERS'][$pos['Login']]['game_rights'] == 'United') {
					// Orange Player
					$xml .= '<quad posn="4.3 '. ($position - 0.25) .' 0.15" sizen="2.1 2.1" style="Icons128x128_1" substyle="ChallengeAuthor"/>';
				}
				else {
					// Green Player
					$xml .= '<quad posn="4.3 '. ($position - 0.25) .' 0.15" sizen="2.1 2.1" style="Icons128x128_1" substyle="Rankinks"/>';
				}
			}

			// Nickname of Player
			if ($ast_config['PLAYERS'][$pos['Login']]['disconnected'] != true) {
				// Link to PlayerWidget
				$xml .= '<label posn="0 '. $position .' 0.13" sizen="76 2.4" action="'. $ast_config['MANIALINK_ID'] . (60 + $ast_config['PLAYERS'][$pos['Login']]['playerid']) .'" focusareacolor1="FFF0" focusareacolor2="FFF3" text=" "/>';	// Transparent background =D
			}
			$xml .= '<label posn="6.9 '. ($position - 0.3) .' 0.14" sizen="17.5 2.4" halign="left" textsize="1" scale="1" textcolor="'. $text_color .'" text="'. (isset($ast_config['PLAYERS'][$pos['Login']]['nickname']) ? $ast_config['PLAYERS'][$pos['Login']]['nickname'] : '???') .'"/>';

			// Mark Player as Specator, if Player is Spectator
			if ( ($ast_config['PLAYERS'][$pos['Login']]['isspectator'] == true) && ($ast_config['PLAYERS'][$pos['Login']]['disconnected'] != true) ) {
				$xml .= '<quad posn="25 '. ($position - 0.3) .' 0.14" sizen="2 2" style="Icons64x64_1" substyle="Camera"/>';
			}

			// Times how often the finish was reached
			$xml .= '<label posn="29.9 '. ($position - 0.6) .' 0.14" sizen="2.5 2.4" halign="right" textsize="1" scale="0.9" textcolor="'. $text_color .'" text="'. number_format((int)$ast_config['PLAYERS'][$pos['Login']]['reached_finish'], 0, '.', ' ') .'"/>';

			if ( ($aseco->server->gameinfo->mode == 0) || ($aseco->server->gameinfo->mode == 3) || ($aseco->server->gameinfo->mode == 4) || ($aseco->server->gameinfo->mode == 5) ) {
				// 0 = Rounds
				// 3 = Laps
				// 4 = Stunts
				// 5 = Cup
				// Score of Player ($caller = $player->login)
				$score = '';
				if ($aseco->server->gameinfo->mode == 0) {
					// 0 = Rounds, display Score instead Time
					if ( isset($ast_config['CurrentGameInfos']['RoundsPointsLimit']) ) {
						$score = $pos['Score'] .'/'. $ast_config['CurrentGameInfos']['RoundsPointsLimit'];
					}
					else {
						$score = $pos['Score'];
					}
				}
				else if ($aseco->server->gameinfo->mode == 3) {
					// 3 = Laps, display Checkpoints instead Time
					$score = $pos['Score'] . (($pos['Score'] == 1) ? ' cp.' : ' cps.');
				}
				else if ($aseco->server->gameinfo->mode == 4) {
					// 4 = Stunts
					$score = $pos['Score'];
				}
				else if ($aseco->server->gameinfo->mode == 5) {
					// 5 = Cup
					if ( isset($ast_config['CurrentGameInfos']['CupPointsLimit']) ) {
						$score = $pos['Score'] .'/'. $ast_config['CurrentGameInfos']['CupPointsLimit'];
					}
					else {
						$score = $pos['Score'];
					}
				}

				if ( ($caller != false) && ($caller == $pos['Login']) && ($pos['Score'] > 0) ) {
					$xml .= '<label posn="36.4 '. ($position - 0.6) .' 0.14" sizen="8.1 2.4" halign="right" style="TextTitle2Blink" textsize="1" scale="0.5" textcolor="'. $text_color .'" text="$W'. (($pos['Score'] > 0) ? $score : '--') .'"/>';
				}
				else {
					$xml .= '<label posn="36.3 '. ($position - 0.6) .' 0.14" sizen="6.1 2.4" halign="right" textsize="1" scale="0.9" textcolor="'. $text_color .'" text="'. (($pos['Score'] > 0) ? $score : '--') .'"/>';
				}
			}
			else {
				// 1 = TimeAttack

				// Besttime of Player ($caller = $player->login)
				if ( ($caller != false) && ($caller == $pos['Login']) && ($pos['BestTime'] > 0) ) {
					$xml .= '<label posn="36.4 '. ($position - 0.6) .' 0.14" sizen="8.1 2.4" halign="right" style="TextTitle2Blink" textsize="1" scale="0.5" textcolor="'. $text_color .'" text="$W'.  ast_formatTime($pos['BestTime']) .'"/>';
				}
				else {
					$xml .= '<label posn="36.3 '. ($position - 0.6) .' 0.14" sizen="6.1 2.4" halign="right" textsize="1" scale="0.9" textcolor="'. $text_color .'" text="'.  ast_formatTime($pos['BestTime']) .'"/>';
				}
			}

			// Personal Best of Player
			if ($aseco->server->gameinfo->mode == 4) {
				// Stunts
				$xml .= '<label posn="42.5 '. ($position - 0.6) .' 0.14" sizen="6 2.4" halign="right" textsize="1" scale="0.9" textcolor="'. $text_color .'" text="'. (($ast_config['PLAYERS'][$pos['Login']]['personal_best'] > 0) ? $ast_config['PLAYERS'][$pos['Login']]['personal_best'] : '--') .'"/>';
			}
			else {
				// All other Gamemodes
				$xml .= '<label posn="42.5 '. ($position - 0.6) .' 0.14" sizen="6 2.4" halign="right" textsize="1" scale="0.9" textcolor="'. $text_color .'" text="'. (($ast_config['PLAYERS'][$pos['Login']]['personal_best'] > 0) ? ast_formatTime($ast_config['PLAYERS'][$pos['Login']]['personal_best']) : '--') .'"/>';
			}

			// Last Score of Player
			$xml .= '<label posn="48.3 '. ($position - 0.6) .' 0.14" sizen="5.4 2.4" halign="right" textsize="1" scale="0.9" textcolor="'. $score_color .'" text="'. (($ast_config['PLAYERS'][$pos['Login']]['lastscore']> 0) ? sprintf("+%.02f", $ast_config['PLAYERS'][$pos['Login']]['lastscore']) : '---') .'"/>';

			// Total Score of Player
			$xml .= '<label posn="54.1 '. ($position - 0.6) .' 0.14" sizen="5.4 2.4" halign="right" textsize="1" scale="0.9" textcolor="'. $score_color .'" text="'. sprintf("%.02f", $ast_config['PLAYERS'][$pos['Login']]['totalscore']) .'"/>';

			// Ladderscore of Player
			$xml .= '<label posn="60.6 '. ($position - 0.6) .' 0.14" sizen="6.2 2.4" halign="right" textsize="1" scale="0.9" textcolor="'. $text_color .'" text="'. number_format((int)$ast_config['PLAYERS'][$pos['Login']]['ladderscore'], 0, '.', ' ') .'"/>';

			// Ladderrank of Player
			$xml .= '<label posn="67.3 '. ($position - 0.6) .' 0.14" sizen="6.5 2.4" halign="right" textsize="1" scale="0.9" textcolor="'. $text_color .'" text="'. number_format((int)$ast_config['PLAYERS'][$pos['Login']]['ladderrank'], 0, '.', ' ') .'"/>';

			// Online Since
			$xml .= '<label posn="72.1 '. ($position - 0.6) .' 0.14" sizen="4.4 2.4" halign="right" textsize="1" scale="0.9" textcolor="'. $text_color .'" text="'. (isset($ast_config['PLAYERS'][$pos['Login']]['online_since']) ? $ast_config['PLAYERS'][$pos['Login']]['online_since'] : '--.--') .'"/>';

			// Flag of Player
			$xml .= '<quad posn="74.3 '. $position .' 0.14" sizen="2.4 2.4" halign="center" image="tmtp://Skins/Avatars/Flags/'. (isset($ast_config['PLAYERS'][$pos['Login']]['nation']) ? $ast_config['PLAYERS'][$pos['Login']]['nation'] : 'other') .'.dds"/>';

			$position -= ($line_height + 0.07);

			$rank_count += 1;

			// Not more then 20 entries per Page
			if ($rank_count >= 20) {
				break;
			}

			// Keep clean for each loop
			$pos = array();
		}
	}

	// Fill empty line to have full 20 lines
	if ( ($ast_config['GRAPHIC_MODE'][0] == 'external') || ($ast_config['GRAPHIC_MODE'][0] == 'internal') ) {
		for ($i = $rank_count; $i < 20; $i++) {
			if ($ast_config['GRAPHIC_MODE'][0] == 'external') {
				$xml .= '<quad posn="0 '. $position .' 0.12" sizen="76 '. $line_height .'" image="'. $ast_config['URLS'][0]['BAR_BLANK'][0] .'"/>';
			}
			else if ($ast_config['GRAPHIC_MODE'][0] == 'internal') {
				$xml .= '<quad posn="0 '. $position .' 0.12" sizen="76 '. $line_height .'" bgcolor="'. $ast_config['COLORS'][0]['BAR_BLANK'][0] .'"/>';
			}
			$position -= ($line_height + 0.07);
		}
	}
	else {
		$position = -2.5;
		for ($i = 1; $i < 20; $i++) {
			$xml .= '<quad posn="0 '. $position .' 0.12" sizen="76 0.1" bgcolor="FFF3"/>';
			$position -= ($line_height + 0.07);
		}
	}

	return $xml;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ast_widgetLegend ($player, $display = true) {
	global $aseco, $ast_config;


	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $ast_config['MANIALINK_ID'] .'08"></manialink>';	// Turn of possible open Player-Window
	$xml .= '<manialink id="'. $ast_config['MANIALINK_ID'] .'04">';

	if ($display == true) {
		if ($aseco->server->gameinfo->mode == 2) {
			// Team
			$xml .= '<frame posn="-4 -2.3 -2">';	// BEGIN: Window Frame
			$xml .= '<quad posn="0.8 -0.8 0.10" sizen="38.2 17.4" bgcolor="000A"/>';
			$xml .= '<quad posn="-0.2 0.2 0.11" sizen="40.2 19.4" style="Bgs1InRace" substyle="BgCard3"/>';
		}
		else {
			$xml .= '<frame posn="-4 14.7 -2">';	// BEGIN: Window Frame
			$xml .= '<quad posn="0.8 -0.8 0.10" sizen="38.2 34.4" bgcolor="000A"/>';
			$xml .= '<quad posn="-0.2 0.2 0.11" sizen="40.2 36.4" style="Bgs1InRace" substyle="BgCard3"/>';
		}


		// Close Button
		$xml .= '<frame posn="37.2 1.3 0">';
		$xml .= '<quad posn="0 0 0.12" sizen="4 4" style="Icons64x64_1" substyle="ArrowDown"/>';
		$xml .= '<quad posn="1.1 -1.35 0.13" sizen="1.8 1.75" bgcolor="EEEF"/>';
		$xml .= '<quad posn="0.65 -0.7 0.14" sizen="2.6 2.6" action="'. $ast_config['MANIALINK_ID'] .'06" style="Icons64x64_1" substyle="Close"/>';
		$xml .= '</frame>';


		// Frame for the Legend
		$xml .= '<frame posn="-6 -3.5 0">';

		// Avatar and Nickname of Player
		$xml .= '<quad posn="8.8 1.2 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="Buddy"/>';
		$xml .= '<label posn="11.8 -0.15 0.12" sizen="5 3" halign="left" textsize="1" scale="0.8" text="$FFFPLAYERS"/>';
		$xml .= '<label posn="18 -0.05 0.12" sizen="35 3" halign="left" textsize="1" scale="0.9" text="$FFFThe Player Nicknames"/>';

		// Finish count
		if ($aseco->server->gameinfo->mode != 2) {
			// 2 = Team
			$xml .= '<quad posn="8.8 -2.5 0.12" sizen="2.5 2.5" style="Icons128x128_1" substyle="Race"/>';
//			$xml .= '<label posn="11.8 -3.65 0.12" sizen="5 3" halign="left" textsize="1" scale="0.8" text="$FFFFIN."/>';
			$xml .= '<label posn="18 -3.55 0.12" sizen="35 3" halign="left" textsize="1" scale="0.9" text="$FFFCount how often you have finished this Track"/>';
		}

		if ( ($aseco->server->gameinfo->mode == 0) || ($aseco->server->gameinfo->mode == 5) ) {
			// 0 = Rounds
			// 5 = Cup
			$xml .= '<quad posn="8.2 -5.65 0.12" sizen="3.2 3.2" style="Icons128x128_1" substyle="Launch"/>';
			$xml .= '<label posn="11.8 -6.95 0.12" sizen="5 3" halign="left" textsize="1" scale="0.8" text="$FFFSCORE"/>';
			$xml .= '<label posn="18 -6.85 0.12" sizen="35 3" halign="left" textsize="1" scale="0.9" text="$FFFScore for this session"/>';
		}
		else if ( ($aseco->server->gameinfo->mode == 1) || ($aseco->server->gameinfo->mode == 2) ) {
			// 1 = TimeAttack
			// 2 = Team
			$xml .= '<quad posn="8.8 -6 0.12" sizen="2.3 2.3" style="Icons128x32_1" substyle="RT_TimeAttack"/>';
			$xml .= '<label posn="11.8 -6.95 0.12" sizen="5 3" halign="left" textsize="1" scale="0.8" text="$FFFTIME"/>';
			$xml .= '<label posn="18 -6.85 0.12" sizen="35 3" halign="left" textsize="1" scale="0.9" text="$FFFYour current best time for this Track"/>';
		}
		else if ($aseco->server->gameinfo->mode == 3) {
			// 3 = Laps
			$xml .= '<quad posn="8.2 -5.65 0.12" sizen="3.2 3.2" style="Icons128x128_1" substyle="Launch"/>';
			$xml .= '<label posn="11.8 -6.95 0.12" sizen="5 3" halign="left" textsize="1" scale="0.8" text="$FFFSCORE"/>';
			$xml .= '<label posn="18 -6.85 0.12" sizen="35 3" halign="left" textsize="1" scale="0.9" text="$FFFCount how many checkpoints reached"/>';
		}
		else if ($aseco->server->gameinfo->mode == 4) {
			// 4 = Stunts
			$xml .= '<quad posn="8.2 -5.65 0.12" sizen="3.2 3.2" style="Icons128x128_1" substyle="Launch"/>';
			$xml .= '<label posn="11.8 -6.95 0.12" sizen="5 3" halign="left" textsize="1" scale="0.8" text="$FFFSCORE"/>';
			$xml .= '<label posn="18 -6.85 0.12" sizen="35 3" halign="left" textsize="1" scale="0.9" text="$FFFStunt-Points won for this session"/>';
		}

		// Personal Best of Player
		$xml .= '<quad posn="8.8 -9.5 0.12" sizen="2.2 2.2" style="BgRaceScore2" substyle="ScoreLink"/>';
		$xml .= '<label posn="11.8 -10.25 0.12" sizen="5 3" halign="left" textsize="1" scale="0.8" text="$FFFBEST"/>';
		$xml .= '<label posn="18 -10.15 0.12" sizen="35 3" halign="left" textsize="1" scale="0.9" text="$FFFYour personal best time for this Track"/>';

		if ($aseco->server->gameinfo->mode != 2) {
			// 2 = Team

			// Last Score of Player
			$xml .= '<quad posn="9 -13 0.12" sizen="1.8 2.4" style="BgRaceScore2" substyle="Points"/>';
			$xml .= '<label posn="11.8 -13.75 0.12" sizen="5 3" halign="left" textsize="1" scale="0.8" text="$FFFLAST"/>';
			$xml .= '<label posn="18 -13.65 0.12" sizen="35 3" halign="left" textsize="1" scale="0.9" text="$FFFYour Score for the last Track"/>';

			// Total Score of Player
			$xml .= '<quad posn="9 -16.5 0.12" sizen="1.8 2.4" style="BgRaceScore2" substyle="Points"/>';
			$xml .= '<label posn="11.8 -17.25 0.12" sizen="5 3" halign="left" textsize="1" scale="0.8" text="$FFFTOTAL"/>';
			$xml .= '<label posn="18 -17.15 0.12" sizen="35 3" halign="left" textsize="1" scale="0.9" text="$FFFYour Total-Score at this Server and this Session"/>';

			// Ladderrank of Player
			$xml .= '<quad posn="8.8 -20 0.12" sizen="2.4 2.5" style="BgRaceScore2" substyle="LadderRank"/>';
			$xml .= '<label posn="11.8 -20.75 0.12" sizen="7 3" halign="left" textsize="1" scale="0.8" text="$FFFRANK"/>';
			$xml .= '<label posn="18 -20.65 0.12" sizen="35 3" halign="left" textsize="1" scale="0.9" text="$FFFYour current Ladder-Rank"/>';

			// Ladderscore of Player
			$xml .= '<quad posn="8.8 -23.5 0.12" sizen="2.4 2.4" style="Icons128x128_1" substyle="MedalCount"/>';
			$xml .= '<label posn="11.8 -24.25 0.12" sizen="7 3" halign="left" textsize="1" scale="0.8" text="$FFFPOINTS"/>';
			$xml .= '<label posn="18 -24.15 0.12" sizen="35 3" halign="left" textsize="1" scale="0.9" text="$FFFYour current Ladder-Score"/>';

			// Online Since
			$xml .= '<quad posn="8.8 -27 0.12" sizen="2.2 2.2" style="Icons64x64_1" substyle="StateFavourite"/>';
			$xml .= '<label posn="11.8 -27.75 0.12" sizen="7 3" halign="left" textsize="1" scale="0.8" text="$FFFONLINE"/>';
			$xml .= '<label posn="18 -27.65 0.12" sizen="35 3" halign="left" textsize="1" scale="0.9" text="$FFFYour Online-Time since connect to this Server"/>';
		}
		$xml .= '</frame>';


		$xml .= '</frame>';	// END: Window Frame
	}

	$xml .= '</manialink>';
	$xml .= '</manialinks>';

	$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ast_widgetPlayer ($target, $caller) {
	global $aseco, $ast_config;


	// Bail out if Player is disconnected
	if ($ast_config['PLAYERS'][$target->login]['disconnected'] == true) {
		return;
	}

	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $ast_config['MANIALINK_ID'] .'04"></manialink>';	// Turn of possible open Legend-Window
	$xml .= '<manialink id="'. $ast_config['MANIALINK_ID'] .'08">';

		if ($aseco->server->gameinfo->mode == 2) {
			if ($ast_config['PLAYERS'][$target->login]['team'] == 0) {
				// Team Blue Position
				$xml .= '<frame posn="-4 20 -1">';	// BEGIN: Window Frame
			}
			else {
				// Team Red Position
				$xml .= '<frame posn="-36 20 -1">';	// BEGIN: Window Frame
			}
		}
		else {
			// Default position
			$xml .= '<frame posn="-4 20 -1">';	// BEGIN: Window Frame
		}
		$xml .= '<quad posn="0.8 -0.8 0.10" sizen="38.2 39.7" bgcolor="000A"/>';
		$xml .= '<quad posn="-0.2 0.2 0.11" sizen="40.2 41.7" style="Bgs1InRace" substyle="BgCard3"/>';

		// Close Button
		$xml .= '<frame posn="37.2 1.3 0">';
		$xml .= '<quad posn="0 0 0.12" sizen="4 4" style="Icons64x64_1" substyle="ArrowDown"/>';
		$xml .= '<quad posn="1.1 -1.35 0.13" sizen="1.8 1.75" bgcolor="EEEF"/>';
		$xml .= '<quad posn="0.65 -0.7 0.14" sizen="2.6 2.6" action="'. $ast_config['MANIALINK_ID'] .'09" style="Icons64x64_1" substyle="Close"/>';
		$xml .= '</frame>';

		// Frame for content
		$xml .= '<frame posn="2.5 -3.5 0">';

		// Avatar of Player (hide default TMN nation flags)
		$xml .= '<quad posn="0 1 0.12" sizen="12.8 12.8" bgcolor="000F"/>';
		if ( (isset($ast_config['PLAYERS'][$target->login]['avatar'])) && (!preg_match('/Skins\/Avatars\/Flags\/(\w{3}|other)\.dds$/i', $ast_config['PLAYERS'][$target->login]['avatar'])) ) {
			$xml .= '<quad posn="0 1 0.14" sizen="12.8 12.8" image="tmtp://'. $ast_config['PLAYERS'][$target->login]['avatar'] .'"/>';
		}
		else {
			if ($ast_config['PLAYERS'][$target->login]['game_rights'] == 'United') {
				// Orange Player
				$xml .= '<quad posn="0 1 0.13" sizen="12.8 12.8" style="Icons128x128_1" substyle="ChallengeAuthor"/>';
			}
			else {
				// Green Player
				$xml .= '<quad posn="0 1 0.13" sizen="12.8 12.8" style="Icons128x128_1" substyle="Rankinks"/>';
			}
		}

		// Player Login/Nickname
		$xml .= '<label posn="13.8 1 0.12" sizen="21 3" halign="left" textsize="2" scale="0.8" text="$FFF$S'. ast_handleSpecialChars($target->nickname) .'"/>';
		$xml .= '<label posn="13.8 -1.6 0.12" sizen="21 1.5" halign="left" textsize="1" scale="0.9" text="$FFF'. $target->login .'"/>';

		// Display Team Membership of Player
		if ($aseco->server->gameinfo->mode == 2) {
			if ($ast_config['PLAYERS'][$target->login]['team'] == 0) {
				$xml .= '<label posn="13.8 -4 0.12" sizen="21 1.5" halign="left" textsize="1" scale="0.9" text="$09FMember of Team Blue"/>';
			}
			else {
				$xml .= '<label posn="13.8 -4 0.12" sizen="21 1.5" halign="left" textsize="1" scale="0.9" text="$F20Member of Team Red"/>';
			}
		}


		// Is Player Driver or Spectator?
		if ($ast_config['PLAYERS'][$target->login]['isspectator'] == true) {
			$xml .= '<quad posn="29.4 -3.6 0.12" sizen="3.4 3.4" style="Icons64x64_1" substyle="CameraLocal"/>';
			$xml .= '<label posn="30.9 -6.8 0.12" sizen="12 1.6" halign="center" textsize="1" scale="0.75" text="$FFFSpectator"/>';
		}
		else {
			$xml .= '<quad posn="29.4 -3.6 0.12" sizen="3.4 3.4" style="Icons128x128_1" substyle="Vehicles"/>';
			$xml .= '<label posn="30.9 -6.8 0.12" sizen="12 1.6" halign="center" textsize="1" scale="0.75" text="$FFFPlayer"/>';
		}

		// Is Player Operator, Admin or MasterAdmin?
		if ( $aseco->isMasterAdminL($target->login) ) {
			$xml .= '<quad posn="29.4 -8.8 0.12" sizen="3.4 3.4" style="Icons128x128_1" substyle="Invite"/>';
			$xml .= '<label posn="30.9 -12 0.12" sizen="12 1.6" halign="center" textsize="1" scale="0.75" text="$FFFMasterAdmin"/>';
		}
		else if ( $aseco->isAdminL($target->login) ) {
			$xml .= '<quad posn="29.4 -8.8 0.12" sizen="3.4 3.4" style="Icons128x128_1" substyle="Rankinks"/>';
			$xml .= '<label posn="30.9 -12 0.12" sizen="12 1.6" halign="center" textsize="1" scale="0.75" text="$FFFAdmin"/>';
		}
		else if ( $aseco->isOperatorL($target->login) ) {
			$xml .= '<quad posn="29.4 -8.8 0.12" sizen="3.4 3.4" style="Icons128x128_1" substyle="ChallengeAuthor"/>';
			$xml .= '<label posn="30.9 -12 0.12" sizen="12 1.6" halign="center" textsize="1" scale="0.75" text="$FFFOperator"/>';
		}

//		// Add Server to Favorites
//		$xml .= '<quad posn="29.4 -8.8 0.12" sizen="3.4 3.4" url="tmtp://#addfavourite='. $aseco->server->serverlogin .'" style="Icons128x128_1" substyle="ServersFavorites"/>';
//		$xml .= '<label posn="30.9 -12 0.12" sizen="12 1.6" url="tmtp://#addfavourite='. $aseco->server->serverlogin .'" halign="center" textsize="1" scale="0.75" text="$FFFServer to Favorites"/>';
//
//		// Add Player to Buddylist
//		if ($target->login != $caller->login) {
//			$xml .= '<quad posn="29.4 -14 0.12" sizen="3.4 3.4" url="tmtp://#addbuddy='. $target->login .'" style="Icons128x128_1" substyle="Solo"/>';
//			$xml .= '<label posn="30.9 -17.2 0.12" sizen="12 1.6" url="tmtp://#addbuddy='. $target->login .'" halign="center" textsize="1" scale="0.75" text="$FFFAdd to Buddylist"/>';
//		}


		// LastMatchScore of Player
		$xml .= '<label posn="0 -13.5 0.12" sizen="8 1.5" halign="left" textsize="1" scale="0.9" text="$FFFLast Score"/>';
		$xml .= '<label posn="9 -13.5 0.12" sizen="25.5 1.5" halign="left" textsize="1" scale="0.8" text="$CC8$W'. (($ast_config['PLAYERS'][$target->login]['lastscore']> 0) ? sprintf("+%.02f", $ast_config['PLAYERS'][$target->login]['lastscore']) : '---') .'"/>';

		// Ladderrank of Player
		$xml .= '<label posn="0 -15.5 0.12" sizen="8 1.5" halign="left" textsize="1" scale="0.9" text="$FFFLadder-Rank"/>';
		$xml .= '<label posn="9 -15.5 0.12" sizen="25.5 1.5" halign="left" textsize="1" scale="0.9" text="$FFF'. number_format((int)$target->ladderrank, 0, '.', ' ') .'"/>';

		// Ladderscore of Player
		$xml .= '<label posn="0 -17.5 0.12" sizen="8 1.5" halign="left" textsize="1" scale="0.9" text="$FFFLadder-Score"/>';
		$xml .= '<label posn="9 -17.5 0.12" sizen="25.5 1.5" halign="left" textsize="1" scale="0.9" text="$FFF'. number_format((int)$target->ladderscore, 0, '.', ' ') .'"/>';


		// NbrMatchWins of Player
		$xml .= '<label posn="0 -20.5 0.12" sizen="8 1.5" halign="left" textsize="1" scale="0.9" text="$FFFTotal Won"/>';
		$xml .= '<label posn="9 -20.5 0.12" sizen="25.5 1.5" halign="left" textsize="1" scale="0.9" text="$FFF'.  number_format($ast_config['PLAYERS'][$target->login]['match_wins'], 0, '.', ' ') .'"/>';

		// NbrMatchDraws of Player
		$xml .= '<label posn="0 -22.5 0.12" sizen="8 1.5" halign="left" textsize="1" scale="0.9" text="$FFFTotal Draws"/>';
		$xml .= '<label posn="9 -22.5 0.12" sizen="25.5 1.5" halign="left" textsize="1" scale="0.9" text="$FFF'. number_format($ast_config['PLAYERS'][$target->login]['match_draws'], 0, '.', ' ') .'"/>';

		// NbrMatchLosses of Player
		$xml .= '<label posn="0 -24.5 0.12" sizen="8 1.5" halign="left" textsize="1" scale="0.9" text="$FFFTotal Losses"/>';
		$xml .= '<label posn="9 -24.5 0.12" sizen="25.5 1.5" halign="left" textsize="1" scale="0.9" text="$FFF'. number_format($ast_config['PLAYERS'][$target->login]['match_losses'], 0, '.', ' ') .'"/>';


		// Zone from Player
		$xml .= '<label posn="0 -27.5 0.12" sizen="8 1.5" halign="left" textsize="1" scale="0.9" text="$FFFZone"/>';
		$xml .= '<quad posn="9 -27 0.12" sizen="2.4 2.4" halign="left" image="tmtp://Skins/Avatars/Flags/'. $ast_config['PLAYERS'][$target->login]['nation'] .'.dds"/>';
		$xml .= '<label posn="12 -27.5 0.12" sizen="25.5 1.5" halign="left" textsize="1" scale="0.9" text="$FFF'. str_replace('|', ', ', $target->zone) .'"/>';

		// OnlineRights of Player
		$xml .= '<label posn="0 -29.5 0.12" sizen="8 1.5" halign="left" textsize="1" scale="0.9" text="$FFFGame"/>';
		$xml .= '<label posn="9 -29.5 0.12" sizen="25.5 1.5" halign="left" textsize="1" scale="0.9" text="$FFF'. $ast_config['PLAYERS'][$target->login]['game_rights'] .'"/>';

		// ClientVersion of Player
		$xml .= '<label posn="0 -31.5 0.12" sizen="8 1.5" halign="left" textsize="1" scale="0.9" text="$FFFClient"/>';
		$xml .= '<label posn="9 -31.5 0.12" sizen="25.5 1.5" halign="left" textsize="1" scale="0.9" text="$FFF'. $ast_config['PLAYERS'][$target->login]['client_version'] .'"/>';

		// Online Since
		$xml .= '<label posn="0 -33.5 0.12" sizen="8 1.5" halign="left" textsize="1" scale="0.9" text="$FFFOnline"/>';
		$xml .= '<label posn="9 -33.5 0.12" sizen="25.5 1.5" halign="left" textsize="1" scale="0.9" text="$FFF'. $ast_config['PLAYERS'][$target->login]['online_since'] .'"/>';

		$xml .= '</frame>';	// END: Frame for content

		$xml .= '</frame>';	// END: Window Frame

	$xml .= '</manialink>';
	$xml .= '</manialinks>';

	// Send Quicklink to given Player ($caller = $player)
	$aseco->client->query('SendDisplayManialinkPageToLogin', $caller->login, $xml, 0, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ast_widgetQuicklink ($caller = false, $display = true) {
	global $aseco, $ast_config;


	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $ast_config['MANIALINK_ID'] .'07">';

	if ($display == true) {
		$xml .= '<frame posn="'. $ast_config['QUICKLINK_BUTTON'][0]['WIDGET_POSITION_X'][0] .' '. $ast_config['QUICKLINK_BUTTON'][0]['WIDGET_POSITION_Y'][0] .' 0">';
		$xml .= '<quad posn="0 0 10" sizen="7 4" action="'. $ast_config['MANIALINK_ID'] .'02" style="Icons64x64_1" substyle="RestartRace"/>';
		$xml .= '</frame>';
	}

	$xml .= '</manialink>';
	$xml .= '</manialinks>';

	if ($caller == false) {
		// Send Quicklink to all connected Players
		$aseco->client->query('SendDisplayManialinkPage', $xml, 0, false);
	}
	else {
		// Send Quicklink to given Player ($caller = $player)
		$aseco->client->query('SendDisplayManialinkPageToLogin', $caller->login, $xml, 0, false);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onPlayerConnect
function ast_onPlayerConnect ($aseco, $player) {
	global $ast_config;


	// Get the detailed Player informations
	$aseco->client->resetError();
	$aseco->client->query('GetDetailedPlayerInfo', $player->login);
	$info = $aseco->client->getResponse();


	$ast_config['PLAYERS'][$player->login]['playerid'] = $player->id;

	$ast_config['PLAYERS'][$player->login]['ladderscore'] = $player->ladderscore;
	$ast_config['PLAYERS'][$player->login]['ladderrank'] = (($player->ladderrank != -1) ? $player->ladderrank : 0);
	$ast_config['PLAYERS'][$player->login]['personal_best'] = $player->panels['pb'];
	$ast_config['PLAYERS'][$player->login]['nation'] = mapCountry($player->nation);

	$ast_config['PLAYERS'][$player->login]['reached_finish'] = 0;
	$ast_config['PLAYERS'][$player->login]['totalscore'] = 0;
	$ast_config['PLAYERS'][$player->login]['lastscore'] = 0;
	$ast_config['PLAYERS'][$player->login]['lastscore_overall'] = array();		// Init
	$ast_config['PLAYERS'][$player->login]['finish_time'] = 0;

	$ast_config['PLAYERS'][$player->login]['last_rank'] = -1;
	$ast_config['PLAYERS'][$player->login]['last_finishscore'] = -1;

	$ast_config['PLAYERS'][$player->login]['isspectator'] = $player->isspectator;
	$ast_config['PLAYERS'][$player->login]['disconnected'] = false;
	$ast_config['PLAYERS'][$player->login]['widget_status'] = false;
	$ast_config['PLAYERS'][$player->login]['delayed_call'] = false;


	if ( !$aseco->client->isError() ) {
		$ast_config['PLAYERS'][$player->login]['avatar'] = $info['Avatar']['FileName'];
		$ast_config['PLAYERS'][$player->login]['game_rights'] = (($info['OnlineRights'] == 3) ? 'United' : 'Nations');
		$ast_config['PLAYERS'][$player->login]['client_version'] = $info['ClientVersion'];

		$ast_config['PLAYERS'][$player->login]['match_wins'] = $info['LadderStats']['NbrMatchWins'];
		$ast_config['PLAYERS'][$player->login]['match_draws'] = $info['LadderStats']['NbrMatchDraws'];
		$ast_config['PLAYERS'][$player->login]['match_losses'] = $info['LadderStats']['NbrMatchLosses'];
		$ast_config['PLAYERS'][$player->login]['team'] = $info['TeamId'];

		// Add the won ladders from other Servers to hide them at this server
		if ($info['LadderStats']['LastMatchScore'] > 0) {
			$ast_config['PLAYERS'][$player->login]['lastscore_overall'][] = $info['LadderStats']['LastMatchScore'];
		}
	}

	// Remove all $S from Nickname and set at first a $S for shadows in Scoretable
	$ast_config['PLAYERS'][$player->login]['nickname'] = '$S'. ast_handleSpecialChars($player->nickname);

	if ($ast_config['PLAYERS'][$player->login]['nation'] == 'SCG') {
		// Flag fix at TMF, change 'SCG' to 'SRB'
		$ast_config['PLAYERS'][$player->login]['nation'] = 'SRB';
	}
	else if ($ast_config['PLAYERS'][$player->login]['nation'] == 'ROM') {
		// Flag fix at TMF, change 'ROM' to 'ROU'
		$ast_config['PLAYERS'][$player->login]['nation'] = 'ROU';
	}
	else if ($ast_config['PLAYERS'][$player->login]['nation'] == 'CAR'){
		// Flag fix at TMF, change 'CAR' to 'CMR'
		$ast_config['PLAYERS'][$player->login]['nation'] = 'CMR';
	}
	else if ( ($ast_config['PLAYERS'][$player->login]['nation'] == 'OTH') || ($ast_config['PLAYERS'][$player->login]['nation'] == '') ) {
		// If nation is 'OTH' rename to 'other' to get the right flag (other.dds)
		$ast_config['PLAYERS'][$player->login]['nation'] = 'other';
	}

	// Do not 'GetCurrentRanking' in Team
	if ($aseco->server->gameinfo->mode != 2) {
		ast_getCurrentRanking();
	}


//	// DEVEL ONLY
//	for ($i = 0; $i < 39; $i++){
//		$ast_config['PLAYERS'][$player->login . $i] = $ast_config['PLAYERS'][$player->login];
//	}


	// Show the Quicklink button?
	if ( ($ast_config['QUICKLINK_BUTTON'][0]['ENABLED'][0] == true) && ($ast_config['CURRENT_STATE'] != 'score') ) {
		// $caller, $display
		ast_widgetQuicklink($player, true);
	}

	// Close Scoretable; Set CustomUI with preloading the images (main intention here)
	$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, ast_closeAllWidgets(true, false, true), 0, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onPlayerFinish
function ast_onPlayerFinish ($aseco, $finish_item) {
	global $ast_config, $ast_ranks;


	// Close all Scoretable-Widgets, if open
	if ($finish_item->score == 0) {
		if ( ($ast_config['PLAYERS'][$finish_item->player->login]['widget_status'] == true) || ($ast_config['PLAYERS'][$finish_item->player->login]['delayed_call'] != false) ) {
			$ast_config['PLAYERS'][$finish_item->player->login]['delayed_call'] = false;
			$ast_config['PLAYERS'][$finish_item->player->login]['widget_status'] = false;

			// Close Scoretable and possible open LegendWidget/PlayerWidget
			$aseco->client->query('SendDisplayManialinkPageToLogin', $finish_item->player->login, ast_closeAllWidgets(false, false, false), 0, false);
		}
		return;
	}

	// Count the finishline crossings
	$ast_config['PLAYERS'][$finish_item->player->login]['reached_finish'] += 1;

	// Save the current finish time in Team
	if ($aseco->server->gameinfo->mode == 2) {
		$ast_config['PLAYERS'][$finish_item->player->login]['finish_time'] = $finish_item->score;
	}


	// Do not 'GetCurrentRanking' in Team
	// and here only if <show_at_finish> AND <player_places_widget> are enabled.
	// Otherwise this are requested in the related area below.
	if ( ($aseco->server->gameinfo->mode != 2) && ($ast_config['SHOW_AT_FINISH'][0] == true) && ($ast_config['PLAYER_PLACES_WIDGET'][0]['ENABLED'][0] == true) ) {
		ast_getCurrentRanking();
	}


	// Build the PlayerPlaceWidget, if enabled
	if ($ast_config['PLAYER_PLACES_WIDGET'][0]['ENABLED'][0] == true) {

		$show = false;
		$addition = '';
		if ( ($aseco->server->gameinfo->mode == 1) || ($aseco->server->gameinfo->mode == 4) ) {
			// 1 = TimeAttack
			// 4 = Stunts

			// Request 'GetCurrentRanking' only if <show_at_finish> is disabled (otherwise done twice)
			if ($ast_config['SHOW_AT_FINISH'][0] == false) {
				ast_getCurrentRanking();
			}

			for ($i = 0 ; $i < count($ast_ranks) ; $i++) {
				$pos = $ast_ranks[$i];
				if ($finish_item->player->login == $pos['Login']) {
					if ( ($ast_config['PLAYERS'][$pos['Login']]['last_rank'] == -1) || (($pos['Rank'] != -1) && ($pos['Rank'] < $ast_config['PLAYERS'][$pos['Login']]['last_rank'])) ) {
						// Display the Widget
						$show = true;

						// Avatar or Flag from Player
						if ( (isset($ast_config['PLAYERS'][$pos['Login']]['avatar'])) && (!preg_match('/Skins\/Avatars\/Flags\/(\w{3}|other)\.dds$/i', $ast_config['PLAYERS'][$pos['Login']]['avatar'])) ) {
							$addition .= '<quad posn="0 0 0.02" sizen="4.5 4.5" image="tmtp://'. $ast_config['PLAYERS'][$pos['Login']]['avatar'] .'"/>';
						}
						else {
							$addition .= '<quad posn="2.2 0 0.01" sizen="4.4 4.4" halign="center" image="tmtp://Skins/Avatars/Flags/'. (isset($ast_config['PLAYERS'][$pos['Login']]['nation']) ? $ast_config['PLAYERS'][$pos['Login']]['nation'] : 'other') .'.dds"/>';
						}
						$addition .= '<label posn="5.5 -2.25 0.03" sizen="65 4.5" valign="center" textsize="'. $ast_config['PLAYER_PLACES_WIDGET'][0]['TEXTSIZE'][0] .'" textcolor="'. $ast_config['PLAYER_PLACES_WIDGET'][0]['TEXTCOLOR'][0]['DEFAULT'][0] .'F" text="$SPlayer $'. $ast_config['PLAYER_PLACES_WIDGET'][0]['TEXTCOLOR'][0]['HIGHLITE'][0] . stripColors($finish_item->player->nickname) .'$Z$S reached the '. $pos['Rank'] .'. Place!"/>';
					}
					else if (
						  ( ($ast_config['PLAYERS'][$pos['Login']]['last_finishscore'] > 0) && ($pos['BestTime'] < $ast_config['PLAYERS'][$pos['Login']]['last_finishscore']) && ($aseco->server->gameinfo->mode != 4) )
							||
						  ( ($ast_config['PLAYERS'][$pos['Login']]['last_finishscore'] > 0) && ($pos['Score'] > $ast_config['PLAYERS'][$pos['Login']]['last_finishscore']) && ($aseco->server->gameinfo->mode == 4) )
						) {
						// 0,1,2,3,5: Lower = Better
						// 4 'Stunts': Higher = Better

						// Display the Widget
						$show = true;

						// Avatar or Flag from Player
						if ( (isset($ast_config['PLAYERS'][$pos['Login']]['avatar'])) && (!preg_match('/Skins\/Avatars\/Flags\/(\w{3}|other)\.dds$/i', $ast_config['PLAYERS'][$pos['Login']]['avatar'])) ) {
							$addition .= '<quad posn="0 0 0.02" sizen="4.5 4.5" image="tmtp://'. $ast_config['PLAYERS'][$pos['Login']]['avatar'] .'"/>';
						}
						else {
							$addition .= '<quad posn="2.2 0 0.01" sizen="4.4 4.4" halign="center" image="tmtp://Skins/Avatars/Flags/'. (isset($ast_config['PLAYERS'][$pos['Login']]['nation']) ? $ast_config['PLAYERS'][$pos['Login']]['nation'] : 'other') .'.dds"/>';
						}
						$addition .= '<label posn="5.5 -2.25 0.03" sizen="65 4.5" valign="center" textsize="'. $ast_config['PLAYER_PLACES_WIDGET'][0]['TEXTSIZE'][0] .'" textcolor="'. $ast_config['PLAYER_PLACES_WIDGET'][0]['TEXTCOLOR'][0]['SECURED'][0] .'F" text="$SPlayer $'. $ast_config['PLAYER_PLACES_WIDGET'][0]['TEXTCOLOR'][0]['HIGHLITE'][0] . stripColors($finish_item->player->nickname) .'$Z$S secured his/her '. $pos['Rank'] .'. Place!"/>';
					}
				}

				// Set all Player to they actual Place and store the time/score (if one)
				// e.g. from Place 2. to 3. if someone was faster
				if ( ($pos['BestTime'] > 0) || ($pos['Score'] > 0) ) {
					$ast_config['PLAYERS'][$pos['Login']]['last_rank'] = $pos['Rank'];
					if ($pos['BestTime'] > 0) {
						$ast_config['PLAYERS'][$pos['Login']]['last_finishscore'] = $pos['BestTime'];
					}
					else if ($pos['Score'] > 0) {
						$ast_config['PLAYERS'][$pos['Login']]['last_finishscore'] = $pos['Score'];
					}
				}
			}
		}
		else {
			// 0 = Rounds
			// 2 = Team
			// 3 = Laps
			// 5 = Cup

			// Display the Widget
			$show = true;

			// Avatar or Flag from Player
			if ( (isset($ast_config['PLAYERS'][$finish_item->player->login]['avatar'])) && (!preg_match('/Skins\/Avatars\/Flags\/(\w{3}|other)\.dds$/i', $ast_config['PLAYERS'][$finish_item->player->login]['avatar'])) ) {
				$addition .= '<quad posn="0 0 0.02" sizen="4.5 4.5" image="tmtp://'. $ast_config['PLAYERS'][$finish_item->player->login]['avatar'] .'"/>';
			}
			else {
				$addition .= '<quad posn="2.2 0 0.01" sizen="4.4 4.4" halign="center" image="tmtp://Skins/Avatars/Flags/'. (isset($ast_config['PLAYERS'][$finish_item->player->login]['nation']) ? $ast_config['PLAYERS'][$finish_item->player->login]['nation'] : 'other') .'.dds"/>';
			}
			$addition .= '<label posn="5.5 -2.25 0.03" sizen="65 4.5" valign="center" textsize="'. $ast_config['PLAYER_PLACES_WIDGET'][0]['TEXTSIZE'][0] .'" textcolor="'. $ast_config['PLAYER_PLACES_WIDGET'][0]['TEXTCOLOR'][0]['DEFAULT'][0] .'F" text="$SPlayer $'. $ast_config['PLAYER_PLACES_WIDGET'][0]['TEXTCOLOR'][0]['HIGHLITE'][0] . stripColors($finish_item->player->nickname) .'$Z$S has Finished!"/>';
		}

		if ($show == true) {
			// Create the Widget
			$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
			$xml .= '<manialinks>';
			$xml .= '<manialink id="'. $ast_config['MANIALINK_ID'] .'10">';
			$xml .= '<frame posn="'. $ast_config['PLAYER_PLACES_WIDGET'][0]['POS_X'][0] .' '. $ast_config['PLAYER_PLACES_WIDGET'][0]['POS_Y'][0] .' 3">';
			$xml .= $addition;
			$xml .= '</frame>';
			$xml .= '</manialink>';
			$xml .= '</manialinks>';

			$aseco->client->query('SendDisplayManialinkPage', $xml, ($ast_config['PLAYER_PLACES_WIDGET'][0]['TIMEOUT'][0] * 1000), false);
		}
	}


	// Do not display Scoretable on Multilap-Maps
	if ($ast_config['SHOW_AT_FINISH'][0] == true) {

		// No Scoretable at Multilap-Maps and Gamemode 'Rounds', 'Team' and 'Cup' at Player finish
		if ( ($finish_item->challenge->laprace == false) && ($aseco->server->gameinfo->mode != 0) && ($aseco->server->gameinfo->mode != 2) && ($aseco->server->gameinfo->mode != 5) ) {
			// 0 = Rounds
			// 2 = Team
			// 5 = Cup

			// Request 'GetCurrentRanking' only if <player_places_widget> is disabled (otherwise done twice)
			if ($ast_config['PLAYER_PLACES_WIDGET'][0]['ENABLED'][0] == false) {
				ast_getCurrentRanking();
			}

			$ast_config['PLAYERS'][$finish_item->player->login]['delayed_call'] = array(
				'delay'		=> (time() + $ast_config['DELAY_FINISH_DISPLAY'][0]),
				'func'		=> 'ast_showScoretable($aseco, "'. $finish_item->player->login .'", 0, true, 0);',
			);
		}
	}

}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onPlayerInfoChanged
function ast_onPlayerInfoChanged ($aseco, $info) {
	global $ast_config;


	// Find out the TeamId
	$ast_config['PLAYERS'][$info['Login']]['team'] = $info['TeamId'];
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onPlayerDisconnect
function ast_onPlayerDisconnect ($aseco, $player) {
	global $ast_config;


	$ast_config['PLAYERS'][$player->login]['disconnected'] = true;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// $answer = [0]=PlayerUid, [1]=Login, [2]=Answer
// called @ onPlayerManialinkPageAnswer
function ast_onPlayerManialinkPageAnswer ($aseco, $answer) {
	global $ast_config;


	// If id = 0, bail out immediately
	if ($answer[2] == 0) {
		return;
	}

	// Get Player
	$player = $aseco->server->players->getPlayer($answer[1]);

	if ($answer[2] == $ast_config['MANIALINK_ID'] .'02') {			// Display Scoretable

		// $aseco, $caller, $timeout, $display_close, $page
		ast_showScoretable($aseco, $player->login, 0, true, 0);

	}
	else if ($answer[2] == $ast_config['MANIALINK_ID'] .'03') {		// Close Scoretable and possible open LegendWidget/PlayerWidget

		$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, ast_closeAllWidgets(false, false, false), 0, false);

	}
	else if ($answer[2] == $ast_config['MANIALINK_ID'] .'05') {		// Display Legend

		ast_widgetLegend($player, true);

	}
	else if ($answer[2] == $ast_config['MANIALINK_ID'] .'06') {		// Close Legend

		ast_widgetLegend($player, false);

	}
	else if ($answer[2] == $ast_config['MANIALINK_ID'] .'09') {		// Close Player

		$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<manialinks>';
		$xml .= '<manialink id="'. $ast_config['MANIALINK_ID'] .'08"></manialink>';	// PlayerWidget
		$xml .= '</manialinks>';
		$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);

	}
	else if ( ($answer[2] >= (int)$ast_config['MANIALINK_ID'] .'20') && ($answer[2] <= (int)$ast_config['MANIALINK_ID'] .'35') ) {		// Previous pages

		// Get the wished Page
		$page = intval( str_replace($ast_config['MANIALINK_ID'], '', $answer[2]) - 20 );

		// $aseco, $caller, $timeout, $display_close, $page
		ast_showScoretable($aseco, $player->login, 0, true, $page);

	}
	else if ( ($answer[2] >= (int)$ast_config['MANIALINK_ID'] .'40') && ($answer[2] <= (int)$ast_config['MANIALINK_ID'] .'55') ) {		// Next pages

	// Get the wished Page
		$page = intval( str_replace($ast_config['MANIALINK_ID'], '', $answer[2]) - 40 );

		// $aseco, $caller, $timeout, $display_close, $page
		ast_showScoretable($aseco, $player->login, 0, true, $page);

	}
	else if ( ($answer[2] >= sprintf("%d%d", $ast_config['MANIALINK_ID'], 60)) && ($answer[2] <= sprintf("%d%d", $ast_config['MANIALINK_ID'], 9999999)) ) {

		// Remove the MANIALINK_ID(+60) from target PlayerID
		$PlayerID = str_replace($ast_config['MANIALINK_ID'], '', $answer[2]);
		$PlayerID -= 60;

		foreach ($aseco->server->players->player_list as &$target) {
			if ($target->id == $PlayerID) {
				ast_widgetPlayer($target, $player);
				break;
			}
		}

	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// TMF: [0]=PlayerUid, [1]=Login, [2]=TimeScore, [3]=CurLap, [4]=CheckpointIndex
function ast_onCheckpoint ($aseco, $checkpt) {
	global $ast_config;


	// Only work at 'Laps'
	if ( ($aseco->server->gameinfo->mode == 3) && ($ast_config['Challenge']['NbCheckpoints'] !== false) ) {
		// 3 = Laps; If one Player has finish a round, refresh current Ranking
		if ( ($checkpt[4]+1) == ($ast_config['Challenge']['NbCheckpoints'] * $checkpt[3]) ) {
			ast_getCurrentRanking();
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onStatusChangeTo3
function ast_onStatusChangeTo3 ($aseco, $call) {
	global $ast_config;


	// Get status of WarmUp
	$ast_config['WarmUpPhase'] = $aseco->warmup_phase;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onStatusChangeTo5
function ast_onStatusChangeTo5 ($aseco, $call) {
	global $ast_config;


	// Get status of WarmUp
	$ast_config['WarmUpPhase'] = $aseco->warmup_phase;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onEndRace1
function ast_onEndRace1 ($aseco, $race) {
	global $ast_config, $ast_todo;


	// Hide the Quicklink button on 'onEndRace' event
	if ($ast_config['QUICKLINK_BUTTON'][0]['ENABLED'][0] == true) {
		// $caller, $display
		ast_widgetQuicklink(false, false);
	}

	// Disable all (ast)Widgets and the automatic Scoretable
	$aseco->client->query('SendDisplayManialinkPage', ast_closeAllWidgets(false, true, false), 0, false);

	// Remove possible added delayed calls at each Player
	foreach ($ast_config['PLAYERS'] as $login => &$struct) {
		$ast_config['PLAYERS'][$login]['delayed_call'] = false;
		$ast_config['PLAYERS'][$login]['widget_status'] = false;
	}
	unset($struct);

	// Set current state
	$ast_config['CURRENT_STATE'] = 'score';

	// Bail out, if nobody is connected
	if ( count($aseco->server->players->player_list) == 0) {
		return;
	}

	// Do not 'GetCurrentRanking' in Team
	if ($aseco->server->gameinfo->mode != 2) {
		ast_getCurrentRanking();
	}

	// ast_showScoretable($aseco, $caller, $timeout, $display_close, $page)
	$ast_todo[] = array(
		'delay'		=> (time() + $ast_config['DELAY_SCORE_DISPLAY'][0]),
		'func'		=> 'ast_showScoretable($aseco, false, 0, false, 0);',
	);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onRestartChallenge2
function ast_onRestartChallenge2 ($aseco, $challenge) {
	global $ast_config, $ast_todo;


	// Set current State
	$ast_config['CURRENT_STATE'] = 'race';

	// Show the Quicklink button?
	if ($ast_config['QUICKLINK_BUTTON'][0]['ENABLED'][0] ==true) {
		// $caller, $display
		ast_widgetQuicklink(false, true);
	}

	// Reset Todo-List
	$ast_todo = array();

	// Close the Alternate-Scoretable at all players
	$aseco->client->query('SendDisplayManialinkPage', ast_closeAllWidgets(false, true, false), 0, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onNewChallenge
function ast_onNewChallenge ($aseco, $challenge) {
	global $ast_config, $ast_todo, $ast_ranks;


	$aseco->client->query('ManualFlowControlEnable', true);

	// Reset Ranking
	$ast_ranks = array();

	// Reset Todo-List
	$ast_todo = array();

	// Set current State
	$ast_config['CURRENT_STATE'] = 'race';


	// Bail out, if nobody is connected
	if ( count($aseco->server->players->player_list) == 0) {
		return;
	}

	// Close the alternate Scoretable at all players
	$aseco->client->query('SendDisplayManialinkPage', ast_closeAllWidgets(false, true, false), 0, false);


	// Show the Quicklink button?
	if ($ast_config['QUICKLINK_BUTTON'][0]['ENABLED'][0] ==true) {
		// $caller, $display
		ast_widgetQuicklink(false, true);
	}


	// Remove all disconnected players
	foreach ($ast_config['PLAYERS'] as $login => &$struct) {
		if ($ast_config['PLAYERS'][$login]['disconnected'] == true) {
			unset($ast_config['PLAYERS'][$login]);
		}
	}
	unset($login, $struct);


	// Refresh Player status
	foreach ($aseco->server->players->player_list as &$player) {

		// Refresh totalscore of each (connected) Player
		$aseco->client->resetError();
		$aseco->client->query('GetDetailedPlayerInfo', $player->login);
		$info = $aseco->client->getResponse();

		if ( !$aseco->client->isError() ) {

			// If the Player did not get a score at the last match, then $info['LadderStats']['LastMatchScore']
			// has the score from the last scored match inside. Need to reset it here, otherwise the ->totalscore
			// grows wrong.
			if ( !in_array($info['LadderStats']['LastMatchScore'], $ast_config['PLAYERS'][$player->login]['lastscore_overall']) ) {
				$ast_config['PLAYERS'][$player->login]['lastscore'] = $info['LadderStats']['LastMatchScore'];

				// Add won LadderPoints to hidelist
				$ast_config['PLAYERS'][$player->login]['lastscore_overall'][] = $info['LadderStats']['LastMatchScore'];

				// Safe mem.
				$ast_config['PLAYERS'][$player->login]['lastscore_overall'] = array_unique($ast_config['PLAYERS'][$player->login]['lastscore_overall']);
			}
			else {
				$ast_config['PLAYERS'][$player->login]['lastscore'] = 0;
			}
			$ast_config['PLAYERS'][$player->login]['totalscore'] += $ast_config['PLAYERS'][$player->login]['lastscore'];

			// Get actual LadderStats
			$ast_config['PLAYERS'][$player->login]['match_wins'] = $info['LadderStats']['NbrMatchWins'];
			$ast_config['PLAYERS'][$player->login]['match_draws'] = $info['LadderStats']['NbrMatchDraws'];
			$ast_config['PLAYERS'][$player->login]['match_losses'] = $info['LadderStats']['NbrMatchLosses'];
		}

		// Reset finishline crossings
		$ast_config['PLAYERS'][$player->login]['reached_finish'] = 0;

		// Reset finish time
		$ast_config['PLAYERS'][$player->login]['finish_time'] = 0;
		$ast_config['PLAYERS'][$player->login]['last_rank'] = -1;
		$ast_config['PLAYERS'][$player->login]['last_finishscore'] = -1;

		// Get actual Ladder of Player
		$ast_config['PLAYERS'][$player->login]['ladderscore'] = $player->ladderscore;
		$ast_config['PLAYERS'][$player->login]['ladderrank'] = (($player->ladderrank != -1) ? $player->ladderrank : 0);
	}

	$aseco->client->query('ManualFlowControlEnable', false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onNewChallenge2
function ast_onNewChallenge2 ($aseco, $challenge) {
	global $ast_config;


	// Bail out, if nobody is connected
	if ( count($aseco->server->players->player_list) == 0) {
		return;
	}


	// Get the current GameInfos, things about Pointslimit in Rounds and Team...
	$aseco->client->query('GetCurrentGameInfo', 1);
	$ast_config['CurrentGameInfos'] = $aseco->client->getResponse();

	// Catch the "new rules" in Team and Rounds Gamemode if any
	$ast_config['CurrentGameInfos']['RoundsPointsLimit'] = (($ast_config['CurrentGameInfos']['RoundsUseNewRules'] == true) ? $ast_config['CurrentGameInfos']['RoundsPointsLimitNewRules'] : $ast_config['CurrentGameInfos']['RoundsPointsLimit']);
	$ast_config['CurrentGameInfos']['TeamPointsLimit']   = (($ast_config['CurrentGameInfos']['TeamUseNewRules']   == true) ? $ast_config['CurrentGameInfos']['TeamPointsLimitNewRules']   : $ast_config['CurrentGameInfos']['TeamPointsLimit']);

	// Store the NbCheckpoints
	$ast_config['Challenge']['NbCheckpoints'] = $challenge->nbchecks;

	// Get status of WarmUp
	$ast_config['WarmUpPhase'] = $aseco->warmup_phase;


	// Do not 'GetCurrentRanking' in Team
	if ($aseco->server->gameinfo->mode != 2) {
		ast_getCurrentRanking();
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onBeginRound
function ast_onBeginRound ($aseco) {


	// Bail out, if nobody is connected
	if ( count($aseco->server->players->player_list) == 0) {
		return;
	}

	// Disable all (ast)Widgets
	$aseco->client->query('SendDisplayManialinkPage', ast_closeAllWidgets(false, true, false), 0, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onEndRound
function ast_onEndRound ($aseco) {
	global $ast_config, $ast_todo;


	// Bail out, if nobody is connected
	if ( count($aseco->server->players->player_list) == 0) {
		return;
	}

	if ( ($ast_config['SHOW_AT_FINISH'][0] == true) && ($ast_config['WarmUpPhase'] != true) ) {

		// Display Scoretable
		if ( ($aseco->server->gameinfo->mode == 0) || ($aseco->server->gameinfo->mode == 2) || ($aseco->server->gameinfo->mode == 5) ) {
			// 0 = Rounds
			// 2 = Team
			// 5 = Cup

			// Do not 'GetCurrentRanking' in Team
			if ($aseco->server->gameinfo->mode != 2) {
				ast_getCurrentRanking();
			}

			// ast_showScoretable($aseco, $caller, $timeout, $display_close, $page)
			ast_showScoretable($aseco, false, 0, true, 0);
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ast_closeAllWidgets ($preload = false, $include_playerplace = false, $include_customui = false) {
	global $aseco, $ast_config;


	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $ast_config['MANIALINK_ID'] .'01">';

	if ($preload == true) {
		// Place preload outside visible area
		$xml .= '<frame posn="-600 -600 0">';
		$xml .= '<quad posn="0 0 0" sizen="76 2.5" image="'. $ast_config['URLS'][0]['BAR_GOLD'][0] .'"/>';
		$xml .= '<quad posn="0 0 0" sizen="76 2.5" image="'. $ast_config['URLS'][0]['BAR_SILVER'][0] .'"/>';
		$xml .= '<quad posn="0 0 0" sizen="76 2.5" image="'. $ast_config['URLS'][0]['BAR_BRONZE'][0] .'"/>';
		$xml .= '<quad posn="0 0 0" sizen="76 2.5" image="'. $ast_config['URLS'][0]['BAR_DEFAULT'][0] .'"/>';
		$xml .= '<quad posn="0 0 0" sizen="76 2.5" image="'. $ast_config['URLS'][0]['BAR_NORANK'][0] .'"/>';
		$xml .= '<quad posn="0 0 0" sizen="76 2.5" image="'. $ast_config['URLS'][0]['BAR_BLANK'][0] .'"/>';
		$xml .= '<quad posn="0 0 0" sizen="76 2.5" image="'. $ast_config['URLS'][0]['BAR_TEAM'][0] .'"/>';
		$xml .= '</frame>';
	}

	$xml .= '</manialink>';	// Scoretable
	$xml .= '<manialink id="'. $ast_config['MANIALINK_ID'] .'04"></manialink>';	// Legend
	$xml .= '<manialink id="'. $ast_config['MANIALINK_ID'] .'08"></manialink>';	// PlayerWidget

	if ($include_playerplace == true) {
		$xml .= '<manialink id="'. $ast_config['MANIALINK_ID'] .'10"></manialink>';	// PlayerPlaceWidget
	}

	if ($include_customui == true) {
		// Include whole CustomUI-Block
		$xml .= getCustomUIBlock();
	}

	$xml .= '</manialinks>';

	return $xml;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ast_handleSpecialChars ($string) {


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

	return validateUTF8String($string);	// validateUTF8String() from basic.inc.php
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// Stolen from basic.inc.php and adjusted
function ast_formatTime ($MwTime, $hsec = true) {


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


function ast_getCurrentRanking () {
	global $aseco, $ast_config, $ast_ranks;


	// Get the current Ranking from Server
	// The first parameter specifies the maximum number of infos to be returned, and the second one the starting index in the ranking
	$aseco->client->resetError();
	$aseco->client->query('GetCurrentRanking', 300,0);
	$ranks = $aseco->client->getResponse();

	if ( !$aseco->client->isError() ) {

//		// DEVEL ONLY
//		$tmp = array();
//		for ($i = 0; $i < 20; $i++) {
//			foreach ($ranks as $pos) {
//				$pos['Rank'] = $i;
//				if ($i >= 10) {
//					$pos['BestTime'] = -1;
//				}
//				$tmp[] = $pos;
//			}
//		}
//		$ranks = $tmp;

		$ast_ranks = $ranks;

	}
	else {
		trigger_error('[plugin.alternate_scoretable.php] Error at GetCurrentRanking(): [' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
		$ast_ranks = array();
	}
}

?>
