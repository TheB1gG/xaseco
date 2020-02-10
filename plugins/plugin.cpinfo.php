<?php
/**	plugin.cpinfo.php, 1.31
 *	Checkpoint info plugin for XAseco by Reaby, 2010
 *	Configuration file: cpinfo.xml
 *	Copy this file to xaseco plugins folder
 */	
Aseco::registerEvent('onStartup', 'cpinfo_startup');
Aseco::registerEvent('onCheckpoint', 'cpinfo_checkpoint');
Aseco::registerEvent('onPlayerFinish', 'cpinfo_finish');
Aseco::registerEvent('onBeginRound', 'cpinfo_beginround');
Aseco::registerEvent('onEndRound', 'cpinfo_end');
Aseco::addChatCommand('cpinfo','cpinfo-releated commands');

function chat_cpinfo($aseco, $command) {
	$params = explode(' ', $command['params'], 2);
	$player = $command['author'];
    if ($params[0] == "on" || $params[0] == "enable") {
	$player->cpinfo = 1;
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}CPinfo enabled'), $player->login);
	return;
	}
    if ($params[0] == "off" || $params[0] == "disable") {
	$player->cpinfo = 0;
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}CPinfo disabled'), $player->login);
	return;
	}
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> Usage: /cpinfo on or /cpinfo off'), $player->login);
}

function cpinfo_startup($aseco, $command)
   {
   global $aseco;
   global $cpinfo;

   $cpinfo= new cpinfo_class();
   $cpinfo->Aseco = $aseco;
   $cpinfo->Startup();
   
   } 

function cpinfo_beginround($aseco, $command) {
global $cpinfo, $checkpoints;
$cpinfo->Local1 = $aseco->server->records->getRecord(0,true);
unset($cpinfo->esttime);
}

function cpinfo_checkpoint($aseco, $command) {
global $cpinfo;
global $aseco;
global $checkpoints;

unset($record);
unset($bestrecord);
unset($times);
unset($besttime);
$esttime = array();

$playerid = $command[0];
$login = $command[1];

$time1 = $command[2];
$cp = $command[4];
$temp = $checkpoints[$login];
$record	= $temp->curr_cps;
$bestrecord = $temp->best_cps;
$local1 = $cpinfo->Local1;
$currtime =$record[$cp]; 

$esttime = $cpinfo->esttime[$login];
$esttime[0] = 0;

if ($cp == 0) {
$esttime[1] = $currtime;
}

for ($i = $cp; $i < count($bestrecord); $i++) {
	if ($i == $cp || $i == 0) {
	$esttime[$i] = $bestrecord[$i+1] * $currtime / $bestrecord[$i];
	}
	else {
	$esttime[$i]= $esttime[$i-1] * $bestrecord[$i+1] / $bestrecord[$i];
	}
}
$cpinfo->esttime[$login] = $esttime;
//print_r($esttime);
//print formatTime($esttime[count($esttime)-2]);

if (count($bestrecord) == 0) $est = "\$fff-:--:--";
else
$est = formatTime($esttime[count($esttime)-2]);

$timediff = $record[$cp] - $bestrecord[$cp];
if ( empty($bestrecord[$cp]) )
$time3 = formatTime($time1);
else { 
if ($timediff < 0) { $time3 = "\$00f-".formatTime(abs($timediff)); } else { $time3 = "\$f00+".formatTime(abs($timediff)); } 
}

$bestdiff = $record[$cp] - $local1->checks[$cp];

if (empty($local1->checks[$cp]) )
$best = "\$fff-:--:--";
else {
if ($bestdiff < 0) { $best = "\$00f-".formatTime(abs($bestdiff)); } else { $best = "\$f00+".formatTime(abs($bestdiff)); } 
}


$xml = $cpinfo->getManialink($cp,$time3,$best,$est);   // get manialink code 

	if ($cpinfo->force_show == 'true') { // check if should display for always
	
		$aseco->client->query("SendDisplayManialinkPageToLogin", $login, $xml, (int)$cpinfo->timeout, false); 
	} else {
		$player = $aseco->server->players->getPlayer($login);
		if (isset($player->cpinfo) && $player->cpinfo == 1 ) {
		
		$aseco->client->query("SendDisplayManialinkPageToLogin", $login, $xml, (int)$cpinfo->timeout, false); 
		}
	}
}

function cpinfo_end($aseco, $command) {
global $cpinfo;
$xml = '<?xml version="1.0" encoding="UTF-8"?>';
$xml .= '<manialink id="'.$cpinfo->manialink_id.'"></manialink>';
$aseco->client->query("SendDisplayManialinkPage", $xml, 0, false); 
}

function cpinfo_finish($aseco, $command) {
global $cpinfo;
$login = $command->player->login;
$cpinfo->Local1 = $aseco->server->records->getRecord(0);
/*$xml = '<?xml version="1.0" encoding="UTF-8"?>';
$xml .= '<manialink id="'.$cpinfo->manialink_id.'"></manialink>';
$aseco->client->query("SendDisplayManialinkPageToLogin", $login, $xml, 0, false);  */
}


		
		
class cpinfo_class
{
public $pb_posn_x, $pb_posn_y, $text_color,$manialink_id,$timeout,$force_show;
public $local1_posn_x, $local1_posn_y;
public $Aseco;
public $Records,$trackId,$Local1;
public $esttime;
public $players;

	function Startup() {
		//settings from config-file
		$settings = simplexml_load_file('cpinfo.xml');
	
		$this->pb_posn_x = (int)$settings->personal_best->hx;
		$this->pb_posn_y = (int)$settings->personal_best->vy;
		$this->local1_posn_x = (int)$settings->local1->hx;
		$this->local1_posn_y = (int)$settings->local1->vy;
		$this->text_color = (string)$settings->colors->text_color;
		$this->manialink_id = (int)$settings->manialink_id;
		$this->timeout = (int)$settings->timeout;
		$this->force_show = (string)$settings->enable_for_all;		
	}

	function getManialink($cp, $time2, $local1,$est) {
		$cp = $cp+1;
		$xml = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<manialink id='.$this->manialink_id.'>';
		$xml .= '<frame posn="'.$this->pb_posn_x.' '.$this->pb_posn_y.' 0.3">';
		$xml .= '<label scale="0.8" posn="-9.6 15.5 0.1" halign="center" valign="center" text="$s$'.$this->text_color.'Personal Best"/>';
		$xml .= '<label scale="0.6" posn="-15.6 17.7 0.1" halign="left" valign="center" style="TextRaceChrono" text="$s'.$time2.'"/>';
		$xml .= '</frame>';
		
		$xml .= '<frame posn="'.$this->local1_posn_x.' '.$this->local1_posn_y.' 0.3">';
		$xml .= '<label scale="0.8" posn="-8.6 15.5 0.1" halign="center" valign="center" text="$s$'.$this->text_color.'Local 1"/>';
		$xml .= '<label scale="0.6" posn="-15.6 17.7 0.1" halign="left" valign="center" style="TextRaceChrono" text="$s'.$local1.'"/>';
		$xml .= '</frame>';
		
		$xml .= '<frame posn="0 10 0.3">';
		$xml .= '<label scale="0.8" posn="0 15.5 0.1" halign="center" valign="center" text="$s$'.$this->text_color.'estimated"/>';
		$xml .= '<label scale="0.6" posn="0 17.7 0.1" halign="center" valign="center" style="TextRaceChrono" text="$s'.$est.'"/>';
		
		$xml .= '</frame>';
		
		$xml .= '</manialink>';
		return $xml;
	}
}
?>
