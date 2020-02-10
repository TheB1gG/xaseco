<?php

/*
==========================================================================================
Description : Display the best checkpoints of the current track of all player,
			  in a widget at the top of the screen (near the fufi menu, if you have it^^)
Last Revision : 18/02/2010
Version : 1.1
Author : galaad
==========================================================================================
*/

Aseco::registerEvent("onStartup", "initStartupServer");
Aseco::registerEvent("onCheckpoint", "principale_bestcps");
Aseco::registerEvent("onNewChallenge", "clear_bestcps");
Aseco::registerEvent("onNewChallenge", "init_bestcps");
Aseco::registerEvent('onEndRace1', 'clear_bestcps');
Aseco::registerEvent('onStartup', 'loadXmlFile');
Aseco::registerEvent("onPlayerFinish", "update_finish_time");

#changed from onPlayerFinish for realtime updates --nerve6
Aseco::registerEvent('onCheckpoint', 'main_display');
Aseco::registerEvent('onPlayerConnect2', 'main_display');

#for Toggle BestCPs link --nerve6
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'toggle_click');
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'main_display');

#command for hiding this widget --nerve6
Aseco::addChatCommand('bestcps', 'Toggle show/hide bestcps');

#array for storing people who don't want to see this widget --nerve6
$HiddenFor = array();

$tab_cp_time = array();
$finish_time = -1;
$fic;

class cp_time{
	var $time;
	var $nickname;
	
	function cp_time($time, $nickname){
		$this->time = $time;
		$this->nickname = $nickname;
	}
}

#function to handle Toggle button (pass to chat command) --nerve6
function toggle_click($aseco, $answer) {

	if ($answer[2] == 9000) {
		// get player
		$player = $aseco->server->players->getPlayer($answer[1]);

		// log clicked command
		$aseco->console('player {1} clicked command "/toggle_click "', $player->login);
		$command = array();
		$command['author'] = $player;
		chat_bestcps($aseco, $command);
	}
} 

#function to handle chat command /bestcps --nerve6
function chat_bestcps($aseco, $command){
	global $HiddenFor;
	$login = $command['author']->login;
	
	if(isset($HiddenFor[$login])){
		#if array key is set, user has already disabled this widget, so we need to enable it --nerve6
		
		#remove user from list of disabled --nerve6
		unset($HiddenFor[$login]);
		$aseco->client->query('ChatSendServerMessageToLogin', 'Bestcps widget enabled', $login);
	} else {	
		#user is not in list of disabled, so add them --nerve6
		$HiddenFor[$login] = true;
		$aseco->client->query('ChatSendServerMessageToLogin', 'Bestcps widget disabled', $login);
		
		#hide widget --nerve6
		hide_for_user($aseco, $login);
	}
	
}


function hide_for_user($aseco, $login){
	#send blank manialink to player to make existing cp times disappear --nerve6
	$xml = '<manialink id="123123456"></manialink>';
	$aseco->client->addCall("SendDisplayManialinkPageToLogin", array($login, $xml, 1, false));
}



function loadXmlFile($aseco, $tab){
	global $fic;
	$fic = simplexml_load_file('bestcps.xml');
}

function principale_bestcps($aseco, $param){
	global $tab_cp_time, $nickname, $time, $cp,	$finish_time, $fic;
	
	$nb_cp_max = $fic->number;
	$nickname = $aseco->server->players->player_list[$param[1]]->nickname;
	$time = $param[2];
	$cp = $param[4];

	if (((!$tab_cp_time[$cp]) or ($time < $tab_cp_time[$cp]->time)) and ($cp != ($aseco->server->challenge->nbchecks-1)) and ($cp < $nb_cp_max)){
		$tab_cp_time[$cp] = new cp_time($time, $nickname);
	}	
}

function main_display($aseco, $record){
	display_best_cps_0($aseco);
}

#New function, mix of other scripts and some tweaks --nerve6
function display_best_cps_0($aseco){ // affiche les best cps
	global $tab_cp_time, $fic, $HiddenFor;
	$texte;
	$min	= 0;
	$sec	= 0; 
	$cen	= 0; 
	$i 		= 0; 
	$place 	= 1; // indice cp
	$line	= 0; // indice de la ligne pour affichage des CP

	$posx_frame 	= $fic->position[0]->x; //main x position of the widget
	$posy_frame 	= $fic->position[0]->y; //main y position of the widget
	$amount_per_line = $fic->newline; 	//Nombre de CPS à afficher par ligne
	$posx;
	$posy;
	
	
	$xml='<?xml version="1.0" encoding="UTF-8"?>';
	$xml.='<manialink id="123123456">';
	$xml.='<frame posn="' .$posx_frame. ' ' .$posy_frame. '">';
	$xml.='<format textsize="1"/>';
	
	if (is_array($tab_cp_time)) {
		foreach($tab_cp_time as $value){ //pour chaque enregistrement
		
		$min = (int) (($value->time) / 60000);
		$sec = (int) ((($value->time) - $min * 60000) / 1000);
		$cen = (int) (($value->time - $min*60000 - $sec*1000) / 10);
		
		#re-written for readability --nerve6
		$texte = '$z';
		#added a preceeding 0 to the checkpoint number so times line up better --nerve6
		$texte .= ($place	<10 ? '0' : '').$place. '. ';
		$texte .= $min.':';
		$texte .= ($sec		<10 ? '0' : '').$sec.	'.';
		$texte .= ($cen		<10 ? '0' : '').$cen;
		#--

		$posx = $i * 11.5;
		if ($place == (($line + 1) * $amount_per_line + 1)){
			$line++;
			$i = 0;
		}
		$posx = $i * 16;
		$posy = -($line*2.5);
		$xml.='<quad  posn="'.$posx.' '.$posy.'" sizen="16 2.5" halign="center" valign="center" style="Bgs1InRace" substyle="NavButton" />';
		$posx = $posx - 7.25;
		$xml.='<label posn="'.$posx.' '.$posy.'" sizen="6.5 2.4" halign="left" valign="center" text="'.$texte.'"/>';
		$posx = $posx + 6.5;
		$xml.='<label posn="'.$posx.' '.$posy.'" sizen="7.5 2.4" halign="left" valign="center" text="'.$value->nickname.'"/>';
		$i++;
		$place++;
	}}
	
	$xml.='</frame></manialink>';
	
/*	#Make sure people have hidden it
	if(count($HiddenFor) > 0){
		
		#instead of showing for all players, look for only ones that are not in the HiddenFor array --nerve6
		#loop through entire player list --nerve6
		foreach($aseco->server->players->player_list as $player){
			#Make sure they are NOT in the HiddenFor array --nerve6
			if(!in_array($player->login,array_keys($HiddenFor))){
				#display the CP times --nerve6
				$aseco->client->addCall("SendDisplayManialinkPageToLogin", array($player->login, $xml, 0, false));
			}
			
		}	*/

	#Make sure people have hidden it
	if(is_array($HiddenFor) > 0){
		
		#instead of showing for all players, look for only ones that are not in the HiddenFor array --nerve6
		#loop through entire player list --nerve6
		foreach($aseco->server->players->player_list as $player){
			#Make sure they are NOT in the HiddenFor array --nerve6
			if(!in_array($player->login,array_keys($HiddenFor))){
				#display the CP times --nerve6
				$aseco->client->addCall("SendDisplayManialinkPageToLogin", array($player->login, $xml, 0, false));
			}
			
		}			
		
	} else {
		#Nobody has the thing hidden, send to all
		$aseco->client->query("SendDisplayManialinkPage", $xml, 0, false);
	}
	
}

function display_best_cps_h($aseco){ // affiche les best cps
	global $tab_cp_time, $fic;
	$texte;
	$min=0;
	$sec=0; 
	$cen=0; 
	$i = 0;
	$place = 1;

	$posx_frame = $fic->position[0]->x; //main x position of the widget
	$posy_frame = $fic->position[0]->y; //main y position of the widget
	$posx;	
	
	$xml='<?xml version="1.0" encoding="UTF-8"?>';
	$xml.='<manialink id="123123456">';
	$xml.='<frame posn="' .$posx_frame. ' ' .$posy_frame. '">';
	$xml.='<format textsize="1"/>';
	
	foreach($tab_cp_time as $value){ //pour chaque enregistrement
		
		$min = (int) (($value->time) / 60000);
		$sec = (int) ((($value->time) - $min * 60000) / 1000);
		$cen = (int) (($value->time - $min*60000 - $sec*1000) / 10);
		$texte ='$z'.$place. '. ';
		$texte .= "$min:";
		if ($sec < 10) $texte .= "0";	
		$texte .= $sec.'.';
		if ($cen < 10) $texte .= "0";
		$texte .= $cen;
		
		$posx = $i * 14;
		$xml.='<quad  posn="' .$posx. ' 0" sizen="14 2.2" halign="center" valign="center" style="Bgs1InRace" substyle="NavButton" />';
		$posx = $posx-6.25;
		$xml.='<label posn="' .$posx. ' 0.1" sizen="6.5 2" halign="left" valign="center" text="'.$texte.'"/>';
		$posx = $posx+5.8;
		$xml.='<label posn="' .$posx. ' 0.1" sizen="6.5 2" halign="left" valign="center" text="'.$value->nickname.'"/>';
		$i++;
		$place++;
	}
	
	$xml.='</frame></manialink>';
	$aseco->client->query("SendDisplayManialinkPage", $xml, 0, false); //requete d'affichage
}


function display_best_cps_1($aseco){ // affiche les best cps
	global $tab_cp_time, $fic;
	$texte;
	$min=0;
	$sec=0; 
	$cen=0; 
	$i = 0;
	$place = 1;

	$posx_frame = $fic->position[0]->x; //main x position of the widget
	$posy_frame = $fic->position[0]->y; //main y position of the widget
	$posx;	
	
	$xml='<?xml version="1.0" encoding="UTF-8"?>';
	$xml.='<manialink id="123123456">';
	$xml.='<frame posn="' .$posx_frame. ' ' .$posy_frame. '">';
	$xml.='<format textsize="1"/>';
	
	foreach($tab_cp_time as $value){ //pour chaque enregistrement
		
		$min = (int) (($value->time) / 60000);
		$sec = (int) ((($value->time) - $min * 60000) / 1000);
		$cen = (int) (($value->time - $min*60000 - $sec*1000) / 10);
		$texte ='$z'.$place. '. ';
		$texte .= "$min:";
		if ($sec < 10) $texte .= "0";	
		$texte .= $sec.'.';
		if ($cen < 10) $texte .= "0";
		$texte .= $cen;
		
		$posy = $i * (-2);
		$xml.='<quad  posn="0 ' .$posy. '" sizen="14 2.2" halign="center" valign="center" style="Bgs1InRace" substyle="NavButton" />';
		$posy = $posy+0.1;
		$xml.='<label posn="-6.5 ' .$posy. '" sizen="6.5 2" halign="left" valign="center" text="'.$texte.'"/>';
		$xml.='<label posn="-0.4 ' .$posy. '" sizen="6.5 2" halign="left" valign="center" text="'.$value->nickname.'"/>';
		$i++;
		$place++;
	}
	
	$xml.='</frame></manialink>';
	$aseco->client->query("SendDisplayManialinkPage", $xml, 0, false); //requete d'affichage
}

function display_best_cps_2($aseco){ // affiche les best cps
	global $tab_cp_time, $fic;
	$texte;
	$min=0;
	$sec=0; 
	$cen=0; 
	$i = 0;
	$place = 1;

	$posx_frame = $fic->position[0]->x; //main x position of the widget
	$posy_frame = $fic->position[0]->y; //main y position of the widget
	$posx;	
	
	$xml='<?xml version="1.0" encoding="UTF-8"?>';
	$xml.='<manialink id="123123456">';
	$xml.='<frame posn="' .$posx_frame. ' ' .$posy_frame. '">';
	$xml.='<format textsize="1"/>';
	$tmp = $nb * 2+0.2;
	$xml.='<quad  posn="0 1.1" sizen="14 '.$tmp.'" halign="center" valign="top" style="Bgs1InRace" substyle="NavButton" />';
	
	foreach($tab_cp_time as $value){ //pour chaque enregistrement
		
		$min = (int) (($value->time) / 60000);
		$sec = (int) ((($value->time) - $min * 60000) / 1000);
		$cen = (int) (($value->time - $min*60000 - $sec*1000) / 10);
		$texte ='$z'.$place. '. ';
		$texte .= "$min:";
		if ($sec < 10) $texte .= "0";	
		$texte .= $sec.'.';
		if ($cen < 10) $texte .= "0";
		$texte .= $cen;
		
		$posy = $i * (-2);
		$posy = $posy+0.1;
		$xml.='<label posn="-6.2 ' .$posy. '" sizen="6.5 2" halign="left" valign="center" text="'.$texte.'"/>';
		$xml.='<label posn="-0.4 ' .$posy. '" sizen="6.5 2" halign="left" valign="center" text="'.$value->nickname.'"/>';
		$i++;
		$place++;
	}
	
	$xml.='</frame></manialink>';
	$aseco->client->query("SendDisplayManialinkPage", $xml, 0, false); //requete d'affichage
}

function display_best_cps_3($aseco){ // affiche les best cps
	global $tab_cp_time, $fic;
	$texte;
	$min=0;
	$sec=0; 
	$cen=0; 
	$i = 0;
	$place = 1;

	$posx_frame = $fic->position[0]->x; //main x position of the widget
	$posy_frame = $fic->position[0]->y; //main y position of the widget
	$posx;	
	
	$xml='<?xml version="1.0" encoding="UTF-8"?>';
	$xml.='<manialink id="123123456">';
	$xml.='<frame posn="' .$posx_frame. ' ' .$posy_frame. '">';
	$xml.='<format textsize="1"/>';
	$tmp = count($tab_cp_time) * 2+0.2;
	$xml.='<quad  posn="0 1.1" sizen="14 '.$tmp.'" halign="center" valign="top" style="Bgs1InRace" substyle="NavButton" />';
	
	foreach($tab_cp_time as $value){ //pour chaque enregistrement
		
		$min = (int) (($value->time) / 60000);
		$sec = (int) ((($value->time) - $min * 60000) / 1000);
		$cen = (int) (($value->time - $min*60000 - $sec*1000) / 10);
		$texte ='$z'.$place. '. ';
		$texte .= "$min:";
		if ($sec < 10) $texte .= "0";	
		$texte .= $sec.'.';
		if ($cen < 10) $texte .= "0";
		$texte .= $cen;
		
		$posy = $i * (-2);
		$posy = $posy+0.1;
		$xml.='<label posn="-6.2 ' .$posy. '" sizen="6.5 2" halign="left" valign="center" text="'.$texte.'"/>';
		$xml.='<label posn="-0.4 ' .$posy. '" sizen="6.5 2" halign="left" valign="center" text="'.$value->nickname.'"/>';
		$i++;
		$place++;
	}
	
	$xml.='</frame></manialink>';
	$aseco->client->query("SendDisplayManialinkPage", $xml, 0, false); //requete d'affichage
}

function init_bestcps($aseco, $challenge){ // initialise les variables globales a chaque nouvelle map
	global $tab_cp_time, $fic;
	$tab_cp_time = array();
	
	/*#Send label for Toggle BestCPs link to everyone --nerve6
	$xml='<?xml version="1.0" encoding="UTF-8"?>';
	$xml.='<manialink id="123123457">';
	$xml.='<frame posn="-25 45.7">';
	$xml.='<format textsize="0.5"/>';
	$xml.='<label posn="1 1" sizen="5.5 2" halign="left" valign="center" text="Toggle BestCPs" action="9000"/>';
	$xml.='</frame></manialink>';
	$aseco->client->query("SendDisplayManialinkPage", $xml, 0, false);*/ 

}

function clear_bestcps($aseco, $challenge){//efface le widget entre 2 challenges
	$xml = '<manialink id="123123456"></manialink>';
    $aseco->client->query("SendDisplayManialinkPage", $xml, 1, false);
}

function update_finish_time($aseco, $record){
	global $finish_time;
	$finish_time = $record->score;
}
?>