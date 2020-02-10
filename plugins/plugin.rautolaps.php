<?php
//automatic adjustment of track's laps
// ©2011-2012 RamCUP2000
// version 1.4

Aseco::registerEvent('onStartup','r_autolaps_startup');
Aseco::registerEvent('onMenuLoaded', 'r_autolaps_menu');
Aseco::registerEvent('onEndRace1', 'r_autolaps');
Aseco::registerEvent('onEndMap1', 'r_autolaps');
Aseco::addChatCommand('rautolaps', 'automatic setting of laps');

global $rautolaps;

function r_autolaps_startup($aseco){
global $rautolaps;

  $rautolaps = array();  
  $rautolaps['akt'] = 0;
  if($aseco->client->query('GetNbLaps')){ $nblaps = $aseco->client->getResponse();}
  $rautolaps['def'] = $nblaps['CurrentValue'];
  
  $aseco->plugin_versions[] = array('plugin'=>'plugin.rautolaps.php','author'=>'RamCUP2000','version'=>'1.4');
}

function r_autolaps_menu($aseco, $menu){
  $menu->addEntry('adminmenu', 'mainhelpsep', false, 'R-AutoLaps', 'ralapsmenu');
  $menu->addEntry('ralapsmenu', '', true, 'Activate: 5 min', 'ralapsmenu-on', '/rautolaps on');
  $menu->addEntry('ralapsmenu', '', true, 'Activate: 10 min', 'ralapsmenu-on-10', '/rautolaps on 10');
  $menu->addEntry('ralapsmenu', '', true, 'Activate: 15 min', 'ralapsmenu-on-15', '/rautolaps on 15');
  $menu->addEntry('ralapsmenu', '', true, 'Activate: 20 min', 'ralapsmenu-on-20', '/rautolaps on 20');
  $menu->addEntry('ralapsmenu', '', true, 'Activate: 30 min', 'ralapsmenu-on-30', '/rautolaps on 30');
  $menu->addEntry('ralapsmenu', '', true, 'Deactivate: init laps', 'ralapsmenu-off', '/rautolaps off');
  $menu->addEntry('ralapsmenu', '', true, 'Deactivate: 10 laps', 'ralapsmenu-off-10', '/rautolaps off 10');
}

function r_autolaps($aseco){
global $rautolaps, $jukebox;

if($rautolaps['akt'] == 1){
 if((($aseco->server->game == 'TmForever') && ($aseco->server->gameinfo->mode == 3)) || 
 (($aseco->server->game == 'ManiaPlanet') && ($aseco->server->gameinfo->mode == 4))){
 
		  if(count($jukebox) > 0){
			  foreach($jukebox as $track){
				$tratinfo = $track;
				break;}
		  }
    	if($tratinfo != false){ $filename = $tratinfo['FileName'];}
      else{
      if($aseco->server->game == 'ManiaPlanet'){
      if($aseco->client->query('GetNextMapInfo')){ $tratinfo = $aseco->client->getResponse();}
        $filename = $tratinfo['FileName'];}
      else{
      if($aseco->client->query('GetNextChallengeInfo')){ $tratinfo = $aseco->client->getResponse();}
        $filename = $tratinfo['FileName'];}
      }
     if($aseco->server->game == 'ManiaPlanet'){
     $gbx = new GBXChallengeFetcher($aseco->server->mapdir.$filename, false);}
     else{
     $gbx = new GBXChallengeFetcher($aseco->server->trackdir.$filename, false);}
     if($gbx->nblaps <= 1){ $gbx->nblaps = 1;}     
     $caskola = $gbx->authortm / $gbx->nblaps;
     $pocetkol = round(($rautolaps['cas'] * 60) / ($caskola / 1000));
     
     if($pocetkol == 0){
     $chat = '$i$0f0<R-AutoLaps> $08ferror in map ! Skipping...';
     $aseco->client->query('ChatSendServerMessage', $chat);}
     else{
     $chat = '$i$0f0<R-AutoLaps> $08fnext track\'s 1 lap is $fff'.formatTime($caskola).' $08fwill be set $fff'.$pocetkol.' lap(s) ~'.$rautolaps['cas'].' min $08f('.$rautolaps['aut'].')';
     $aseco->client->query('ChatSendServerMessage', $chat);
     
     $aseco->client->query('SetNbLaps', (int)$pocetkol);
     }
  }
 else{ 
  $chat = '$i$0f0<R-AutoLaps> $08fserver isn\'t in laps mode ! Skipping...';
  $aseco->client->query('ChatSendServerMessage', $chat);
  }
 }
}


function chat_rautolaps($aseco, $command){
global $rautolaps;
$command['params'] = explode(' ', $command['params']);
$autor = $command['author'];

if($aseco->isMasterAdmin($autor) || $aseco->isAdmin($autor)){
  if(($command['params'][0] == 'on') && ($rautolaps['akt'] == 0)){
    $rautolaps['akt'] = 1;
    if($command['params'][1] != null){
      $rautolaps['cas'] = (int)$command['params'][1];
    }
    else{
      $rautolaps['cas'] = 5;
    }
  $rautolaps['aut'] = stripColors($autor->nickname);
  $chat = '$i$0f0<R-AutoLaps> $fffACTIVATED$08f will be set $fff~'.$rautolaps['cas'].' min $08f('.stripColors($autor->nickname).')';
  $aseco->client->query('ChatSendServerMessage', $chat);
 }
  elseif(($command['params'][0] == 'off') && ($rautolaps['akt'] == 1)){
    $rautolaps['akt'] = 0;
    if($command['params'][1] != null){
      $pocetkol = $command['params'][1];
    }
    else{ $pocetkol = $rautolaps['def'];
    }
    $chat = '$i$0f0<R-AutoLaps> $fffDISABLED$08f will always set $fff'.$pocetkol.' lap(s) $08f('.stripColors($autor->nickname).')';
    $aseco->client->query('ChatSendServerMessage', $chat);
    $aseco->client->query('SetNbLaps', (int)$pocetkol);
  }
  else{
    $chat = '$i$0f0<R-AutoLaps> $08ffor activate this feature, use: $fff/rautolaps on [minutes] $08ffor disable this function, use: $fff/rautolaps off [number of laps]';
    $aseco->client->query('ChatSendServerMessageToLogin', $chat, $autor->login);
  }
}
else{
  $chat = '$i$0f0<R-AutoLaps> $08ffor this function you do not have the necessary rights';
  $aseco->client->query('ChatSendServerMessageToLogin', $chat, $autor->login);
}  
}
?>