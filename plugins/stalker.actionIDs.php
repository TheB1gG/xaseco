<?php

// XAseco plugin to assign an ID to specific action
// By STALKER-SVK
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'StalkerAction');
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'StalkerAction2');
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'StalkerAction3');
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'StalkerAction4');

//global variables
global $chatcommand;
global $chatcommand2;
global $chatcommand3;
global $chatcommand4;

//command binding
$chatcommand = '/st chatall';
$chatcommand2 = '/jfreu players live';
$chatcommand3 = '/music list';
$chatcommand4 = '/list';

function StalkerAction($aseco, $command) {
   global $chatcommand;
   $playerid = $command[0];
   $login = $command[1];
   $action = $command[2].'';
   
   if ($action == '27008505'){
      $chat = array();
      $chat[0] = $playerid;
      $chat[1] = $login;
      $chat[2] = $chatcommand;
      $chat[3] = true;
      $aseco->playerChat($chat);
   }
}

function StalkerAction2($aseco2, $command2) {
   global $chatcommand2;
   $playerid2 = $command2[0];
   $login2 = $command2[1];
   $action2 = $command2[2].'';
   
   if ($action2 == '270085052'){
      $chat2 = array();
      $chat2[0] = $playerid2;
      $chat2[1] = $login2;
      $chat2[2] = $chatcommand2;
      $chat2[3] = true;
      $aseco2->playerChat($chat2);
   }
}

function StalkerAction3($aseco3, $command3) {
   global $chatcommand3;
   $playerid3 = $command3[0];
   $login3 = $command3[1];
   $action3 = $command3[2].'';
   
   if ($action3 == '270085053'){
      $chat3 = array();
      $chat3[0] = $playerid3;
      $chat3[1] = $login3;
      $chat3[2] = $chatcommand3;
      $chat3[3] = true;
      $aseco3->playerChat($chat3);
   }
}

function StalkerAction4($aseco4, $command4) {
   global $chatcommand4;
   $playerid4 = $command4[0];
   $login4 = $command4[1];
   $action4 = $command4[2].'';
   
   if ($action4 == '270085054'){
      $chat4 = array();
      $chat4[0] = $playerid4;
      $chat4[1] = $login4;
      $chat4[2] = $chatcommand4;
      $chat4[3] = true;
      $aseco4->playerChat($chat4);
   }
}

?>