<?php

/**
 * AutoQueue Plugin for (X)ASECO/FAST by oorf-fuckfish
 * Version 0.75 (XAseco 1.04, FAST 3.2.x, ASECO 2.2.0c)
 * 
 * updated by schmidi to fit latest server-release (2009-05-25)
 *
 * used manialink ids: 3841000, 3842000
 * used action ids: 3841001, 3841002, 3842001
 * 
 * This plugin is an automatic management system for chronically overpopulated servers.
 * It creates a spectator queue to let those players join first who came first.
 * Every spectator will be put into forced spectator mode, so constantly hitting "Enter" won't accomplish anything.
 * If a spot on the server opens, the first one who came will automatically exit spectator mode and enter the driving.
 * After that the second one who joined... and so on...
 * 
 * If a player choses to put himself into spectator mode to make a break from driving, he'll be put into the "Unqueue" mode.
 * That means, to rejoin the game he'll have to reenter the queue by typing "/queue".
 * 
 * A player in the queue can exit the queue and be a normal spectator by typing "/unqueue".
 * 
 * You can display a self-updating list of players in the queue by typing /queuelist
 * 
 * --------------------
 * The kick functions
 * --------------------
 * I know, Xaseco already has an Idlekicker, but I kinda wanted a time based one and since it matches this plugin
 * I implemented it. Therefore inside the config file you can configure your idle kicking preferences. (kick admins,
 * idle time, idle time for spectators,...). You can of course deactivate the kicking.
 * 
 * Another thing I implemented is a Kick-Worst function. That means after each race the plugin checks if there are players
 * in the queue and if this is the case, the plugin kicks the x worst players from the last race. This is still experimental
 * since I don't know how it reacts in some Game Modes and especially I did not try WarmUp.
 */

global $autoqueueVersion;
$autoqueueVersion = '0.75a';

if (!class_exists('Aseco')){
	if (!defined('IN_FAST')) define('IN_FAST', true);
	if (!defined('IN_XASECO')) define('IN_XASECO', false);
	if (!defined('IN_ASECO')) define('IN_ASECO', false);
} else {
	if (defined('XASECO_VERSION')){
		if (!defined('IN_XASECO')) define('IN_XASECO', true);
		if (!defined('IN_ASECO')) define('IN_ASECO', false);
	} else {
		if (!defined('IN_ASECO')) define('IN_ASECO', true);
		if (!defined('IN_XASECO')) define('IN_XASECO', false);
	}
	if (!defined('IN_FAST')) define('IN_FAST', false);
}

if (!IN_ASECO){
	//dummy plugin class for XAseco/FAST
	if (!class_exists('Plugin')){
		class Plugin{
			var $author, $version, $description, $Aseco, $dependencies;
			function setAuthor($auth){
				$this->author = $auth;
			}
			function setVersion($version){
				$this->version = $version;
			}
			function setDescription($description){
				$this->description = $description;
			}
			function addDependence($plugin_name, $id_variable){
				if (!$this->dependencies) $this->dependencies = array();
				$this->dependencies[$id_variable] = $plugin_name;
			}
			function checkDependencies(){
				if (!$this->dependencies) return;
				foreach ($this->dependencies as $id_variable => $plugin_name) {
					$checkFor = null;
					eval('global $'.$id_variable.'; $checkFor = $'.$id_variable.';');
					if (!$checkFor){
						$this->Aseco->console('['.get_class($this).'] Unmet Dependency! With your current configuration you need to activate "'.$plugin_name.'" to run this plugin!');
						die();
					}
				}
			}
		}
	}
}

//dummy helper class
class PlayObj{
	var $login;
}

/**
 * The main class for the queue
 *
 */
class AutoQueue extends Plugin {

	var $debug;
	
	private $active;
	private $idletime;
	private $specIdletime;
	private $kickadmins;
	private $kickworst;
	private $strings;
	private $queue;
	private $serverlimit;
	private $serverlogin;
	var $Aseco;
	private $time;
	private $kickInterval;
	private $lastKickTime;
	private $isWarmup;
	private $warmupTime;
	private $buttonxml;
	private $useManialinks;
	private $queueaction, $unqueueaction, $closelistaction;
	private $checkActive;
	private $firstBeginRace;
	private $listconfig;
	private $listCustomers;
	private $kickToSpec;

	private $maxplayers, $maxspecs;

	private $playerList;

	function init($configFile){
		$this->loadSettings($configFile);
	}

	/**
	 * Load the settings from the config file
	 *
	 * @param String $configFile
	 */
	function loadSettings($configFile){
		$xml = simplexml_load_file($configFile);
		$this->listCustomers = array();
		$this->active = $this->stringToBool(strval($xml->active));
		$this->idletime = intval($xml->idletime);
		$this->kickworst = intval($xml->kickworst);
		$this->strings = array();
		$this->strings['queuepos'] = strval($xml->str_queuepos);
		$this->strings['unqueue'] = strval($xml->str_unqueue);
		$this->strings['specmode'] = strval($xml->str_specmode);
		$this->strings['drive'] = strval($xml->str_drive);
		$this->strings['ml_queuepos'] = strval($xml->str_ml_queuepos);
		$this->strings['ml_specmode'] = strval($xml->str_ml_specmode);
		$this->strings['ml_drive'] = strval($xml->str_ml_drive);
		$this->strings['kickspec'] = strval($xml->str_kick_spec);
		$this->strings['kickidle'] = strval($xml->str_kick_idle);
		$this->strings['kickworst'] = strval($xml->str_kick_worst);
		$this->strings['kicktospec'] = strval($xml->str_kick_tospec);
		$this->strings['forbidden'] = strval($xml->str_forbidden);
		$this->specIdletime = intval($xml->unqueued_spec_idletime);
		$this->useManialinks  = $this->stringToBool(strval($xml->use_manialinks));
		$this->kickToSpec = $this->stringToBool(strval($xml->kicktospec));
		$this->kickadmins = $this->stringToBool(strval($xml->kickadmins));
		$this->kickInterval = intval($xml->kickinterval);

		$this->listconfig = array();
		$this->listconfig['autoshow'] = $this->stringToBool($xml->listconfig->autoshow);
		$this->listconfig['title'] = strval($xml->listconfig->title);
		$this->listconfig['style'] = strval($xml->listconfig->style);
		$this->listconfig['substyle'] = strval($xml->listconfig->substyle);
		$this->listconfig['hlstyle'] = strval($xml->listconfig->highlitestyle);
		$this->listconfig['hlsubstyle'] = strval($xml->listconfig->highlitesubstyle);
		$this->listconfig['posx'] = floatval($xml->listconfig->posx);
		$this->listconfig['posy'] = floatval($xml->listconfig->posy);
		$this->listconfig['width'] = floatval($xml->listconfig->maxwidth);
		$this->listconfig['rows'] = intval($xml->listconfig->rows);
		$this->listconfig['cols'] = intval($xml->listconfig->maxcols);
		$this->listconfig['scale'] = floatval($xml->listconfig->scale);


		$this->playerList = array();
		$this->queue = array();
		$this->time = time();
		$this->lastKickTime = time();
		$this->buttonxml = '<?xml version="1.0" encoding="UTF-8"?><manialinks><manialink id="3841000">'.strval($xml->buttonxml->asXML()).'</manialink></manialinks>';
		$this->buttonxml = str_replace(array('<buttonxml', '</buttonxml>'), '', $this->buttonxml);
		$this->queueaction = 3841001;
		$this->unqueueaction = 3841002;
		$this->closelistaction = 3842001;
		$this->warmupTime = time();
		$this->checkActive = true;
		$this->firstBeginRace = false;
	}

	/**
	 * Update the ManiaLink for a specific login
	 *
	 * @param String $login
	 * @param String $text
	 * @param String $action
	 * @param int $timeout
	 */
	function updateML($login, $text, $action='', $timeout=0){
		global $autoqueueVersion;
		$xml  = str_replace(array('%text%', '%version%', '%action%'), array($text, $autoqueueVersion, $action), $this->buttonxml);
		$this->addCall("SendDisplayManialinkPageToLogin", array($login, $xml, $timeout, false));
	}

	function handleClick($command){
		$login = $command[1];
		$action = $command[2];
		$this->updateIdle($login);
		if ($action==$this->queueaction){
			$this->doQueue($login);
		} else if ($action==$this->unqueueaction){
			$this->doUnqueue($login);
		} else if ($action==$this->closelistaction){
			$this->hideList($login);
		}
	}

	function loadServerLimit(){
		$res = $this->query('GetLadderServerLimits');
		$this->serverlimit = $res['LadderServerLimitMin'];
	}

	/**
	 * Converts a string to a boolean
	 *
	 * @param String $string
	 * @return boolean
	 */
	function stringToBool($string){
		if (strtoupper($string)=="FALSE" || $string=="0" || $string=="") return false;
		return true;
	}

	/**
	 * Converts a boolean to a string
	 *
	 * @param boolean $bool
	 * @return string
	 */
	function boolToString($bool){
		return $bool ? "true" : "false";
	}

	/**
	 * Little helper function for chatsendservermessage
	 *
	 * @param String $message
	 * @param String $login
	 */
	function sendChatLine($message, $login=''){
		if ($login){
			$this->addCall('ChatSendServerMessageToLogin', array('$ff0[AutoQueue] $fff'.$message, $login));

		} else {
			$this->addCall('ChatSendServerMessage', array('$ff0[AutoQueue] $fff'.$message));
		}
	}

	/**
	 * Query wrapper
	 *
	 * @return response
	 */
	function query(){
		$args = func_get_args();
		if (IN_FAST){
			global $_client;
			call_user_method_array('query', $_client, $args);
			$result = $_client->getResponse();
		} else {
			call_user_method_array('query', $this->Aseco->client, $args);
			$result = $this->Aseco->client->getResponse();
		}
		return $result;
	}

	/**
	 * Enters a new player into the internally used playerlist / queue
	 *
	 * @param Player $player
	 */
	function playerConnect($player){
		if (IN_FAST) $login = $player; else $login = $player->login;
		$info = $this->query('GetPlayerInfo', $login, 1);
		$info['lastActive']	= time();
		$info['joined'] = time();
		$info['finishedRaces'] = 0;
		$info['isSpectator'] = $info['SpectatorStatus'] % 2 == 1;
		$info['ForcedSpectator'] = $info['Flags']%10;
		$info['Unqueue'] = false;
		$ladderstats = $this->query('GetDetailedPlayerInfo', $login);
		$ladderpoints = $ladderstats['LadderStats']['PlayerRankings'][0]['Score'];
		$info['LadderPoints'] = $ladderpoints;
		if ($ladderpoints>=$this->serverlimit){
			$info['Allowed'] = true;
		} else {
			$info['Allowed'] = false;
			$this->addCall("SendDisplayManialinkPageToLogin", array($login, '<?xml version="1.0" encoding="UTF-8"?><manialinks><manialink id="3841000"> </manialink></manialinks>', 0, false));
		}

		//check for relay
		$isserver = (($info['Flags'] / 100000) % 10) ? 1 : 0;
		$isrelay   = ($isserver && $this->serverlogin != $info['Login']) ? 1 : 0;
		$isReferee = (($info['Flags'] / 10) % 10) ? 1 : 0;
		$info['isReferee'] = $isReferee;
		$info['isRelay'] = $isrelay;
		if ($isrelay || $isReferee) $info['Allowed'] = false;

		unset ($info['Flags']);
		unset ($info['TeamId']);
		unset ($info['LadderRanking']);
		unset ($info['SpectatorStatus']);
		$this->playerList[$login] = $info;
		//if ($info['isSpectator']){
		$this->forceSpec($login, 1);
		//add to queue
		if ($info['Allowed']){
			$this->addToQueue($login);
			$this->notifyQueue($login, count($this->queue));
		} else {
			$info['Unqueue']=true;
		}
		//}
	}

	/**
	 * Notify the enqueued persons about their current position in the queue,
	 * login='' => notify all,
	 * if the login is set, you'll also have to give the position.
	 * If you want to just notify the players above a specific index, specify $startpos as the index in the queue
	 *
	 * @param String $login
	 * @param int $pos
	 * @param int $startpos
	 */
	function notifyQueue($login='', $pos=0, $startpos=0){
		if ($login!=''){
			if ($this->useManialinks){
				$msg = str_replace('%queuepos%', $pos, $this->strings['ml_queuepos']);
				$this->updateML($login, $msg, $this->unqueueaction);
			} else {
				$this->sendChatLine(str_replace('%queuepos%', $pos, $this->strings['queuepos']), $login);
			}
		} else {
			for($i=0; $i<count($this->queue); $i++){
				if ($i >= $startpos){
					if ($this->useManialinks){
						$msg = str_replace('%queuepos%', $i+1, $this->strings['ml_queuepos']);
						$this->updateML($this->queue[$i], $msg, $this->unqueueaction);
					} else {
						$this->sendChatLine(str_replace('%queuepos%', $i+1, $this->strings['queuepos']), $this->queue[$i]);
					}
				}
			}
		}
		foreach ($this->listCustomers as $login=>$isCustomer){
			if ($isCustomer){
				$this->showQueueList($login);
			}
		}
	}

	/**
	 * Returns the index of a player in the queue
	 *
	 * @param String $login
	 * @return int
	 */
	function getQueuePos($login){
		for($i=0; $i<count($this->queue); $i++){
			if ($this->queue[$i] == $login) return $i;
		}
		return -1;
	}

	function resetLast(){
		$info = end($this->playerList);
		while ($info !== false && $info['isSpectator']) {
			$info = prev($this->playerList);
		}
		
		if ($info !== false) {
			$login = key($this->playerList);
			$info = &$this->playerList[$login];
			$this->forceSpec($login, 1);
			$info['isSpectator']=true;
			//add to queue
			if ($info['Allowed']) {
				$info['Unqueue']=false;
				$this->addToQueue($login);
				$this->notifyQueue($login, count($this->queue));
				$this->console('[AutoQueue] Too much players, putting "'.$login.'" to spec.');
			} 
			else {
				$info['Unqueue']=true;
			}
		}
	}

	/**
	 * Release the first player out of the queue and let him play
	 *
	 */
	function releaseFirst(){
		$login = $this->queue[0];
		$this->forceSpec($login, 2);
		$this->forceSpec($login, 0);
		$this->removeFromQueue($login);
		$this->playerList[$login]['isSpectator']=false;
		$this->playerList[$login]['finishedRaces']=0;
		$this->playerList[$login]['joined']=time();
		$this->updateIdle($login);
		if ($this->useManialinks){
			$this->updateML($login, $this->strings['ml_drive'], '', 3500);
		} else {
			$this->sendChatLine($this->strings['drive'], $login);
		}
		$this->hideList($login);
		$this->console('[AutoQueue] Released first player from queue: '.$login);
	}

	/**
	 * Add a new player into the queue, if the player is already inside the queue, this will only return his position
	 *
	 * @param String $login
	 * @return position of the player in the queue
	 */
	function addToQueue($login){
		for ($i=0; $i< count ($this->queue); $i++){
			if ($this->queue[$i] == $login) return $i+1;
		}
		$this->queue[] = $login;
		if ($this->listconfig['autoshow']) $this->listCustomers[$login] = true;
		return (count($this->queue));
	}

	/**
	 * Shows a list of the queue contents to a specific login
	 */
	function showQueueList($login, $hide = false){
		$xml = $this->getQueueXML($hide);
		$this->addCall("SendDisplayManialinkPageToLogin", array($login, $xml, 0, false));
	}

	/**
	 * Returns a valid UTF String and replaces faulty byte values with a given string
	 * Thanks a lot to Slig for his original tm_substring function.
	 *
	 * @param String $str
	 * @param String $replaceInvalidWith
	 * @return String
	 */
	function getValidUTF8String($str, $replaceInvalidWith = ''){
		$s = strlen($str); // byte string length
		$pos = 0; // current byte pos in string
		$newStr = '';

		while($pos < $s){
			$c = $str[$pos];
			$co = ord($c);

			if($co >= 240 && $co <248){ // 4 bytes utf8 => 11110xxx 10xxxxxx 10xxxxxx 10xxxxxx
				if(($pos+3 < $s ) &&
				(ord($str[$pos+1]) >= 128) && (ord($str[$pos+1]) < 192) &&
				(ord($str[$pos+2]) >= 128) && (ord($str[$pos+2]) < 192) &&
				(ord($str[$pos+3]) >= 128) && (ord($str[$pos+3]) < 192)){
					// ok, it was 1 character, increase counters
					$newStr.=substr($str, $pos, 4);
					$pos += 4;
				}else{
					// bad multibyte char
					$newStr.= $replaceInvalidWith;
					$pos++;
				}

			}elseif($co >= 224){ // 3 bytes utf8 => 1110xxxx 10xxxxxx 10xxxxxx
				if(($pos+2 < $s ) &&
				(ord($str[$pos+1]) >= 128) && (ord($str[$pos+1]) < 192) &&
				(ord($str[$pos+2]) >= 128) && (ord($str[$pos+2]) < 192)){
					// ok, it was 1 character, increase counters
					$newStr.=substr($str, $pos, 3);
					$pos += 3;
				}else{
					// bad multibyte char
					$newStr.= $replaceInvalidWith;
					$pos++;
				}

			}elseif($co >= 192){ // 2 bytes utf8 => 110xxxxx 10xxxxxx
				if(($pos+1 < $s ) &&
				(ord($str[$pos+1]) >= 128) && (ord($str[$pos+1]) < 192)){
					$newStr.=substr($str, $pos, 2);
					$pos += 2;
				}else{
					// bad multibyte char
					$newStr.=$replaceInvalidWith;
					$pos++;
				}

			}else{
				// ascii char or erroneus middle multibyte char
				if($co >=128)
				$newStr.=$replaceInvalidWith;
				else
				$newStr.=$str[$pos];

				$pos++;

			}
		}
		return $newStr;
	}


	function hideList($login){
		$this->listCustomers[$login] = false;
		$this->showQueueList($login, true);
	}

	function getQueueXML($hide=false){
		$queue = $this->queue;
		
		$cols = min(ceil(count($queue)/$this->listconfig['rows']), $this->listconfig['cols']);
		if ($cols==0) $cols = 1;
		$width = (($this->listconfig['width']-1) / $this->listconfig['cols'] * $cols)+1;

		$xml = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml.= '<manialinks><manialink id ="3842000">';

		if (!$hide){

			$xml.= '<frame posn="'.$this->listconfig['posx'].' '.$this->listconfig['posy'].' 10" scale="'.$this->listconfig['scale'].'">';
			$xml.= '<quad sizen="1.5 1.5" halign="center" valign="center" posn="'.($width-1.5).' -1.5 0.003" style="Icons64x64_1" substyle="Close" action="3842001"/>';

			$xml.= '<format textsize="1"/>';
			//bg
			$xml.= '<quad posn="0 0 0.001" sizen="'.$width.' '.($this->listconfig['rows']*2+3).'" style="'.$this->listconfig['style'].'" substyle="'.$this->listconfig['substyle'].'"/>';
			$xml.= '<label posn="1 -0.5 0.002" sizen="10 2" text="$o'.$this->listconfig['title'].'"/>';
			$usablewidth = $width-1;
			$left=0.5;
			$gap=0.3;
			$colwidth = ($usablewidth - ($cols -1)*$gap) / $cols;
			$i = 0;
			for ($col = 0; $col<$cols; $col++){
				for ($row = 0; $row<$this->listconfig['rows']; $row++){
					if (isset($queue[$i])){
						$nick=$this->playerList[$queue[$i]]['NickName'];
						
						$nick=htmlspecialchars(str_replace(array('$O', '$o', '$i', '$I', '$w', '$W'), '', $this->getValidUTF8String($nick)));
						$xml.= '<quad posn="'.$left.' '.(-2.5 - $row*2).' 0.002" sizen="2 1.8" style="'.$this->listconfig['hlstyle'].'" substyle="'.$this->listconfig['hlsubstyle'].'"/>';
						$xml.= '<quad posn="'.($left+2.2).' '.(-2.5 - $row*2).' 0.002" sizen="'.($colwidth-2.2).' 1.8" style="'.$this->listconfig['hlstyle'].'" substyle="'.$this->listconfig['hlsubstyle'].'"/>';
						$xml.= '<label posn="'.($left+1.7).' '.(-2.65 - $row*2).' 0.003" halign="right" text="'.($i+1).'"/>';
						$xml.= '<label sizen="'.($colwidth-3).' 2" posn="'.($left+2.5).' '.(-2.65 - $row*2).' 0.003" text="'.$nick.'"/>';
					} else {
						$xml.= '<quad posn="'.$left.' '.(-2.5 - $row*2).' 0.002" sizen="'.$colwidth.' '.(($this->listconfig['rows']-$row)*2).'" style="'.$this->listconfig['hlstyle'].'" substyle="'.$this->listconfig['hlsubstyle'].'"/>';
						$i+=$this->listconfig['rows']-$row;
						break 1;
					}
					$i++;
				}
				$left +=$colwidth+$gap;
			}

			$xml.= '</frame>';
		}
		$xml.= '</manialink></manialinks>';
		return ($xml);


	}

	/**
	 * Remove a player from the queue, returns true, if the player was in the queue, false, if he was not in there
	 *
	 * @param String $login
	 * @return boolean
	 */
	function removeFromQueue($login){
		$newQueue = array();
		for ($i=0; $i< count ($this->queue); $i++){
			if ($this->queue[$i] != $login){
				$newQueue[] = $this->queue[$i];
			}
		}
		$result = (count($newQueue) != count($this->queue));
		$this->queue = $newQueue;
		return $result;
	}

	/**
	 * Needed, if a player choses to put himself into spectator mode
	 *
	 * @param array $info
	 */
	function playerInfoChanged($info){
		$isSpectator = $info['SpectatorStatus'] % 2 == 1;
		$forcedSpectator = $info['Flags']%10;
		$isReferee = (($info['Flags'] / 10) % 10) ? 1 : 0;
		$hasPlayerSlot = (($info['Flags'] / 1000000) % 10) ? 1 : 0;
		
		$login = $info['Login'];
		$plr = &$this->playerList[$login];
		
		if ($plr['isReferee'] != $isReferee){
			$this->resetPlayer($login);
			return;
		}

		if ($isSpectator && $hasPlayerSlot) {
			$this->addCall('SpectatorReleasePlayerSlot', array($login));
			if ($this->debug) $this->console("[AutoQueue] SpectatorReleasePlayerSlot: $login\n");
		}
			

		if ($plr['isSpectator'] != $isSpectator || $plr['ForcedSpectator'] != $forcedSpectator ){
			$plr['isSpectator'] = $isSpectator;
			$plr['ForcedSpectator'] = $forcedSpectator;
			if ($plr['isSpectator'] && $plr['ForcedSpectator'] == 0){
				//spectator by choice
				$plr['Unqueue'] = true;
				$this->forceSpec($login, 1);
				if ($this->useManialinks){
					$this->updateML($login, $this->strings['ml_specmode'], $this->queueaction);
				} else {
					$this->sendChatLine($this->strings['specmode'], $login);
				}
				//$this->everySecond();
			}
		}


	}

	/**
	 * Removes a player from the internally used lists
	 *
	 * @param Player $player
	 */
	function playerDisconnect($player){
		if (IN_FAST) $login = $player; else $login = $player->login;
		unset($this->playerList[$login]);
		unset($this->listCustomers[$login]);
		$idx = $this->getQueuePos($login);
		if ($this->removeFromQueue($login)){
			$this->notifyQueue('', 0, $idx);
		} else {
			//$this->everySecond();
		}
	}

	/**
	 * Wrapper for ForceSpectator call
	 *
	 * @param unknown_type $login
	 * @param unknown_type $mode
	 */
	function forceSpec($login, $mode){
		if (IN_FAST){
			addCall(null,'ForceSpectator', $login, $mode);
		} else {
			$this->query('ForceSpectator', $login, $mode);
		}
	}

	function startUp(){
		$this->updateMaxPlayers();
		$this->loadServerLimit();
		$serverinfo = $this->query('GetMainServerPlayerInfo');
		$this->serverlogin = $serverinfo['Login'];
	}

	function updateMaxPlayers(){
		$this->maxplayers = $this->query('GetMaxPlayers');
		$this->maxspecs = $this->query('GetMaxSpectators');
		$this->maxplayers = $this->maxplayers['CurrentValue'];
		$this->maxspecs = $this->maxspecs['CurrentValue'];
	}

	/**
	 * Checks for free spots and releases queued players, if possible
	 * Also needed for the timebased idlekicker
	 *
	 */
	function everySecond(){

		$this->time = time();

		if ($this->checkActive){
			if ($this->time%10 == 0) $this->updateMaxPlayers();

			$maxplayers = $this->maxplayers;
			$maxspecs = $this->maxspecs;

			$actualplayers = 0;
			foreach ($this->playerList as $login => &$info) {
				if (!$info['isSpectator']) $actualplayers++;
			}

			$freespots = $maxplayers - $actualplayers;
			if ($this->debug && $this->time%10 == 0) $this->console("[AutoQueue] free: $freespots max: $maxplayers actual: $actualplayers\n");

			if ($freespots > 0 && count($this->queue) > 0) {
				//for ($i=0; $i<min($freespots, count($this->queue)); $i++){
				$this->releaseFirst();
				//}
				$this->notifyQueue();
			} 
			else if ($freespots < 0) {
				//$this->console("[AutoQueue] resetLast()\n");
				//$this->resetLast();
				//if ($this->debug) $this->console("[AutoQueue] everySecond: resetLast()\n");
			}
		}

		//Idlekicker (only if activated and not in Warmup)

		if ($this->kickInterval && !$this->isWarmup) {
			if ($this->lastKickTime + $this->kickInterval < $this->time) {

				//check for idle players
				foreach ($this->playerList as $login => &$info) {

					if ($info['isSpectator'] && $info['Unqueue']) {
						//specs that are not in the queue
						if ($this->specIdletime && (($info['lastActive'] + $this->specIdletime) < $this->time)) {
							$this->doIdleKick($login, $this->specIdletime, 'Kicked unqueued spectator: ', $this->strings['kickspec']);
						}
					} 
					else if (!$info['isSpectator']) {
						if ($this->idletime && (($info['lastActive'] + $this->idletime) < $this->time)) {
							$this->doIdleKick($login, $this->idletime, 'Kicked idle player: ', $this->strings['kickidle']);
						}
					}
				}
				$this->lastKickTime = $this->time;
			}
		}
	}


	//reset a player
	function resetPlayer($login){
		$playObj = null;
		if (IN_FAST) $playObj = $login;
		else {
			$playObj = new PlayObj();
			$playObj->login = $login;
		}
		if ($this->debug) $this->console('[AutoQueue] Resetting invalid player: '.$login);
		$this->playerDisconnect($playObj);
		$this->playerConnect($playObj);
	}

	function isAdmin($login){
		if (IN_XASECO){
			$playerObj = $this->Aseco->server->players->getPlayer($login);
			return $this->Aseco->isAnyAdmin($playerObj);
		} else if (IN_FAST){
			return verifyAdmin($login);
		} else {
			return $this->Aseco->isAdmin($login);
		}
		return false;
	}

	function beginRound(){
		$this->isWarmup = $this->query('GetWarmUp');
		if ($this->debug) $this->console("[AutoQueue] beginRound: isWarmup=" . $this->boolToString($this->isWarmup) . "\n");

		if ($this->firstBeginRace && !$this->isWarmup) {
			$this->firstBeginRace=false;
			//increment finishedRaces count
			foreach ($this->playerList as &$player) {
				if (!$player['isSpectator']) {
					$player['finishedRaces']++;
				}
			}
		}
	}

	function beginRace(){
		$isWarmup = $this->query('GetWarmUp');
		if ($this->debug) $this->console("[AutoQueue] beginRace: isWarmup=" . $this->boolToString($isWarmup) . "\n");

		$this->firstBeginRace=true;

		$this->loadServerLimit();
		foreach ($this->playerList as $login => &$info) {
			if ($info['LadderPoints'] < $this->serverlimit && $info['Allowed']) {
				$this->resetPlayer($login);
				if ($this->debug) $this->console("[AutoQueue] beginRace: resetPlayers({$login})\n");
			}
		}

		if (!$isWarmup && $this->warmupTime != -1){
			$currentTime = time();
			$this->warmupTime = $currentTime - $this->warmupTime;
			foreach ($this->playerList as $login => &$player){
				$player['lastActive'] += $this->warmupTime;
				if ($player['lastActive'] > $currentTime) { 
					$player['lastActive'] = $currentTime;
				}
			}
			$this->warmupTime = -1;
		} else {
			$this->warmupTime = time();
		}
		
		$this->isWarmup = $isWarmup;
	}

	/**
	 * Lock the checking in "Loading" state
	 */
	function lockCheck(){
		$this->checkActive = false;
		if ($this->debug) $this->console('[AutoQueue] Locking all unqueue actions.');
	}

	/**
	 * Unlock checking in "Synchronisation" state
	 */
	function unlockCheck(){
		if (!$this->checkActive){
			$this->checkActive = true;
			if ($this->debug) $this->console('[AutoQueue] Unlocking all unqueue actions.');
		}
	}

	/**
	 * Kicks the worst players, if specified and neccessary
	 *
	 * @param array $command
	 */
	function endRace($command){
		if ($this->debug) $this->console("[AutoQueue] endRace: isWarmup=" . $this->boolToString($this->isWarmup). ", kickworst={$this->kickworst}\n");
		
		if ($this->kickworst && !$this->isWarmup){
		//$this->updateMaxPlayers();

		//sort out spectators
		$logins = array();
		$specs = 0;
		foreach ($command[0] as &$player) {
			$login = &$player['Login'];
			if (isset($this->playerList[$login])) {
				if ($this->playerList[$login]['isSpectator']) {
					$specs++;
				}
				else {
					$logins[] = $login;
				}
			}
		}

		$this->rotatePlayers($logins);
		}
	}


	function rotatePlayers($logins) {
		// fakequeue
		/*for ($i=1; $i<=3; $i++) {
			$this->addToQueue("fake" . $i);
		}*/

		$kickcount = min(count($this->queue), $this->kickworst);
		if ($this->debug) $this->console("[AutoQueue] rotatePlayers: kickcount={$kickcount}\n");

		$tospec = (($this->maxspecs - $specs - $kickcount) >= 3) && $this->kickToSpec;	
		if ($this->debug) $this->console("[AutoQueue] rotatePlayers: tospec=" . $this->boolToString($tospec). "\n");

		$toqueue = array();
		$login = end($logins);
		while ($login !== false && 0 < $kickcount) {
			$kickcount--;
			$player = &$this->playerList[$login];
				
			if ($this->kickadmins || (!$this->isAdmin($login))) {
				//player is no admin or admins are kicked anyways
				if ($player['finishedRaces'] < 1){
					$this->console('[AutoQueue] rotatePlayers: Couldn\'t process player "'.$login.'" because of newbie protection');
				} 
				else {
					if ($tospec) {
						$this->forceSpec($login, 1);
						array_unshift($toqueue, $login);
						$this->console("[AutoQueue] rotatePlayers: {$login} kicked to spec");
						$chatText = str_replace('%nick%', $player['NickName'], $this->strings['kicktospec']);
						$this->sendChatLine($chatText);
					}
					else {
						$this->doWorstKick($login, 'Kicked worst player: ', $this->strings['kickworst']);
						$this->console("[AutoQueue] rotatePlayers: kicked player {$login}");
					}
				}
			} 
			else {
				$this->console('[AutoQueue] rotatePlayers: Couldn\'t process player "'.$login.'" because of admin protection');
			}
				
			$login = prev($logins);
		}

		foreach ($toqueue as &$login) {
			$this->addToQueue($login);
			$this->notifyQueue($login, count($this->queue));
		}
					
		if ($this->debug) $this->console("[AutoQueue] rotatePlayers: kickcount={$kickcount}\n");
	}
	
	
	/**
	 * Proceed a "Worst Player" Kick
	 *
	 * @param String $login
	 * @param String $consoleText
	 * @param String $chatText
	 */
	function doWorstKick($login, $consoleText, $chatText){
		$this->doIdleKick($login, 0, $consoleText, $chatText);
	}

	/**
	 * Proceed an Idle Kick
	 *
	 * @param String $login
	 * @param login $idleTime
	 * @param String $consoleText
	 * @param String $chatText
	 */
	function doIdleKick($login, $idleTime, $consoleText, $chatText){
		$player = &$this->playerList[$login];
		
		if ((!$player['isRelay']) &&(!$player['isReferee']) && ($this->kickadmins || !($this->isAdmin($login)))){
			$chatText = str_replace(array('%nick%', '%time%'), array($player['NickName'], $this->getTimeString($idleTime)), $chatText);
			$this->sendChatLine($chatText);
			$this->console('[AutoQueue] '.$consoleText.$login);
			$this->addCall('Kick', array($login));
		}
	}

	/**
	 * AddCall wrapper
	 *
	 * @param String $method
	 * @param String[] $args
	 */
	function addCall($method, $args){
		if (IN_FAST){
			addCallArray(null, array_merge(array($method), $args));
		} else {
			$this->Aseco->addCall($method, $args);
		}
	}

	/**
	 * Console wrapper
	 *
	 * @param String $str
	 */
	function console($str){
		if (IN_FAST){
			console($str);
		} else {
			$this->Aseco->console($str);
		}
	}

	/**
	 * returns a customized time string
	 *
	 * @param int $time
	 * @return String
	 */
	function getTimeString($time){
		if ($time<60) return ($time.' seconds');
		else {
			$seconds = $time % 60;
			if ($seconds < 10) $seconds = '0'.$seconds;
			$minutes = floor($time/60);
			$timeStr = $minutes.':'.$seconds.' minutes';
			return $timeStr;
		}
	}

	/**
	 * Updates the idle time of a player
	 *
	 * @param String $login
	 */
	function updateIdle($login){
		if (isset($this->playerList[$login])){
			$this->playerList[$login]['lastActive'] = time();
		}
	}

	/**
	 * Handles the chat command "/queue" and puts the author into the queue
	 * Displays a chatline which indicates the authors queue position
	 *
	 * @param array $command
	 */
	function chatQueue($command){
		if (IN_FAST) $login = $command; else $login = $command['author']->login;
		$this->doQueue($login);
	}

	/**
	 * Handles the chat command "/queuelist" and sends an updating list to the author
	 * 
	 * @param array $command
	 */
	function chatQueueList($command){
		if (IN_FAST) $login = $command; else $login = $command['author']->login;
		$this->listCustomers[$login] = true;
		$this->showQueueList($login);
	}

	/**
	 * Handles the chat command "/queueversion" and sends version information to the author
	 *
	 * @param array $command
	 */
	function chatQueueVersion($command){
		global $autoqueueVersion;
		$this->serverlimit = 50000;
		if (IN_FAST) $login = $command; else $login = $command['author']->login;
		$this->sendChatLine('Current version: $ff0v'.$autoqueueVersion.'$z$fff by $i$000$08foorf$000 I $ffffuck$08ffish', $login);
	}

	/**
	 * Handles the chat command "/queue" and puts the author into the queue
	 * Displays a chatline which indicates the authors queue position
	 *
	 * @param array $command
	 */
	function doQueue($login){
		if ($this->playerList[$login]['isSpectator']){
			if ($this->playerList[$login]['Allowed']){
				$pos = $this->addToQueue($login);
				$this->playerList[$login]['Unqueue'] = false;
				$this->notifyQueue($login, $pos);
			} else {
				$this->sendChatLine($this->strings['forbidden'], $login);
			}
		}
	}

	/**
	 * Handles the chat command "/unqueue" and removes the author from the queue
	 *
	 * @param String $command
	 */
	function chatUnQueue($command){
		if (IN_FAST) $login = $command; else $login = $command['author']->login;
		$this->doUnqueue($login);
	}

	function doUnqueue($login){
		if ($this->playerList[$login]['isSpectator']){
			$idx = $this->getQueuePos($login);
			$this->removeFromQueue($login);
			$this->notifyQueue('', 0, $idx);
			$this->playerList[$login]['Unqueue'] = true;
			if ($this->useManialinks){
				$this->updateML($login, $this->strings['ml_specmode'], $this->queueaction);
			} else {
				$this->sendChatLine($this->strings['unqueue'], $login);
			}
		}
	}

	/**
	 * Emulate onEverySecond
	 *
	 */
	function asecoMainLoop(){
		$newTime = time();
		if ($newTime > $this->time){
			$this->everySecond();
		}
	}

	/**
	 * Handles events that have the player login in an array
	 *
	 * @param array $command
	 */
	function asecoArrayIdleUpdate($command){
		$this->updateIdle($command[1]);
	}

	/**
	 * Handles playerfinish for ASECO
	 *
	 * @param object $command
	 */
	function asecoFinish($command){
		if ($command->score!=0){
			$this->updateIdle($command->player->login);
		}
	}
}

if (IN_XASECO){

	Aseco::registerEvent('onPlayerConnect', 'autoqueue_playerConnect');
	Aseco::registerEvent('onPlayerInfoChanged', 'autoqueue_playerInfoChanged');
	Aseco::registerEvent('onPlayerDisconnect', 'autoqueue_playerDisconnect');
	Aseco::registerEvent('onNewChallenge', 'autoqueue_newChallenge');
	Aseco::registerEvent('onBeginRound', 'autoqueue_beginRound');
	Aseco::registerEvent('onStatusChangeTo2', 'autoqueue_lockCheck');
	Aseco::registerEvent('onStatusChangeTo3', 'autoqueue_unlockCheck');
	Aseco::registerEvent('onEndRace', 'autoqueue_endRace');
	Aseco::registerEvent('onStartup', 'autoqueue_startup');
	Aseco::registerEvent('onEverySecond', 'autoqueue_everySecond');

	//FufiMenu
	Aseco::registerEvent('onMenuLoaded', 'autoqueue_initMenu');

	//idlekicker events
	Aseco::registerEvent('onChat', 'autoqueue_arrayIdleUpdate');
	Aseco::registerEvent('onCheckpoint', 'autoqueue_arrayIdleUpdate');
	Aseco::registerEvent('onPlayerFinish', 'autoqueue_finish');
	Aseco::registerEvent('onPlayerManialinkPageAnswer', 'autoqueue_handleClick');

	Aseco::addChatCommand('queue', 'Put yourself into the waiting queue.');
	Aseco::addChatCommand('unqueue', 'Remove yourself out of the waiting queue.');
	Aseco::addChatCommand('queuelist', 'Show the currently queued players.');
	Aseco::addChatCommand('queueversion', 'Get AutoQueue version information.');

	function autoqueue_startup($aseco){
		global $autoqueue;
		$autoqueue->Aseco = $aseco;
		$autoqueue->startUp();
	}

	function autoqueue_playerConnect($aseco, $command){
		global $autoqueue;
		$autoqueue->playerConnect($command);
	}

	function autoqueue_playerInfoChanged($aseco, $command){
		global $autoqueue;
		$autoqueue->playerInfoChanged($command);
	}

	function autoqueue_playerDisconnect($aseco, $command){
		global $autoqueue;
		$autoqueue->playerDisconnect($command);
	}

	function autoqueue_newChallenge($aseco, $command){
		global $autoqueue;
		$autoqueue->beginRace();
	}

	function autoqueue_beginRound($aseco, $command){
		global $autoqueue;
		$autoqueue->beginRound();
	}


	function autoqueue_lockCheck($aseco){
		global $autoqueue;
		$autoqueue->lockCheck();
	}

	function autoqueue_unlockCheck($aseco){
		global $autoqueue;
		$autoqueue->unlockCheck();
	}

	function autoqueue_endRace($aseco, $command){
		global $autoqueue;
		$autoqueue->endRace($command);
	}

	function autoqueue_everySecond($aseco){
		global $autoqueue;
		$autoqueue->everySecond();
	}

	function autoqueue_arrayIdleUpdate($aseco, $command){
		global $autoqueue;
		$autoqueue->updateIdle($command[1]);
	}


	function autoqueue_finish($aseco, $command){
		global $autoqueue;
		if ($command->score!=0){
			$autoqueue->updateIdle($command->player->login);
		}
	}

	function autoqueue_handleClick($aseco, $command){
		global $autoqueue;
		$autoqueue->handleClick($command);
	}

	function autoqueue_initMenu($aseco, $menu){
		$menu->addEntry('', 'mainhelpsep', false, 'AutoQueue', 'aqmenu');
		$menu->addEntry('aqmenu', '', true, 'Join Queue', 'joinqueue', '/queue');
		$menu->addEntry('aqmenu', '', true, 'Leave Queue', 'leavequeue', '/unqueue');
		$menu->addEntry('aqmenu', '', true, 'Show Queue', 'queuelist', '/queuelist');
	}

	function chat_queue($aseco, $command){
		global $autoqueue;
		$autoqueue->chatQueue($command);
	}

	function chat_unqueue($aseco, $command){
		global $autoqueue;
		$autoqueue->chatUnQueue($command);
	}

	function chat_queuelist($aseco, $command){
		global $autoqueue;
		$autoqueue->chatQueueList($command);
	}

	function chat_queueversion($aseco, $command){
		global $autoqueue;
		$autoqueue->chatQueueVersion($command);
	}
}

//Additions for FAST

if (IN_FAST){
	function autoqueuePlayerConnect($event,$login){
		global $autoqueue;
		$autoqueue->playerConnect($login);
	}
	
	function autoqueueStatusChanged($event, $content){
		global $autoqueue;
		$code = $content['Code'];
		if ($code == 2) $autoqueue->lockCheck();
		else if ($code == 3) $autoqueue->unlockCheck();
	}

	function autoqueueStartToServe(){
		global $autoqueue;
		$autoqueue->startUp();
	}

	function autoqueuePlayerInfoChanged($event,$login,$playerinfo){
		global $autoqueue;
		$autoqueue->playerInfoChanged($playerinfo);
	}

	function autoqueuePlayerDisconnect($event,$login){
		global $autoqueue;
		$autoqueue->playerDisconnect($login);
	}

	function autoqueueBeginRace($event, $GameInfos, $ChallengeInfos){
		global $autoqueue;
		$autoqueue->beginRace();
	}

	function autoqueueBeginRound(){
		global $autoqueue;
		$autoqueue->beginRound();
	}

	function autoqueueEndRace($event,$Ranking,$ChallengeInfo,$GameInfos){
		global $autoqueue;
		$autoqueue->endRace(array($Ranking));
	}

	function autoqueueEverysecond($event,$seconds){
		global $autoqueue;
		$autoqueue->everySecond();
	}

	function autoqueueIdleUpdate($login){
		global $autoqueue;
		$autoqueue->updateIdle($login);
	}

	function autoqueuePlayerChat($event,$login,$message){
		autoqueueIdleUpdate($login);
	}

	function autoqueuePlayerCheckpoint($event,$login,$time,$lapnum,$checkpt){
		autoqueueIdleUpdate($login);
	}

	function autoqueuePlayerFinish($event,$login,$time){
		if ($time > 0) autoqueueIdleUpdate($login);
	}

	function autoqueuePlayerManialinkPageAnswer($event,$login,$answer,$action){
		$command = array('', $login, $answer);
		global $autoqueue;
		$autoqueue->handleClick($command);
	}

	function chat_queue($author, $login, $params){
		global $autoqueue;
		$autoqueue->chatQueue($login);
	}

	function chat_unqueue($author, $login, $params){
		global $autoqueue;
		$autoqueue->chatUnQueue($login);
	}

	function chat_queuelist($author, $login, $params){
		global $autoqueue;
		$autoqueue->chatQueueList($login);
	}

	function chat_queueversion($author, $login, $params){
		global $autoqueue;
		$autoqueue->chatQueueVersion($login);
	}

	registerPlugin('autoqueue',98);
	registerCommand('queue', 'Put yourself into the waiting queue.');
	registerCommand('unqueue', 'Remove yourself out of the waiting queue.');
	registerCommand('queuelist', 'Show the currently queued players.');
	registerCommand('queueversion', 'Get AutoQueue version information.');

}

//initialize
if (!IN_ASECO){
	global $autoqueue;
	$autoqueue = new AutoQueue();
	$autoqueue->init('autoqueue_config.xml');
} else {
	$_PLUGIN = new AutoQueue('autoqueue_config.xml');
	$_PLUGIN->init('autoqueue_config.xml');
	$_PLUGIN->setAuthor('Alexander Peitz (fuckfish)');
	$_PLUGIN->setVersion($autoqueueVersion);
	$_PLUGIN->setDescription('Creates a queue for spectators.');

	$_PLUGIN->addEvent('onPlayerConnect', 'playerConnect');
	$_PLUGIN->addEvent('onPlayerInfoChanged', 'playerInfoChanged');
	$_PLUGIN->addEvent('onPlayerDisconnect', 'playerDisconnect');
	$_PLUGIN->addEvent('onNewChallenge', 'beginRace');
	$_PLUGIN->addEvent('onBeginRound', 'beginRound');
	$_PLUGIN->addEvent('onEndRace', 'endRace');
	$_PLUGIN->addEvent('onStatusChangeTo2', 'lockCheck');
	$_PLUGIN->addEvent('onStatusChangeTo3', 'unlockCheck');
	$_PLUGIN->addEvent('onEndRace', 'endRace');
	$_PLUGIN->addEvent('onMainLoop', 'asecoMainLoop');

	$_PLUGIN->addEvent('onChat', 'asecoArrayIdleUpdate');
	$_PLUGIN->addEvent('onCheckpoint', 'asecoArrayIdleUpdate');
	$_PLUGIN->addEvent('onPlayerManialinkPageAnswer', 'handleClick');
	$_PLUGIN->addEvent('onPlayerFinish', 'asecoFinish');
	$_PLUGIN->addEvent('onStartup', 'startUp');


	$_PLUGIN->addChatCommand('queue', 'chatQueue', 'Put yourself into the waiting queue.');
	$_PLUGIN->addChatCommand('unqueue', 'chatUnQueue', 'Remove yourself out of the waiting queue.');
	$_PLUGIN->addChatCommand('queuelist', 'chatQueueList', 'Show the currently queued players.');
	$_PLUGIN->addChatCommand('queueversion', 'chatQueueVersion', 'Get AutoQueue version information.');

}


?>
