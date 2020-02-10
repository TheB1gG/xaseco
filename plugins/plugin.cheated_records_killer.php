<?php

/*
 * Plugin: Cheated Records Killer
 * ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
 * This Plugin helps the Admins to have a clean database of local records. With this
 * Plugin you can easily remove cheated records. Just type "/rmcheat" and select
 * the cheated local records to delete.
 *
 * Introduced with XAseco/1.10 you can also use "/admin delrec [N]", so this Plugin
 * is in effect just an GUI for that command and safes "only" many typing for the
 * (Master)Admins.
 *
 * This Plugin works only with TMF!
 * ----------------------------------------------------------------------------------
 * Author:		undef.de
 * Version:		0.9.3
 * Date:		2011-10-22
 * Copyright:		2009-2011 by undef.de
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
 * Dependencies: chat.admin.php
 */

/* The following manialink id's are used in this plugin (the 914 part of id can be changed on trouble):
 *
 * ManialinkID's
 * ~~~~~~~~~~~~~
 *  9140			id for manialink Window
 *
 * ActionID's
 * ~~~~~~~~~~
 *  9140			id for action close Window
 * -914000 to -914249		id for action previous page in Window (max. 250 pages a 20 entries = 5000 total)
 *  914000 to  914249		id for action next page in Window (max. 250 pages a 20 entries = 5000 total)
 *  914300 to  914320		id for action remove given Record
 */

Aseco::registerEvent('onSync',				'crk_onSync');
Aseco::registerEvent('onPlayerManialinkPageAnswer',	'crk_onPlayerManialinkPageAnswer');

Aseco::addChatCommand('rmcheat',			'Select a cheated local record to erase them.', true);

global $crk_config;

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function crk_onSync ($aseco) {
	global $crk_config;


	// Check for the right XAseco-Version
	$xaseco_min_version = 1.14;
	if ( defined('XASECO_VERSION') ) {
		if ( version_compare(XASECO_VERSION, $xaseco_min_version, '<') ) {
			trigger_error('[plugin.cheated_records_killer.php] Not supported XAseco version ('. XASECO_VERSION .')! Please update to min. version '. $xaseco_min_version .'!', E_USER_ERROR);
		}
	}
	else {
		trigger_error('[plugin.cheated_records_killer.php] Can not identify the System, "XASECO_VERSION" is unset! This plugin runs only with XAseco/'. $xaseco_min_version .'+', E_USER_ERROR);
	}

	if ($aseco->server->getGame() != 'TMF') {
		trigger_error('[plugin.cheated_records_killer.php] This plugin supports only TMF, can not start with a "'. $aseco->server->getGame() .'" Dedicated-Server!', E_USER_ERROR);
	}

	if (!function_exists('chat_admin')) {
		trigger_error('[plugin.cheated_records_killer.php] You have to enable the Plugin "chat.admin.php" in your "plugins.xml" to run this Plugin!!', E_USER_ERROR);
	}

	$crk_config['MANIALINK_ID'] = '914';
	$crk_config['VERSION'] = '0.9.3';
	$crk_config['BG_LINE'] = 'http://www.abload.de/img/s_bar_ranked7j65.png';


	// Register this to the global version pool (for up-to-date checks)
	$aseco->plugin_versions[] = array(
		'plugin'	=> 'plugin.cheated_records_killer.php',
		'author'	=> 'undef.de',
		'version'	=> $crk_config['VERSION']
	);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onPlayerManialinkPageAnswer
// $answer[0] = $player->id from sender
// $answer[1] = $player->login from sender
// $answer[2] = ManialinkId
function crk_onPlayerManialinkPageAnswer ($aseco, $answer) {
	global $crk_config;


	// Get the Player object
	$player = $aseco->server->players->player_list[$answer[1]];

	// Init
	$xml = '';

	if ($answer[2] == $crk_config['MANIALINK_ID'] .'0') {					// Close Window

		$xml .= '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<manialinks>';
		$xml .= '<manialink id="'. $crk_config['MANIALINK_ID'] .'0"></manialink>';	// Window
		$xml .= '</manialinks>';

		// Clean the Database (no action requested)
		$player->data['CRK_STORAGE'] = array();

	}
	else if ( ($answer[2] <= -(int)sprintf("%d%03d", $crk_config['MANIALINK_ID'], 0)) && ($answer[2] >= -(int)sprintf("%d%03d", $crk_config['MANIALINK_ID'], 249)) ) {

		// Get the wished Page
		$page = intval( str_replace($crk_config['MANIALINK_ID'], '', abs($answer[2])) );

		// Build the Window with the requested page
		$xml .= crk_buildRecordsListWindow($page, $player);

	}
	else if ( ($answer[2] >= (int)sprintf("%d%03d", $crk_config['MANIALINK_ID'], 0)) && ($answer[2] <= (int)sprintf("%d%03d", $crk_config['MANIALINK_ID'], 249)) ) {

		// Get the wished Page
		$page = intval( str_replace($crk_config['MANIALINK_ID'], '', $answer[2]) );

		// Build the Window with the requested page
		$xml .= crk_buildRecordsListWindow($page, $player);

	}
	else if ( ($answer[2] >= (int)$crk_config['MANIALINK_ID'] .'300') && ($answer[2] <= (int)$crk_config['MANIALINK_ID'] .'320') ) {

		// Allow only Admins or MasterAdmins
		if ( ($aseco->isAdmin($player)) || ($aseco->isMasterAdmin($player)) ) {

			// Retrieve the Array position of cheated local record.
			$position = intval( str_replace($crk_config['MANIALINK_ID'], '', $answer[2] - 300) );

			if ( isset($aseco->server->records->record_list[$position]) ) {
				$local = $aseco->server->records->record_list[$player->data['CRK_STORAGE'][$position]['record']];
				$cheat = $player->data['CRK_STORAGE'][$position];
				if ( ($local->player->login == $cheat['login']) && ($local->score == $cheat['score']) ) {

					// Match, now delete the cheated local record
					$command['params'] = 'delrec '. ($cheat['record']+1);	// +1 is required, because chat_admin() does $param-- and we start at 0
					$command['author'] = $player;
					chat_admin($aseco, $command);

					// Clean the Database (no action requested)
					$player->data['CRK_STORAGE'] = array();

					// Build the Window from page 0
					$xml .= crk_buildRecordsListWindow(0, $player);
				}
				else {
					// Show chat message
					$aseco->client->query('ChatSendToLogin', $aseco->formatColors('{#error}Record mismatch, it is possible that an new record has been driven, refreshing the Window now...'), $player->login);

					// Clean the Database (no action requested)
					$player->data['CRK_STORAGE'] = array();

					// Build the Window from page 0
					$xml .= crk_buildRecordsListWindow(0, $player);
				}
			}
		}
	}

	// Send all widgets
	if ($xml != '') {
		// Send Manialink
		$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function chat_rmcheat ($aseco, $command) {
	global $crk_config;


	// Get the Player object
	$player = $aseco->server->players->player_list[$command['author']->login];

	if ( ($aseco->isAdmin($player)) || ($aseco->isMasterAdmin($player)) ) {
		if (count($aseco->server->records->record_list) > 0) {

			// Clean the Database
			$player->data['CRK_STORAGE'] = array();

			// Build the Window with the first page
			$xml = crk_buildRecordsListWindow(0, $player);

			// Send Window to given Player
			$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);

		}
		else {
			// Show chat message
			$aseco->client->query('ChatSendToLogin', $aseco->formatColors('{#error}There are currently no local records to delete!'), $player->login);
		}
	}
	else {
		// Write warning in console
		$aseco->console('Player "'. $player->login .'" tried to use admin chat command "/rmcheat" without the required rights to do this!');

		// Show chat message
		$aseco->client->query('ChatSendToLogin', $aseco->formatColors('{#error}You don\'t have the required admin rights to do that!'), $player->login);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function crk_buildRecordsListWindow ($page, $player) {
	global $aseco, $crk_config;


	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $crk_config['MANIALINK_ID'] .'0">';

	// Window
	$xml .= '<frame posn="-40.1 30.45 -4">';	// BEGIN: Window Frame
	$xml .= '<quad posn="0.8 -0.8 0.10" sizen="78.4 53.7" bgcolor="000A"/>';
	$xml .= '<quad posn="-0.2 0.2 0.11" sizen="80.4 55.7" style="Bgs1InRace" substyle="BgCard3"/>';

	// Header Icon
	$xml .= '<quad posn="-1.7 2 0.20" sizen="5 5" style="BgRaceScore2" substyle="LadderRank"/>';

	// Link and About
	$xml .= '<quad posn="2.7 -54.1 0.12" sizen="14.5 1" url="http://www.undef.de/Trackmania/Plugins/" bgcolor="0000"/>';
	$xml .= '<label posn="2.7 -54.1 0.12" sizen="30 1" halign="left" textsize="1" scale="0.7" textcolor="000F" text="CHEATED RECORDS KILLER/'. $crk_config['VERSION'] .'"/>';

	// Close Button
	$xml .= '<frame posn="77.4 1.3 0">';
	$xml .= '<quad posn="0 0 0.12" sizen="4 4" style="Icons64x64_1" substyle="ArrowDown"/>';
	$xml .= '<quad posn="1.1 -1.35 0.13" sizen="1.8 1.75" bgcolor="EEEF"/>';
	$xml .= '<quad posn="0.65 -0.7 0.14" sizen="2.6 2.6" action="'. $crk_config['MANIALINK_ID'] .'0" style="Icons64x64_1" substyle="Close"/>';
	$xml .= '</frame>';


	// Frame for Previous/Next/... Buttons
	$xml .= '<frame posn="67.05 -53.2 0">';

	// Reload button, display not at Score
	$xml .= '<quad posn="1.65 0 0.12" sizen="3.2 3.2" action="'. sprintf("%d%03d", $crk_config['MANIALINK_ID'], $page) .'" style="Icons64x64_1" substyle="Refresh"/>';

	// Previous button
	if ($page > 0) {
		$xml .= '<quad posn="4.95 0 0.12" sizen="3.2 3.2" action="-'. sprintf("%d%03d", $crk_config['MANIALINK_ID'], ($page - 1)) .'" style="Icons64x64_1" substyle="ArrowPrev"/>';
	}
	else {
		$xml .= '<quad posn="4.95 0 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
		$xml .= '<quad posn="4.95 0 0.13" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
	}

	// Next button (display only if more pages to display)
	$records_count = count($aseco->server->records->record_list);
	if ( ($page < 250) && ($records_count > 20) && (($page+1) < (ceil($records_count/20))) ) {
		$xml .= '<quad posn="8.25 0 0.12" sizen="3.2 3.2" action="'. sprintf("%d%03d", $crk_config['MANIALINK_ID'], ($page + 1)) .'" style="Icons64x64_1" substyle="ArrowNext"/>';
	}
	else {
		$xml .= '<quad posn="8.25 0 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
		$xml .= '<quad posn="8.25 0 0.13" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
	}

	$xml .= '</frame>';


	$xml .= '<frame posn="0 0 0">';

	if ( ($aseco->server->gameinfo->mode == 0) || ($aseco->server->gameinfo->mode == 3) || ($aseco->server->gameinfo->mode == 4) ) {
		// 0 = Rounds
		// 3 = Laps
		// 4 = Stunts
		// Besttime (current) of Player
		$xml .= '<quad posn="6.8 1.3 0.12" sizen="3.1 3.1" style="Icons128x128_1" substyle="Launch"/>';
		$xml .= '<label posn="9.8 -0.15 0.12" sizen="5 3" halign="left" textsize="1" scale="0.7" text="$000SCORE"/>';
	}
	else {
		// Besttime (current) of Player
		$xml .= '<quad posn="6.8 0.8 0.12" sizen="2.3 2.3" style="Icons128x32_1" substyle="RT_TimeAttack"/>';
		$xml .= '<label posn="9.8 -0.15 0.12" sizen="5 3" halign="left" textsize="1" scale="0.7" text="$000TIME"/>';
	}

	// Nickname of Player
	$xml .= '<quad posn="15.2 1.2 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="Buddy"/>';
	$xml .= '<label posn="18.4 -0.15 0.12" sizen="5 3" halign="left" textsize="1" scale="0.7" text="$000PLAYER"/>';

	// Login of Player
	$xml .= '<quad posn="47.2 0.6 0.12" sizen="2.2 2.2" style="Icons128x128_1" substyle="Lan"/>';
	$xml .= '<label posn="49.7 -0.15 0.12" sizen="5 3" halign="left" textsize="1" scale="0.7" text="$000LOGIN"/>';

	$xml .= '</frame>';


	// Build the rows
	$xml .= '<frame posn="2 -2 0">';			// BEGIN: Player lines
	$xml .= '<quad posn="3.9 0 0.20" sizen="0.1 51.26" bgcolor="0003"/>';		// Besttime/Score of Player
	$xml .= '<quad posn="12.2 0 0.20" sizen="0.1 51.26" bgcolor="0003"/>';		// Nickname of Player
	$xml .= '<quad posn="44 0 0.20" sizen="0.1 51.26" bgcolor="0003"/>';		// Login of Player


	// Set line height for Player lines
	$line_height = 2.5;


	// Build the background lines to have full 20 team lines
	$position = 0;
	for ($i = 0; $i < 20; $i++) {
		$xml .= '<quad posn="0 '. $position .' 0.12" sizen="76 '. $line_height .'" image="'. $crk_config['BG_LINE'] .'"/>';
		$position -= ($line_height + 0.07);
	}


	$position = 0;
	$entries = 0;

	$xml .= '<format textsize="1" textcolor="FFFF"/>';
	for ($i = ($page * 20); $i < (($page * 20) + 20); $i ++) {

		// Is there a Record?
		if ( !isset($aseco->server->records->record_list[$i]) ) {
			break;
		}

		// Get record entry
		$entry = &$aseco->server->records->record_list[$i];

		$player->data['CRK_STORAGE'][$entries] = array(
			'record'	=> $i,
			'login'		=> $entry->player->login,
			'score'		=> $entry->score
		);

		// Include the action for deleting this record
		$xml .= '<label posn="0 '. $position .' 0.13" sizen="76 2.4" action="'. $crk_config['MANIALINK_ID'] . sprintf("3%02d", $entries) .'" focusareacolor1="FFF0" focusareacolor2="FFF3" text=" "/>';	// Transparent background =D

		// Record num.
		$xml .= '<label posn="2 '. ($position - 0.5) .' 0.15" sizen="3 2.4" halign="center" textsize="1" text="'. ($i+1) .'"/>';

		// Time/Score; 4 = Stunts
		$xml .= '<label posn="11.5 '. ($position - 0.6) .' 0.15" sizen="8 0" halign="right" textsize="1" scale="0.9" text="'. (($aseco->server->gameinfo->mode == 4) ? $entry->score : crk_formatTime($entry->score)) .'"/>';

		// Nickname
		$xml .= '<label posn="13 '. ($position - 0.3) .' 0.15" sizen="30.5 2.4" halign="left" textsize="1" text="$S'. crk_handleSpecialChars($entry->player->nickname) .'"/>';

		// Login
		$xml .= '<label posn="44.9 '. ($position - 0.3) .' 0.15" sizen="30.5 2.4" halign="left" textsize="1" text="'. $entry->player->login .'"/>';


		$position -= ($line_height + 0.07);

		$entries += 1;

		// Not more then 20 entries per Page
		if ($entries >= 20) {
			break;
		}
	}

	$xml .= '</frame>';					// END: Player lines


	// END: Window Frame
	$xml .= '</frame>';

	$xml .= '</manialink>';
	$xml .= '</manialinks>';

	return $xml;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function crk_handleSpecialChars ($string) {
	global $crk_config;


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

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// Stolen from basic.inc.php and adjusted
function crk_formatTime ($MwTime, $hsec = true) {

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
