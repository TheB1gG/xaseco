<?php

/*
 * Plugin: Info Widget
 * ~~~~~~~~~~~~~~~~~~~
 * For a detailed description and documentation, please refer to:
 * http://labs.undef.de/XAseco1+2/Info-Widget.php
 *
 * ----------------------------------------------------------------------------------
 * Author:		undef.de
 * Version:		1.0.1
 * Date:		2012-02-19
 * Copyright:		2009 - 2012 by undef.de
 * System:		XAseco/1.06+ or XAseco2/0.90+
 * Game:		Trackmania Forever (TMF) or Trackmania2 (ManiaPlanet)
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
 * Dependencies: none
 * Do not use this plugin in combination with "plugin.msglog.php"!
 */

/* The following manialink id's are used in this plugin (the 910 part of id can be changed on trouble):
 * 91000	id for manialinks
 * 91001	id for manialink MessageWindow
 */

Aseco::registerEvent('onSync',				'iwid_onSync');
Aseco::registerEvent('onPlayerConnect',			'iwid_onPlayerConnect');
Aseco::registerEvent('onPlayerDisconnect',		'iwid_onPlayerDisconnect');
Aseco::registerEvent('onPlayerManialinkPageAnswer',	'iwid_onPlayerManialinkPageAnswer');
if (defined('XASECO_VERSION')) {
	Aseco::registerEvent('onNewChallenge',		'iwid_onBeginMap');
	Aseco::registerEvent('onEndRace1',		'iwid_onEndMap1');
}
else if (defined('XASECO2_VERSION')) {
	Aseco::registerEvent('onBeginMap',		'iwid_onBeginMap');
	Aseco::registerEvent('onEndMap1',		'iwid_onEndMap1');
}

Aseco::addChatCommand('toggleinfo',			'Toggle the display of the Info-Widget');

global $iwid_config, $iwid_msgs;
$iwid_msgs = array();

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onSync
function iwid_onSync ($aseco, $command) {
	global $iwid_config;


	$xaseco1_min_version = '1.06';
	$xaseco2_min_version = '0.90';
	if (defined('XASECO_VERSION') && version_compare(XASECO_VERSION, $xaseco1_min_version, '<') ) {
		trigger_error('[plugin.info_widget.php] Not supported XAseco version ('. XASECO_VERSION .')! Please update to min. version '. $xaseco1_min_version .'!', E_USER_ERROR);
	}
	else if (defined('XASECO2_VERSION') && version_compare(XASECO2_VERSION, $xaseco2_min_version, '<') ) {
		trigger_error('[plugin.info_widget.php] Not supported XAseco2 version ('. XASECO2_VERSION .')! Please update to min. version '. $xaseco2_min_version .'!', E_USER_ERROR);
	}
	else if ( !defined('XASECO_VERSION') && !defined('XASECO2_VERSION') ) {
		trigger_error('[plugin.info_widget.php] Can not identify the System, "XASECO_VERSION" or "XASECO2_VERSION" is unset! This plugin runs only with XAseco/'. $xaseco1_min_version .'+ or XAseco2/'. $xaseco2_min_version .'+', E_USER_ERROR);
	}


	if (!$iwid_config = $aseco->xml_parser->parseXML('info_widget.xml')) {
		trigger_error("[plugin.info_widget.php] Could not read/parse config file info_widget.xml !", E_USER_ERROR);
	}
	$iwid_config = $iwid_config['INFO_WIDGET'];


	$iwid_config['MANIALINK_ID'] = '910';
	$iwid_config['VERSION'] = '1.0.1';

	// Register this to the global version pool (for up-to-date checks)
	$aseco->plugin_versions[] = array(
		'plugin'	=> 'plugin.info_widget.php',
		'author'	=> 'undef.de',
		'version'	=> $iwid_config['VERSION']
	);


	$iwid_config['TEXT_SIZE'][0] = intval($iwid_config['TEXT_SIZE'][0]);
	$iwid_config['TEXT_SCALE'][0] = intval($iwid_config['TEXT_SCALE'][0]);
	$iwid_config['WIDGET'][0]['AUTO_HIDE'][0] = intval($iwid_config['WIDGET'][0]['AUTO_HIDE'][0]);
	$iwid_config['AMOUNT_MESSAGES'][0] = intval($iwid_config['AMOUNT_MESSAGES'][0]);

	if ($iwid_config['TEXT_SCALE'][0] == 0) {
		$iwid_config['TEXT_SCALE'][0] = 1;
	}
	if ($iwid_config['TEXT_SCALE'][0] > 1) {
		$iwid_config['TEXT_SCALE'][0] = 1;
	}

	if ($iwid_config['TEXT_SIZE'][0] <= 0) {
		$iwid_config['TEXT_SIZE'][0] = 1;
	}
	if ($iwid_config['TEXT_SIZE'][0] > 3) {
		$iwid_config['TEXT_SIZE'][0] = 1;
	}

	if ($iwid_config['WIDGET'][0]['AUTO_HIDE'][0] > 0) {
		$iwid_config['WIDGET'][0]['AUTO_HIDE'][0] = $iwid_config['WIDGET'][0]['AUTO_HIDE'][0] * 1000;
	}

	// Set multiplier for line-height of Textsizes
	$iwid_config['MULTIPLIER'] = array(2,2,5,9);

	$iwid_config['OPACITY'] = array('1','3','5','8','A','B','D','E','F');
	$iwid_config['DISPLAY_STATUS'] = true;

	if (strtoupper($iwid_config['HIDE_NOTICE_MESSAGES'][0]) == 'TRUE') {
		setCustomUIField('notice', false);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onPlayerConnect
function iwid_onPlayerConnect ($aseco, $player) {
	global $iwid_config, $iwid_msgs;


	// Init the Player Array
	$iwid_msgs[$player->login] = array();

	$player->data['InfoWidgetStatus'] = true;

	$message = '$0B3This is the plugin {#highlite}$L[http://labs.undef.de/XAseco1+2/Info-Widget.php]Info-Widget/'. $iwid_config['VERSION'] .'$L$0B3 for xaseco.'. LF .'$0B3To hide this message type {#highlite}/toggleinfo';
	send_window_message($aseco, $message, $player);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onPlayerDisconnect
function iwid_onPlayerDisconnect ($aseco, $player) {
	global $iwid_msgs;


	// Remove Player log from array to save mem
	$tmp = array();
	foreach ($iwid_msgs as $ply => $array) {
		if ($ply != $player->login) {
			$tmp[$ply] = $array;
		}
	}
	$iwid_msgs = $tmp;
	unset($tmp);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onNewChallenge/onBeginMap
function iwid_onBeginMap ($aseco) {
	global $iwid_config;


	$iwid_config['DISPLAY_STATUS'] = true;

	// display the messages
	foreach ($aseco->server->players->player_list as $player) {
		iwid_displayMessageWindow($aseco, $player);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onEndRace1/onEndMap1
function iwid_onEndMap1 ($aseco) {
	global $iwid_config;


	$iwid_config['DISPLAY_STATUS'] = false;

	// Hide the Widget at all Players
	iwid_hideMessageWindow($aseco, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// Save the messages into an array
// $param can be 'true', 'false' or the Player object
function send_window_message ($aseco, $message, $param = false) {
	global $iwid_config, $iwid_msgs;


	// If $param is the Player object, send the message to the given player
	if ( ($param !== true) && ($param !== false) && (isset($param->id)) ) {

		// Add message to Player
		iwid_addMessageToPlayer($aseco, $param, $message);

		if ($iwid_config['DISPLAY_STATUS'] == true) {
			iwid_displayMessageWindow($aseco, $param);
		}
	}
	else {
		// Display the messages to all players
		foreach ($aseco->server->players->player_list as $player) {

			// Add message to Player
			iwid_addMessageToPlayer($aseco, $player, $message);

			if ($iwid_config['DISPLAY_STATUS'] == true) {
				iwid_displayMessageWindow($aseco, $player);
			}
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// Add an new message to the Player Array
function iwid_addMessageToPlayer ($aseco, $player, $message) {
	global $iwid_config, $iwid_msgs;


	// Transform CRLF to LF
	$message = str_replace("\r", '', $message);

	// Add the new message
	$msg = explode(LF, $message);
	if ( is_array($msg) ) {
		foreach ($msg as &$text) {
			$text = iwid_cleanUp($text);
		}
		$iwid_msgs[$player->login] = array_merge($iwid_msgs[$player->login], $msg);
	}
	else {
		array_push($iwid_msgs[$player->login], iwid_cleanUp($msg));
	}
	unset($msg);


	// If more then $iwid_config['AMOUNT_MESSAGES'][0] messages in the array, get the last $iwid_config['AMOUNT_MESSAGES'][0]
	if ( count($iwid_msgs[$player->login]) > $iwid_config['AMOUNT_MESSAGES'][0]) {
		$iwid_msgs[$player->login] = array_slice($iwid_msgs[$player->login], (count($iwid_msgs[$player->login]) - $iwid_config['AMOUNT_MESSAGES'][0]), $iwid_config['AMOUNT_MESSAGES'][0]);
	}


	// If less then $iwid_config['AMOUNT_MESSAGES'][0], just fill up
	if ( count($iwid_msgs[$player->login]) < $iwid_config['AMOUNT_MESSAGES'][0]) {
		$tmp = array();
		for ($i = count($iwid_msgs[$player->login]); $i < $iwid_config['AMOUNT_MESSAGES'][0]; $i++) {
			array_push($tmp, ' ');
		}
		$iwid_msgs[$player->login] = array_merge($tmp, $iwid_msgs[$player->login]);
		unset($tmp);
	}

	// If message window is hidden for this player, so just show all messages in chat too.
	if ($player->data['InfoWidgetStatus'] == false) {
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
	}

}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function iwid_cleanUp ($message) {


	// clean up for xml
	// http://en.tm-wiki.org/wiki/Text_Formatting
	$message = preg_replace('/^(\{#[A-Z]+\}|\$[A-F0-9TISWNMGZOHLP\$]+)(>|>>) (.*)$/i', '$1$3', $message);	// remove > or >> from message or all colors and formating codes
	$message = str_ireplace('$S', '', $message);								// remove $s case-insensitive
	$message = str_replace('"', '&quot;', $message);
	$message = str_replace("'", '&apos;', $message);
	$message = str_replace('<', '&lt;', $message);
	$message = str_replace('>', '&gt;', $message);

	return $message;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// displays the message window
function iwid_displayMessageWindow ($aseco, $player) {
	global $iwid_config, $iwid_msgs;


	// When the player wish to hide this window, bail out.
	if ($player->data['InfoWidgetStatus'] == false) {
		return;
	}

	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks id="'. $iwid_config['MANIALINK_ID'] .'00">';
	$xml .= '<manialink id="'. $iwid_config['MANIALINK_ID'] .'01">';
	$xml .= '<frame posn="'. $iwid_config['WIDGET'][0]['WIDGET_POS_X'][0] .' '. $iwid_config['WIDGET'][0]['WIDGET_POS_Y'][0] .' 0">';
	$line = 0;
	$opacity = 0;
	foreach ($iwid_msgs[$player->login] as $msg) {
		if (strtoupper($iwid_config['TEXT_SHADOW'][0]) == 'TRUE') {
			$msg = '$S'. $msg;
		}
		$xml .= '<label sizen="79.6 0" posn="0 '. -$line .' 0" scale="'. $iwid_config['TEXT_SCALE'][0] .'" textsize="'. $iwid_config['TEXT_SIZE'][0] .'" textcolor="FFF'. $iwid_config['OPACITY'][$opacity] .'" text="'. $aseco->formatColors($msg) .'"/>';
		$line += (($iwid_config['TEXT_SCALE'][0] / $iwid_config['TEXT_SIZE'][0]) * $iwid_config['MULTIPLIER'][$iwid_config['TEXT_SIZE'][0]]);
		$opacity += 1;
	}
	$xml .= '</frame>';
	$xml .= '</manialink>';
	$xml .= '</manialinks>';

	$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, $iwid_config['WIDGET'][0]['AUTO_HIDE'][0], false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function iwid_hideMessageWindow ($aseco, $player = false) {
	global $iwid_config, $iwid_msgs;


	// If the Window is already hidden, bail out.
	if ( ($player != false) && ($player->data['InfoWidgetStatus'] == false) ) {
		return;
	}

	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks id="'. $iwid_config['MANIALINK_ID'] .'00">';
	$xml .= '<manialink id="'. $iwid_config['MANIALINK_ID'] .'01">';
	$xml .= '</manialink>';
	$xml .= '</manialinks>';


	if ($player == false) {
		$aseco->client->query('SendDisplayManialinkPage', $xml, 0, false);
	}
	else {
		$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function chat_toggleinfo ($aseco, $command) {


	if ($command['author']->data['InfoWidgetStatus'] == false) {
		$command['author']->data['InfoWidgetStatus'] = true;
		iwid_displayMessageWindow($aseco, $command['author']);

		$message = '{#server}> $0F3Your message window is now shown, type {#highlite}/toggleinfo$0F3 to hide them.';
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
	}
	else {
		iwid_hideMessageWindow($aseco, $command['author']);
		$command['author']->data['InfoWidgetStatus'] = false;

		$message = '{#server}> $0F3Your message window is now hidden, type {#highlite}/toggleinfo$0F3 to reactivate them.';
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function iwid_onPlayerManialinkPageAnswer ($aseco, $answer) {
	global $sn_config;


	if ($answer[2] == 382009003) {

		// Get Player
		$player = $aseco->server->players->getPlayer($answer[1]);

		// Player has pressed key F7 (same ManialinkId as plugin.fufi.widgets.php)
		if ($player->data['InfoWidgetStatus'] == true) {
			// Hide the widget
			iwid_hideMessageWindow($aseco, $player);
			$player->data['InfoWidgetStatus'] = false;
		}
		else {
			// Show the widget
			$player->data['InfoWidgetStatus'] = true;
			iwid_displayMessageWindow($aseco, $player);
		}

	}

}

?>
