<?php

// forceMods plugin
// (c) schmidi 2011
// www.doh-nuts.at



// v0.5.2
// add gui

// v0.5.1
// add TMF support
// add chat-commands
// add undef.de UpToDate

// v0.5 beta
// complete rewrite

// v0.4
// load settings even if disabled

// v0.3
// forceMods reworked
// new config-layout (easier to config i hope)
// added manialink-windows (...need some improvement)
// added settings-reload

// v0.2
// added chat-commands
// little improvements
 
// v0.1
// initial release
// thx to Yorkshire @ tm-forum.com


require_once('forceMods/FMod.php');
require_once('forceMods/FModList.php');


class ForceMods {

	private $aseco = false;
	
	private $manialink_id = 13201;
	
	// mode: off=0 sequential=1 random=2
	private $mode = 1;
	private $mods = array();
	private $override = false;

	private $modLists = array();
	private $ml_users = array();
	private $environments = array();

	
	function onSync($aseco) {
		$this->aseco = &$aseco;
		
		$this->aseco->plugin_versions[] = array('plugin' => 'plugin.forceMods.php', 'author' => 'schmidi', 'version' => '0.5.2');
   
   	switch($this->aseco->server->game) {
			case 'TmForever':
				$this->environments = array(1 => 'Stadium', 2 => 'Island', 3 => 'Speed', 4 => 'Rally', 5 => 'Bay', 6 => 'Coast', 7 => 'Alpine');
				break;
				
			case 'ManiaPlanet':
				$this->environments = array(1 => 'Canyon');
				break;
		}
		
		// 1 list per enviroment
		foreach($this->environments as $id => $name) {
			$this->modLists[$id] = new FModList();
		}

		$this->loadSettings('forceMods.xml');
	}


	function onEndMap($aseco, $race) {
		if($this->mode == 0) {
			return;
		}
		
		$challenge = &$race[1];
		$restart = &$race[4];

		$this->aseco->client->query('GetNextChallengeInfo');
		$nextChallenge = $this->aseco->client->getResponse();
		
		$env = $nextChallenge['Environnement'];
		if($restart == 1) {
			$env = $challenge['Environnement'];
		}

		$id = array_search($env, $this->environments);

		if($id === false) {
			$this->console('[forceMods] Enviroment "' . $env . '" not defined, canceling...');
			return;
		}

		$modList = &$this->modLists[$id];
		$index = $modList->nextMod($this->mode == 2);

		if($index < 0) {
			$this->console('[forceMods] No mods defined (enabled) for this environment, canceling...');
			$this->aseco->client->query('SetForcedMods', false, array());
			return;
		}

		$this->aseco->client->query('SetForcedMods', $this->override, array(array('Env' => $env, 'Url' => $modList->getUrl())));
		$result = $this->aseco->client->getResponse();
		
		if($result === true) {
			$this->console('[forceMods] Enabling mod: ' . $env . ' > ' . $modList->getName());
		}
		else {
			$this->console('[forceMods] ' . $result['faultString'] . '(ErrorCode: ' . $result['faultCode'] . ')');
		}
	}
	
	
	function onChat($admin, $cmd, $args) {
		// check for permission
		if (!$this->aseco->isMasterAdmin($admin) || ($this->aseco->settings['lock_password'] != '' && !$admin->unlocked)) {
			// write warning in console
			$this->console($admin->login . ' tried to use admin chat command (no permission!): /fmods');

			// show chat message
			$message = $this->aseco->getChatMessage('NO_ADMIN');
			$this->aseco->addCall('ChatSendToLogin', array($this->aseco->formatColors($message), $admin->login));
			return false;
		}
		
		// fmods OFF
		if ($cmd == 'off' || (strncmp($cmd, 'disabled', 4) == 0)) {
			$this->setMode($admin, 0);
		}
			
		// fmods SEQUENTIAL
		elseif (strncmp($cmd, 'sequential', 3) == 0) {
			$this->setMode($admin, 1);
		}
		
		// fmods RANDOM
		elseif (strncmp($cmd, 'random', 4) == 0) {
			$this->setMode($admin, 2);
		}
		
  	// fmods override ON/OFF
  	elseif ($cmd == 'override'  && ($args[0] == 'on' || $args[0] == 'off')) {
	 		$this->setOverride($admin, ($args[0] == 'on'));
  	}

  	// fmods reload settings
  	elseif (strncmp($cmd, 'reload', 3) == 0) {
  		$this->loadSettings('forceMods.xml');				
  		
  		$this->console('[forceMods] ' . $player->login . ' reloads settings!');
  
  		$message = formatText('{#server}> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} reloads forceMods settings!',
  			$this->aseco->titles['MASTERADMIN'][0], $admin->nickname);
  		$this->aseco->addCall('ChatSendServerMessageToLogin', array($this->aseco->formatColors($message), $admin->login));
  	}

  	// fmods ENVIRONMENT
  	elseif ($cmd) {
			foreach($this->environments as $id => $name) {
				if (strncmp($name, ucfirst($cmd), 4) == 0) {
					$this->showMods($admin, $id);
				}
			}
  	}
  	
  	// fmods
  	elseif ($cmd == '') {
			$this->showSettings($admin);
  	}
  	
	}


	function onPlayerManialinkPageAnswer($aseco, $answer, $entries = false) {
		$action = intval($answer[2]);
		$login = $answer[1];
		
		if ($action == 0) {
			unset ($this->ml_users[$login]);
			return;
		}
		
		// check range
		if ($action < $this->manialink_id || $action > $this->manialink_id + 200) {
			return;
		}
		
		$action -= $this->manialink_id;
		$player = $this->aseco->server->players->getPlayer($login);
		
		// Mode
		if ($action <=  + 2) {
			$this->setMode($player, $action);
			$this->showSettings($player);
		}
		
		// Override
		elseif ($action == 3) {
			$this->setOverride($player, false);
			$this->showSettings($player);
		}
		elseif ($action == 4) {
			$this->setOverride($player, true);
			$this->showSettings($player);
		}
		
		// Reload
		elseif ($action == 5) {
  		$this->loadSettings('forceMods.xml');				
  		
  		$this->console('[forceMods] ' . $player->login . ' reloads settings!');
  
  		$message = formatText('{#server}> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} reloads forceMods settings!',
  			$this->aseco->titles['MASTERADMIN'][0], $player->nickname);
  		$this->aseco->addCall('ChatSendServerMessageToLogin', array($this->aseco->formatColors($message), $player->login));

			$this->showSettings($player);
		}

		// Environment
		elseif ($action < 15) {
			$this->showMods($player, $action - 5);
		}

		// Mods
		else {
			$id = $action - 15;
			$page = $id / 12;
			$env_id = $this->ml_users[$player->login];
			
			$list = &$this->modLists[$env_id]; 
			$data = &$player->msgs[$page + 1];
			
			if ($list->isEnabled($id)) {
				$list->disable($id);
				$data[$id % 12] = array($list->getName($id), array('$f00off', $this->manialink_id + 15 + $id));
			}
			else {
				$list->enable($id);
				$data[$id % 12] = array($list->getName($id), array('$0f0on', $this->manialink_id + 15 + $id));
			}
			
			display_manialink_multi($player);
		}
		
	}


	private function showSettings($player) {
		$header = 'forceMods:  Settings';

		$data = array();
		
		//Mode
		$tmp = array('Mode');
		$tmp[] = ($this->mode == 0) ? '$ff0$odisabled' : array('disabled', $this->manialink_id);
		$tmp[] = ($this->mode == 1) ? '$ff0$osequential' : array('sequential', $this->manialink_id + 1);
		$tmp[] = ($this->mode == 2) ? '$ff0$orandom' : array('random', $this->manialink_id + 2);
		$data[] = $tmp;

		// Override
		if ($this->override) {
			$data[] = array('Override', '$ff0$oenabled', array('disabled', $this->manialink_id + 3), '');
		}
		else {
			$data[] = array('Override', array('enabled', $this->manialink_id + 4), '$ff0$odisabled', '');
		}

		$data[] = array();

		// Reload
		$data[] = array('Settings', array('$ff0$oreload', $this->manialink_id + 5), '','');

		$data[] = array();
		
		// Environments
		foreach ($this->environments as $id => $name) {
			$data[] = array('', array('$ff0$o' . $name, $this->manialink_id + 5 + $id), '', '');
		}
		
		display_manialink($player->login, $header, array('Icons128x128_1', 'Options', 0.02), $data, array(0.73, 0.19, 0.18, 0.18, 0.18), 'OK');
	}
	

	private function showMods($player, $env_id, $page = 1) {
		if(!array_key_exists($env_id, $this->environments)) {
			return;
		}
		
		$this->ml_users[$player->login] = $env_id;
		
		$list = &$this->modLists[$env_id];
		
		$n = $list->countMods();
		if ($n < 1) {
			$message = formatText('{#server}> $z$s{#admin}forceMods: no mods for {1}!', $this->environments[$env_id]);
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
			return;
		}
		
		$header = 'forceMods:  ' . $this->environments[$env_id];
		$player->msgs = array();
		$player->msgs[0] = array($page, $header, array(0.46, 0.35, 0.11), array('Icons128x128_1', 'Options', 0.02));

		$data = array();
		$i = 0;
		while ($i < $n) {
			$data[] = array($list->getName($i), array($list->isEnabled($i) ? '$0f0on' : '$f00off', $this->manialink_id + 15 + $i));

			if (++$i % 12 == 0) {
				$player->msgs[] = $data;
				$data = array();
			}
		}
		if ($i % 12 != 0) {
			$player->msgs[] = $data;
		}
		
		display_manialink_multi($player);
	}
	

	private function loadSettings($file) {
		if (($xml = @simplexml_load_file($file)) === false) {
			$this->console('[forceMods] Could not read/parse config file ' . $file . ' !');
			die();
		}
		
		$this->mode = intval($xml->mode);
		$this->mode = ($this->mode >= 0 && $this->mode <= 2) ? $this->mode : 0;
		$this->override = (strtolower($xml->override) == 'true' || $xml->override == '1') ? true : false;
		
		foreach($this->environments as $id => $name) {
			$this->modLists[$id] = new FModList();
		}

		foreach ($xml->mod as $mod) {

			if (!isset($mod['name']) || !isset($mod['url'])) {
				continue;
			}
			
			$fmod = new FMod($mod['name'], $mod['url'], $mod['enabled']);

			if(isset($mod['env']) && $mod['env'] != '') {
				// add mod to specified environments
				foreach (str_split($mod['env']) as $c) {
					$i = intval($c);
					if(array_key_exists($i, $this->environments)) {
						$this->modLists[$i]->addMod($fmod);
					}
				}
			}
			else {
				// add mod to all environments
				foreach($this->environments as $id => $name) {
					$this->modLists[$id]->addMod($fmod);
				}
			}
		}
	}


	private function setMode($admin, $mode) {
		if (!is_int($mode) || ($mode < 0) || ($mode > 2)) {
			return;
		}
		$this->mode = $mode;
		
		switch ($this->mode) {
			case 0:
				$msg = 'OFF';
				break;

			case 1:
				$msg = 'SEQUENTIAL';
				break;

			case 2:
				$msg = 'RANDOM';
				break;
		}

		$this->console('[forceMods] ' . $admin->login . ' set mode ' . $msg . ' !');

		// show chat message
		$message = formatText('{#server}> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} set forceMods {3}',
			$this->aseco->titles['MASTERADMIN'][0], $admin->nickname, $msg);
		$this->aseco->client->query('ChatSendServerMessageToLogin', $this->aseco->formatColors($message), $admin->login);
		
		if ($this->mode == 0) {
		  $this->aseco->addCall('SetForcedMods', array(false, array()));
		}
	}
	
	
	private function setOverride($admin, $enabled) {
 		$this->override = ($enabled === true);
  
 		$this->console('[forceMods] ' . $admin->login . ' set override ' . ($this->override ? 'ON' : 'OFF') . ' !');
  
 		$message = formatText('{#server}> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} set forceMods override {3}',
 			$this->aseco->titles['MASTERADMIN'][0], $admin->nickname, ($this->override ? 'ON' : 'OFF'));
 		$this->aseco->addCall('ChatSendServerMessageToLogin', array($this->aseco->formatColors($message), $admin->login));
	}
	
	
	private function console($txt) {
		$this->aseco->console($txt);
	}
	
}


global $FORCEMODS;
$FORCEMODS = new ForceMods();

Aseco::registerEvent('onSync', array($FORCEMODS, 'onSync'));
if (defined('XASECO2_VERSION')) {
	Aseco::registerEvent('onEndMap', array($FORCEMODS, 'onEndMap'));
}
if (defined('XASECO_VERSION')) {
	Aseco::registerEvent('onEndRace', array($FORCEMODS, 'onEndMap'));
}
Aseco::registerEvent('onPlayerManialinkPageAnswer', array($FORCEMODS, 'onPlayerManialinkPageAnswer'));
Aseco::addChatCommand('fmods', 'forceMods settings');

function chat_fmods($aseco, $command) {
	global $FORCEMODS;

	$admin = $command['author'];
	$args = explode(' ', strtolower($command['params']), 2);
	if (isset($args[1])) {
		$FORCEMODS->onChat($admin, $args[0], explode(' ', $args[1]));
	}
	else {
		$FORCEMODS->onChat($admin, $args[0], array());
	}
}

?>