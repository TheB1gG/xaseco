<?php

/* ----------------------------------------------------------------------------------
 * 
 * Xaseco2 plugin to display a ingame manialink with 3 windows. one for link 
 * manialink page and two for link to url.
 * possible to change main position via the config below.
 * Manialink is combined with all /togglewidget commands (Menu, Chatcommand, F7).
 * if you want to change other things, check the manialink tags line 85 - 104.
 *
 * ----------------------------------------------------------------------------------
 *
 * Author: 			nouseforname @ http://www.tm-forum.com
 * Home: 			nouseforname.de
 * Date: 			28.02.2012
 * Version:			1.0.1
 * Dependencies: 	none
 *
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
 */
 
/******** dont change this **********/
global $frameposition;				//
global $title, $closebutton;		//
global $manialinktext, $manialink;	//
global $url1text, $url1;			//
global $url2text, $url2;			//
global $xml_link_on, $xml_link_off;	//
/************************************/

// configuration
$frameposition = '0 45'; // main position x and y

$title = ''; // title with tmf colorcode

$manialinktext = '$fffManiaLink';
$manialink = 'Kripke';

$url1text = '$fffUpload your Map';
$url1 = 'mapup';

$url2text = '$fffYoutube';
$url2 = 'https://www.youtube.com/user/KripkesWorld';

/************* dont change anything below ****************************/
Aseco::registerEvent('onSync', 'link_upToDate');
Aseco::registerEvent('onStartup', 'init_link_manialinks');
Aseco::registerEvent('onBeginMap', 'link_on');
Aseco::registerEvent('onPlayerConnect', 'link_deal_with_players');
Aseco::registerEvent('onEndRound', 'link_off');
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'link_handle_buttonclick');
Aseco::registerEvent('onChat', 'link_toggle_command');
Aseco::registerEvent('onPlayerDisconnect', 'link_remove_player');

global $active_players;
$active_players = array();

function link_upToDate($aseco) {
    $aseco->plugin_versions[] = array(
        'plugin'	=> 'plugin.nouse.link.php',
        'author'	=> 'Nouseforname',
        'version'	=> '1.0.1'
    );
}

function init_link_manialinks($aseco) {
	global $xml_link_on, $xml_link_off;
	global $active_players;
	global $frameposition;
	global $title, $closebutton;
	global $manialinktext, $manialink;
	global $url1text, $url1;
	global $url2text, $url2;
	global $withwidgets;
	global $action_;
	
	$xml_link_on = '<manialink id="0815471122111">
		<frame posn="'.$frameposition.' 3">
			<format size="1" />
			<label posn="0 2.5 0" sizen="36 3" halign="center" valign="center" style="TextCardInfoSmall"  text="'.$title.'"  />
			<frame posn="-58 -51 0">
				<quad posn="0 0 0" sizen="15 3"  halign="center" valign="center" style="Bgs1" substyle="BgCardList"  />
				<label posn="0 0.1 1" sizen="11 1" halign="center" valign="center" style="TextCardInfoSmall" text="'.$manialinktext.'" manialink="'.$manialink.'"  />
			</frame>
			<frame posn="-58 -54 0">
				<quad posn="0 0 0 " sizen="15 3"  halign="center" valign="center" style="Bgs1" substyle="BgCardList"  />
				<label posn="0 0.1 1" sizen="11 1" halign="center" valign="center" style="TextCardInfoSmall" text="'.$url1text.'" manialink="'.$url1.'"  />
			</frame>
			<frame posn="-58 -57 2">
				<quad posn="0 0 0 " sizen="15 3"  halign="center" valign="center" style="Bgs1" substyle="BgCardList"  />
				<label posn="0 0.1 1" sizen="11 1" halign="center" valign="center" style="TextCardInfoSmall" text="'.$url2text.'" url="'.$url2.'" />
			</frame>
		</frame>
	</manialink>';

	$xml_link_off = '<manialink id="0815471122111">
	<frame posn="0 0 0">
	<quad posn="0 0 0" sizen="0 0" halign="center" valign="center" action="382009003" actionkey="3" /> 
	</frame>
	</manialink>';
}

// display manialink
function link_on($aseco) {
	global $active_players;
	global $xml_link_on;
	$aseco->client->addCall('SendDisplayManialinkPageToLogin', array(implode(',', $active_players), $xml_link_on, 0, false));
}  

// switch off manialink at roundsend
function link_off($aseco) {
	global $xml_link_off;
	$aseco->client->addCall('SendDisplayManialinkPage', array($xml_link_off, 0, false));
} 

// put playerlogins into array at player connect, and display manialink
function link_deal_with_players($aseco, $player) {
	global $active_players, $xml_link_on;
	$active_players[] = $player->login;
	$aseco->client->addCall('SendDisplayManialinkPageToLogin', array($player->login, $xml_link_on, 0, false));
}

// remove leaving players from array
function link_remove_player($aseco, $player) {
	global $active_players;
	$login = $player->login;
	
	if (in_array($login, $active_players)) {
			$key = array_search($login, $active_players);
			unset($active_players[$key]);
			sort($active_players);
	}
}
 
// F7 key press action id for widgets 382009003
// button click from menu "toggle widgets" 3831330
function link_handle_buttonclick($aseco, $command) {
	$login = $command[1];
	$action = $command[2];
	if ( $action == 382009003 || $action == 3831330 ) {
		link_change_player_status($aseco, $login);
	}
}

// change display status to specifig login
function link_change_player_status($aseco, $login) {
	global $active_players;
	global $xml_link_on, $xml_link_off;
	if (in_array($login, $active_players)) {
			$key = array_search($login, $active_players);
			unset($active_players[$key]);
			sort($active_players);
			$aseco->client->addCall('SendDisplayManialinkPageToLogin', array( $login, $xml_link_off, 0, false));
		}
		else {
			$active_players[] = $login;
			$aseco->client->addCall('SendDisplayManialinkPageToLogin', array($login, $xml_link_on, 0, false));
		}
}

// get chat command /togglewidgets from any player
function link_toggle_command($aseco, $command) {
	$playerid = $command[0];
	$login = $command[1];
	$action = $command[2];
	$state = $command[3];
	if ($playerid != 0 && $action == '/togglewidgets' && $state == 1) {
		link_change_player_status($aseco, $login);
	}
}
?>