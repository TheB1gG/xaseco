<?php
	//////////////////////////////////////////////////////////////////////
	//	@Name: 		ZeroSwitch
	//	@Version:	1.1f
	//	@Author:	BBMV & Büddl
	//	@Date:		06-03-2009
	//////////////////////////////////////////////////////////////////////
	
	function getInfofromserver($client, $query, $arr = NULL, $param1 = -1, $param2 = -1, $getdump = FALSE, $count = FAlSE, $while_field = NULL, $while_value = NULL, $vardump_result = FALSE, $just_ret_subfield = NULL) {
			if (($param1 == -1) AND ($param2 == -1)) {
				$client->query($query);
			} else if (($param1 != -1) AND ($param2 == -1)) {
				$client->query($query, $param1);
			} else if (($param1 != -1) AND ($param2 != -1)) {
				$client->query($query, $param1, $param2);
			}
			$res = NULL;
			$buff = NULL;
			$buff = $client->getResponse();
			if ($getdump == true){
				var_dump($buff);
			}
			if ($count) {
				if ($while_field != NULL) {
					if ($arr == NULL) {
						foreach($buff as $elem) {
							if ($elem[$while_field] == $while_value) {
								$res[] = $elem;
							}
						}
						return count($res);
					} else {
						foreach($buff[$arr] as $elem) {
							if ($elem[$while_field] == $while_value) {
								$res[] = $elem;
							}
						}
						return count($res);
					}
				} else {
					if ($arr == NULL) {
						return count($buff);
					} else {
						return count($buff[$arr]);
					}
				}
			} else {
				if ($while_field != NULL) {
					if ($arr == NULL) {
						foreach($buff as $elem) {
							if ($elem[$while_field] == $while_value) {
								if ($just_ret_subfield != NULL) {
									$res[] = $elem[$just_ret_subfield];
								} else {
									$res[] = $elem;
								}
							}
						}
						if ($vardump_result == true){
							var_dump($res);
						}
						return $res;
					} else {
						foreach($buff[$arr] as $elem) {
							if ($elem[$while_field] == $while_value) {
								$res[] = $elem;
							}
						}
						return $res;
					}
				} else {
					if ($arr == NULL) {
						return $buff;
					} else {
						return $buff[$arr];
					}
				}
			}
		}	
		
	Aseco::registerEvent('onStartup', 'zeroswitch_startup');
	Aseco::registerEvent('onEverySecond', 'zeroswitch_call');
	Aseco::registerEvent('onEndRace', 'zeroswitch_onEndRace');
	Aseco::registerEvent('onNewChallenge', 'zeroswitch_onBeginRace');
	Aseco::registerEvent('onMenuLoaded', 'zeroswitch_initMenu');
	Aseco::addChatCommand('zeroswitch', '/zeroswitch {linkserver|removeserver|crosslink} {serverlogin}');
	Aseco::addChatCommand('zs', '/zs {ls|rs|cs} {serverlogin}');
	
	global $mysql_login, $mysql_pw, $mysql_db, $objConn, $mysql_conn,$refresh_time,$counter,$mysql_ip,$display;
	global $zs_ENV;
	$zs_ENV = array();
	//modify these lines to your zeroswitch Datbase
	$mysql_login = 'zeroswitch';
	$mysql_ip = 'localhost';
	$mysql_pw = 'aUVkPgFD';
	$mysql_db = 'zeroswitch';
	//end setup
	$mysql_conn = false; 
	$counter = 0;
	$display = true;
	
	
	function zeroswitch_readenv($aseco) {
		global $objConn, $zs_ENV;
		$sql = "SELECT * FROM `envvar` WHERE server = '*';";
		$res = $objConn->query($sql);
		while ($envvar = $res->fetch_assoc()) {
			$zs_ENV[$envvar['var']] = $envvar['value'];
		}
		$sql = "SELECT * FROM `envvar` WHERE server = '".$aseco->server->serverlogin."';";
		$res = $objConn->query($sql);
		while ($envvar = $res->fetch_assoc()) {
			$zs_ENV[$envvar['var']] = $envvar['value'];
		}
	}
	
	function zeroswitch_initMenu($aseco, $menu){
		global $zs_ENV;
		zeroswitch_startup($aseco);
		$menu->addEntry('', 'mainhelpsep', false, 'ZeroSwitch', 'zsmenu');
		$menu->addEntry('zsmenu', '', false, 'Global', 'zsmenu3');
		$menu->addEntry('zsmenu', '', false, 'Local', 'zsmenu4');
		$menu->addEntry('zsmenu', '', true, 'clear list', 'clear-list', '/zs cls', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu', '', true, 'delete duplicates', 'delete-duplicates', '/zs dd', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu', '', true, 'show settings', 'show-settings', '/zs showenvs', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addSeparator('zsmenu', '', true, 'zs_seperator');
		$menu->addEntry('zsmenu', '', true, 'Help', 'zs_help', '/zs helpall', null, $zs_ENV['HAS_RIGHTS'], null, null, 'help');
		$menu->addSeparator('zsmenu', '', true, 'zs_seperator2');
		$menu->addEntry('zsmenu', '', true, 'Servers list', 'zs_help2', '/zs helplist', null, $zs_ENV['HAS_RIGHTS'], null, null, 'help');
		$menu->addEntry('zsmenu', '', true, 'Plugin settings', 'zs_help3', '/zs helpsettings', null, $zs_ENV['HAS_RIGHTS'], null, null, 'help');
		$menu->addEntry('zsmenu', '', true, 'Extended Options', 'zs_help4', '/zs helpsettings2', null, $zs_ENV['HAS_RIGHTS'], null, null, 'help');
		
		$menu->addEntry('zsmenu3', '', true, 'Display State', 'zsmenu5');
		$menu->addEntry('zsmenu5', '', true, 'Show current', 'display-current', '/zs show global DISPLAY_STATE', null, $zs_ENV['HAS_RIGHTS'], null, null, 'help');
		$menu->addSeparator('zsmenu5', '', true, 'zs_seperator');
		$menu->addEntry('zsmenu5', '', true, 'Display never', 'display-never', '/zs global DISPLAY_STATE never', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu5', '', true, 'Display in race', 'display-race', '/zs global DISPLAY_STATE race', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu5', '', true, 'Display on score', 'display-score', '/zs global DISPLAY_STATE score', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu5', '', true, 'Display always', 'display-always', '/zs global DISPLAY_STATE always', null, $zs_ENV['HAS_RIGHTS']);
		
		$menu->addEntry('zsmenu3', '', true, 'Refresh Time', 'zsmenu8');
		$menu->addEntry('zsmenu8', '', true, 'Show current', 'refresh-current', '/zs show global REFRESH_TIME', null, $zs_ENV['HAS_RIGHTS'], null, null, 'help');
		$menu->addSeparator('zsmenu8', '', true, 'zs_seperator');
		$menu->addEntry('zsmenu8', '', true, 'Refresh after 1 secs', 'Refresh-1', '/zs global REFRESH_TIME 1', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu8', '', true, 'Refresh after 2 secs', 'Refresh-2', '/zs global REFRESH_TIME 2', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu8', '', true, 'Refresh after 3 secs', 'Refresh-3', '/zs global REFRESH_TIME 3', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu8', '', true, 'Refresh after 5 secs', 'Refresh-5', '/zs global REFRESH_TIME 5', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu8', '', true, 'Refresh after 8 secs', 'Refresh-8', '/zs global REFRESH_TIME 8', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu8', '', true, 'Refresh after 10 secs', 'Refresh-10', '/zs global REFRESH_TIME 10', null, $zs_ENV['HAS_RIGHTS']);

		$menu->addEntry('zsmenu3', '', true, 'Entry Count', 'zsmenu10');
		$menu->addEntry('zsmenu10', '', true, 'Show current', 'entrycount-current', '/zs show global ENTRY_COUNT', null, $zs_ENV['HAS_RIGHTS'], null, null, 'help');
		$menu->addSeparator('zsmenu10', '', true, 'zs_seperator');
		$menu->addEntry('zsmenu10', '', true, 'List 1 server', 'entrycount-1', '/zs global ENTRY_COUNT 1', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu10', '', true, 'List 2 servers', 'entrycount-2', '/zs global ENTRY_COUNT 2', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu10', '', true, 'List 3 servers', 'entrycount-3', '/zs global ENTRY_COUNT 3', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu10', '', true, 'List 4 servers', 'entrycount-4', '/zs global ENTRY_COUNT 4', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu10', '', true, 'List 5 servers', 'entrycount-5', '/zs global ENTRY_COUNT 5', null, $zs_ENV['HAS_RIGHTS']);		
		$menu->addEntry('zsmenu10', '', true, 'List 8 servers', 'entrycount-8', '/zs global ENTRY_COUNT 8', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu10', '', true, 'List 10 servers', 'entrycount-10', '/zs global ENTRY_COUNT 10', null, $zs_ENV['HAS_RIGHTS']);
		
		$menu->addEntry('zsmenu3', '', true, 'Permissions', 'zsmenu11');
		$menu->addEntry('zsmenu11', '', true, 'Show current', 'rights-current', '/zs show global HAS_RIGHTS', null, $zs_ENV['HAS_RIGHTS'], null, null, 'help');
		$menu->addSeparator('zsmenu11', '', true, 'zs_seperator');
		$menu->addEntry('zsmenu11', '', true, 'At least Operators', 'rights-Operator', '/zs global HAS_RIGHTS Operator', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu11', '', true, 'At least Admins', 'rights-Admin', '/zs global HAS_RIGHTS Admin', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu11', '', true, 'At least MasterAdmins', 'rights-MasterAdmin', '/zs global HAS_RIGHTS MasterAdmin', null, $zs_ENV['HAS_RIGHTS']);
		
		$menu->addEntry('zsmenu3', '', true, 'Restore all settings', 'restoreglobal', '/zs restore', null, $zs_ENV['HAS_RIGHTS']);
	
		//
	
		$menu->addEntry('zsmenu4', '', true, 'Display State', 'zsmenu6');
		$menu->addEntry('zsmenu6', '', true, 'Show current', 'display-current', '/zs show local DISPLAY_STATE', null, $zs_ENV['HAS_RIGHTS'], null, null, 'help');
		$menu->addSeparator('zsmenu6', '', true, 'zs_seperator');
		$menu->addEntry('zsmenu6', '', true, 'Display never', 'display-never', '/zs local DISPLAY_STATE never', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu6', '', true, 'Display in race', 'display-race', '/zs local DISPLAY_STATE race', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu6', '', true, 'Display on scoreboard', 'display-score', '/zs local DISPLAY_STATE score', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu6', '', true, 'Display always', 'display-always', '/zs local DISPLAY_STATE always', null, $zs_ENV['HAS_RIGHTS']);
		
		$menu->addEntry('zsmenu4', '', true, 'Refresh Time', 'zsmenu7');
		$menu->addEntry('zsmenu7', '', true, 'Show current', 'refresh-current', '/zs show local REFRESH_TIME', null, $zs_ENV['HAS_RIGHTS'], null, null, 'help');
		$menu->addSeparator('zsmenu7', '', true, 'zs_seperator');
		$menu->addEntry('zsmenu7', '', true, 'Refresh after 1 secs', 'Refresh-1', '/zs local REFRESH_TIME 1', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu7', '', true, 'Refresh after 2 secs', 'Refresh-2', '/zs local REFRESH_TIME 2', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu7', '', true, 'Refresh after 3 secs', 'Refresh-3', '/zs local REFRESH_TIME 3', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu7', '', true, 'Refresh after 5 secs', 'Refresh-5', '/zs local REFRESH_TIME 5', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu7', '', true, 'Refresh after 8 secs', 'Refresh-8', '/zs local REFRESH_TIME 8', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu7', '', true, 'Refresh after 10 secs', 'Refresh-10', '/zs local REFRESH_TIME 10', null, $zs_ENV['HAS_RIGHTS']);
		
		$menu->addEntry('zsmenu4', '', true, 'Entry Count', 'zsmenu9');
		$menu->addEntry('zsmenu9', '', true, 'Show current', 'entrycount-current', '/zs show local ENTRY_COUNT', null, $zs_ENV['HAS_RIGHTS'], null, null, 'help');
		$menu->addSeparator('zsmenu9', '', true, 'zs_seperator');
		$menu->addEntry('zsmenu9', '', true, 'List 1 server', 'entrycount-1', '/zs local ENTRY_COUNT 1', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu9', '', true, 'List 2 servers', 'entrycount-2', '/zs local ENTRY_COUNT 2', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu9', '', true, 'List 3 servers', 'entrycount-3', '/zs local ENTRY_COUNT 3', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu9', '', true, 'List 4 servers', 'entrycount-4', '/zs local ENTRY_COUNT 4', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu9', '', true, 'List 5 servers', 'entrycount-5', '/zs local ENTRY_COUNT 5', null, $zs_ENV['HAS_RIGHTS']);		
		$menu->addEntry('zsmenu9', '', true, 'List 8 servers', 'entrycount-8', '/zs local ENTRY_COUNT 8', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu9', '', true, 'List 10 servers', 'entrycount-10', '/zs local ENTRY_COUNT 10', null, $zs_ENV['HAS_RIGHTS']);
		
		$menu->addEntry('zsmenu4', '', true, 'Permissions', 'zsmenu12');
		$menu->addEntry('zsmenu12', '', true, 'Show current', 'rights-current', '/zs show local HAS_RIGHTS', null, $zs_ENV['HAS_RIGHTS'], null, null, 'help');
		$menu->addSeparator('zsmenu12', '', true, 'zs_seperator');
		$menu->addEntry('zsmenu12', '', true, 'At least Operators', 'rights-Operator', '/zs local HAS_RIGHTS Operator', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu12', '', true, 'At least Admins', 'rights-Admin', '/zs local HAS_RIGHTS Admin', null, $zs_ENV['HAS_RIGHTS']);
		$menu->addEntry('zsmenu12', '', true, 'At least MasterAdmins', 'rights-MasterAdmin', '/zs local HAS_RIGHTS MasterAdmin', null, $zs_ENV['HAS_RIGHTS']);
		
		$menu->addEntry('zsmenu4', '', true, 'Set to default', 'settodefault', '/zs default', null, $zs_ENV['HAS_RIGHTS']);
	}
	
	function zeroswitch_restoredefaults($aseco) {
		global $objConn;
		
		$sql1 = "DELETE FROM `envvar`;";
		$sql2 = "
INSERT INTO `envvar` (`server`, `var`, `value`) VALUES
('*', 'DISPLAY_STATE', 'always'),
('*', 'LEFT_POS', '34.0'),
('*', 'TOP_POS', '-33.5'),
('*', 'REFRESH_TIME', '5'),
('*', 'ENTRY_COUNT', '5'),
('*', 'HAS_RIGHTS', 'MasterAdmin');
		";
		if (($objConn->query($sql1)) and ($objConn->query($sql2))) {
			$aseco->console("[ZeroSwitch] Info: Default settings loaded.");
			return true;
		}
		else {
			$aseco->console( "[ZeroSwitch] Warning: Error loading default settings!");
			return false;
		}
	}
	
	function zeroswitch_setuptable($aseco, $tablename) {
		global $objConn;
		switch ($tablename) {
			case "serverstats":
				$sql = "
CREATE TABLE IF NOT EXISTS `serverstats` (
  `login` varchar(45) COLLATE latin1_bin NOT NULL,
  `serverName` varchar(255) COLLATE latin1_bin NOT NULL,
  `ladderMin` int(11) NOT NULL,
  `ladderMax` int(11) NOT NULL,
  `playerCount` int(11) NOT NULL,
  `maxPlayers` int(11) NOT NULL,
  `spectatorCount` int(11) NOT NULL,
  `maxSpectators` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_bin;
				";
			break;
			case "serverrelations":
				$sql = "
CREATE TABLE IF NOT EXISTS `serverrelations` (
  `login` varchar(45) COLLATE latin1_bin NOT NULL,
  `rellogin` varchar(45) COLLATE latin1_bin NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_bin;
				";
			break;
			case "envvar":
				$sql = "
CREATE TABLE IF NOT EXISTS `envvar` (
  `server` varchar(45) COLLATE utf8_bin NOT NULL,
  `var` varchar(45) COLLATE utf8_bin NOT NULL,
  `value` varchar(255) COLLATE utf8_bin NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
				
				";
			break;
			default:
				echo "[ZeroSwitch] Warning: Could not install table '".$tablename."'! Table not known or no install source available.";
			break;
		}
		if ($objConn->query($sql))
			$aseco->console( "[ZeroSwitch] Info: Table '".$tablename."' installed.");
		else
			$aseco->console( "[ZeroSwitch] Warning: Error installing table '".$tablename."'!");
	}
	
	function zeroswitch_onEndRace($aseco) {
		zeroswitch_visibility($aseco, 'onEndRace');
	}
	
	function zeroswitch_onBeginRace($aseco) {
		zeroswitch_visibility($aseco, 'onBeginRace');
	}
	
	function zeroswitch_visibility($aseco, $what) {
		global $zs_ENV, $display;
		switch ($zs_ENV['DISPLAY_STATE'] ) {
			case 'never': 
				$display = false;
				break;
			case 'race':
				if ($what == 'onBeginRace') 
					$display = true;
				else if ($what == 'onEndRace') 
					$display = false;
				break;
			case 'score':
				if ($what == 'onBeginRace') 
					$display = false;
				else if ($what == 'onEndRace') 
					$display = true;
				break;
			case 'always':
				$display = true;
				break;
		}
		zeroswitch_call($aseco);
	}
	
	function zeroswitch_startup($aseco) {
		global $mysql_login, $mysql_pw, $mysql_db, $objConn, $mysql_conn,$mysql_ip,$zs_ENV;
		if (!$mysql_conn) {
			if (($objConn = new mysqli($mysql_ip, $mysql_login, $mysql_pw)) && ($objConn->select_db($mysql_db))) {
				$mysql_conn = true;
				$sql = "SELECT * FROM `serverstats`;";
				if (!$objConn->query($sql)) {
					zeroswitch_setuptable($aseco, 'serverstats');
				}
				$sql = "SELECT * FROM `serverrelations`;";
				if (!$objConn->query($sql)) {
					zeroswitch_setuptable($aseco, 'serverrelations');
				}
				$sql = "SELECT * FROM `envvar`;";
				if (!$objConn->query($sql)) {
					zeroswitch_setuptable($aseco, 'envvar');
					zeroswitch_restoredefaults($aseco);
				}
				zeroswitch_readenv($aseco);
			} else {
				$mysql_conn = false;
				$aseco->console("[ZeroSwitch] Fatal Error: No connection to database or databaseserver!");
			}
		} else {
			$aseco->console( "[ZeroSwitch] Notice: Connection already initialized and database structure checked.");
		}
	}
	
	function chat_zs($aseco, $info) {
		chat_zeroswitch($aseco, $info);
	}
	
	function zeroswitch_haspermission($aseco, $author) {
		global $zs_ENV;
		if ($zs_ENV['HAS_RIGHTS'] == "Operator") {	
			if ($aseco->isMasterAdmin($author))
				return true;
			else
			if ($aseco->isAdmin($author))
				return true;
			else
			if ($aseco->isOperator($author))
				return true;
			else
				return false;
		} else
		if ($zs_ENV['HAS_RIGHTS'] == "Admin") {
			if ($aseco->isMasterAdmin($author))
				return true;
			else
			if ($aseco->isAdmin($author))
				return true;
			else
				return false;
		} else
		if ($zs_ENV['HAS_RIGHTS'] == "MasterAdmin") {
			if ($aseco->isMasterAdmin($author))
				return true;
			else
				return false;
		} else
			return false;
	}
	
	function chat_zeroswitch($aseco, $info) {
		global $objConn,$mysql_conn,$zs_ENV;
		if ($mysql_conn) {
			$author = $info["author"]->login;
			if (zeroswitch_haspermission($aseco, $info["author"])) {
				$command = $info["params"];
				$params = explode(" ", $command);	
				if (($params[0] == 'linkserver') or
					($params[0] == 'ls')) {
						$sql = "INSERT INTO `serverrelations` ( `login` , `rellogin` ) VALUES ('".$aseco->server->serverlogin."', '".$params[1]."');";
						if ($objConn->query($sql))
							$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] Server $fff'.$params[1].'$ff0 linked.', $author));
						else
							$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] $i$f00Server could not be linked. Sorry. :(', $author));			
				} else 
				if (($params[0] == 'removeserver') or
					($params[0] == 'rs')) {
						$sql = "DELETE FROM `serverrelations` WHERE login='".$aseco->server->serverlogin."' AND rellogin='".$params[1]."';";
						$objConn->query($sql);		
						$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] Server linkage with $fff'.$params[1].' $ff0removed.', $author));
				} else
				if (($params[0] == 'crosslink') or
					($params[0] == 'cl')) {
						$sql = "INSERT INTO `serverrelations` ( `login` , `rellogin` ) VALUES ('".$aseco->server->serverlogin."', '".$params[1]."');";
						if ($objConn->query($sql)) {
							$sql = "INSERT INTO `serverrelations` ( `login` , `rellogin` ) VALUES ('".$params[1]."', '".$aseco->server->serverlogin."');";
							if ($objConn->query($sql)) {
								$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] Server crosslinked now with $fff'.$params[1].'!', $author));
							} else {
								$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] Server $fff'.$params[1].' linked now$i$f00, but could not relink this server to the other!', $author));
							}
						} else
							$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] $i$f00Servers could not be crosslinked. Sorry. :(', $author));
				} else
				if (($params[0] == 'clearlist') or
					($params[0] == 'cls')) {
						$sql = "DELETE FROM `serverrelations` WHERE login='".$aseco->server->serverlogin."';";
						$objConn->query($sql);		
						$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] All server linkages removed.', $author));
				} else 
				if (($params[0] == 'copylist') or
					($params[0] == 'cp')) {
					$sql = "SELECT `rellogin` FROM `serverrelations` WHERE `login` = '".$params[1]."';";
					$res = $objConn->query($sql);
					$copy_ok = true;
					while ($server = $res->fetch_assoc()) {
						$rellogin = $server['rellogin'];
						$sql = "INSERT INTO `serverrelations` ( `login` , `rellogin` ) VALUES ('".$aseco->server->serverlogin."', '".$rellogin."');";
						if (!$objConn->query($sql)) {
							$copy_ok = false;
						}
					}
					if ($copy_ok)
						$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] Serverlist copied from $fff'.$params[1], $author));
					else
						$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] $i$f00Serverlist could not be copied. Sorry. :(', $author));
				} else
				if (($params[0] == 'setlocal') or
					($params[0] == 'local')) {
					$sql = "SELECT count(*) AS cnt FROM `envvar` WHERE server = '".$aseco->server->serverlogin."' AND var = '".$params[1]."';";
					$res = $objConn->query($sql);
					if ($res->fetch_assoc() == array('cnt' => 0)) {
						$sql = "INSERT INTO `envvar` (`server`, `var`, `value`) VALUES ('".$aseco->server->serverlogin."', '".$params[1]."', '".$params[2]."');";
						if ($objConn->query($sql))
							$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] Local Envrionementvariable $fff'.$params[1].' $ff0setted to $fff'.$params[2].'$ff0!', $author));
						else
							$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] $i$f00Error setting local Envrionementvariable $fff'.$params[1].' $f00 to $fff'.$params[2].'$f00!', $author));	
					} else {
						$sql = "UPDATE `envvar` SET value = '".$params[2]."' WHERE server = '".$aseco->server->serverlogin."' AND var = '".$params[1]."';";
						if ($objConn->query($sql))
							$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] Local Envrionementvariable $fff'.$params[1].' $ff0setted to $fff'.$params[2].'$ff0!', $author));
						else
							$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] $i$f00Error setting local Envrionementvariable $fff'.$params[1].' $f00 to $fff'.$params[2].'$f00!', $author));	
					}
				} else
				if (($params[0] == 'setglobal') or
					($params[0] == 'global')) {
					$sql = "SELECT count(*) AS cnt FROM `envvar` WHERE server = '*' AND var = '".$params[1]."';";
					$res = $objConn->query($sql);
					if ($res->fetch_assoc() == array('cnt' => 0)) {
						$sql = "INSERT INTO `envvar` (`server`, `var`, `value`) VALUES ('*', '".$params[1]."', '".$params[2]."');";
						if ($objConn->query($sql))
							$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] Global Envrionementvariable $fff'.$params[1].' $ff0setted to $fff'.$params[2].'$ff0!', $author));
						else
							$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] $i$f00Error setting global Envrionementvariable $fff'.$params[1].' $f00 to $fff'.$params[2].'$f00!', $author));	
					} else {
						$sql = "UPDATE `envvar` SET value = '".$params[2]."' WHERE server = '*' AND var = '".$params[1]."';";
						if ($objConn->query($sql))
							$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] Global Envrionementvariable $fff'.$params[1].' $ff0setted to $fff'.$params[2].'$ff0!', $author));
						else
							$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] $i$f00Error setting global Envrionementvariable $fff'.$params[1].' $f00 to $fff'.$params[2].'$f00!', $author));	
					}
				} else
				if (($params[0] == 'settodefault') or
					($params[0] == 'default')) {
					if (($params[1] = '*') or ($params[1] == '')) {
						$params[1] = '*';
						$WHERE_VAR = '';
					} else {
						$WHERE_VAR = ' AND var=\''.$params[1].'\' ';
					}
					$sql = "DELETE FROM `envvar` WHERE server = '".$aseco->server->serverlogin."'".$WHERE_VAR.";";
					if ($objConn->query($sql))
						$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] Setted $fff'.$params[1].' $ff0back to default/global!', $author));
					else
						$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] $i$f00Error setting  $fff'.$params[1].' $f00 to global!', $author));	
				} else 
				if (($params[0] == 'restoredefaults') or
					($params[0] == 'restore')) {
					if (zeroswitch_restoredefaults($aseco))
						$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] Default settings restored!', $author));
					else
						$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] $i$f00Default settings could not been restored!', $author));			
				} else
				if (($params[0] == 'show') or
					($params[0] == 'v')) {
					if ($params[1] == 'global')
						$sql = "SELECT value FROM `envvar` WHERE var = '".$params[2]."' AND server = '*';";
					else
					if  ($params[1] == 'local')
						$sql = "SELECT value FROM `envvar` WHERE var = '".$params[2]."' AND server = '".$aseco->server->serverlogin."';";
					if ($res = $objConn->query($sql)) {
						$res = $res->fetch_assoc();
						if ($res['value'] != '') 
							$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] Current setting of $fff'.$params[2].', '.$params[1].' $ff0is $fff'.$res['value'].'$ff0.', $author));
						else {
							$sql = "SELECT value FROM `envvar` WHERE var = '".$params[2]."' AND server = '*';";
							$res = $objConn->query($sql);
							$res = $res->fetch_assoc();
							$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] No local setting defined for $fff'.$params[2].'$ff0. Global setting is $fff'.$res['value'].'$ff0.', $author));
						}
					}
				} else
				if (($params[0] == 'deleteduplicates') or
					($params[0] == 'dd')) {
					$sql = "SELECT rellogin FROM `serverrelations` WHERE login = '".$aseco->server->serverlogin."';";
					$res = $objConn->query($sql);
					$relations = array();
					$sql = "DELETE FROM `serverrelations` WHERE login = '".$aseco->server->serverlogin."';";
					$objConn->query($sql);
					while ($relation  = $res->fetch_assoc()) {
						if (in_array($relation['rellogin'], $relations) == false) {
							$relations[] = $relation['rellogin'];
						}
					}
					foreach($relations as $rel) {
						$sql = "INSERT INTO `serverrelations` ( `login` , `rellogin` ) VALUES ('".$aseco->server->serverlogin."', '".$rel."');";
						$objConn->query($sql);
					}
					var_dump($relations);
				} else
				if ($params[0] == 'helpall') {
					$header = '{#black}/zs$g will manage your ZeroSwitch Plugin:';
					$help = array();
					$help[] = array('Welcome to the ZeroSwitch Plugin Help.');
					$help[] = array('The current running vesion is 1.1');
					$help[] = array('If you have problems or suggestions, please post it in the $l[http://www.tm-forum.com/viewtopic.php?f=127&t=19996]tm-forum$l.');
					$help[] = array('');
					$help[] = array('...', '{#black}helpall',
									'Displays this help information');
					$help[] = array('...', '{#black}helplist',
									'Displays the Help to adding/removing Servers to/from list');
					$help[] = array('...', '{#black}helpsettings',
									'Displays the Help to set the options of the plugin');
					$help[] = array('...', '{#black}helpsettings2',
									'Displays extended informations to the options');
					display_manialink($author, $header, array('Icons64x64_1', 'TrackInfo', -0.01), $help, array(1.2, 0.05, 0.35, 0.8), 'OK');
				} else
				if ($params[0] == 'helplist') {
					$header = '{#black}/zs$g will manage your ZeroSwitch Serverslist:';
					$help = array();
					$help[] = array('...', '{#black}helpall',
									'Will show you the main help of ZeroSwitch');
					$help[] = array('...', '{#black}helplist',
									'Displays this help information');
					$help[] = array('...', '{#black}ls {login}',
									'Adds a Server to the list');
					$help[] = array('...', '{#black}rs {login}',
									'Removes a Server from the list');
					$help[] = array('...', '{#black}cl {login}',
									'Crosslinks both Servers on each other');
					$help[] = array('...', '{#black}cls',
									'Clears the current list');
					$help[] = array('...', '{#black}dd',
									'Removes all duplicate entries from list');
					$help[] = array();
					$help[] = array('Type {#black}/zs helpsettings $fffor press the Menu button');
					$help[] = array('to get an introduction into the settings management.');
					display_manialink($author, $header, array('Icons64x64_1', 'TrackInfo', -0.01), $help, array(1.2, 0.05, 0.35, 0.8), 'OK');
				} else
				if ($params[0] == 'helpsettings') {
					$header = '{#black}/zs$g will manage your ZeroSwitch Plugin Settings:';
					$help = array();
					$help[] = array('You can set a global variable for all Servers using this plugin.');
					$help[] = array('But if you set a local variable for one Server, it wil replace the');
					$help[] = array('global setting (just on this Server!). Possible Variables are:');
					$help[] = array('{#black}ENTRY_COUNT , DISPLAY_STATE , REFRESH_TIME , HAS_RIGHTS');
					$help[] = array('You can set all of them via the menu buttons, but there are 2');
					$help[] = array('additional variables ({#black}LEFT_POS , TOP_POS$fff) which you');
					$help[] = array('just can set via the fallowing chat commands.');
					$help[] = array('You can find additional informations when typing {#black}/zs helpsettings2');
					$help[] = array('or press the Menu button.');
					$help[] = array();
					$help[] = array('...', '{#black}global {var} {val}',
									'Setts the global variabe {var} to {val}');
					$help[] = array('...', '{#black}local {var} {val}',
									'Setts the local variabe {var} to {val}');
					$help[] = array('...', '{#black}show global {var}',
									'Shows the current value of the global variabe {var}');
					$help[] = array('...', '{#black}show local {var}',
									'Shows the current value of the local variabe {var}');
					$help[] = array('...', '{#black}default',
									'Sets the local variables to the global value');
					$help[] = array('...', '{#black}restore',
									'Restores the default global and local values');
					$help[] = array();
					$help[] = array('Type {#black}/zs helplist $fffor press the Menu button');
					$help[] = array('to get an introduction into the Serverlist management.');
					display_manialink($author, $header, array('Icons64x64_1', 'TrackInfo', -0.01), $help, array(1.2, 0.05, 0.35, 0.8), 'OK');
				} else
				if ($params[0] == 'helpsettings2') {
					$header = '{#black}/zs$g will manage your ZeroSwitch Settings management:';
					$help = array();
					$help[] = array('...', '{#black}helpall',
									'Will show you the main help of ZeroSwitch');
					$help[] = array('...', '{#black}helpsettings2',
									'Displays this help information');
					$help[] = array();
					$help[] = array('', '{#black}ENTRY_COUNT', 'The entry count of the Serverslist (number)');
					$help[] = array('', '{#black}DISPLAY_STATE', 'The display state (never, race, score, always)');
					$help[] = array('', '{#black}REFRESH_TIME', 'The Time after which the plugin refreshs (number)');
					$help[] = array('', '{#black}HAS_RIGHTS', 'Rights, who can set values (masteradmin, admin, operator)');
					$help[] = array('', '{#black}LEFT_POS', 'Left position of the plugin (float)');
					$help[] = array('', '{#black}TOP_POS', 'Top position of the plugin (float)');
					$help[] = array();
					$help[] = array('Type {#black}/zs helpsettings $fffor press the Menu button');
					$help[] = array('to get an introduction into the settings management.');
					display_manialink($author, $header, array('Icons64x64_1', 'TrackInfo', -0.01), $help, array(1.2, 0.05, 0.35, 0.8), 'OK');
				} else
				if ($params[0] == 'showenvs') {
					$header = 'Current Envrionement-variables Datbase';
					$help = array();
					
					$res = $objConn->query("SELECT * FROM `envvar` WHERE server = '*' ;");
					$help[] = array('Globals:');
					$help[] = array('');
					while($row = $res->fetch_assoc()) {
						$help[] = array('', '{#black}'.$row['var'],
										$row['value']);
					}

					$res = $objConn->query("SELECT * FROM `envvar` WHERE server = '".$aseco->server->serverlogin."' ;");
					$help[] = array('');
					$help[] = array('');
					$help[] = array('Locals:');
					$help[] = array('');
					while($row = $res->fetch_assoc()) {
						$help[] = array('', '{#black}'.$row['var'],
										$row['value']);
					}
					display_manialink($author, $header, array('Icons64x64_1', 'TrackInfo', -0.01), $help, array(1.2, 0.05, 0.35, 0.8), 'OK');
				}
			} else {
				$aseco->addCall('ChatSendServerMessageToLogin', array('$ff0>> [ZeroSwitch] $i$f00You have to be at least $fff'.$zs_ENV['HAS_RIGHTS'].'$f00!', $author));	
				$aseco->console( "[ZeroSwitch] Not authorized user (".$author.") tried to change something!");
			}
		}
	}
	
	function zeroswitch_call($aseco) {
		global $counter,$mysql_conn,$zs_ENV;
		if ($mysql_conn) {
			zeroswitch_readenv($aseco);
			zeroswitch_writeInfo($aseco);
			$counter++;
			if ($counter == $zs_ENV['REFRESH_TIME']) {
				zeroswitch_Display($aseco);
				$counter = 0;
			}
		} else {
			$aseco->console( "[ZeroSwitch] Fatal Error: MYSQL Server closed connection!");
		}
	}

	function zeroswitch_Display($aseco) {
		global $objConn, $zs_ENV,$display;
		
		
		try {
			if ($display) {
				$query = "SELECT count(*) as count FROM serverstats, serverrelations WHERE '".$aseco->server->serverlogin."' = serverrelations.login AND serverrelations.rellogin = serverstats.login";
				$res = $objConn->query($query);
				$row = $res->fetch_assoc();
				$count = $row['count'];
				$query = "SELECT serverstats.* FROM serverstats, serverrelations WHERE '".$aseco->server->serverlogin."' = serverrelations.login AND serverrelations.rellogin = serverstats.login";
				$res = $objConn->query($query);
				$aseco->client->query('GetLadderServerLimits');
				$ladder = $aseco->client->getResponse();
				if ($aseco->client->isError()) {
					trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
				} else {
					$min = floor($ladder['LadderServerLimitMin'] / 1000);
					$max = floor($ladder['LadderServerLimitMax'] / 1000);
				}
				if ($min == 0) $min = "0".$min;
					
				if ($count < $zs_ENV['ENTRY_COUNT']) {
					$cnt = $count;
				} else {
					$cnt = $zs_ENV['ENTRY_COUNT'];
				}
				$xml = '<manialinks><manialink id="3487823">';
				$xml .= '<frame>';	
				$xml .= '<quad  posn="'.($zs_ENV['LEFT_POS']-1).' '.($zs_ENV['TOP_POS']+0.35).' 2" sizen="31.5 '.($cnt*1.5+1.0-0.25).'" halign="left" valign="bottom" style="Bgs1InRace" substyle="NavButton" />';
				$serverName = preg_replace("\\$(s|>|<|o|l|h)","",$aseco->server->name);
				$serverName = preg_replace("\\[[A-Za-z0-9]+\.[A-Za-z0-9]+\.[A-Za-z0-9]+\]","", $serverName);
				$serverName = preg_replace("\\[[A-Za-z0-9]+\.[A-Za-z0-9]+\-[A-Za-z0-9]+\.[A-Za-z0-9]+\.[A-Za-z0-9]+\]","", $serverName);
				
				$xml .= '<quad style="Icons64x64_1" valign="bottom" halign="right" substyle="ArrowGreen" posn="'.($zs_ENV['LEFT_POS']+6.6).' '.($zs_ENV['TOP_POS']+$cnt*1.5+1.0-0.30).' 3" sizen="2.5 2.5"/>';
				$xml .= '<quad sizen="24.5 2.3" valign="bottom" posn="'.($zs_ENV['LEFT_POS']+30.5).' '.($zs_ENV['TOP_POS']+$cnt*1.5+1.0-0.25).' 3" halign="right" style="Bgs1InRace" substyle="NavButton" />';
				$xml .= '<format textsize="1.5"/>';
				$xml .= '<label style="TextPlayerCardName" scale="0.7" sizen="20 1.5" valign="bottom" posn="'.($zs_ENV['LEFT_POS']+6.5).' '.($zs_ENV['TOP_POS']+0.5 +(1.5*$cnt)+0.3).' 3" halign="left" text="'.$serverName.'" />';
				$xml .= '<label sizen="23 1.5" scale="0.3" style="TextRaceChrono" valign="bottom" posn="'.($zs_ENV['LEFT_POS']+27.0).' '.($zs_ENV['TOP_POS']+0.5 +(1.5*$cnt)+0.3).' 3" halign="right" text="$s$888'.$min.'k-'.$max.'k" />';	
				$xml .= '<quad style="Icons128x128_1" valign="bottom" halign="left" substyle="LadderPoints" posn="'.($zs_ENV['LEFT_POS']+27.2).' '.($zs_ENV['TOP_POS']-0.3 +(1.5*$cnt)+0.3).' 3" sizen="2.5 3.8"/>';
						
				for($i = 0; $i < $cnt; $i++) {
					$server = $res->fetch_assoc();
					
					$serverName = preg_replace("\\$(s|>|<|o|l|h)","",$server['serverName']);
					$serverName = preg_replace("\\[[A-Za-z0-9]+\.[A-Za-z0-9]+\.[A-Za-z0-9]+\]","", $serverName);
					$serverName = preg_replace("\\[[A-Za-z0-9]+\.[A-Za-z0-9]+\-[A-Za-z0-9]+\.[A-Za-z0-9]+\.[A-Za-z0-9]+\]","", $serverName);
					
					if ($server['maxPlayers']-$server['playerCount'] > 1) {
						$method = 'join';
					} else {
						$method = 'spectate';
					}
					
					if (($server['ladderMin'] == "0") OR ($server['ladderMin'] == "")) {
						$ladmin = "00";
					} else {
						$ladmin = floor($server['ladderMin']/1000);
					}
					
					$ladmax = floor($server['ladderMax']/1000);
					
					if ($server['playerCount'] < $server['maxPlayers']) {
						$playercolor = '$0f0';
					} else {
						$playercolor = '$c00';
					}
					
					if ($server['spectatorCount'] < $server['maxSpectators']) {
						$spectatorcolor = '$fff';
					} else {
						$spectatorcolor = '$c00';
					}

					$xml .= '<format textsize="1.5"/>';
					$xml .= '<label scale="0.3" style="TextRaceChrono"  sizen="7 1.5" valign="bottom" posn="'.($zs_ENV['LEFT_POS']+1.7).' '.($zs_ENV['TOP_POS']+0.5 +(1.5*$i)).' 3" halign="right" text="$s'.$playercolor.$server['playerCount'].'" />';			
					$xml .= '<quad style="Icons128x128_1" valign="bottom" halign="left" substyle="Vehicles" posn="'.($zs_ENV['LEFT_POS']+1.6).' '.($zs_ENV['TOP_POS']+0.4 +(1.5*$i)).' 3" sizen="1.7 2.4" />';
					$xml .= '<label scale="0.3" style="TextRaceChrono"  sizen="7 1.5" valign="bottom" posn="'.($zs_ENV['LEFT_POS']+5.0).' '.($zs_ENV['TOP_POS']+0.5 +(1.5*$i)).' 3" halign="right" text="$s'.$spectatorcolor.$server['spectatorCount'].'" />';			
					$xml .= '<quad style="BgRaceScore2" valign="bottom" halign="left" substyle="Tv" posn="'.($zs_ENV['LEFT_POS']+5.0).' '.($zs_ENV['TOP_POS']+0.5 +(1.5*$i)).' 3" sizen="1.5 2.0" />';
					$xml .= '<label manialink="tmtp://#'.$method.'='.$server['login'].'" style="TextPlayerCardName" scale="0.7" sizen="20 1.5" valign="bottom" posn="'.($zs_ENV['LEFT_POS']+6.5).' '.($zs_ENV['TOP_POS']+0.5 +(1.5*$i)).' 3" halign="left" text="$s'.$serverName.'" />';
					$xml .= '<label scale="0.3" style="TextRaceChrono" sizen="23 1.5" valign="bottom" posn="'.($zs_ENV['LEFT_POS']+27.0).' '.($zs_ENV['TOP_POS']+0.5 +(1.5*$i)).' 3" halign="right" text="$s$888'.$ladmin.'k$888-$888'.$ladmax.'k" />';	
					$xml .= '<quad style="Icons128x128_1" valign="bottom" halign="left" substyle="LadderPoints" posn="'.($zs_ENV['LEFT_POS']+27.2).' '.($zs_ENV['TOP_POS']-0.3 +(1.5*$i)).' 3" sizen="2.5 3.8" />';
				}
					
				$xml .= '<format textsize="1"/>';
				$xml .= '<label sizen="20.5 3.5"  posn="'.($zs_ENV['LEFT_POS']+1.5).' '.($zs_ENV['TOP_POS']+0.6).' 3" scale="0.8" halign="left" text="$s$fff$oClick to join other Server!"/>';
				$xml .= '<label sizen="20.5 3.5"  posn="'.($zs_ENV['LEFT_POS']+1.5).' '.($zs_ENV['TOP_POS']-0.6).' 3" scale="0.6" valign="top" halign="left" text="$s$ff0$o$l[http://ping-kings.com]ZeroSwitch v1.1$l"/>';
				$xml .= '</frame>';
			} else {
				$xml = '<manialinks><manialink id="3487823">';
			}
			$xml .= '</manialink></manialinks>';
			$aseco->client->query("SendDisplayManialinkPage", $xml, 0, false);	
		} catch (Exception $e) {
			$aseco->console( "[ZeroSwitch] Fatal Error: Could not show list!");
		}
	}
	function zeroswitch_writeinfo($aseco) {
		global $objConn,$mysql_conn;
		if ($mysql_conn) {
			try {
			$servername = getInfofromserver($aseco->client, 'GetServerName');
			$login = getInfofromserver($aseco->client, 'GetSystemInfo', 'ServerLogin');
			$players = getInfofromserver($aseco->client, 'GetPlayerList', NULL , 255, 0, false,true, 'IsSpectator', False);
			$spectators = getInfofromserver($aseco->client, 'GetPlayerList', NULL , 255, 0, false,true, 'IsSpectator', True);
			$maxplayers = getInfofromserver($aseco->client, 'GetMaxPlayers', 'CurrentValue');
			$maxspectators = getInfofromserver($aseco->client, 'GetMaxSpectators', 'CurrentValue');
			$laddermin = getInfofromserver($aseco->client, 'GetLadderServerLimits', 'LadderServerLimitMin');
			$laddermax = getInfofromserver($aseco->client, 'GetLadderServerLimits', 'LadderServerLimitMax');
			$query = "SELECT count(*) as cnt FROM `serverstats` WHERE login='".$login."';";
			$res = $objConn->query($query);
			$row = $res->fetch_assoc();
			$count = $row['cnt'];

			if ($count == 0) $query = "INSERT INTO `serverstats` (`login`, `serverName`, `ladderMin`, `ladderMax`, `playerCount`, `maxPlayers`, `spectatorCount`, `maxSpectators`) VALUES ('".$login."', '".$servername."', ".$laddermin.", ".$laddermax.", ".$players.", ".$maxplayers.", ".$spectators.", ".$maxspectators.");";
			else $query = 'UPDATE `serverstats` SET  `serverName` = \''.$servername.'\', `ladderMin` = \''.$laddermin.'\', `ladderMax` = \''.$laddermax.'\', `playerCount` = \''.$players.'\', `maxPlayers` = \''.$maxplayers.'\', `spectatorCount` = \''.$spectators.'\', `maxSpectators` = \''.$maxspectators.'\' WHERE `serverstats`.`login`  = \''.$login.'\';';
			$res = $objConn->query($query);
			} catch (Exception $e) {
				$aseco->console("[ZeroSwitch] Fatal Error: Unhandeled error at storing data into database!");
			}
		} else {
			$aseco->console("[ZeroSwitch] Fatal Error: MYSQL Server closed connection!");
		}
	}
?>