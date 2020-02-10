<?php

/*
 * Plugin: Teamspeak3-Info
 * ~~~~~~~~~~~~~~~~~~~~~~~
 * For a detailed description and documentation, please refer to:
 * http://labs.undef.de/XAseco1/Teamspeak3-Info.php
 *
 * ----------------------------------------------------------------------------------
 * Author:		undef.de
 * Contributors:	SilentStorm
 * Version:		0.9.7
 * Date:		2012-01-19
 * Copyright:		2011 - 2012 by undef.de
 * System:		XAseco/1.14+
 * Game:		Trackmania Forever (TMF)
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
 * ----------------------------------------------------------------------------------
 *
 * Dependencies:	none
 */

/* The following manialink id's are used in this plugin (the 921 part of id can be changed on trouble):
 *
 * ManialinkID's
 * ~~~~~~~~~~~~~
 * 92100		id for manialink InfoWidget
 * 92101		id for manialink InfoWindow
 *
 * ActionID's
 * ~~~~~~~~~~
 * 92100		id for action show InfoWindow
 * 92101		id for action hide InfoWindow
 *
 */

Aseco::registerEvent('onSync',				'ts3_onSync');
Aseco::registerEvent('onPlayerConnect',			'ts3_onPlayerConnect');
Aseco::registerEvent('onPlayerManialinkPageAnswer',	'ts3_onPlayerManialinkPageAnswer');
Aseco::registerEvent('onEverySecond',			'ts3_onEverySecond');
Aseco::registerEvent('onNewChallenge',			'ts3_onNewChallenge');
Aseco::registerEvent('onEndRace1',			'ts3_onEndRace1');


global $ts3_config;

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ts3_onSync ($aseco) {
	global $ts3_config;


	// Check for the right XAseco-Version
	$xaseco_min_version = '1.14';
	if ( defined('XASECO_VERSION') ) {
		if ( version_compare(XASECO_VERSION, $xaseco_min_version, '<') ) {
			trigger_error('[plugin.teamspeak3_info.php] Not supported XAseco version ('. XASECO_VERSION .')! Please update to min. version '. $xaseco_min_version .'!', E_USER_ERROR);
		}
	}
	else {
		trigger_error('[plugin.teamspeak3_info.php] Can not identify the System, "XASECO_VERSION" is unset! This plugin runs only with XAseco/'. $xaseco_min_version .'+', E_USER_ERROR);
	}

	if ($aseco->server->getGame() != 'TMF') {
		trigger_error('[plugin.teamspeak3_info.php] This plugin supports only TMF, can not start with a "'. $aseco->server->getGame() .'" Dedicated-Server!', E_USER_ERROR);
	}

	if (!$ts3_config = $aseco->xml_parser->parseXML('teamspeak3_info.xml')) {
		trigger_error('[plugin.teamspeak3_info.php] Could not read/parse config file "teamspeak3_info.xml"!', E_USER_ERROR);
	}

	$ts3_config = $ts3_config['TEAMSPEAK3_INFO'];


	// Check for required <public_host>, <public_port> and <query_port>
	if ( ( !isset($ts3_config['TS3_SERVER'][0]['PUBLIC_HOST'][0]) ) || ($ts3_config['TS3_SERVER'][0]['PUBLIC_HOST'][0] == '') ) {
		trigger_error('[plugin.teamspeak3_info.php] Missing required option <public_host> in config file "teamspeak3_info.xml"!', E_USER_ERROR);
	}
	if ( ( !isset($ts3_config['TS3_SERVER'][0]['PUBLIC_PORT'][0]) ) || ($ts3_config['TS3_SERVER'][0]['PUBLIC_PORT'][0] == '') ) {
		trigger_error('[plugin.teamspeak3_info.php] Missing required option <public_port> in config file "teamspeak3_info.xml"!', E_USER_ERROR);
	}
	if ( ( !isset($ts3_config['TS3_SERVER'][0]['QUERY_PORT'][0]) ) || ($ts3_config['TS3_SERVER'][0]['QUERY_PORT'][0] == '') ) {
		trigger_error('[plugin.teamspeak3_info.php] Missing required option <query_port> in config file "teamspeak3_info.xml"!', E_USER_ERROR);
	}
	if ( (!isset($ts3_config['TS3_SERVER'][0]['ID'][0])) || ($ts3_config['TS3_SERVER'][0]['ID'][0] == 0) ) {
		$ts3_config['TS3_SERVER'][0]['ID'][0] = 1;			// Set default
	}

	// Setup Query-Host to Public-Host if unset
	if ( ( !isset($ts3_config['TS3_SERVER'][0]['QUERY_HOST'][0]) ) || ($ts3_config['TS3_SERVER'][0]['QUERY_HOST'][0] == '') ) {
		$ts3_config['TS3_SERVER'][0]['QUERY_HOST'][0] = $ts3_config['TS3_SERVER'][0]['PUBLIC_HOST'][0];
	}


	$ts3_config['Timeout'] = 2;
	$ts3_config['DecodeUtf8'] = false;
	$ts3_config['Ts3Data'] = array();
	$ts3_config['RefreshTimestamp'] = time();		// Refresh now
	$ts3_config['State'] = 'race';				// Set default to 'race'

	// Setup TS3 Escape Sequence
	$ts3_config['EscapeTable']['ts3']	= array('\\\\', 	"\/", 		"\s", 		"\p", 		"\a", 	"\b", 	"\f", 		"\n", 		"\r", 	"\t", 	"\v");
	$ts3_config['EscapeTable']['char']	= array(chr(92),	chr(47),	chr(32),	chr(124),	chr(7),	chr(8),	chr(12),	chr(10),	chr(3),	chr(9),	chr(11));

	$ts3_config['ManialinkId'] = '921';
	$ts3_config['Version'] = '0.9.7';

	// Register this to the global version pool (for up-to-date checks)
	$aseco->plugin_versions[] = array(
		'plugin'	=> 'plugin.teamspeak3_info.php',
		'author'	=> 'undef.de',
		'version'	=> $ts3_config['Version']
	);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ts3_onEverySecond ($aseco) {
	global $ts3_config;


	// Is it time for refresh the InfoWidgets?
	if ( (time() >= $ts3_config['RefreshTimestamp']) && ($ts3_config['State'] == 'race') ) {

		// Set next refresh timestamp
		$ts3_config['RefreshTimestamp'] = (time() + $ts3_config['WIDGET'][0]['REFRESH_INTERVAL'][0]);

		// Update ServerData
		ts3_queryServer();

		// Update InfoWidget at all Players at Race
		ts3_buildInfoWidget(false, true);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ts3_onPlayerConnect ($aseco, $player) {
	global $ts3_config;


	// Send InfoWidget to this Player
	ts3_buildInfoWidget($player->login, true);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// $answer = [0]=PlayerUid, [1]=Login, [2]=Answer
function ts3_onPlayerManialinkPageAnswer ($aseco, $answer) {
	global $ts3_config;


	// If id = 0, bail out immediately
	if ($answer[2] == 0) {
		return;
	}

	// Get the Player object
	$player = $aseco->server->players->player_list[$answer[1]];

	$widgets = '';
	if ($answer[2] == $ts3_config['ManialinkId'] .'00') {

		$widgets .= ts3_buildInfoWindow($player);					// Build InfoWindow

	}
	else if ($answer[2] == $ts3_config['ManialinkId'] .'01') {

		$widgets .= '<manialink id="'. $ts3_config['ManialinkId'] .'01"></manialink>';	// Close InfoWindow

	}


	if ($widgets != '') {
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<manialinks>';
		$xml .= $widgets;
		$xml .= '</manialinks>';

		// Send to given Player
		$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ts3_onNewChallenge ($aseco, $race) {
	global $ts3_config;


	// Set 'race' state
	$ts3_config['State'] = 'race';

	// Show InfoWidget at all Players at Race
	ts3_buildInfoWidget(false, true);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ts3_onEndRace1 ($aseco, $race) {
	global $ts3_config;


	// Set 'score' state
	$ts3_config['State'] = 'score';

	// Hide InfoWidget and InfoWindow at all Players at Score
	$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $ts3_config['ManialinkId'] .'00"></manialink>';	// InfoWidget
	$xml .= '<manialink id="'. $ts3_config['ManialinkId'] .'01"></manialink>';	// InfoWindow
	$xml .= '</manialinks>';

	// Send to all connected Players
	$aseco->client->query('SendDisplayManialinkPage', $xml, 0, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ts3_buildInfoWidget ($login = false, $display = true) {
	global $aseco, $ts3_config;


	$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $ts3_config['ManialinkId'] .'00">';
	if ($display == true) {
		$current_online = (isset($ts3_config['Ts3Data']['server']['virtualserver_clientsonline']) ? $ts3_config['Ts3Data']['server']['virtualserver_clientsonline'] : '---');
		$current_max = (isset($ts3_config['Ts3Data']['server']['virtualserver_maxclients']) ? $ts3_config['Ts3Data']['server']['virtualserver_maxclients'] : '---');

		$xml .= '<frame posn="'. $ts3_config['WIDGET'][0]['POS_X'][0] .' '. $ts3_config['WIDGET'][0]['POS_Y'][0] .' 0">';
		$xml .= '<format textsize="1"/>';
		$xml .= '<quad posn="0 0 0.001" sizen="4.6 6.5" action="'. $ts3_config['ManialinkId'] .'00" style="'. $ts3_config['WIDGET'][0]['BACKGROUND_STYLE'][0] .'" substyle="'. $ts3_config['WIDGET'][0]['BACKGROUND_SUBSTYLE'][0] .'"/>';
		$xml .= '<quad posn="-0.18 -4.6 0.002" sizen="2.1 2.1" image="'. $ts3_config['IMAGES'][0]['WIDGET_OPEN_SMALL'][0] .'"/>';
		$xml .= '<quad posn="0.8 -0.4 0.002" sizen="3 3" image="'. $ts3_config['IMAGES'][0]['TEAMSPEAK_LOGO'][0] .'"/>';
		$xml .= '<label posn="2.3 -3.4 0.1" sizen="3.65 2" halign="center" text="'. $current_online .'/'. $current_max .'"/>';
		$xml .= '<label posn="2.3 -4.9 0.1" sizen="6.35 2" halign="center" textcolor="'. $ts3_config['WIDGET'][0]['TEXT_COLOR'][0] .'" scale="0.6" text="TEAMSPEAK"/>';
		$xml .= '</frame>';
	}
	$xml .= '</manialink>';
	$xml .= '</manialinks>';


	if ($login !=  false) {
		// Send to given Player
		$aseco->client->query('SendDisplayManialinkPageToLogin', $login, $xml, 0, false);
	}
	else {
		// Send to all connected Players
		$aseco->client->query('SendDisplayManialinkPage', $xml, 0, false);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ts3_buildInfoWindow ($player) {
	global $aseco, $ts3_config;


	// Bail out if Data not already retrieved
	if ( !isset($ts3_config['Ts3Data']['server']) ) {
		return;
	}

	$xml  = '<manialink id="'. $ts3_config['ManialinkId'] .'01">';
	$xml .= '<frame posn="-40.1 30.45 18.50">';	// BEGIN: Window Frame
	$xml .= '<quad posn="0.8 -0.8 0.01" sizen="78.4 53.7" bgcolor="'. $ts3_config['WINDOW'][0]['WINDOW_BGCOLOR'][0] .'"/>';
	$xml .= '<quad posn="-0.2 0.2 0.04" sizen="80.4 55.7" style="Bgs1InRace" substyle="BgCard3"/>';

	// Header Line
	$xml .= '<quad posn="0.8 -1.3 0.02" sizen="78.4 3" bgcolor="'. $ts3_config['WINDOW'][0]['HEADLINE_BGCOLOR'][0] .'"/>';
	$xml .= '<quad posn="0.8 -4.3 0.03" sizen="78.4 0.1" bgcolor="FFF9"/>';
	$xml .= '<quad posn="2.2 -1.5 0.04" sizen="2.4 2.4" image="'. $ts3_config['IMAGES'][0]['TEAMSPEAK_LOGO'][0] .'"/>';

	// Title
	$xml .= '<label posn="5.5 -1.9 0.04" sizen="74 0" textsize="2" scale="0.9" textcolor="'. $ts3_config['WINDOW'][0]['HEADLINE_TEXTCOLOR'][0] .'" text="'. ts3_handleSpecialChars($ts3_config['Ts3Data']['server']['virtualserver_name']) .' - Teamspeak 3"/>';
	$xml .= '<quad posn="2.7 -54.1 0.04" sizen="11 1" url="http://labs.undef.de/XAseco1/Teamspeak3-Info.php" bgcolor="0000"/>';
	$xml .= '<label posn="2.7 -54.1 0.04" sizen="30 1" textsize="1" scale="0.7" textcolor="000F" text="TEAMSPEAK3-INFO/'. $ts3_config['Version'] .'"/>';

	// Close Button
	$xml .= '<frame posn="77.4 1.3 0.05">';
	$xml .= '<quad posn="0 0 0.01" sizen="4 4" style="Icons64x64_1" substyle="ArrowDown"/>';
	$xml .= '<quad posn="1.1 -1.35 0.02" sizen="1.8 1.75" bgcolor="EEEF"/>';
	$xml .= '<quad posn="0.65 -0.7 0.03" sizen="2.6 2.6" action="'. $ts3_config['ManialinkId'] .'01" style="Icons64x64_1" substyle="Close"/>';
	$xml .= '</frame>';

	// Build Link to the Teamspeak3-Server
	// http://www.teamspeak.com/?page=faq&cat=ts3server#ts3server_weblink
	// ts3server://ts3.hoster.com/?port=9987&nickname=UserNickname&password=serverPassword&channel=MyDefaultChannel&channelpassword=defaultChannelPassword&token=TokenKey&addbookmark=1
	$url = $ts3_config['TS3_SERVER'][0]['PUBLIC_HOST'][0] .'/?port='. $ts3_config['TS3_SERVER'][0]['PUBLIC_PORT'][0] .'&amp;nickname='. rawurlencode(stripColors($player->nickname, true));
	if ($ts3_config['TS3_SERVER'][0]['DEFAULT_CHANNEL'][0] != '') {
		$url .= '&amp;channel='. rawurlencode($ts3_config['TS3_SERVER'][0]['DEFAULT_CHANNEL'][0]);
	}
	$xml .= '<frame posn="54 -53.7 -3.1">';
	$xml .= '<quad posn="-0.2 0.2 0.09" sizen="18.4 5.7" style="Bgs1InRace" substyle="BgCard3"/>';
	$xml .= '<label posn="0.8 -0.8 0.01" sizen="16.4 3.7" url="http://www.teamspeak.com/invite/'. $url .'" focusareacolor1="EEEF" focusareacolor2="FFFF" text=" "/>';
	$xml .= '<label posn="9 -2.2 0.10" sizen="14 1.4" halign="center" textsize="1" scale="0.9" textcolor="0BF" text="TALK AT THIS SERVER"/>';
	$xml .= '</frame>';



	// BEGIN: Server Overview Box
	$xml .= '<frame posn="2.5 -5.7 1">';
	$xml .= '<format textsize="1" textcolor="'. $ts3_config['WINDOW'][0]['COLORS'][0]['DEFAULT'][0] .'"/>';
	$xml .= '<quad posn="0 0 0.02" sizen="17.75 23.34" style="BgsPlayerCard" substyle="BgRacePlayerName"/>';
	$xml .= '<quad posn="0.4 -0.36 0.04" sizen="16.95 2" style="BgsPlayerCard" substyle="ProgressBar"/>';
	$xml .= '<quad posn="0.6 0 0.05" sizen="2.5 2.5" style="Icons128x128_1" substyle="Statistics"/>';
	$xml .= '<label posn="3.2 -0.55 0.05" sizen="17.3 0" textsize="1" text="Server Overview"/>';

	$xml .= '<label posn="0.8 -2.8 0.05" sizen="5 1.8" text="Status:"/>';
	$xml .= '<label posn="0.8 -4.8 0.05" sizen="5 1.8" text="Ping:"/>';
	$xml .= '<label posn="0.8 -6.8 0.05" sizen="5 1.8" text="Clients:"/>';
	$xml .= '<label posn="0.8 -8.8 0.05" sizen="5 1.8" text="Channels:"/>';
	$xml .= '<label posn="0.8 -10.8 0.05" sizen="5 1.8" text="Up since:"/>';
	$xml .= '<label posn="0.8 -12.8 0.05" sizen="5 1.8" text="Plattform:"/>';
	$xml .= '<label posn="0.8 -14.8 0.05" sizen="5 1.8" text="Version:"/>';

	$xml .= '<label posn="5.9 -2.8 0.05" sizen="11 1.8" text=" '. $ts3_config['Ts3Data']['server']['virtualserver_status'] .'"/>';
	$xml .= '<label posn="5.9 -4.8 0.05" sizen="11 1.8" text=" '. $ts3_config['Ts3Data']['server']['virtualserver_total_ping'] .'"/>';
	$xml .= '<label posn="5.9 -6.8 0.05" sizen="11 1.8" text=" '. $ts3_config['Ts3Data']['server']['virtualserver_clientsonline'] .' / '. $ts3_config['Ts3Data']['server']['virtualserver_maxclients'] .' / $FD0'. $ts3_config['Ts3Data']['server']['virtualserver_reserved_slots'] .'"/>';
	$xml .= '<label posn="5.9 -8.8 0.05" sizen="11 1.8" text=" '. $ts3_config['Ts3Data']['server']['virtualserver_channelsonline'] .'"/>';
	$xml .= '<label posn="5.9 -10.8 0.05" sizen="11 1.8" text=" '. $ts3_config['Ts3Data']['server']['virtualserver_uptime'] .'"/>';
	$xml .= '<label posn="5.9 -12.8 0.05" sizen="11 1.8" text=" '. $ts3_config['Ts3Data']['server']['virtualserver_platform'] .'"/>';
	$xml .= '<label posn="5.9 -14.8 0.05" sizen="11 1.8" text=" '. $ts3_config['Ts3Data']['server']['virtualserver_version'] .'"/>';
	$xml .= '</frame>';
	// END: Server Overview Box


	// BEGIN: Legend Box
	$xml .= '<frame posn="2.5 -29.37 1">';
	$xml .= '<format textsize="1" textcolor="'. $ts3_config['WINDOW'][0]['COLORS'][0]['DEFAULT'][0] .'"/>';
	$xml .= '<quad posn="0 0 0.02" sizen="17.75 11.43" style="BgsPlayerCard" substyle="BgRacePlayerName"/>';
	$xml .= '<quad posn="0.4 -0.36 0.04" sizen="16.95 2" style="BgsPlayerCard" substyle="ProgressBar"/>';
	$xml .= '<quad posn="0.6 0 0.05" sizen="2.5 2.5" style="Icons128x128_1" substyle="Profile"/>';
	$xml .= '<label posn="3.2 -0.55 0.05" sizen="17.3 0" textsize="1" text="Legend"/>';

	$xml .= '<quad posn="0.9 -2.9 0.05" sizen="1.7 1.7" style="Icons128x128_1" substyle="Lan"/>';
	$xml .= '<quad posn="0.9 -4.5 0.05" sizen="1.7 1.7" style="Icons64x64_1" substyle="StatePrivate"/>';
	$xml .= '<quad posn="0.9 -6.1 0.05" sizen="1.7 1.7" style="BgRaceScore2" substyle="Fame"/>';
	$xml .= '<quad posn="0.9 -7.7 0.05" sizen="1.7 1.7" style="Icons128x128_1" substyle="ChallengeAuthor"/>';
	$xml .= '<quad posn="0.9 -9.3 0.05" sizen="1.7 1.7" style="Icons64x64_1" substyle="NotBuddy"/>';
	$xml .= '<label posn="3 -3 0.05" sizen="15.3 1.7" scale="0.8" text=" Channel (open)"/>';
	$xml .= '<label posn="3 -4.6 0.05" sizen="15.3 1.7" scale="0.8" text=" Channel (non-public)"/>';
	$xml .= '<label posn="3 -6.2 0.05" sizen="15.3 1.7" scale="0.8" text=" Channel (default)"/>';
	$xml .= '<label posn="3 -7.8 0.05" sizen="15.3 1.7" scale="0.8" text=" User"/>';
	$xml .= '<label posn="3 -9.4 0.05" sizen="15.3 1.7" scale="0.8" text=" User (AFK)"/>';
	$xml .= '</frame>';
	// END: Legend Box


	// BEGIN: Download Box
	$xml .= '<frame posn="2.5 -41.27 1">';
	$xml .= '<format textsize="1" textcolor="'. $ts3_config['WINDOW'][0]['COLORS'][0]['DEFAULT'][0] .'"/>';
	$xml .= '<quad posn="0 0 0.02" sizen="17.75 11.43" style="BgsPlayerCard" substyle="BgRacePlayerName"/>';
	$xml .= '<quad posn="0.4 -0.36 0.04" sizen="16.95 2" style="BgsPlayerCard" substyle="ProgressBar"/>';
	$xml .= '<quad posn="0.6 0 0.05" sizen="2.5 2.5" style="Icons64x64_1" substyle="TrackInfo"/>';
	$xml .= '<label posn="3.2 -0.55 0.05" sizen="17.3 0" textsize="1" text="Please note"/>';

	$xml .= '<quad posn="0.85 -2.6 0.04" sizen="5 5" style="Icons64x64_1" substyle="YellowHigh"/>';
	$xml .= '<label posn="3.45 -4.2 0.05" sizen="9.2 0" halign="center" textsize="3.5" text="$O$000!"/>';
	$xml .= '<label posn="6.7 -3 0.05" sizen="13 1.7" scale="0.8" autonewline="1" text="It is required, that you'. LF .'have the Teamspeak3'. LF .'client installed!"/>';
	$xml .= '<label posn="8.9 -9.3 0.05" sizen="15.35 2.3" halign="center" valign="center" url="http://www.teamspeak.com/" focusareacolor1="070F" focusareacolor2="0C0F" text=" "/>';
	$xml .= '<label posn="8.9 -9.13 0.06" sizen="15.35 2.3" scale="1" halign="center" valign="center" text="Download Teamspeak3"/>';
	$xml .= '</frame>';
	// END: Download Box


	// BEGIN: Channel and User Box
	$lists = array();
	foreach ($ts3_config['Ts3Data']['channels'] as &$item) {
		$lists[$item['cid']]['name']		= $item['channel_name'];
		$lists[$item['cid']]['pid']		= $item['pid'];
		$lists[$item['cid']]['default']		= $item['channel_flag_default'];
		$lists[$item['cid']]['password']	= $item['channel_flag_password'];
	}
	unset($item);
	foreach ($ts3_config['Ts3Data']['users'] as &$item) {
		if ($item['client_type'] == 0) {		// reliable detection of any Query Clients ( 0 = regular client, 1 = query client)
			$lists[$item['cid']]['users'][] = array(
				'name'		=> $item['client_nickname'],
				'afk'		=> $item['client_away'],
			);
		}
	}
	unset($item);

	$xml .= '<frame posn="21.55 -5.7 1">';
	$xml .= '<format textsize="1" textcolor="'. $ts3_config['WINDOW'][0]['COLORS'][0]['DEFAULT'][0] .'"/>';
	$xml .= '<quad posn="0 0 0.02" sizen="55.85 47" style="BgsPlayerCard" substyle="BgRacePlayerName"/>';
	$xml .= '<quad posn="0.4 -0.36 0.04" sizen="55.05 2" style="BgsPlayerCard" substyle="ProgressBar"/>';
	$xml .= '<quad posn="0.6 0 0.05" sizen="2.5 2.5" style="Icons128x128_1" substyle="Multiplayer"/>';
	$xml .= '<label posn="3.2 -0.65 0.05" sizen="17.3 0" textsize="1" text="Channels and Users"/>';

	$xml .= '<frame posn="0.25 -1.05 0">';
	$xml .= '<quad posn="18.4 -1.75 0.05" sizen="0.1 43.8" bgcolor="0003"/>';
	$xml .= '<quad posn="36.9 -1.75 0.05" sizen="0.1 43.8" bgcolor="0003"/>';

	$line = 1;
	$offset = 0;
	$entries = 0;

	foreach ($lists as &$item) {
		if ($line > 25) {
			$offset += 18.6;
			$line = 1;
		}

		// Indent Subchannels
		$pid_offset = 0;
		if ($item['pid'] > 0) {
			$pid_offset = 1;
		}

		$icon = array('style' => 'Icons128x128_1', 'substyle' => 'Lan');	// Open Channel
		if ($item['password'] == 1) {
			// Mark Channel as password protected
			$icon = array('style' => 'Icons64x64_1', 'substyle' => 'StatePrivate');
		}
		else if ($item['default'] == 1) {
			// Mark Channel as default channel
			$icon = array('style' => 'BgRaceScore2', 'substyle' => 'Fame');
		}
		$xml .= '<quad posn="'. ($offset + 15.7) .' -'. (1.75 * $line) .' 0.05" sizen="1.8 1.8" style="'. $icon['style'] .'" substyle="'. $icon['substyle'] .'"/>';
		$xml .= '<label posn="'. ($offset + $pid_offset + 0.8) .' -'. (1.75 * $line) .' 0.05" sizen="'. (16.2 - $pid_offset) .' 1.85" scale="0.9" textcolor="'. $ts3_config['WINDOW'][0]['COLORS'][0]['CHANNELS'][0] .'" text="'. htmlspecialchars(validateUTF8String($item['name'])) .'"/>';

		$line++;
		$entries++;

		if ( (isset($item['users'])) && (count($item['users']) > 0) ) {
			foreach ($item['users'] as &$user) {
				if ($line > 25) {
					$offset += 18.5;
					$line = 1;
				}

				$icon = array('style' => 'Icons128x128_1', 'substyle' => 'ChallengeAuthor');	// Talking User
				if ($user['afk'] == 1) {
					// Mark user as "away from keyboard"
					$icon = array('style' => 'Icons64x64_1', 'substyle' => 'NotBuddy');
				}
				$xml .= '<quad posn="'. ($offset + 15.7) .' -'. (1.75 * $line) .' 0.05" sizen="1.7 1.7" style="'. $icon['style'] .'" substyle="'. $icon['substyle'] .'"/>';
				$xml .= '<label posn="'. ($offset + 2.8) .' -'. (1.75 * $line) .' 0.05" sizen="15.7 1.85" scale="0.8" textcolor="'. $ts3_config['WINDOW'][0]['COLORS'][0]['USERS'][0] .'" text="'. htmlspecialchars(validateUTF8String($user['name'])) .'"/>';

				$line++;
				$entries++;

				if ($entries >= 75) {
					break;
				}
			}
			unset($user);
		}
		if ($entries >= 75) {
			break;
		}
	}
	unset($item);
	$xml .= '</frame>';
	$xml .= '</frame>';
	// END: Channel and User Box


	$xml .= '</frame>';				// END: Window Frame
	$xml .= '</manialink>';

	return $xml;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ts3_handleSpecialChars ($string) {


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

// The next functions are based upon tsstatus.php from http://tsstatus.sebastien.me/
// and was optimized by SilentStorm to our needs
function ts3_queryServer () {
	global $aseco, $ts3_config;


	$socket = fsockopen($ts3_config['TS3_SERVER'][0]['QUERY_HOST'][0], $ts3_config['TS3_SERVER'][0]['QUERY_PORT'][0], $errno, $errstr, $ts3_config['Timeout']);
	if ($socket) {
		socket_set_timeout($socket, $ts3_config['Timeout']);
		$is_ts3 = trim(fgets($socket)) == 'TS3';
		if (!$is_ts3) {
			$aseco->console('[plugin.teamspeak3_info.php] Server at "'. $ts3_config['TS3_SERVER'][0]['QUERY_HOST'][0] .'" is not a Teamspeak3-Server or you have setup a bad query-port!');
		}

		if (isset($ts3_config['TS3_SERVER'][0]['QUERY_USER'][0]) && !empty($ts3_config['TS3_SERVER'][0]['QUERY_USER'][0]) && !is_numeric($ts3_config['TS3_SERVER'][0]['QUERY_USER'][0]) && $ts3_config['TS3_SERVER'][0]['QUERY_USER'][0] != false && isset($ts3_config['TS3_SERVER'][0]['QUERY_PASS'][0]) && !empty($ts3_config['TS3_SERVER'][0]['QUERY_PASS'][0]) && !is_numeric($ts3_config['TS3_SERVER'][0]['QUERY_PASS'][0]) && $ts3_config['TS3_SERVER'][0]['QUERY_PASS'][0] != false) {
			$ret = ts3_sendCommand($socket, 'login client_login_name='. ts3_escape($ts3_config['TS3_SERVER'][0]['QUERY_USER'][0]) .' client_login_password='. ts3_escape($ts3_config['TS3_SERVER'][0]['QUERY_PASS'][0]));
			if (stripos($ret, "error id=0") === false) {
				trigger_error("[plugin.teamspeak3_info.php] Failed to authenticate with TS3 Server! Make sure you put the correct Username & Password in teamspeak3_info.xml", E_USER_WARNING);
				return;
			}
		}
//		else {
//			$aseco->console('[plugin.teamspeak3_info.php] Login and/or Password not specified, using Guest Login.');
//		}

		$response = '';
		$response .= ts3_sendCommand($socket, 'use sid=' . $ts3_config['TS3_SERVER'][0]['ID'][0]);
		if (!empty($ts3_config['TS3_SERVER'][0]['QUERY_FRIENDLY_NICKNAME'][0]) && isset($ts3_config['TS3_SERVER'][0]['QUERY_FRIENDLY_NICKNAME'][0])) {
			ts3_sendCommand($socket, 'clientupdate client_nickname=' . ts3_escape($ts3_config['TS3_SERVER'][0]['QUERY_FRIENDLY_NICKNAME'][0]));
		}
		$response .= ts3_sendCommand($socket, 'serverinfo');
		$response .= ts3_sendCommand($socket, 'channellist -topic -flags -voice -limits');
		$response .= ts3_sendCommand($socket, 'clientlist -uid -away -voice -groups');

		fputs($socket, "quit\n");
		fclose($socket);

		if ($ts3_config['DecodeUtf8'] == true) {
			$response = utf8_decode($response);
		}

		$lines = explode("error id=0 msg=ok\n\r", $response);
		if (count($lines) == 5) {
			$serverdata = ts3_parseLine($lines[1]);
			$ts3_config['Ts3Data']['server'] = $serverdata[0];
			$ts3_config['Ts3Data']['channels'] = ts3_parseLine($lines[2]);
			$ts3_config['Ts3Data']['users'] = ts3_parseLine($lines[3]);

			// Subtract reserved slots
			$ts3_config['Ts3Data']['server']['virtualserver_maxclients'] -= $ts3_config['Ts3Data']['server']['virtualserver_reserved_slots'];

			// Make ping value int
			$ts3_config['Ts3Data']['server']['virtualserver_total_ping'] = intval($ts3_config['Ts3Data']['server']['virtualserver_total_ping']);

			// Format the Date of server startup
			$ts3_config['Ts3Data']['server']['virtualserver_uptime'] = date('Y-m-d H:i:s', (time() - $ts3_config['Ts3Data']['server']['virtualserver_uptime']) );

			// Always subtract all Query Clients
			$ts3_config['Ts3Data']['server']['virtualserver_clientsonline'] -= $ts3_config['Ts3Data']['server']['virtualserver_queryclientsonline'];
		}
	}
	// else throw new Exception("Socket error: $errstr [$errno]");
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ts3_sendCommand ($socket, $cmd) {

	fputs($socket, "$cmd\n");

	$response = '';
	while (strpos($response, 'error id=') === false) {
		$response .= fread($socket, 8096);
	}
//	if (strpos($response, 'error id=0') === false) {
//		//throw new Exception("TS3 Server returned the following error: " . ts3_unescape(trim($response)));
//	}
	return $response;
}


/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ts3_parseLine ($rawLine) {

	$datas = array();
	$rawItems = explode('|', $rawLine);

	foreach ($rawItems as &$rawItem) {
		$rawDatas = explode(' ', $rawItem);
		$tempDatas = array();
		foreach ($rawDatas as &$rawData) {
			$ar = explode("=", $rawData, 2);
			$tempDatas[$ar[0]] = isset($ar[1]) ? ts3_unescape($ar[1]) : '';
		}
		$datas[] = $tempDatas;
	}
	unset($rawItem, $rawData);

	return $datas;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ts3_escape ($str) {
	global $ts3_config;
	return str_replace($ts3_config['EscapeTable']['char'], $ts3_config['EscapeTable']['ts3'], $str);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function ts3_unescape ($str) {
	global $ts3_config;
	return str_replace($ts3_config['EscapeTable']['ts3'], $ts3_config['EscapeTable']['char'], $str);
}

?>
