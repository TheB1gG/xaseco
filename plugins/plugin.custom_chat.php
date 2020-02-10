<?php
/**
* Neat chat / Chat filter 0.4
*
* Provides parsing & custom look & filtering to chat
* Created by Reaby
*
*/
Aseco::registerEvent('onStartup',		'cChat_onStartup');
Aseco::registerEvent('onChat',			'cChat_chat');
Aseco::registerEvent('onPlayerConnect',			'cChat_connect');
Aseco::registerEvent('onPlayerDisconnect',		'cChat_disconnect');
Aseco::addChatCommand('ignore','Ignores user from chat');
Aseco::addChatCommand('mute','mute user from chat, only for admins');
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'cchat_events');



function cchat_events($aseco, $answer) {
global $customChat;


	// leave actions outside 2001 - 2200 to other handlers
	if ($answer[2] >= 50001 && $answer[2] <= 50200) {
		// get player
		
		$player = $aseco->server->players->getPlayer($answer[1]);
		$target = $player->playerlist[$answer[2]-50001]['login'];
		
		if (isset($customChat->ignores[$answer[1]][$target])) {
		$customChat->remove_ignore($answer[1], $target);		
		} else {
		$customChat->add_ignore($answer[1], $target);
		}
		$command = array();
		$command['author'] = $player;
		$command['params'] = "";
		chat_ignore($aseco, $command);
	}
	if ($answer[2] >= 60001 && $answer[2] <= 60200) {
		// get player
		
		$player = $aseco->server->players->getPlayer($answer[1]);
		$target = $player->playerlist[$answer[2]-60001]['login'];
		
		if (isset($customChat->mutes[$target])) {
		$customChat->remove_mute($answer[1], $target);		
		} else {
		$customChat->add_mute($answer[1], $target);
		}
		$command = array();
		$command['author'] = $player;
		$command['params'] = "";
		chat_mute($aseco, $command);
	}


}  // event_players



function cChat_onStartup ($aseco, $command) 
{
	$aseco->client->query('ChatEnableManualRouting', true);  // do the trick to disable on game chat :)
	global $customChat;
	$customChat = new customChat();
	$customChat->Aseco = $aseco;	
	$customChat->mutes = array();
	
	$xml_parser = new Examsly();

if ($settings = $xml_parser->parseXml('customchat.xml')) {
      $customChat->settings = array();
      $customChat->settings['BWfilter'] = $settings['SETTINGS']['BADWORDS_FILTER'][0];
   	  $customChat->settings['badwords'] = explode(",",$settings['SETTINGS']['BADWORDS_LIST'][0]);
	  $customChat->settings['badword_replace'] = $settings['SETTINGS']['BADWORD_REPLACE'][0];
	  $customChat->settings['chatFormat'] = $settings['SETTINGS']['CHAT_FORMAT'][0];
	  $customChat->settings['muteTime'] = $settings['SETTINGS']['MUTE_TIME'][0];
   } else {
      // could not parse XML file
      trigger_error('[customchat] Could not read/parse setttings file customchat.xml!', E_USER_WARNING);
      return false;
   }	
   
}

function cChat_connect($aseco, $player) {
	global $customChat;
	$customChat->users[$player->login] = array();
	$customChat->users[$player->login]['login'] =  $player->login;
	$customChat->ignores[$player->login] = array();
}

function cChat_disconnect($aseco, $player) {
	global $customChat;
    unset($customChat->users[$player->login]);
	unset($customChat->ignores[$player->login]);
}


class customChat {
public $player;
public $users;
public $ignores;
public $Aseco;
public $settings;
public $muteSettings;

function add_mute($src,$target) {
	$this->mutes[$target] = $target;
	$message = ">> Admin forces ".$target." to global mute!";
	$this->Aseco->client->query('ChatSendServerMessage', $this->Aseco->formatColors($message));
}

function remove_mute($src, $target) {
	unset($this->mutes[$target]);
	$message = ">> Admin allowes ".$target." now to speak!";
	$this->Aseco->client->query('ChatSendServerMessage', $this->Aseco->formatColors($message));
}

function add_ignore($src, $target) {
	$this->ignores[$src][$target] = $target;
	$message = ">> ".$target." is now at your ignore list and will be muted from chat!";
	$this->Aseco->client->query('ChatSendServerMessageToLogin', $this->Aseco->formatColors($message), $src);
}

function remove_ignore($src, $target) {
	unset($this->ignores[$src][$target]);
	$message = ">> ".$target." is now allowed to speak!";
	$this->Aseco->client->query('ChatSendServerMessageToLogin', $this->Aseco->formatColors($message), $src);
}

function sendchat($message, $from, $player = false) {
	if ( $player !== false ) {
		$this->Aseco->client->query('ChatSendServerMessageToLogin', $message,$player->login);	
	}
	
	else {
		if (!in_array($from,$this->mutes)) {	
			foreach ($this->users as $data) {
				if (!in_array($from,$this->ignores[$data['login']]) ) {
					$this->Aseco->client->query('ChatSendServerMessageToLogin', $message, $data['login']);
				} 
			}
		}
	
	}
}

}


function cChat_chat($aseco, $command)
	{
	global $customChat;

	if ( $command[0] != $aseco->server->login)  // check if chat text is really from player, not from server.
		{
			$player = $aseco->server->players->getPlayer($command[1]);
			$nick = $player->nickname;   // assign nickname from data. 
			$login = $command[1];
			$chat = $command[2];  // we want to process chat text later, so save it to temp variable
				
			if ($command != '' && substr($command[2], 0, 1) != '/' )  // check is command is ment to be server releated
			{

		if (strtolower($customChat->settings['BWfilter']) == "true") {
					foreach ($customChat->settings['badwords'] as $words) // find badwords
					{
					$chat = str_ireplace(trim($words), $customChat->settings['badword_replace'],$chat); // and replace them with better word
					}
				} 

		$chat = str_replace('"',"''",$chat);
		
		$replace['nick'] = $nick;
		$replace['chat'] = $chat;
				
		$message = $customChat->settings['chatFormat'];
		
		foreach ($replace as $key => $value) {
			$message = str_replace('{#'.strtolower($key).'}', $value, $message);
		}
	 
	    	$customChat->sendchat($message,$command[1]);
		
			}
			else 
			{
			$customChat->player = $command[1];
			return; // return if command is server releated, else it will get executed twice.
			}
		
		} else   // and server messages are shown
		
		{
		$message = $command[2];
		$customChat->sendchat($message,$command[1],$customChat->player);
		}
	
}



?>