<?php

/*
 * Plugin: Server Neighborhood
 * ~~~~~~~~~~~~~~~~~~~~~~~~~~~
 * For a detailed description and documentation, please refer to:
 * http://www.undef.name/XAseco1/Server-Neighborhood.php
 *
 * ----------------------------------------------------------------------------------
 * Author:		undef.de
 * Contributors:	Milenco, .anDy
 * Version:		1.4.8
 * Date:		2016-07-20
 * Copyright:		2009 - 2016 by undef.de
 * System:		XAseco2/1.16+
 * Game:		Trackmania Forever (TMF)
 * ----------------------------------------------------------------------------------
 *
 * LICENSE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * ----------------------------------------------------------------------------------
 *
 * Dependencies: chat.players.php, chat.stats.php
 */

/* The following manialink id's are used in this plugin (the 912 part of id can be changed on trouble):
 * 91200		id for action that is ignored
 * 91201		id for manialink Widgets
 * 91202		id for action call chat_players() (Dependencies: chat.players.php, chat.stats.php)
 * 91203		id for manialink Overview/ServerOverview
 * 91204		id for action Close Overview/ServerOverview
 * 91205		id for manialink Trackinfo
 * 91206		id for action display Trackinfo
 * 91207		id for action close Trackinfo
 * 91208		id for manialink Countdown refresh
 * 91209		id for action open ServerOverview (display all Neighbors)
 * 91220-91235		id for action previous page in Overview (max. 15 pages)
 * 91240-91255		id for action next page in Overview (max. 15 pages)
 * 912100-912199	id for action open server overview (nn count of servers, max. 100 Server)
 * 912200-912299	id for action open Trackinfo (nn count of servers, max. 100 Server/Tracks)
 * 382009003		id for action pressed Key F7 to toggle Widget (same ManialinkId as plugin.fufi.widgets.php/plugin.records_eyepiece.php)
 */

Aseco::registerEvent('onSync',					'sn_onSync');
Aseco::registerEvent('onPlayerConnect',				'sn_storeData');
Aseco::registerEvent('onPlayerConnect',				'sn_onPlayerConnect');
Aseco::registerEvent('onPlayerDisconnect',			'sn_storeData');
Aseco::registerEvent('onPlayerDisconnect',			'sn_onPlayerDisconnect');
Aseco::registerEvent('onPlayerInfoChanged',			'sn_storeData');
Aseco::registerEvent('onNewChallenge',				'sn_onNewChallenge');
Aseco::registerEvent('onNewChallenge2',				'sn_onNewChallenge2');
Aseco::registerEvent('onEndRace1',				'sn_onEndRace1');
Aseco::registerEvent('onEverySecond',				'sn_onEverySecond');
Aseco::registerEvent('onPlayerManialinkPageAnswer',		'sn_onPlayerManialinkPageAnswer');

Aseco::addChatCommand('neighborreload',				'Reload the settings for the Server-Neighborhood plugin', true);

global $sn_config, $sn_servers;

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function sn_onSync ($aseco) {
	global $sn_config, $sn_servers;


	// Check for the right XAseco-Version
	$xaseco_min_version = '1.16';
	if ( defined('XASECO_VERSION') ) {
		$version = str_replace(
			array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i'),
			array('.1','.2','.3','.4','.5','.6','.7','.8','.9'),
			XASECO_VERSION
		);
		if ( version_compare($version, $xaseco_min_version, '<') ) {
			trigger_error('[plugin.server_neighborhood.php] Not supported XAseco version ('. XASECO_VERSION .')! Please update to min. version '. $xaseco_min_version .'!', E_USER_ERROR);
		}
	}
	else {
		trigger_error('[plugin.server_neighborhood.php] Can not identify the System, "XASECO_VERSION" is unset! This plugin runs only with XAseco/'. $xaseco_min_version .'+', E_USER_ERROR);
	}

	if ($aseco->server->getGame() != 'TMF') {
		trigger_error('[plugin.server_neighborhood.php] This plugin supports only TMF, can not start with a "'. $aseco->server->getGame() .'" Dedicated-Server!', E_USER_ERROR);
	}

	if (!$sn_config = $aseco->xml_parser->parseXML('server_neighborhood.xml', true, true)) {
		trigger_error("[plugin.server_neighborhood.php] Could not read/parse config file server_neighborhood.xml !", E_USER_ERROR);
	}

	$sn_config = $sn_config['SERVER_NEIGHBORHOOD'];
	$sn_servers = array();

	$gamemodes = array(
		Gameinfo::RNDS	=> array('name' => 'ROUNDS',		'icon' => 'RT_Rounds'),
		Gameinfo::TA	=> array('name' => 'TIME_ATTACK',	'icon' => 'RT_TimeAttack'),
		Gameinfo::TEAM	=> array('name' => 'TEAM',		'icon' => 'RT_Team'),
		Gameinfo::LAPS	=> array('name' => 'LAPS',		'icon' => 'RT_Laps'),
		Gameinfo::STNT	=> array('name' => 'STUNTS',		'icon' => 'RT_Stunts'),
		Gameinfo::CUP	=> array('name' => 'CUP',		'icon' => 'RT_Cup'),
	);

	foreach ($gamemodes as $id => &$gamemode) {
		if ( isset($sn_config['WIDGET'][0]['GAMEMODE'][0][$gamemode['name']]) ) {
			// Translate e.g. 'rounds' to id '0', 'time_attack' to id '1'...
			$sn_config['WIDGET'][0]['GAMEMODE'][0][$id] = $sn_config['WIDGET'][0]['GAMEMODE'][0][$gamemode['name']];
			unset($sn_config['WIDGET'][0]['GAMEMODE'][0][$gamemode['name']]);
		}

		if ( isset($sn_config['WIDGET'][0]['GAMEMODE'][0][$id][0]['ENABLED'][0]) ) {
			// Transform 'TRUE' or 'FALSE' from string to boolean
			$sn_config['WIDGET'][0]['GAMEMODE'][0][$id][0]['ENABLED'][0] = ((strtoupper($sn_config['WIDGET'][0]['GAMEMODE'][0][$id][0]['ENABLED'][0]) == 'TRUE')  ? true : false);
		}
		else{
			// not set disable this
			$sn_config['WIDGET'][0]['GAMEMODE'][0][$id][0]['ENABLED'][0] = false;
		}

		// Make sure positions are set, otherwise set to 0
		if (!isset($sn_config['WIDGET'][0]['GAMEMODE'][0][$id][0]['POS_X'][0])) {
			$sn_config['WIDGET'][0]['GAMEMODE'][0][$id][0]['POS_X'][0] = 0;
		}
		if (!isset($sn_config['WIDGET'][0]['GAMEMODE'][0][$id][0]['POS_Y'][0])) {
			$sn_config['WIDGET'][0]['GAMEMODE'][0][$id][0]['POS_Y'][0] = 0;
		}
		// Set Icon for gamemode
		$sn_config['WIDGET'][0]['GAMEMODE'][0][$id][0]['ICON'][0] = $gamemode['icon'];
	}
	unset($gamemodes, $gamemode);

	// Transform 'TRUE' or 'FALSE' from string to boolean
	$sn_config['NICEMODE'][0]['ENABLED'][0]			= ((strtoupper($sn_config['NICEMODE'][0]['ENABLED'][0]) == 'TRUE')			? true : false);
	$sn_config['NICEMODE'][0]['FORCE'][0]			= ((strtoupper($sn_config['NICEMODE'][0]['FORCE'][0]) == 'TRUE')			? true : false);
	$sn_config['SHOW_ON_END_RACE'][0]			= ((strtoupper($sn_config['SHOW_ON_END_RACE'][0]) == 'TRUE')				? true : false);
	$sn_config['WIDGET'][0]['TIMER_BAR'][0]['ENABLED'][0]	= ((strtoupper($sn_config['WIDGET'][0]['TIMER_BAR'][0]['ENABLED'][0]) == 'TRUE')	? true : false);

	// Make sure refresh´s are set, otherwise set default
	$sn_config['REFRESH_INTERVAL'][0]			= ((isset($sn_config['REFRESH_INTERVAL'][0])) ? (int)$sn_config['REFRESH_INTERVAL'][0] : 10);
	$sn_config['NICEMODE'][0]['REFRESH_INTERVAL'][0]	= ((isset($sn_config['NICEMODE'][0]['REFRESH_INTERVAL'][0])) ? (int)$sn_config['NICEMODE'][0]['REFRESH_INTERVAL'][0] : 20);

	// Save the default refresh interval, in NiceMode the $sn_config['REFRESH_INTERVAL'][0] are replaced
	$sn_config['REFRESH_INTERVAL_DEFAULT'][0]		= $sn_config['REFRESH_INTERVAL'][0];

	$sn_config['DEBUG'][0]					= (isset($sn_config['DEBUG'][0]))? strtoupper($sn_config['DEBUG'][0]) : 'FALSE';

	$sn_config['DISPLAY_COUNTER'] = false;

	$sn_config['MANIALINK_ID'] = '912';
	$sn_config['SET_ACTIVE'] = true;
	$sn_config['REFRESH_COUNT'] = 0;
	$sn_config['VERSION'] = '1.4.8';

	// Register this to the global version pool (for up-to-date checks)
	$aseco->plugin_versions[] = array(
		'plugin'	=> 'plugin.server_neighborhood.php',
		'author'	=> 'undef.de',
		'version'	=> $sn_config['VERSION']
	);

	if ( !$sn_config['SERVER_DISPLAY_MAX'][0] ) {
		$sn_config['SERVER_DISPLAY_MAX'][0] = 0;
	}
	$sn_config['LAST_START'][0] = 0;

	// Allows overwriting of existing files on the remote FTP server,
	// creates a stream context resource with the defined options
	$sn_config['STREAM_CONTEXT'] = stream_context_create( array('ftp' => array('overwrite' => true)) );

	// Set self (serverlogin)
	$sn_config['SERVER_LOGIN'] = $aseco->server->serverlogin;

	// At startup do not send any Widgets, only after onNewChallenge2
	$sn_config['STARTUP_PHASE'] = true;

	// Set some States
	$sn_config['STATES']['LAST_UPDATE'] = 0;
	$sn_config['STATES']['NICEMODE'] = (($sn_config['NICEMODE'][0]['ENABLED'][0] == true) ? $sn_config['NICEMODE'][0]['FORCE'][0] : false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function chat_neighborreload ($aseco, $command) {
	global $sn_config;


	// Bailout if Player is not an MasterAdmin
	if (!$aseco->isMasterAdmin($command['author'])) {
		return;
	}

	// Write notice to the logfile
	$aseco->console('[plugin.server_neighborhood.php] MasterAdmin '. $command['author']->login .' reloads the configuration.');

	// Close all Widgets at all Players
	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'01"></manialink>';	// Widget
	$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'03"></manialink>';	// Overview
	$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'05"></manialink>';	// Trackinfo
	$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'08"></manialink>';	// Refresh Counter
	$xml .= '</manialinks>';
	$aseco->client->query('SendDisplayManialinkPage', $xml, 0, false);

	// Reload the config
	sn_onSync($aseco);

	// Simulate the event 'onNewChallenge'
	sn_onNewChallenge($aseco, $aseco->server->challenge);

	// Simulate the event 'onNewChallenge2'
	sn_onNewChallenge2($aseco, $aseco->server->challenge);


	$message = '{#admin}>> Reload of the configuration "server_neighborhood.xml" done.';
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function sn_onNewChallenge ($aseco, $challenge_item) {
	global $sn_config;


	// Check if it is time to switch from "normal" to "nice"-mode or back
	sn_checkServerLoad();

	$sn_config['SET_ACTIVE'] = true;
	sn_buildWidget(null);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function sn_onNewChallenge2 ($aseco, $challenge_item) {
	global $sn_config;


	$sn_config['STARTUP_PHASE'] = false;
	sn_storeData($aseco, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function sn_onEndRace1 ($aseco, $command) {
	global $sn_config;


	// Close all Widgets
	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'03"></manialink>';		// Overview
	$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'05"></manialink>';		// Trackinfo
	$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'08"></manialink>';		// Refresh Counter
	$xml .= '</manialinks>';
	$aseco->client->query('SendDisplayManialinkPage', $xml, 0, false);


	if ($sn_config['SHOW_ON_END_RACE'][0] == false) {
		$sn_config['SET_ACTIVE'] = false;
		sn_buildWidget(null);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function sn_onEverySecond ($aseco, $command) {
	global $sn_config;


	if ($sn_config['REFRESH_COUNT'] >= $sn_config['REFRESH_INTERVAL'][0]) {

		// Reset RefreshCount
		$sn_config['REFRESH_COUNT'] = 1;

		sn_storeData($aseco, false);
		sn_buildWidget(null);
	}
	else {
		$sn_config['REFRESH_COUNT'] += 1;		// Count up RefreshCount
	}

	if ( ($sn_config['SERVER_DISPLAY_MAX'][0] > 0) && ($sn_config['SET_ACTIVE'] == true) && ($sn_config['DISPLAY_COUNTER'] == true) ) {
		sn_buildWidgetRefreshCounter();
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function sn_storeData ($aseco, $unused) {
	global $sn_config;


	if ($sn_config['STORING_PATH'][0] == '') {
		sn_onSync($aseco);
	}

	// Do nothing at startup
	if ($sn_config['STARTUP_PHASE'] == true) {
		return;
	}

	$CurrentPlayerCount = count($aseco->server->players->player_list);
	$CurrentSpectatorCount = 0;
	foreach ($aseco->server->players->player_list as &$player) {
			if ($player->isspectator == 1) {
				$CurrentSpectatorCount++;
			}
	}
	unset($player);
	$CurrentPlayerCount = $CurrentPlayerCount - $CurrentSpectatorCount;


	$aseco->client->query('GetServerPassword');
	$ServerWithPassword = ($aseco->client->getResponse() ? 'true' : 'false');

	// Need this query, because XASECO does not know the changes in
	// $aseco->server->maxspec and $aseco->server->maxplay
	$aseco->client->query('GetServerOptions');
	$ServerOptions = $aseco->client->getResponse();

	// Search and replace some special chars from ServerName
	$ServerOptions['Name'] = str_ireplace('$S', '', $ServerOptions['Name']);	// Remove $S (case-insensitive)
	$ServerOptions['Name'] = str_replace('&', '&amp;', $ServerOptions['Name']);	// Convert &
	$ServerOptions['Name'] = str_replace('"', '&quot;', $ServerOptions['Name']);	// Convert "
	$ServerOptions['Name'] = str_replace("'", '&apos;', $ServerOptions['Name']);	// Convert '
	$ServerOptions['Name'] = str_replace('>', '&gt;', $ServerOptions['Name']);	// Convert >
	$ServerOptions['Name'] = str_replace('<', '&lt;', $ServerOptions['Name']);	// Convert <

	// Remove links, e.g. "$(L|H|P)[...]...$(L|H|P)"
	$ServerOptions['Name'] = preg_replace('/\${1}(L|H|P)\[.*?\](.*?)\$(L|H|P)/i', '$2', $ServerOptions['Name']);
	$ServerOptions['Name'] = preg_replace('/\${1}(L|H|P)\[.*?\](.*?)/i', '$2', $ServerOptions['Name']);
	$ServerOptions['Name'] = preg_replace('/\${1}(L|H|P)(.*?)/i', '$2', $ServerOptions['Name']);

	// Search and replace some special chars from MapName
	$MapName = $aseco->server->challenge->name;
	$MapName = str_ireplace('$S', '', $MapName);					// Remove $S (case-insensitive)
	$MapName = str_replace('&', '&amp;', $MapName);					// Convert &
	$MapName = str_replace('"', '&quot;', $MapName);				// Convert "
	$MapName = str_replace("'", '&apos;', $MapName);				// Convert '
	$MapName = str_replace('>', '&gt;', $MapName);					// Convert >
	$MapName = str_replace('<', '&lt;', $MapName);					// Convert <


	// Create Server informations
	$xml = '<?xml version="1.0" encoding="utf-8" ?>'."\n";
	$xml .= '<server_neighborhood>'."\n";
	$xml .= ' <server>'."\n";
	$xml .= '  <last_modified>'. time() .'</last_modified>'."\n";
	$xml .= '  <login>'. $sn_config['SERVER_LOGIN'] .'</login>'."\n";
	$xml .= '  <name>'. $ServerOptions['Name'] .'</name>'."\n";
	$xml .= '  <zone>'. $aseco->server->zone .'</zone>'."\n";
	$xml .= '  <private>'. $ServerWithPassword .'</private>'."\n";
	$xml .= '  <game>'. $aseco->server->getGame() .'</game>'."\n";
	$xml .= '  <gamemode>'. $aseco->server->gameinfo->mode .'</gamemode>'."\n";
	$xml .= '  <packmask>'. strtoupper($aseco->server->packmask) .'</packmask>'."\n";
	$xml .= '  <players>'."\n";
	$xml .= '   <current>'. $CurrentPlayerCount .'</current>'."\n";
	$xml .= '   <maximum>'. $ServerOptions['CurrentMaxPlayers'] .'</maximum>'."\n";
	$xml .= '  </players>'."\n";
	$xml .= '  <spectators>'."\n";
	$xml .= '   <current>'. $CurrentSpectatorCount .'</current>'."\n";
	$xml .= '   <maximum>'. $ServerOptions['CurrentMaxSpectators'] .'</maximum>'."\n";
	$xml .= '  </spectators>'."\n";
	$xml .= '  <ladder>'."\n";
	$xml .= '   <minimum>'. substr(($aseco->server->laddermin / 1000), 0, 3) .'</minimum>'."\n";
	$xml .= '   <maximum>'. substr(($aseco->server->laddermax / 1000), 0, 3) .'</maximum>'."\n";
	$xml .= '  </ladder>'."\n";
	$xml .= ' </server>'."\n";
	$xml .= ' <current>'."\n";
	$xml .= '  <map>'."\n";
	$xml .= '   <name>'. $MapName .'</name>'."\n";
	$xml .= '   <author>'. $aseco->server->challenge->author .'</author>'."\n";
	$xml .= '   <environment>'. $aseco->server->challenge->environment .'</environment>'."\n";
	$xml .= '   <mood>'. $aseco->server->challenge->mood .'</mood>'."\n";
	$xml .= '   <authortime>'. (($aseco->server->gameinfo->mode == Gameinfo::STNT) ? $aseco->server->challenge->gbx->authorScore : formatTime($aseco->server->challenge->gbx->authorTime)) .'</authortime>'."\n";
	$xml .= '   <goldtime>'. (($aseco->server->gameinfo->mode == Gameinfo::STNT) ? $aseco->server->challenge->gbx->goldTime : formatTime($aseco->server->challenge->gbx->goldTime)) .'</goldtime>'."\n";
	$xml .= '   <silvertime>'. (($aseco->server->gameinfo->mode == Gameinfo::STNT) ? $aseco->server->challenge->gbx->silverTime : formatTime($aseco->server->challenge->gbx->silverTime)) .'</silvertime>'."\n";
	$xml .= '   <bronzetime>'. (($aseco->server->gameinfo->mode == Gameinfo::STNT) ? $aseco->server->challenge->gbx->bronzeTime : formatTime($aseco->server->challenge->gbx->bronzeTime)) .'</bronzetime>'."\n";
	$xml .= '   <mxurl>'. str_replace('&', '&amp;', (isset($aseco->server->challenge->tmx->pageurl)) ? $aseco->server->challenge->tmx->pageurl : '') .'</mxurl>'."\n";
	$xml .= '  </map>'."\n";
	$xml .= '  <players>'."\n";
	foreach ($aseco->server->players->player_list as &$player) {
			$nickname = validateUTF8String($player->nickname);

			$nickname = str_ireplace('$S', '', $nickname);			// Remove $S (case-insensitive)
			$nickname = str_replace('&', '&amp;', $nickname);		// Convert &
			$nickname = str_replace('"', '&quot;', $nickname);		// Convert "
			$nickname = str_replace("'", '&apos;', $nickname);		// Convert '
			$nickname = str_replace('>', '&gt;', $nickname);		// Convert >
			$nickname = str_replace('<', '&lt;', $nickname);		// Convert <

			// Remove links, e.g. "$(L|H)[...]...$(L|H)"
			$nickname = preg_replace('/\${1}(L|H)\[.*?\](.*?)\$(L|H)/i', '$2', $nickname);
			$nickname = preg_replace('/\${1}(L|H)\[.*?\](.*?)/i', '$2', $nickname);

			$xml .= '   <player>'."\n";
			$xml .= '     <nickname>'. $nickname .'</nickname>'."\n";
			$xml .= '     <login>'. $player->login .'</login>'."\n";
			$xml .= '     <nation>'. sn_getFlagOfNation($player->nation) .'</nation>'."\n";
			$xml .= '     <ladder>'. $player->ladderrank .'</ladder>'."\n";
			$xml .= '     <spectator>'. bool2text($player->isspectator) .'</spectator>'."\n";
			$xml .= '   </player>'."\n";
	}
	unset($player);
	$xml .= '  </players>'."\n";
	$xml .= ' </current>'."\n";
	$xml .= '</server_neighborhood>'."\n";

	$filename = $sn_config['STORING_PATH'][0] . $sn_config['SERVER_LOGIN'] .'_serverinfo.xml';

	// Opens the file for writing and truncates it to zero length
	// Try min. 40 times to open if it fails (write block)
	$tries = 0;
	while ( !$fh = fopen($filename, "w", 0, $sn_config['STREAM_CONTEXT']) ) {
		if ($tries > 40) {
			break;
		}
		$tries ++;
	}
	if ($tries >= 40) {
		trigger_error('[plugin.server_neighborhood.php] Could not open file "'. $filename .'" to store the Server Information!', E_USER_WARNING);
	}
	else {
		fwrite($fh, $xml);
		fclose($fh);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function sn_getServerData () {
	global $aseco, $sn_config, $sn_servers;


	if ((count($sn_config['SERVER_ACCOUNTS'][0]['SERVER_NEIGHBOR']) > 0) && ((time() - $sn_config['STATES']['LAST_UPDATE']) >= $sn_config['REFRESH_INTERVAL'][0]) ) {
		// Retrieve all server data
		$servers = array();
		foreach ($sn_config['SERVER_ACCOUNTS'][0]['SERVER_NEIGHBOR'] as &$server_neighbor) {

			// Reset and clean-up server-data
			$neighbor = array();

			if ( ($server_neighbor['PATH'][0] == '') || (strtoupper($server_neighbor['PATH'][0]) == 'PATH_TO_SERVERINFO_FROM_NEIGHBOR') ) {
				if ($sn_config['DEBUG'][0] == 'TRUE') {
					$aseco->console("[plugin.server_neighborhood.php] Skipping entry '". $server_neighbor['PATH'][0] ."'");
				}
				$neighbor['STATUS'] = false;
			}
			else {
				if (strtoupper($server_neighbor['ENABLE'][0]) == 'FALSE') {
					// Deactivate server and skip
					$neighbor['STATUS'] = false;
				}
				else {
					// Read XML-File
					if ($neighbor = $aseco->xml_parser->parseXML($server_neighbor['PATH'][0], true, 'UTF-8')) {
						$neighbor = $neighbor['SERVER_NEIGHBORHOOD'];

						// Set status default to 'true'
						$neighbor['STATUS'] = true;

						// Check the Servername, Challenge and Ladder. On failure hide this Server.
						if ( ($neighbor['SERVER'][0]['NAME'][0] == '') || ($neighbor['CURRENT'][0]['MAP'][0] == '') || ($neighbor['SERVER'][0]['LADDER'][0]['MAXIMUM'][0] == '') ) {
							$neighbor['STATUS'] = false;
						}

						// If last modified time is higher then this given seconds, hide this Server.
						if ( (time() - (int)$neighbor['SERVER'][0]['LAST_MODIFIED'][0]) > $sn_config['HIDE_SERVER_LAST_MODIFIED'][0]) {
							if ($sn_config['DEBUG'][0] == 'TRUE') {
								$aseco->console("[plugin.server_neighborhood.php] Hide '". $neighbor['SERVER'][0]['LOGIN'][0] ."' on timestamp difference larger as ". $sn_config['HIDE_SERVER_LAST_MODIFIED'][0] ."!");
							}
							$neighbor['STATUS'] = false;
						}

						// Check for the same "Game" e.g. TMF/TM2
						if ($aseco->server->getGame() != $neighbor['SERVER'][0]['GAME'][0]) {
							if ($sn_config['DEBUG'][0] == 'TRUE') {
								$aseco->console("[plugin.server_neighborhood.php] Hide '". $neighbor['SERVER'][0]['LOGIN'][0] ."' on different Game '". $neighbor['SERVER'][0]['GAME'][0] ."'!");
							}
							$neighbor['STATUS'] = false;
						}

					}
					else {
						if ($sn_config['DEBUG'][0] == 'WARN') {
							$aseco->console("[plugin.server_neighborhood.php] Error loading '". $server_neighbor['PATH'][0] ."'!");
						}

						// Deactivate server
						$neighbor['STATUS'] = false;
					}
				}
			}

			// Save the server infos
			array_push($servers, $neighbor);
		}
		$sn_servers = $servers;
		unset($server_neighbor, $servers);
	}
	$sn_config['STATES']['LAST_UPDATE'] = time();
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function sn_buildWidgetRefreshCounter () {
	global $aseco, $sn_config;


	$gamemode = $aseco->server->gameinfo->mode;

	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'08">';
	$xml .= '<frame posn="'. $sn_config['WIDGET'][0]['GAMEMODE'][0][$gamemode][0]['POS_X'][0] .' '. $sn_config['WIDGET'][0]['GAMEMODE'][0][$gamemode][0]['POS_Y'][0] .' 0">';
	if ($sn_config['WIDGET'][0]['TIMER_BAR'][0]['ENABLED'][0] == true) {
		$xml .= '<quad posn="3.3 -2.95 0.01" sizen="'. (($sn_config['REFRESH_INTERVAL'][0] - $sn_config['REFRESH_COUNT'] + 1.2) / 2) .' 1" bgcolor="'. $sn_config['WIDGET'][0]['TIMER_BAR'][0]['BACKGROUND_COLOR'][0] .'" />';
		$xml .= '<label posn="3.3 -2.98 0.02" sizen="10 1" textsize="1" scale="0.75" textcolor="'. $sn_config['WIDGET'][0]['TIMER_BAR'][0]['TEXT_COLOR'][0] .'" text="'. ($sn_config['REFRESH_INTERVAL'][0] - $sn_config['REFRESH_COUNT'] + 1) .'"/>';
	}
	else {
		$xml .= '<label posn="3.3 -2.98 0.02" sizen="10 1" textsize="1" scale="0.75" textcolor="FFFF" text="'. ($sn_config['REFRESH_INTERVAL'][0] - $sn_config['REFRESH_COUNT'] + 1) .'"/>';
	}
	$xml .= '</frame>';
	$xml .= '</manialink>';
	$xml .= '</manialinks>';


	// Send Counter to all Players, but not to Players how has pressed F7 to hide the Widget
	$login_list = '';
	foreach ($aseco->server->players->player_list as &$player) {
		if ($player->data['ServerNeighborhoodWidget'] == true) {
			$login_list .= $player->login. ',';
		}
	}
	unset($player);
	$login_list = substr($login_list, 0, strlen($login_list)-1);	// remove trailing ,

	if ($login_list != '') {
		$aseco->client->query('SendDisplayManialinkPageToLogin', $login_list, $xml, 0, false);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function sn_buildWidget ($player = null) {
	global $aseco, $sn_config, $sn_servers;


	$gamemode = $aseco->server->gameinfo->mode;

	// If widget is disabled in current gamemode, do not display the Widget
	if ($sn_config['WIDGET'][0]['GAMEMODE'][0][$gamemode][0]['ENABLED'][0] == false) {
		return;
	}

	// Set Icon and Title position
	if ($sn_config['WIDGET'][0]['GAMEMODE'][0][$gamemode][0]['POS_X'][0] < 0) {
		$sn_config['WIDGET'][0]['ICON_POSITION'][0] = 'RIGHT';
	}
	else {
		$sn_config['WIDGET'][0]['ICON_POSITION'][0] = 'LEFT';
	}


	// Change per Player behavior or set the default, only available if nicemode is false
	if (isset($player) && ($sn_config['STATES']['NICEMODE'] == false) && ($sn_config['SET_ACTIVE'] == true)) {
		if ($player->data['ServerNeighborhoodWidget'] == false) {
			$status = false;
		}
		else{
			$status = true;
		}
	}
	else{
		$status = $sn_config['SET_ACTIVE'];
	}


	if ($status == false) {
		$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<manialinks>';
		$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'01"></manialink>';	// Widget
		$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'03"></manialink>';	// Overview
		$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'05"></manialink>';	// Trackinfo
		$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'08"></manialink>';	// Refresh Counter
		$xml .= '</manialinks>';
	}
	else {

		//Refresh the sn_servers, if it is time to do that
		sn_getServerData();
		if ( count($sn_servers) > 0) {

			// Count only active servers for the right height of the widget
			$server_count = 0;
			foreach ($sn_servers as &$neighbor) {
				if ($neighbor['STATUS'] == true) {
					$server_count++;
				}
			}
			unset($neighbor);

			if ($server_count == 0) {
				$sn_config['DISPLAY_COUNTER'] = false;

				// Hide the Widget (no servers)
				$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
				$xml .= '<manialinks>';
				$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'01">';
				$xml .= '</manialink>';
				$xml .= '</manialinks>';
			}
			else {
				$sn_config['DISPLAY_COUNTER'] = true;

				// Build the Widget
				$line_height = 3.6;
				$headerline_height = 2.1;

				if ($sn_config['SERVER_DISPLAY_MAX'][0] > 0) {
					$widget_height = (($server_count > $sn_config['SERVER_DISPLAY_MAX'][0]) ? $sn_config['SERVER_DISPLAY_MAX'][0] : $server_count);
					$link_button_height = 2;
				}
				else {
					$widget_height = $server_count;
					$link_button_height = 0;
				}

				$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
				$xml .= '<manialinks>';
				$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'01">';
				$xml .= '<frame posn="'. $sn_config['WIDGET'][0]['GAMEMODE'][0][$gamemode][0]['POS_X'][0] .' '. $sn_config['WIDGET'][0]['GAMEMODE'][0][$gamemode][0]['POS_Y'][0] .' 0">';
				$xml .= '<quad posn="0 0 0" sizen="15.5 '. ($widget_height * $line_height + $headerline_height + 0.75 + $link_button_height) .'" style="Bgs1InRace" substyle="NavButton"/>';
				$xml .= '<quad posn="0.4 -0.36 1" sizen="14.8 2" style="'. $sn_config['WIDGET'][0]['TITLE_STYLE'][0] .'" substyle="'. $sn_config['WIDGET'][0]['TITLE_SUBSTYLE'][0] .'"/>';

				if ($sn_config['WIDGET'][0]['ICON_POSITION'][0] == 'LEFT') {
					$xml .= '<quad sizen="2.5 2.5" posn="0.6 -0.15 2" style="Icons128x128_1" substyle="Browse"/>';
					$xml .= '<label textsize="1" sizen="10.2 0" posn="3.2 -0.5 2" text="'. $sn_config['WIDGET'][0]['HEADER_NAME'][0] .'"/>';
				}
				else {
					$xml .= '<quad sizen="2.5 2.5" posn="12 -0.15 2" style="Icons128x128_1" substyle="Browse"/>';
					$xml .= '<label textsize="1" sizen="10.2 0" posn="1.4 -0.5 2" text="'. $sn_config['WIDGET'][0]['HEADER_NAME'][0] .'"/>';
				}


				// Is the display of Servers limited?
				if ($sn_config['SERVER_DISPLAY_MAX'][0] > 0) {

					if ($server_count == 1) {
						$xml .= '<quad posn="0.4 -2.56 0.002" sizen="14.76 1.9" style="'. $sn_config['WIDGET'][0]['SELF_STYLE'][0] .'" substyle="'. $sn_config['WIDGET'][0]['SELF_SUBSTYLE'][0] .'"/>';
						$xml .= '<quad posn="1.2 -2.46 0.003" sizen="1.9 1.9" style="BgRaceScore2" substyle="SendScore"/>';
					}
					else {
						$xml .= '<quad posn="0.4 -2.56 0.002" sizen="14.76 1.9" action="'. $sn_config['MANIALINK_ID'] .'09" style="'. $sn_config['WIDGET'][0]['SELF_STYLE'][0] .'" substyle="'. $sn_config['WIDGET'][0]['SELF_SUBSTYLE'][0] .'"/>';
						$xml .= '<quad posn="1.2 -2.46 0.003" sizen="1.9 1.9" style="BgRaceScore2" substyle="SendScore"/>';
						$xml .= '<label posn="8.8 -2.95 0" sizen="4.9 1" textsize="1" scale="0.75" textcolor="FFFF" text="Show all"/>';
						$xml .= '<quad posn="12.5 -2.76 0.003" sizen="1.5 1.5" style="Icons64x64_1" substyle="ArrowNext"/>';
					}

					if ( ($sn_config['LAST_START'][0] + 1) <= $server_count) {
						$start = $sn_config['LAST_START'][0] + 1;
					}
					else {
						// Start with the first Server again
						$start = 0;
					}
					$sn_config['LAST_START'][0] = $start;


					// Generate the Server Neighborhoods rotation
					$neighbors = array();
					for ($i = $start; $i <= $server_count; $i++) {
						if (( isset($sn_servers[$i]) ) && ($sn_servers[$i]['STATUS'] == false) ) {
							continue;
						}
						$neighbors[] = $sn_servers[$i];
						if (count($neighbors) >= $sn_config['SERVER_DISPLAY_MAX'][0]) {
							break;
						}
					}

					// Did we reach the wished count of Neighbors? If not start from the first one and fill
					if (count($neighbors) < $sn_config['SERVER_DISPLAY_MAX'][0]) {
						foreach ($sn_servers as &$neighbor) {
							if ($neighbor['STATUS'] == false) {
								continue;
							}

							// Do not add one Server more then once!
							$found = false;
							foreach ($neighbors as &$item) {
								if ($item['SERVER'][0]['LOGIN'][0] == $neighbor['SERVER'][0]['LOGIN'][0]) {
									$found = true;
									break;
								}
							}
							unset($item);
							if ($found == false) {
								$neighbors[] = $neighbor;
							}

							if (count($neighbors) >= $sn_config['SERVER_DISPLAY_MAX'][0]) {
								break;
							}
						}
						unset($neighbor);
					}

					$entry_count = 0;
					foreach ($neighbors as &$neighbor) {

						$neighbor_count = 0;
						foreach ($sn_servers as &$item) {
							if ($item['SERVER'][0]['LOGIN'][0] == $neighbor['SERVER'][0]['LOGIN'][0]) {
								break;
							}
							$neighbor_count++;
						}

						// Build an Server entry
						$xml .= '<frame posn="0 -'. ($line_height * $entry_count + $headerline_height + $link_button_height) .' 0.03">';
						$xml .= sn_buildServerEntry($neighbor, $neighbor_count);
						$xml .= '</frame>';

						$entry_count++;

						// Support for max. 56 servers
						if ($entry_count >= 57) {
							break;
						}
					}
					unset($neighbor);
				}
				else {
					// Build all the Server Neighborhoods
					$entry_count = 0;
					$neighbor_count = 0;
					foreach ($sn_servers as &$neighbor) {
						if ($neighbor['STATUS'] == false) {
							$neighbor_count++;
							continue;
						}

						// Build an Server entry
						$xml .= '<frame posn="0 -'. ($line_height * $entry_count + $headerline_height + $link_button_height) .' 0.03">';
						$xml .= sn_buildServerEntry($neighbor, $neighbor_count);
						$xml .= '</frame>';

						$entry_count++;
						$neighbor_count++;

						// Support for max. 56 servers
						if ($neighbor_count >= 57) {
							break;
						}
					}
					unset($neighbor);
				}
				$xml .= '</frame>';

				$xml .= '</manialink>';
				$xml .= '</manialinks>';
			}
		}
		else {
			trigger_error('[plugin.server_neighborhood.php] No other server accounts given. Do not show the Widget!', E_USER_WARNING);
		}
	}

	if ($xml) {
		if ( isset($player) ) {
			// Send XML to given Player
			$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);
		}
		else if ($sn_config['STATES']['NICEMODE'] == true) {
			// Nicemode enabled, send to all players
			$aseco->client->query('SendDisplayManialinkPage', $xml, 0, false);
		}
		else {
			$showing_count = 0;
			$player_list = $aseco->server->players->player_list;
			foreach ($player_list as &$player) {
				if ($player->data['ServerNeighborhoodWidget'] == true) {
					$showing_count ++;
				}
			}
			unset($player);

			if (count($player_list) == $showing_count) {
				// Send XML to all Players, more efficient
				$aseco->client->query('SendDisplayManialinkPage', $xml, 0, false);
			}
			else {
				// Send XML to all Players, but not to Players how has pressed F7 to hide the Widget
				$login_list = '';
				foreach ($aseco->server->players->player_list as &$player) {
					if ($player->data['ServerNeighborhoodWidget'] == true) {
						$login_list .= $player->login. ',';
					}
				}
				unset($player);
				$login_list = substr($login_list, 0, strlen($login_list)-1);	// remove trailing ,

				if ($login_list != '') {
					$aseco->client->query('SendDisplayManialinkPageToLogin', $login_list, $xml, 0, false);
				}
			}
			unset($player_list);
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function sn_buildServerEntry ($neighbor, $neighbor_count) {
	global $sn_config;


	$line_height = 3.6;
	$xml = '';

	// Serverframe
	if ($sn_config['SERVER_LOGIN'] == (string)$neighbor['SERVER'][0]['LOGIN'][0]) {
		if ( function_exists('chat_players') ) {
			$xml .= '<quad sizen="14.76 '. $line_height .'" action="'. sprintf("%d%d%02d", $sn_config['MANIALINK_ID'], 0, 2) .'" posn="0.4 -0.36 0.002" style="'. $sn_config['WIDGET'][0]['SELF_STYLE'][0] .'" substyle="'. $sn_config['WIDGET'][0]['SELF_SUBSTYLE'][0] .'"/>';
		}
		else {
			$xml .= '<quad sizen="14.76 '. $line_height .'" posn="0.4 -0.36 0.002" style="'. $sn_config['WIDGET'][0]['SELF_STYLE'][0] .'" substyle="'. $sn_config['WIDGET'][0]['SELF_SUBSTYLE'][0] .'"/>';
		}
		if ($sn_config['WIDGET'][0]['ICON_POSITION'][0] == 'LEFT') {
			$xml .= '<quad posn="-1.1 -1.4 0.3" sizen="1.6 1.6" style="Icons64x64_1" substyle="ArrowNext"/>';
		}
		else {
			$xml .= '<quad posn="14.85 -1.4 0.3" sizen="1.6 1.6" style="Icons64x64_1" substyle="ArrowPrev"/>';
		}
	}
	else {
		$xml .= '<quad sizen="14.76 '. $line_height .'" action="'. sprintf("%d%d%02d", $sn_config['MANIALINK_ID'], 1, $neighbor_count) .'" posn="0.4 -0.36 0.002" style="'. $sn_config['WIDGET'][0]['NEIGHBOR_STYLE'][0] .'" substyle="'. $sn_config['WIDGET'][0]['NEIGHBOR_SUBSTYLE'][0] .'"/>';
	}

	// Check for a password and if true, show a padlock icon (not for the server itself)
	if ( (strtoupper($neighbor['SERVER'][0]['PRIVATE'][0]) == 'TRUE') && ($sn_config['SERVER_LOGIN'] != (string)$neighbor['SERVER'][0]['LOGIN'][0]) ) {
		if ($sn_config['WIDGET'][0]['ICON_POSITION'][0] == 'LEFT') {
			$xml .= '<quad sizen="2.8 2.6" posn="-1.5 -0.6 0.2" style="Icons128x128_1" substyle="Padlock"/>';
		}
		else {
			$xml .= '<quad sizen="2.8 2.6" posn="14.15 -0.6 0.2" style="Icons128x128_1" substyle="Padlock"/>';
		}
	}
	else if ( (strtoupper($sn_config['SERVER_ACCOUNTS'][0]['SERVER_NEIGHBOR'][$neighbor_count]['FORCE_SPECTATOR'][0]) == 'TRUE') && ($sn_config['SERVER_LOGIN'] != (string)$neighbor['SERVER'][0]['LOGIN'][0]) ) {
		if ($sn_config['WIDGET'][0]['ICON_POSITION'][0] == 'LEFT') {
			$xml .= '<quad sizen="2.6 2.4" posn="-1.5 -0.8 0.2" style="Icons64x64_1" substyle="Camera"/>';
		}
		else {
			$xml .= '<quad sizen="2.6 2.4" posn="14.15 -0.8 0.2" style="Icons64x64_1" substyle="Camera"/>';
		}
	}
	// Servername and gamemode icon
	$xml .= '<label textsize="1" scale="0.9" sizen="12 0" posn="1.25 -0.55 0.003" text="'. (string)$neighbor['SERVER'][0]['NAME'][0] .'"/>';
	$xml .= '<quad sizen="1.6 1.6" posn="12.4 -0.55 0.003" style="Icons128x32_1" substyle="'.(string)$sn_config['WIDGET'][0]['GAMEMODE'][0][(int)$neighbor['SERVER'][0]['GAMEMODE'][0]][0]['ICON'][0].'"/>';

	// Detailframe for this Server
	$xml .= '<frame posn="1.2 -2.4 0.003">';

	// Players
	$xml .= '<quad sizen="1.6 1.6" posn="0 0.2 0.004" style="Icons128x128_1" substyle="Hotseat"/>';
	$xml .= '<label sizen="3.5 0" posn="1.58 0 0.004" textsize="1" scale="0.75" text="'. (((int)$neighbor['SERVER'][0]['PLAYERS'][0]['CURRENT'][0] >= (int)$neighbor['SERVER'][0]['PLAYERS'][0]['MAXIMUM'][0]) ? $sn_config['WIDGET'][0]['SERVER_FULL_COLOR'][0] : $sn_config['WIDGET'][0]['SERVER_NORMAL_COLOR'][0]). (int)$neighbor['SERVER'][0]['PLAYERS'][0]['CURRENT'][0] .'/'. (int)$neighbor['SERVER'][0]['PLAYERS'][0]['MAXIMUM'][0] .'"/>';

	// Spectators
	$xml .= '<quad sizen="1.6 1.6" posn="4.45 0.2 0.004" style="Icons128x128_1" substyle="ChallengeAuthor"/>';
	$xml .= '<label sizen="3.5 0" posn="5.9 0 0.004" textsize="1" scale="0.75" text="'. (((int)$neighbor['SERVER'][0]['SPECTATORS'][0]['CURRENT'][0] >= (int)$neighbor['SERVER'][0]['SPECTATORS'][0]['MAXIMUM'][0]) ? $sn_config['WIDGET'][0]['SERVER_FULL_COLOR'][0] : $sn_config['WIDGET'][0]['SERVER_NORMAL_COLOR'][0]). (int)$neighbor['SERVER'][0]['SPECTATORS'][0]['CURRENT'][0] .'/'. (int)$neighbor['SERVER'][0]['SPECTATORS'][0]['MAXIMUM'][0] .'"/>';

	// Ladder
	$xml .= '<quad sizen="1.6 1.5" posn="9 0.3 0.004" style="Icons128x128_1" substyle="Rankings"/>';
	$xml .= '<label sizen="3.5 0" posn="10.4 0 0.004" textsize="1" scale="0.75" text="'. $sn_config['WIDGET'][0]['SERVER_NORMAL_COLOR'][0] . (float)$neighbor['SERVER'][0]['LADDER'][0]['MINIMUM'][0] .'-'. (float)$neighbor['SERVER'][0]['LADDER'][0]['MAXIMUM'][0] .'k"/>';

	$xml .= '</frame>'; // END Detailframe for this server

	return $xml;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function sn_onPlayerConnect ($aseco, $player) {


	// Set Widget to displayed default
	$player->data['ServerNeighborhoodWidget'] = true;

	// Check if it is time to switch from "normal" to "nice"-mode or back
	sn_checkServerLoad();

	// Display Widget for the new player
	sn_buildWidget($player);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function sn_onPlayerDisconnect ($aseco, $player) {


	// Check if it is time to switch from "normal" to "nice"-mode or back
	sn_checkServerLoad();
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// $answer = [0]=PlayerUid, [1]=Login, [2]=Answer
function sn_onPlayerManialinkPageAnswer ($aseco, $answer) {
	global $sn_config;


	// If id = 0, bail out immediately
	if ($answer[2] == 0) {
		return;
	}

	if (($answer[2] == 382009003) && ($sn_config['STATES']['NICEMODE'] == false)) {

		// Get Player
		$player = $aseco->server->players->getPlayer($answer[1]);

		// Player has pressed key F7 (same ManialinkId as plugin.fufi.widgets.php)
		if ($player->data['ServerNeighborhoodWidget'] == true) {
			// Hide the widget
			$player->data['ServerNeighborhoodWidget'] = false;
			sn_buildWidget($player);
		}
		else {
			// Show the widget
			$player->data['ServerNeighborhoodWidget'] = true;
			sn_buildWidget($player);
		}

	}
	else if ( ($answer[2] >= sprintf("%d%d%02d", $sn_config['MANIALINK_ID'], 1, 0) ) && ($answer[2] <= sprintf("%d%d%02d", $sn_config['MANIALINK_ID'], 1, 99)) ) {

		// Get the calling Player
		$player = $aseco->server->players->getPlayer($answer[1]);

		// Player has clicked a server, show the Server-Overview-Widget and save the ServerId
		$player->data['ServerNeighborhoodServerId'] = intval( str_replace($sn_config['MANIALINK_ID'].'1', '', $answer[2]) );

		// Display the Overview-Widget
		sn_buildServerOverviewWindow($player, 0);

	}
	else if ($answer[2] == $sn_config['MANIALINK_ID'] .'04') {

		// Get Player
		$player = $aseco->server->players->getPlayer($answer[1]);

		// Close Overview
		$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<manialinks>';
		$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'03"></manialink>';	// Overview
		$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'05"></manialink>';	// Trackinfo
		$xml .= '</manialinks>';

		$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);

	}
	else if ($answer[2] == $sn_config['MANIALINK_ID'] .'09') {

		// Display the ServerOverviewWindow
		sn_buildNeighborOverviewWindow($answer[1]);

	}
	else if ( ($answer[2] >= sprintf("%d%d%02d", $sn_config['MANIALINK_ID'], 2, 0) ) && ($answer[2] <= sprintf("%d%d%02d", $sn_config['MANIALINK_ID'], 2, 99)) ) {

		// Get Player
		$player = $aseco->server->players->getPlayer($answer[1]);

		// Display Widget Trackinfo
		sn_buildTrackinfoWidget($player, $answer[2], true);

	}
	elseif ( ($answer[2] >= (int)$sn_config['MANIALINK_ID'] .'20') && ($answer[2] <= (int)$sn_config['MANIALINK_ID'] .'35') ) {		// Previous pages

		// Get the calling Player
		$player = $aseco->server->players->getPlayer($answer[1]);

		// Get the wished Page
		$page = intval( str_replace($sn_config['MANIALINK_ID'], '', $answer[2]) - 20 );

		// Display the Overview-Widget
		sn_buildServerOverviewWindow($player, $page);

	}
	elseif ( ($answer[2] >= (int)$sn_config['MANIALINK_ID'] .'40') && ($answer[2] <= (int)$sn_config['MANIALINK_ID'] .'55') ) {		// Next pages

		// Get the calling Player
		$player = $aseco->server->players->getPlayer($answer[1]);

		// Get the wished Page
		$page = intval( str_replace($sn_config['MANIALINK_ID'], '', $answer[2]) - 40 );

		// Display the Overview-Widget
		sn_buildServerOverviewWindow($player, $page);

	}
	else if ($answer[2] == $sn_config['MANIALINK_ID'] .'07') {

		// Get Player
		$player = $aseco->server->players->getPlayer($answer[1]);

		// Hide Widget Trackinfo
		sn_buildTrackinfoWidget($player, false, false);

	}
	else if ($answer[2] == sprintf("%d%d%02d", $sn_config['MANIALINK_ID'], 0, 2) ) {

		// Get Player
		$player = $aseco->server->players->getPlayer($answer[1]);

		// Close Overview
		$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<manialinks>';
		$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'03"></manialink>';	// Overview
		$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'05"></manialink>';	// Trackinfo
		$xml .= '</manialinks>';

		$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);

		if (function_exists('chat_players')) {
			$command['author'] = $player;
			$command['params'] = ' ';
			chat_players($aseco, $command);
		}

	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function sn_buildNeighborOverviewWindow ($login) {
	global $aseco, $sn_config, $sn_servers;


	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'05"></manialink>';		// Trackinfo
	$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'03">';

	// Window
	$xml .= '<frame posn="-40.1 30.45 -3">';	// BEGIN: Window Frame
	$xml .= '<quad posn="0.8 -0.8 0.01" sizen="78.4 53.7" bgcolor="'. $sn_config['COLORS'][0]['NEIGHBOR_BACKGROUND'][0] .'"/>';
	$xml .= '<quad posn="-0.2 0.2 0.09" sizen="80.4 55.7" style="Bgs1InRace" substyle="BgCard3"/>';

	// Header Icon
	$xml .= '<quad posn="-1.7 2.5 0.20" sizen="6 6" style="Icons128x128_1" substyle="Multiplayer"/>';
	$xml .= '<label posn="5.5 -0.15 0.04" sizen="74 0" textsize="1" scale="0.7" textcolor="000F" text="SERVER OVERVIEW"/>';

	// Link and About
	$xml .= '<quad posn="2.7 -54.1 0.12" sizen="14.5 1" url="http://www.undef.name/" bgcolor="0000"/>';
	$xml .= '<label posn="2.7 -54.1 0.13" sizen="30 1" halign="left" textsize="1" scale="0.7" textcolor="000F" text="SERVER-NEIGHBORHOOD/'. $sn_config['VERSION'] .'"/>';

	// Close Button
	$xml .= '<frame posn="77.4 1.3 0">';
	$xml .= '<quad posn="0 0 0.10" sizen="4 4" style="Icons64x64_1" substyle="ArrowDown"/>';
	$xml .= '<quad posn="1.1 -1.35 0.11" sizen="1.8 1.75" bgcolor="EEEF"/>';
	$xml .= '<quad posn="0.65 -0.7 0.12" sizen="2.6 2.6" action="'. $sn_config['MANIALINK_ID'] .'04" style="Icons64x64_1" substyle="Close"/>';
	$xml .= '</frame>';

	$total['server'] = 0;
	$total['player'] = 0;
	$total['spectator'] = 0;

	$offset = 0;
	$line = 0;
	$display_count = 0;
	$neighbor_count = 0;
	$xml .= '<frame posn="2.2 -2 0">';
	foreach ($sn_servers as &$neighbor) {
		if ($neighbor['STATUS'] == false) {
			$neighbor_count++;
			continue;
		}

		// Hide himself
		if ($neighbor['SERVER'][0]['LOGIN'][0] == $aseco->server->serverlogin) {
			$neighbor_count++;
			continue;
		}

		// Count the Totals
		$total['server'] += 1;
		$total['player'] += $neighbor['SERVER'][0]['PLAYERS'][0]['CURRENT'][0];
		$total['spectator'] += $neighbor['SERVER'][0]['SPECTATORS'][0]['CURRENT'][0];

		// Build an Server entry
		$xml .= '<frame posn="'. $offset .' -'. (3.6 * $line) .' 0.03">';
		$xml .= sn_buildServerEntry($neighbor, $neighbor_count);
		$xml .= '</frame>';

		$neighbor_count++;
		$display_count++;
		$line ++;

		// Reset lines
		if ($line >= 14) {
			$offset += 19.5;
			$line = 0;
		}

		// Support for max. 56 servers
		if ($display_count >= 56) {
			break;
		}
	}
	unset($neighbor);
	$xml .= '</frame>';

	// Totals of Server/Player/Spectator
	$xml .= '<label posn="77.2 -54.1 0.13" sizen="50 2" halign="right" textsize="1" scale="0.7" textcolor="000F" text="'. $total['server'] .' SERVER'. (($total['server'] == 1) ? '': 'S') .' WITH '. $total['player'] .' PLAYER'. (($total['player'] == 1) ? '': 'S') .' AND '. $total['spectator'] .' SPECTATOR'. (($total['spectator'] == 1) ? '': 'S') .' TOTAL"/>';

	$xml .= '</frame>';	// END: Window Frame
	$xml .= '</manialink>';
	$xml .= '</manialinks>';

	// Send Overview to given Player
	$aseco->client->query('SendDisplayManialinkPageToLogin', $login, $xml, 0, false);

}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function sn_buildServerOverviewWindow ($player, $page = 0) {
	global $aseco, $sn_config, $sn_servers;


	// Is neighbor set?
	if (!isset($sn_servers[$player->data['ServerNeighborhoodServerId']])) {
		if ($sn_config['DEBUG'][0] == 'WARN') {
			$aseco->console("[plugin.server_neighborhood.php] Error can not find NeighborhoodId '". $player->data['ServerNeighborhoodServerId'] ."'");
		}
		return;
	}

	// No action if inactive (possible at score)
	if ($sn_config['SET_ACTIVE'] == false) {
		return;
	}


	// Read Server
	$neighbor = $sn_servers[$player->data['ServerNeighborhoodServerId']];

	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'05"></manialink>';		// Close possible open Trackinfo
	$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'03">';

	// Window
	$xml .= '<frame posn="-40.1 30.45 -3">';	// BEGIN: Window Frame
	$xml .= '<quad posn="0.8 -0.8 0.01" sizen="78.4 53.7" bgcolor="'. $sn_config['COLORS'][0]['SERVER_BACKGROUND'][0] .'"/>';
	$xml .= '<quad posn="-0.2 0.2 0.09" sizen="80.4 55.7" style="Bgs1InRace" substyle="BgCard3"/>';

	// Header Icon
	$xml .= '<quad posn="-1.7 2.5 0.20" sizen="6 6" style="Icons128x128_1" substyle="Multiplayer"/>';

	// Link and About
	$xml .= '<quad posn="2.7 -54.1 0.12" sizen="14.5 1" url="http://www.undef.name/" bgcolor="0000"/>';
	$xml .= '<label posn="2.7 -54.1 0.13" sizen="30 1" halign="left" textsize="1" scale="0.7" textcolor="000F" text="SERVER-NEIGHBORHOOD/'. $sn_config['VERSION'] .'"/>';

	// Close Button
	$xml .= '<frame posn="77.4 1.3 0">';
	$xml .= '<quad posn="0 0 0.10" sizen="4 4" style="Icons64x64_1" substyle="ArrowDown"/>';
	$xml .= '<quad posn="1.1 -1.35 0.11" sizen="1.8 1.75" bgcolor="EEEF"/>';
	$xml .= '<quad posn="0.65 -0.7 0.12" sizen="2.6 2.6" action="'. $sn_config['MANIALINK_ID'] .'04" style="Icons64x64_1" substyle="Close"/>';
	$xml .= '</frame>';



	// Frame for Legend/Previous/Next Buttons
	$xml .= '<frame posn="67.05 -53.2 0">';

	// Trackinfo button
	$xml .= '<frame posn="-1.65 0 0">';
	if ( isset($neighbor['CURRENT'][0]['MAP'][0]['NAME'][0]) ){
		$xml .= '<quad posn="0 0 0.12" sizen="3.2 3.2" action="'. sprintf("%d%d%02d", $sn_config['MANIALINK_ID'], 2, $player->data['ServerNeighborhoodServerId']) .'" style="Icons64x64_1" substyle="ArrowDown"/>';
		$xml .= '<quad posn="0.85 -1.1 0.13" sizen="1.5 1.4" bgcolor="EEEF"/>';
		$xml .= '<quad posn="0.65 -0.7 0.14" sizen="1.8 1.8" style="Icons64x64_1" substyle="TrackInfo"/>';
	}
	else {
		$xml .= '<quad posn="0 0 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
		$xml .= '<quad posn="0 0 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
	}
	$xml .= '</frame>';


	// Reload button
	$xml .= '<quad posn="1.65 0 0.12" sizen="3.2 3.2" action="'. $sn_config['MANIALINK_ID'] . ($page + 40) .'" style="Icons64x64_1" substyle="Refresh"/>';


	// Display Previous/Next-Buttons if Players online
	if ( isset($neighbor['CURRENT'][0]['PLAYERS'][0]['PLAYER']) ) {

		// Previous button
		if ($page > 0) {
			$xml .= '<quad posn="4.95 0 0.12" sizen="3.2 3.2" action="'. $sn_config['MANIALINK_ID'] . ($page + 19) .'" style="Icons64x64_1" substyle="ArrowPrev"/>';
		}
		else {
			$xml .= '<quad posn="4.95 0 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
			$xml .= '<quad posn="4.95 0 0.13" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
		}


		// Next button (display only if more pages to display)
		if ( ($page < 15) && (count($neighbor['CURRENT'][0]['PLAYERS'][0]['PLAYER']) > 20) && (($page+1) < (ceil(count($neighbor['CURRENT'][0]['PLAYERS'][0]['PLAYER'])/20))) ) {
			$xml .= '<quad posn="8.25 0 0.12" sizen="3.2 3.2" action="'. $sn_config['MANIALINK_ID'] . ($page + 41) .'" style="Icons64x64_1" substyle="ArrowNext"/>';
		}
		else {
			$xml .= '<quad posn="8.25 0 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
			$xml .= '<quad posn="8.25 0 0.13" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
		}
	}
	else {
		// Previous button (empty)
		$xml .= '<quad posn="4.95 0 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
		$xml .= '<quad posn="4.95 0 0.13" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';

		// Next button (empty)
		$xml .= '<quad posn="8.25 0 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
		$xml .= '<quad posn="8.25 0 0.13" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
	}
	$xml .= '</frame>';




	// BEGIN: Header Frame
	$xml .= '<frame posn="0 0 0">';

	// Flag and Nickname of Player
	$xml .= '<quad posn="10.6 1.2 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="Buddy"/>';
	$xml .= '<label posn="13.6 -0.15 0.12" sizen="5 3" halign="left" textsize="1" scale="0.7" text="$000PLAYERS"/>';

	// Login
	$xml .= '<quad posn="48.7 0.7 0.12" sizen="2.5 2.5" style="Icons128x128_1" substyle="Solo"/>';
	$xml .= '<label posn="51.2 -0.15 0.12" sizen="5 3" halign="left" textsize="1" scale="0.7" text="$000LOGIN"/>';

	// Ladderscore of Player
	$xml .= '<quad posn="61.2 0.7 0.12" sizen="2.4 2.4" style="BgRaceScore2" substyle="LadderRank"/>';
	$xml .= '<label posn="63.8 -0.15 0.12" sizen="7 3" halign="left" textsize="1" scale="0.7" text="$000RANK"/>';

	// Player or Spectator?
	$xml .= '<quad posn="70.1 0.8 0.12" sizen="2.6 2.6" style="Icons64x64_1" substyle="Opponents"/>';
	$xml .= '<label posn="72.9 -0.15 0.12" sizen="7 3" halign="left" textsize="1" scale="0.7" text="$000STATUS"/>';

	// END: Header Frame
	$xml .= '</frame>';



	// BEGIN: Player lines
	$xml .= '<frame posn="2 -2 0">';

	// Default Layout
	$xml .= '<quad posn="3.9 0 0.20" sizen="0.1 51.26" bgcolor="0003"/>';		// Flag and Nickname of Player
	$xml .= '<quad posn="45.9 0 0.20" sizen="0.1 51.26" bgcolor="0003"/>';		// Login of Player
	$xml .= '<quad posn="58.3 0 0.20" sizen="0.1 51.26" bgcolor="0003"/>';		// Ladderrank of Player
	$xml .= '<quad posn="67 0 0.20" sizen="0.1 51.26" bgcolor="0003"/>';		// Player Status of Player

	// Set line height for Player lines
	$line_height = 2.5;
	$position = 0;

	$player_count = 1;
	$line_count = 0;
	if ( isset($neighbor['CURRENT'][0]['PLAYERS'][0]['PLAYER']) ) {
		foreach ($neighbor['CURRENT'][0]['PLAYERS'][0]['PLAYER'] as &$ply) {

			// Start at wished position
			if ($player_count <= ($page * 20)) {
				$player_count ++;
				continue;
			}

			// Add one Player line
			$xml .= '<quad posn="0 '. $position .' 0.12" sizen="76 '. $line_height .'" image="'. $sn_config['URLS'][0]['BAR_DEFAULT'][0] .'"/>';

			// Player count
			$xml .= '<label posn="2 '. ($position - 0.5) .' 0.15" sizen="3 2.4" halign="center" textsize="1" scale="0.9" textcolor="FFFF" text="'. $player_count .'"/>';

			// Flag of Player
			$xml .= '<quad posn="5.5 '. $position .' 0.15" sizen="2.5 2.5" image="tmtp://Skins/Avatars/Flags/'. (isset($ply['NATION'][0]) ? $ply['NATION'][0] : 'other') .'.dds"/>';

			// Nickname of Player
			$xml .= '<label posn="9.2 '. ($position - 0.3) .' 0.14" sizen="35 2.4" halign="left" textsize="1" scale="1" textcolor="FFFF" text="$S'. validateUTF8String($ply['NICKNAME'][0]) .'"/>';

			// Login of Player
			$xml .= '<label posn="57.7 '. ($position - 0.6) .' 0.14" sizen="11 2.4" halign="right" textsize="1" scale="0.9" textcolor="FFFF" text="'. $ply['LOGIN'][0] .'"/>';

			// Ladderrank of Player
			$xml .= '<label posn="66.3 '. ($position - 0.6) .' 0.14" sizen="7.1 2.4" halign="right" textsize="1" scale="0.9" textcolor="FFF" text="'. number_format((int)$ply['LADDER'][0], 0, '.', ' ') .'"/>';

			// Mark Player as Specator, if Player is Spectator
			if (strtoupper((string)$ply['SPECTATOR'][0]) == 'TRUE') {
				$xml .= '<quad posn="70.2 '. ($position + 0.2) .' 0.14" sizen="2.8 2.8" style="Icons64x64_1" substyle="Camera"/>';
			}
			else {
				$xml .= '<quad posn="70 '. ($position + 0.6) .' 0.14" sizen="3.2 3.2" style="Icons128x128_1" substyle="Vehicles"/>';
			}

			// Not more then 20 entries per Page
			if ($line_count >= 19) {
				$line_count ++;		// Add one to not overfill below
				break;
			}

			$position -= ($line_height + 0.07);
			$line_count ++;
			$player_count ++;
		}
		unset($ply);
	}

	// Fill empty line to have full 20 lines
	for ($i = $line_count; $i < 20; $i++) {
		$xml .= '<quad posn="0 '. $position .' 0.12" sizen="76 '. $line_height .'" image="'. $sn_config['URLS'][0]['BAR_BLANK'][0] .'"/>';
		$position -= ($line_height + 0.07);
	}

	// END: Player lines
	$xml .= '</frame>';

	// Check which LinkMode we can use. If ServerMaxPlayer is reached,
	// try to 'spectate'. If ServerMaxSpectator is reached, do not show an link.
	$xml .= '<frame posn="4 -53.7 -3.1">';		// BEGIN: Link Button
	$xml .= '<quad posn="-0.2 0.2 0.09" sizen="48.4 6.7" style="Bgs1InRace" substyle="BgCard3"/>';
	if ( ($player->rights == true) || (($player->rights == false) && ($neighbor['SERVER'][0]['PACKMASK'][0] == 'STADIUM')) ) {
		if ( ((int)$neighbor['SERVER'][0]['PLAYERS'][0]['CURRENT'][0] < (int)$neighbor['SERVER'][0]['PLAYERS'][0]['MAXIMUM'][0]) && (strtoupper($sn_config['SERVER_ACCOUNTS'][0]['SERVER_NEIGHBOR'][$player->data['ServerNeighborhoodServerId']]['FORCE_SPECTATOR'][0]) != 'TRUE') ) {
			$xml .= '<label posn="0.8 -0.8 0.01" sizen="46.4 4.7" manialink="tmtp://#join='. $neighbor['SERVER'][0]['LOGIN'][0] .'" focusareacolor1="EEEF" focusareacolor2="FFFF" text=" "/>';
			$xml .= '<label posn="24 -2.3 0.10" sizen="42 2.4" halign="center" textsize="2" scale="1" textcolor="0B3F" text="CLICK HERE TO JOIN TO '. $neighbor['SERVER'][0]['NAME'][0] .'"/>';
		}
		else if ((int)$neighbor['SERVER'][0]['SPECTATORS'][0]['CURRENT'][0] < (int)$neighbor['SERVER'][0]['SPECTATORS'][0]['MAXIMUM'][0]) {
			$xml .= '<label posn="0.8 -0.8 0.01" sizen="46.4 4.7" manialink="tmtp://#spectate='. $neighbor['SERVER'][0]['LOGIN'][0] .'" focusareacolor1="EEEF" focusareacolor2="FFFF" text=" "/>';
			$xml .= '<label posn="24 -2.3 0.10" sizen="42 2.4" halign="center" textsize="2" scale="1" textcolor="BB3F" text="CLICK HERE TO SPECTATE ON '. $neighbor['SERVER'][0]['NAME'][0] .'"/>';
		}
		else {
			$xml .= '<label posn="0.8 -0.8 0.01" sizen="46.4 4.7" action="'. $sn_config['MANIALINK_ID'] .'00" focusareacolor1="EEEF" focusareacolor2="FFFF" text=" "/>';
			$xml .= '<label posn="24 -2.3 0.10" sizen="42 2.4" halign="center" textsize="2" scale="1" textcolor="B30F" text="SERVER FULL, NO SWITCH POSSIBLE TO '. $neighbor['SERVER'][0]['NAME'][0] .'"/>';
		}
	}
	else {
		$xml .= '<label posn="0.8 -0.8 0.01" sizen="46.4 4.7" action="'. $sn_config['MANIALINK_ID'] .'00" focusareacolor1="EEEF" focusareacolor2="FFFF" text=" "/>';
		$xml .= '<label posn="26 -2.3 0.10" sizen="44 2.4" halign="center" textsize="2" scale="1" textcolor="B30F" text="NO SWITCH POSSIBLE TO A TMUF-SERVER!"/>';
	}
	$xml .= '</frame>';				// END: Link Button


	$xml .= '<frame posn="52.4 -53.7 -3.1">';	// BEGIN: AddFavorite Button
	$xml .= '<quad posn="-0.2 0.2 0.09" sizen="13.4 6.7" style="Bgs1InRace" substyle="BgCard3"/>';
	$xml .= '<label posn="0.8 -0.8 0.01" sizen="11.4 4.7" manialink="addfavorite?action=add&amp;server='. rawurlencode($neighbor['SERVER'][0]['LOGIN'][0]) .'&amp;name='. rawurlencode($neighbor['SERVER'][0]['NAME'][0]) .'&amp;zone='. rawurlencode($neighbor['SERVER'][0]['ZONE'][0]) .'" addplayerid="1" focusareacolor1="EEEF" focusareacolor2="FFFF" text=" "/>';
	$xml .= '<quad posn="2 -1.5 0.10" sizen="3.2 3.2" style="Icons128x128_Blink" substyle="ServersFavorites"/>';
	$xml .= '<label posn="8.5 -2.05 0.10" sizen="6 2.4" halign="center" textsize="1" scale="1" textcolor="0B3F" text="$ZAdd"/>';
	$xml .= '<label posn="8.5 -3.15 0.10" sizen="6 2.4" halign="center" textsize="1" scale="1" textcolor="0B3F" text="$ZFavorite"/>';
	$xml .= '</frame>';				// END: AddFavorite Button


	$xml .= '</frame>';	// END: Window Frame
	$xml .= '</manialink>';
	$xml .= '</manialinks>';

	// Send Overview to given Player
	$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function sn_buildTrackinfoWidget ($player, $answer, $display = true) {
	global $aseco, $sn_config, $sn_servers;


	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $sn_config['MANIALINK_ID'] .'05">';

	if ($display == true){

		$neighborid = intval( str_replace($sn_config['MANIALINK_ID'].'2', '', $answer) );

		// Is neighbor set?
		if (!isset($sn_servers[$neighborid])) {
			if ($sn_config['DEBUG'][0] == 'WARN') {
				$aseco->console("[plugin.server_neighborhood.php] Error can not find NeighborhoodId '". $neighborid ."' on ManialinkId '". $answer[2] ."'");
			}
		}
		else {
			// Read Server
			$neighbor = $sn_servers[$neighborid];
			if ( isset($neighbor['CURRENT'][0]['MAP'][0]['NAME'][0]) ) {
				$xml .= '<frame posn="6 1.2 -2">';	// BEGIN: Window Frame
				$xml .= '<quad posn="0.8 -0.8 0.10" sizen="28.2 20.9" bgcolor="EEEA"/>';
				$xml .= '<quad posn="-0.2 0.2 0.13" sizen="30.2 22.9" style="Bgs1InRace" substyle="BgCard3"/>';
				$xml .= '<quad posn="0.8 -1.3 0.11" sizen="28.2 2.3" bgcolor="09FC"/>';
				$xml .= '<quad posn="0.8 -3.6 0.12" sizen="28.2 0.1" bgcolor="FFF9"/>';
				$xml .= '<label posn="2.5 -1.8 0.12" sizen="23.8 0" halign="left" textsize="1" scale="0.9" textcolor="FFFF" text="Current running Map"/>';

				// Close Button
				$xml .= '<frame posn="27.2 1.3 0">';
				$xml .= '<quad posn="0 0 0.14" sizen="4 4" style="Icons64x64_1" substyle="ArrowDown"/>';
				$xml .= '<quad posn="1.1 -1.35 0.15" sizen="1.8 1.75" bgcolor="EEEF"/>';
				$xml .= '<quad posn="0.65 -0.7 0.16" sizen="2.6 2.6" action="'. $sn_config['MANIALINK_ID'] .'07" style="Icons64x64_1" substyle="Close"/>';
				$xml .= '</frame>';


				// BEGIN: Mapinfo "Name"
				$xml .= '<frame posn="2 -13.5 0">';
				$xml .= '<format textsize="1" textcolor="000F"/>';
				if ( isset($neighbor['CURRENT'][0]['MAP'][0]['MXURL'][0]) ) {
					$xml .= '<quad posn="1 8.5 0.11" sizen="24 2" bgcolor="0000" url="'. $neighbor['CURRENT'][0]['MAP'][0]['MXURL'][0] .'"/>';
				}
				$xml .= '<label posn="1 8.5 0.11" sizen="24 2" textsize="2" text="'. $neighbor['CURRENT'][0]['MAP'][0]['NAME'][0] .'"/>';
				$xml .= '<label posn="1 6.2 0.11" sizen="24 2" textsize="1" text="by '. $neighbor['CURRENT'][0]['MAP'][0]['AUTHOR'][0] .'"/>';
				$xml .= '</frame>';
				// END: Mapinfo "Name"


				// BEGIN: Mapinfo "Times"
				$xml .= '<frame posn="2 -14 0">';
				$xml .= '<format textsize="1" textcolor="000F"/>';
				$xml .= '<quad posn="2.75 3.1 0.11" sizen="2 2" halign="right" style="MedalsBig" substyle="MedalNadeo"/>';
				$xml .= '<label posn="3.3 2.9 0.11" sizen="6 2" textsize="1" scale="1" text="'. $neighbor['CURRENT'][0]['MAP'][0]['AUTHORTIME'][0] .'"/>';

				$xml .= '<quad posn="2.75 0.9 0.11" sizen="2 2" halign="right" style="MedalsBig" substyle="MedalGold"/>';
				$xml .= '<label posn="3.3 0.7 0.11" sizen="6 2" textsize="1" scale="1" text="'. $neighbor['CURRENT'][0]['MAP'][0]['GOLDTIME'][0] .'"/>';

				$xml .= '<quad posn="2.75 -1.3 0.11" sizen="2 2" halign="right" style="MedalsBig" substyle="MedalSilver"/>';
				$xml .= '<label posn="3.3 -1.7 0.11" sizen="6 2" textsize="1" scale="1" text="'. $neighbor['CURRENT'][0]['MAP'][0]['SILVERTIME'][0] .'"/>';

				$xml .= '<quad posn="2.75 -3.5 0.11" sizen="2 2" halign="right" style="MedalsBig" substyle="MedalBronze"/>';
				$xml .= '<label posn="3.3 -3.9 0.11" sizen="6 2" textsize="1" scale="1" text="'. $neighbor['CURRENT'][0]['MAP'][0]['BRONZETIME'][0] .'"/>';
				$xml .= '</frame>';
				// END: Mapinfo "Times"


				// BEGIN: Mapinfo "Details"
				$xml .= '<frame posn="12 -14 0">';
				$xml .= '<format textsize="1" textcolor="000F"/>';
				$xml .= '<quad posn="2.75 3.2 0.11" sizen="2.4 2.4" halign="right" style="Icons128x128_1" substyle="Advanced"/>';
				$xml .= '<label posn="3.3 2.9 0.11" sizen="12 2" textsize="1" scale="1" text="'. $neighbor['CURRENT'][0]['MAP'][0]['ENVIRONMENT'][0] .'"/>';

				$xml .= '<quad posn="2.75 1 0.11" sizen="2.4 2.4" halign="right" style="Icons128x128_1" substyle="Manialink"/>';
				$xml .= '<label posn="3.3 0.7 0.11" sizen="12 2" textsize="1" scale="1" text="'. $neighbor['CURRENT'][0]['MAP'][0]['MOOD'][0] .'"/>';
				$xml .= '</frame>';
				// END: Mapinfo "Details"


				$xml .= '</frame>';	// END: Window Frame
			}
		}
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

function sn_checkServerLoad () {
	global $aseco, $sn_config;


	if (($sn_config['NICEMODE'][0]['ENABLED'][0] == true) && ($sn_config['NICEMODE'][0]['FORCE'][0] == false)) {

		// Get Playercount
		$player_count = count($aseco->server->players->player_list);

		// Check Playercount and if to high, switch to nicemode
		if ( ($sn_config['STATES']['NICEMODE'] == false) && ($player_count >= (int)$sn_config['NICEMODE'][0]['LIMITS'][0]['UPPER_LIMIT'][0]) ) {

			// Turn nicemode on
			$sn_config['STATES']['NICEMODE'] = true;

			// Set new refresh interval
			$sn_config['REFRESH_INTERVAL'][0] = $sn_config['NICEMODE'][0]['REFRESH_INTERVAL'][0];
		}
		else if ( ($sn_config['STATES']['NICEMODE'] == true) && ($player_count <= (int)$sn_config['NICEMODE'][0]['LIMITS'][0]['LOWER_LIMIT'][0]) ) {

			// Turn nicemode off
			$sn_config['STATES']['NICEMODE'] = false;

			// Restore default refresh interval
			$sn_config['REFRESH_INTERVAL'][0] = $sn_config['REFRESH_INTERVAL_DEFAULT'][0];
		}
	}
	else if (($sn_config['NICEMODE'][0]['ENABLED'][0] == true) && ($sn_config['NICEMODE'][0]['FORCE'][0] == true)){
		// Turn nicemode on
		$sn_config['STATES']['NICEMODE'] = true;

		// Set new refresh interval
		$sn_config['REFRESH_INTERVAL'][0] = $sn_config['NICEMODE'][0]['REFRESH_INTERVAL'][0];
	}
	else{
		// Turn nicemode off
		$sn_config['STATES']['NICEMODE'] = false;

		// Restore default refresh interval
		$sn_config['REFRESH_INTERVAL'][0] = $sn_config['REFRESH_INTERVAL_DEFAULT'][0];
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// Based on mapCountry() in basic.inc.php,
// but some changes to display the correct flag in game
function sn_getFlagOfNation ($country) {


	$nations = array(
		'Afghanistan'			=> 'AFG',
		'Albania'			=> 'ALB',
		'Algeria'			=> 'ALG',
		'Andorra'			=> 'AND',
		'Angola'			=> 'ANG',
		'Argentina'			=> 'ARG',
		'Armenia'			=> 'ARM',
		'Aruba'				=> 'ARU',
		'Australia'			=> 'AUS',
		'Austria'			=> 'AUT',
		'Azerbaijan'			=> 'AZE',
		'Bahamas'			=> 'BAH',
		'Bahrain'			=> 'BRN',
		'Bangladesh'			=> 'BAN',
		'Barbados'			=> 'BAR',
		'Belarus'			=> 'BLR',
		'Belgium'			=> 'BEL',
		'Belize'			=> 'BIZ',
		'Benin'				=> 'BEN',
		'Bermuda'			=> 'BER',
		'Bhutan'			=> 'BHU',
		'Bolivia'			=> 'BOL',
		'Bosnia&Herzegovina'		=> 'BIH',
		'Botswana'			=> 'BOT',
		'Brazil'			=> 'BRA',
		'Brunei'			=> 'BRU',
		'Bulgaria'			=> 'BUL',
		'Burkina Faso'			=> 'BUR',
		'Burundi'			=> 'BDI',
		'Cambodia'			=> 'CAM',
		'Cameroon'			=> 'CMR',	// was CAR, but in TMF CMR
		'Canada'			=> 'CAN',
		'Cape Verde'			=> 'CPV',
		'Central African Republic'	=> 'CAF',
		'Chad'				=> 'CHA',
		'Chile'				=> 'CHI',
		'China'				=> 'CHN',
		'Chinese Taipei'		=> 'TPE',
		'Colombia'			=> 'COL',
		'Congo'				=> 'CGO',
		'Costa Rica'			=> 'CRC',
		'Croatia'			=> 'CRO',
		'Cuba'				=> 'CUB',
		'Cyprus'			=> 'CYP',
		'Czech Republic'		=> 'CZE',
		'Czech republic'		=> 'CZE',
		'DR Congo'			=> 'COD',
		'Denmark'			=> 'DEN',
		'Djibouti'			=> 'DJI',
		'Dominica'			=> 'DMA',
		'Dominican Republic'		=> 'DOM',
		'Ecuador'			=> 'ECU',
		'Egypt'				=> 'EGY',
		'El Salvador'			=> 'ESA',
		'Eritrea'			=> 'ERI',
		'Estonia'			=> 'EST',
		'Ethiopia'			=> 'ETH',
		'Fiji'				=> 'FIJ',
		'Finland'			=> 'FIN',
		'France'			=> 'FRA',
		'Gabon'				=> 'GAB',
		'Gambia'			=> 'GAM',
		'Georgia'			=> 'GEO',
		'Germany'			=> 'GER',
		'Ghana'				=> 'GHA',
		'Greece'			=> 'GRE',
		'Grenada'			=> 'GRN',
		'Guam'				=> 'GUM',
		'Guatemala'			=> 'GUA',
		'Guinea'			=> 'GUI',
		'Guinea-Bissau'			=> 'GBS',
		'Guyana'			=> 'GUY',
		'Haiti'				=> 'HAI',
		'Honduras'			=> 'HON',
		'Hong Kong'			=> 'HKG',
		'Hungary'			=> 'HUN',
		'Iceland'			=> 'ISL',
		'India'				=> 'IND',
		'Indonesia'			=> 'INA',
		'Iran'				=> 'IRI',
		'Iraq'				=> 'IRQ',
		'Ireland'			=> 'IRL',
		'Israel'			=> 'ISR',
		'Italy'				=> 'ITA',
		'Ivory Coast'			=> 'CIV',
		'Jamaica'			=> 'JAM',
		'Japan'				=> 'JPN',
		'Jordan'			=> 'JOR',
		'Kazakhstan'			=> 'KAZ',
		'Kenya'				=> 'KEN',
		'Kiribati'			=> 'KIR',
		'Korea'				=> 'KOR',
		'Kuwait'			=> 'KUW',
		'Kyrgyzstan'			=> 'KGZ',
		'Laos'				=> 'LAO',
		'Latvia'			=> 'LAT',
		'Lebanon'			=> 'LIB',
		'Lesotho'			=> 'LES',
		'Liberia'			=> 'LBR',
		'Libya'				=> 'LBA',
		'Liechtenstein'			=> 'LIE',
		'Lithuania'			=> 'LTU',
		'Luxembourg'			=> 'LUX',
		'Macedonia'			=> 'MKD',
		'Malawi'			=> 'MAW',
		'Malaysia'			=> 'MAS',
		'Mali'				=> 'MLI',
		'Malta'				=> 'MLT',
		'Mauritania'			=> 'MTN',
		'Mauritius'			=> 'MRI',
		'Mexico'			=> 'MEX',
		'Moldova'			=> 'MDA',
		'Monaco'			=> 'MON',
		'Mongolia'			=> 'MGL',
		'Montenegro'			=> 'MNE',
		'Morocco'			=> 'MAR',
		'Mozambique'			=> 'MOZ',
		'Myanmar'			=> 'MYA',
		'Namibia'			=> 'NAM',
		'Nauru'				=> 'NRU',
		'Nepal'				=> 'NEP',
		'Netherlands'			=> 'NED',
		'New Zealand'			=> 'NZL',
		'Nicaragua'			=> 'NCA',
		'Niger'				=> 'NIG',
		'Nigeria'			=> 'NGR',
		'Norway'			=> 'NOR',
		'Oman'				=> 'OMA',
		'Pakistan'			=> 'PAK',
		'Palau'				=> 'PLW',
		'Palestine'			=> 'PLE',
		'Panama'			=> 'PAN',
		'Paraguay'			=> 'PAR',
		'Peru'				=> 'PER',
		'Philippines'			=> 'PHI',
		'Poland'			=> 'POL',
		'Portugal'			=> 'POR',
		'Puerto Rico'			=> 'PUR',
		'Qatar'				=> 'QAT',
		'Romania'			=> 'ROU',	// was ROM, but in TMF ROU
		'Russia'			=> 'RUS',
		'Rwanda'			=> 'RWA',
		'Samoa'				=> 'SAM',
		'San Marino'			=> 'SMR',
		'Saudi Arabia'			=> 'KSA',
		'Senegal'			=> 'SEN',
		'Serbia'			=> 'SRB',	// was SCG, but in TMF SRB
		'Sierra Leone'			=> 'SLE',
		'Singapore'			=> 'SIN',
		'Slovakia'			=> 'SVK',
		'Slovenia'			=> 'SLO',
		'Somalia'			=> 'SOM',
		'South Africa'			=> 'RSA',
		'Spain'				=> 'ESP',
		'Sri Lanka'			=> 'SRI',
		'Sudan'				=> 'SUD',
		'Suriname'			=> 'SUR',
		'Swaziland'			=> 'SWZ',
		'Sweden'			=> 'SWE',
		'Switzerland'			=> 'SUI',
		'Syria'				=> 'SYR',
		'Taiwan'			=> 'TWN',
		'Tajikistan'			=> 'TJK',
		'Tanzania'			=> 'TAN',
		'Thailand'			=> 'THA',
		'Togo'				=> 'TOG',
		'Tonga'				=> 'TGA',
		'Trinidad and Tobago'		=> 'TRI',
		'Tunisia'			=> 'TUN',
		'Turkey'			=> 'TUR',
		'Turkmenistan'			=> 'TKM',
		'Tuvalu'			=> 'TUV',
		'Uganda'			=> 'UGA',
		'Ukraine'			=> 'UKR',
		'United Arab Emirates'		=> 'UAE',
		'United Kingdom'		=> 'GBR',
		'United States of America'	=> 'USA',
		'Uruguay'			=> 'URU',
		'Uzbekistan'			=> 'UZB',
		'Vanuatu'			=> 'VAN',
		'Venezuela'			=> 'VEN',
		'Vietnam'			=> 'VIE',
		'Yemen'				=> 'YEM',
		'Zambia'			=> 'ZAM',
		'Zimbabwe'			=> 'ZIM',
	);

	if ( array_key_exists($country, $nations) ) {
		$nation = $nations[$country];
	}
	else {
		$nation = 'other';
	}
	return $nation;
}

?>
