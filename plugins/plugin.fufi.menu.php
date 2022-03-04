<?php

/**
 * Fufi Menu Plugin for XASECO by oorf-fuckfish
 * Version 0.32
 *
 * Sorry for some redundant functions, but the object oriented approach brought some problems I wasn't able to solve for now.
 */

Aseco::registerEvent('onPlayerConnect', 'fufiMenu_playerConnect');
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'fufiMenu_handleClick');
Aseco::registerEvent('onStartup', 'fufiMenu_startup');
Aseco::registerEvent('onNewChallenge', 'fufiMenu_newChallenge');

global $fufiMenu;

/**
 * The menu which holds the entries and the necessary functions
 *
 */
class FufiMenu {

	private $posx, $posy, $width, $height, $separatorheight, $menutimeout;
	private $firstChallenge;
	private $entries;
	private $xmlRaw;
	private $styles;
	private $pluginList;
	private $manialinkID;
	private $uniqueID;
	private $blocks;
	var $position, $caption, $id, $horientation, $vorientation;
	var $Aseco;
	var $entriesList;
	var $gameinfo;
	var $gameinfonext;

	/**
	 * Creates a new menu from a given XML
	 *
	 * @param String $xmlpath
	 * @return FufiMenu
	 */
	function FufiMenu($xmlpath){
		$this->manialinkID = 383;
		$this->uniqueID = 1001;
		$this->id = '0000';
		$this->xmlRaw = simplexml_load_file($xmlpath);
		$this->firstChallenge = true;

		$plugins = simplexml_load_file('plugins.xml');
		foreach ($plugins->plugin as $plugin){
			$this->pluginList .= strval($plugin).'|';
		}

		$this->entries = array();
	}

	/**
	 * Returns a unique ID for a menu entry
	 *
	 * @return unknown
	 */
	function getUniqueID(){
		return $this->uniqueID++;
	}

	/**
	 * Initializes the menu
	 *
	 */
	function init(){
		$this->loadSettings();
		$this->loadStyles();
		$this->loadEntries();
	}

	/**
	 * Loads the settings from the xml config file
	 *
	 */
	function loadSettings(){
		$position = explode(' ', strval($this->xmlRaw->position));
		$size = explode(' ', strval($this->xmlRaw->size));
		$this->separatorheight = floatval($this->xmlRaw->separatorheight);
		$this->posx = $position[0];
		$this->posy = $position[1];
		$this->width = $size[0];
		$this->height = $size[1];
		$this->horientation = intval($this->xmlRaw->horizontalorientation);
		$this->vorientation = intval($this->xmlRaw->verticalorientation);
		$this->caption = strval($this->xmlRaw->menu_caption);
		$this->menutimeout = intval($this->xmlRaw->menutimeout);
		$this->blocks = $this->getXMLTemplateBlocks(file_get_contents('./plugins/fufi/fufi_menu.xml'));
	}

	/**
	 * Loads the styles from within the xml config file
	 *
	 */
	function loadStyles(){
		$this->styles = array();

		$elements = array('menubutton', 'menubackground', 'menuentry', 'menuentryactive', 'menugroupicon', 'menuicon', 'menuactionicon', 'menuhelpicon', 'separator', 'indicatorfalse', 'indicatortrue', 'indicatoronhold');

		foreach ($elements as $element){
			$this->styles[$element] = array();
			$node = $this->xmlRaw->styles->$element;
			$this->styles[$element]['style'] = strval($node['style']);
			$this->styles[$element]['substyle'] = strval($node['substyle']);
		}
	}

	/**
	 * Loads the menu entries from the config file and creates the menu structure
	 *
	 */
	function loadEntries(){
		foreach ($this->xmlRaw->entries->entry as $entry){
			if ($this->dependenciesMet(strval($entry['dependencies']), strval($entry['globalvariable']))){
				$fmentry = new FufiMenuEntry($entry, $this->id);
				$this->entries[] = $fmentry;
				$this->entriesList[] = $fmentry;
			}
		}
	}

	/**
	 * Adds a new entry (can be used by plugin authors to add groups and entries into the existing menu)
	 *
	 * @param String $insertInGroup
	 * @param String $entryPoint
	 * @param String $insertAfter
	 * @param String $caption
	 * @param String $unique
	 * @param String $chatcmd
	 * @param String $chatcmdparams
	 * @param String $ability
	 * @param String $indicator
	 * @param String $params
	 * @param String $type
	 * @param String $rights
	 */
	function addEntry($insertInGroup, $entryPoint, $insertAfter=true, $caption, $unique, $chatcmd='', $chatcmdparams='', $ability='', $indicator='', $params='', $type='', $rights=''){
		if ($caption!='') $caption = " caption='".$caption."'";
		if ($unique!='') $unique = " unique='".$unique."'";
		if ($chatcmd!='') $chatcmd = " chatcmd='".$chatcmd."'";
		if ($chatcmdparams!='') $chatcmdparams = " chatcmdparams='".$chatcmdparams."'";
		if ($ability!='') $ability = " ability='".$ability."'";
		if ($indicator!='') $indicator = " indicator='".$indicator."'";
		if ($params!='') $params = " params='".$params."'";
		if ($type!='') $type = " type='".$type."'";
		if ($rights!='') $rights = " rights='".$rights."'";
		$xml = "<entry".$caption.$unique.$chatcmd.$chatcmdparams.$ability.$indicator.$params.$type.$rights."/>\n";
		$xmlbranch = simplexml_load_string($xml);
		$parent = $this->getEntryByUniqueKey($insertInGroup);
		if (!$parent) {
			$this->Aseco->console('FufiMenu: External plugin tried to add an entry to non-existing group "'.$insertInGroup.'"');
			return;
		}
		$fmentry = new FufiMenuEntry($xmlbranch, $parent->id);
		$parent->insertEntry($entryPoint, $insertAfter, $fmentry);
		$this->entriesList[] = $fmentry;
	}

	/**
	 * Adds a separator to the menu (can be used by plugin authors)
	 *
	 * @param String $insertInGroup
	 * @param String $entryPoint
	 * @param boolean $insertAfter
	 * @param String $unique
	 */
	function addSeparator($insertInGroup, $entryPoint, $insertAfter, $unique){
		$this->addEntry($insertInGroup, $entryPoint, $insertAfter, '', $unique, '', '', '', '', '', 'separator');
	}

	/**
	 * Internally used for adding an entry
	 *
	 * @param String $insertAfter
	 * @param FufiMenuEntry $entry
	 */
	function insertEntry($entryPoint, $insertAfter, $entry){
		$inserted = false;
		if (!$entryPoint){
			if ($insertAfter){
				$this->entries[] = $entry;
				$inserted = true;
			} else {
				$this->entries = array_merge(array($entry), $this->entries);
				$inserted = true;
			}
		} else {
			$entries = array();
			if ($insertAfter){
				foreach ($this->entries as $ent){
					$entries[] = $ent;
					if ($ent->unique == $entryPoint){
						$entries[] = $entry;
						$inserted = true;
					}
				}
				$this->entries = $entries;
			} else {
				foreach ($this->entries as $ent){
					if ($ent->unique == $entryPoint){
						$entries[] = $entry;
						$inserted = true;
					}
					$entries[] = $ent;
				}
				$this->entries = $entries;
			}
		}

		if (!$inserted){
			if ($insertAfter){
				$this->entries[] = $entry;
				$inserted = true;
				$str = 'beginning.';
			} else {
				$this->entries = array_merge(array($entry), $this->entries);
				$inserted = true;
				$str = 'end.';
			}

			$this->Aseco->console('FufiMenu: External plugin tried to insert after an invalid key "'.$insertAfter.'", entry was inserted at the '.$str);
		}
	}

	/**
	 * Checks whether a menu entry will be inserted into the menu (based on the XAseco configuration)
	 *
	 * @param String $dependencies
	 * @param String $globalvariable
	 * @return boolean
	 */
	function dependenciesMet($dependencies, $globalvariable){
		if (!$dependencies && !$globalvariable) return true;
		$dependencies = explode(',', $dependencies);
		$result = true;
		foreach ($dependencies as $dependency){
			if (trim($dependency)){
				$result = $result && strstr($this->pluginList, trim($dependency));
			}
		}
		if ($globalvariable){
			eval('global $'.$globalvariable.'; $active = $'.$globalvariable.';');
			$result = $result && $active;
		}
		return $result;
	}

	/**
	 * Extracts the template blocks from the given xml file and returns an array
	 *
	 * @param String $xml
	 * @return array
	 */
	function getXMLTemplateBlocks($xml){
		$result = array();
		$xml_ = $xml;
		while (strstr($xml_, '<!--start_')){
			$xml_ = substr($xml_, strpos($xml_, '<!--start') + 10);
			$title = substr($xml_, 0, strpos($xml_, '-->'));
			$result[$title]= trim($this->getXMLBlock($xml, $title));
		}
		return $result;
	}

	/**
	 * Extracts a specific marked block from the manialink XML templates
	 * in the folder "./plugins/fufi"
	 *
	 * @param String $haystack
	 * @param String $caption
	 * @return The requested block in a String
	 */
	function getXMLBlock($haystack, $caption){
		$startStr = '<!--start_'.$caption.'-->';
		$endStr = '<!--end_'.$caption.'-->';
		$haystack = substr($haystack, strpos($haystack, $startStr) + strlen($startStr));
		$haystack = substr($haystack, 0, strpos($haystack, $endStr));
		return ($haystack);
	}

	/**
	 * Sends the default menu button to a specified login
	 *
	 * @param String $login
	 */
	function sendMenuButtonToLogin($login){

		$header = str_replace(array('%menuid%', '%framepos%'), array($this->manialinkID.'0000', '0 0 1'), $this->blocks['header']);
		$footer = $this->blocks['footer'];
		$content = str_replace(array('%size%', '%pos%', '%poslabel%', '%style%', '%substyle%', '%action%', '%text%'), array($this->width.' '.$this->height, $this->posx.' '.$this->posy.' 1', ($this->posx+$this->width/2).' '.($this->posy-($this->height/2-0.1)).' 1', $this->styles['menubutton']['style'], $this->styles['menubutton']['substyle'], $this->manialinkID.'0000', $this->caption), $this->blocks['menubutton']);
		$icon = str_replace(array('%x%', '%y%', '%style%', '%substyle%'), array($this->posx + 1, $this->posy-0.2, $this->styles['menuicon']['style'], $this->styles['menuicon']['substyle']), $this->blocks['icon']);
		$xml = $header.$content.$icon.$footer;

		if ($login == ''){
			if ($this->firstChallenge){
				$this->firstChallenge = false;
				if ($this->Aseco->debug) $this->Aseco->console('[FufiMenu] sending menu button to all');
				$this->Aseco->client->addCall('SendDisplayManialinkPage', array($xml, 0, false));
			}
		} else {
			if (!$this->firstChallenge){
				if ($this->Aseco->debug) $this->Aseco->console('[FufiMenu] sending menu button to login: '.$login);
				$this->Aseco->client->addCall('SendDisplayManialinkPageToLogin', array($login, $xml, 0, false));
			}
		}
	}

	/**
	 * Checks whether to handle clicks or not and executes the commands
	 *
	 * @param int $playerid
	 * @param String $login
	 * @param String $action
	 */
	function handleClick($playerid, $login, $action){
		if (!(substr($action, 0, 3) == $this->manialinkID)) return;
		$action = substr($action, 3);
		$this->executeAction($playerid, $login, $action);
	}

	/**
	 * Executes the clicked commands
	 *
	 * @param int $playerid
	 * @param String $login
	 * @param String $action
	 */
	function executeAction($playerid, $login, $action){
		//$action = 1124;
		if ($action=='0000'){
			$this->displayMenu($login, '0000');
		} else if ($action=='0001'){
			$this->closeMenu($login);
		} else {
			$entry = $this->getEntryByID($action);
			if ($entry && $entry->isGroup()){
				$this->displayMenu($login, $action);
			} else {
				//fake a chat command
				$param = '';
				if ($entry->chatcmdparams!=''){
					$chatparams = explode('/', $entry->chatcmdparams);
					if (function_exists($entry->indicator)){
						$params = array();
						if ($entry->params!=''){
							if (strstr($entry->$params, ',')) $params = explode(',', $entry->params);
							else $params[] = $entry->params;
						}
						$params = array_merge(array($this->Aseco, $login), $params);
						$indicator = call_user_func_array($entry->indicator, $params);
						$param = ' '.$chatparams[!$indicator];
					}
				}

				$chat = array();
				$chat[0] = $playerid;
				$chat[1] = $login;
				$chat[2] = $entry->chatcmd.$param;
				$chat[3] = true;
				$this->Aseco->playerChat($chat);
				if ($entry->indicator == '') $this->closeMenu($login);
				else ($this->updateMenu($login, $action));
			}
		}
	}

	/**
	 * Returns a menu entry with the given ID, returns "false" if the ID doesn't exist
	 *
	 * @param int $id
	 * @return FufiMenuEntry
	 */
	function getEntryByID($id){
		if ($id=='0000') return $this;
		foreach ($this->entriesList as $entry){
			if ($entry->id == $id) return $entry;
		}
		return false;
	}

	/**
	 * Returns a menu entry with the given unique key, returns the root menu if the key doesn't exist
	 *
	 * @param String $unique
	 * @return FufiMenuEntry
	 */
	function getEntryByUniqueKey($unique){
		if ($unique=='') return $this;
		foreach ($this->entriesList as $entry){
			if ($entry->unique == $unique) return $entry;
		}
		return false;
	}

	/**
	 * Returns all entries in this menu which can be accessed by the player with the given login, removes empty groups
	 *
	 * @param String $login
	 * @return FufiMenuEntry[]
	 */
	function getValidEntries($login){
		$result = array();
		$player = $this->Aseco->server->players->getPlayer($login);

		foreach ($this->entries as $entry){
			if (($entry->rights == $player->rights) || (!$entry->rights)){
				if (strstr($entry->chatcmd, '/admin')){
					$params = explode(' ', $entry->chatcmd);
					$cmd = $params[1];
					if ($this->Aseco->allowAbility($player, $cmd)){
						$result[] = $entry;
					}
				} else if (strstr($entry->chatcmd, '/jfreu')){
					if ($this->Aseco->allowAbility($player, 'chat_jfreu')){
						$result[] = $entry;
					}
				} else {
					if ($entry->ability != ''){
						if ($this->Aseco->allowAbility($player, $entry->ability)){
							$result[] = $entry;
						}
					} else {
						$result[] = $entry;
					}
				}
			}
		}
		//clean out empty groups
		$resultnew = array();
		foreach ($result as $entry){

			if (!($entry->isGroup()) || !$this->groupIsEmpty($entry, $login) || $entry->type=='separator'){
				$resultnew[] = $entry;
			}
		}
		$result = $resultnew;

		return $result;
	}

	/**
	 * Closes the menu for the given login
	 *
	 * @param String $login
	 */
	function closeMenu($login){
		$xml = '<?xml version="1.0" encoding="UTF-8"?><manialinks><manialink id='.$this->manialinkID.'0001'.'></manialink></manialinks>';
		$this->Aseco->client->addCall('SendDisplayManialinkPageToLogin', array($login, $xml, 0, false));
	}

	/**
	 * Updates a specific menu for the given login
	 *
	 * @param String $login
	 * @param int $id
	 */
	function updateMenu($login, $id){
		$ids = $this->getWindowIDs($id);
		$this->displayMenu($login, $ids[count($ids)-2]);
	}

	/**
	 * Displays a specific menu to the given login
	 *
	 * @param String $login
	 * @param int $id
	 */
	function displayMenu($login, $id){

		//update gameinfo
		$this->Aseco->client->query('GetCurrentGameInfo', 1);
		if (!$this->Aseco->client->isError()) $this->gameinfo = $this->Aseco->client->getResponse();
		$this->Aseco->client->query('GetNextGameInfo', 1);
		if (!$this->Aseco->client->isError()) $this->gameinfonext = $this->Aseco->client->getResponse();

		$ids = $this->getWindowIDs($id);

		if ($this->horientation == 1){
			$posx = $this->posx + $this->width;
		} else {
			$posx = $this->posx;
		}
		$posy = $this->posy;
		$width = $this->width;
		$height = $this->height;
		$content = '';
		foreach ($ids as $id){
			$itemoffset = 0;
			if (!isset($oldId)){
				$closeaction = $this->manialinkID.'0001';
			} else {
				$closeaction = $this->manialinkID.$oldId;
			}
			if (isset($entries)) $itemoffset = $this->getItemOffset($entries, $id);
			if ($id=='0000')
				$entries = $this->getValidEntries($login);
			else {
				$entry = $this->getEntryByID($id);
				$entries = $entry->getValidEntries($login);
			}
			$windowwidth = $this->getWindowWidth($entries);

			if ($this->horientation == 1){
				$windowx = $posx + 0.5;
			} else {
				$windowx = $posx - $windowwidth - 0.5;
			}

			$countEntries = count($entries);
			$windowheight = $this->getWindowHeight($entries);
			$windowy = $posy+$itemoffset;
			if ($this->vorientation == 1){
				if ($windowy > $this->posy) $windowy = $this->posy;
			} else {
				if ($windowy - $windowheight < ($this->posy - $this->height)) $windowy = $this->posy - $this->height + $windowheight;
			}

			$content .= str_replace(array('%size%', '%pos%', '%style%', '%substyle%'), array($windowwidth.' '.$windowheight, $windowx.' '.$windowy.' 23', $this->styles['menubackground']['style'], $this->styles['menubackground']['substyle']), $this->blocks['menuwindow']);

			$content .= '<frame posn="'.($windowx+0.5).' '.($windowy-0.5).'">'.str_replace(array('%width%', '%lblwidth%', '%indx%'), array($windowwidth-1, $windowwidth-5, $windowwidth-3), $this->getMenuWindow($login, $entries, $ids, $this->getEntryByID($id)->caption)).'</frame>';

			$closebuttoncontainer = str_replace(array('%size%', '%pos%', '%style%', '%substyle%'), array('2 2', ($windowx+$windowwidth-2.6).' '.($windowy-0.4).' 23.1', $this->styles['menubackground']['style'], $this->styles['menubackground']['substyle']), $this->blocks['menuwindow']);
			$closebutton = str_replace(array('%pos%', '%action%'), array(($windowx+$windowwidth-1.6).' '.($windowy-1.4), $closeaction), $this->blocks['close']);

			$content .= $closebutton;

			if ($this->horientation == 1){
				$posx = $windowx + $windowwidth;
			} else {
				$posx = $windowx;
			}
			$posy = $windowy;
			$oldId = $id;
		}

		$header = str_replace(array('%menuid%', '%framepos%'), array($this->manialinkID.'0001', '0 0 1'), $this->blocks['header']);
		$footer = $this->blocks['footer'];

		$xml = $header.$content.$footer;
		$this->Aseco->client->addCall('SendDisplayManialinkPageToLogin', array($login, $xml, $this->menutimeout, false));
	}

	/**
	 * Returns the vertical offset for a submenu
	 *
	 * @param FufiMenuEntry[] $entries
	 * @param int $id
	 * @return int
	 */
	function getItemOffset($entries, $id){
		$result = 0;
		foreach ($entries as $entry){
			if ($entry->id == $id) return $result;
			if ($entry->type=='separator') $result -= $this->separatorheight;
			else $result -= 2;
		}
		return $result;
	}

	/**
	 * Returns an array of all menus that lead to the active submenu
	 *
	 * @param int $id
	 * @return int[]
	 */
	function getWindowIDs($id){
		$result = array();
		$result[] = $id;
		while ($id!='0000'){
			$entry = $this->getEntryByID($id);
			$id = $entry->parentid;
			$result[] = $id;
		}
		return array_reverse($result);
	}

	/**
	 * Returns the height of a menu window based on the entries
	 *
	 * @param FufiMenuEntry[] $entries
	 * @return float
	 */
	function getWindowHeight($entries){
		$result = 0;
		foreach ($entries as $entry){
			if ($entry->type=='separator') $result += $this->separatorheight;
			else $result += 2;
		}
		return $result+3;
	}

	/**
	 * Calculates and returns a width of a menu window based on the length of the captions
	 *
	 * @param FufiMenuEntry[] $entries
	 * @return float
	 */
	function getWindowWidth($entries){
		$longestCaption = 0;
		foreach ($entries as $entry){
			if (strlen($entry->caption) > $longestCaption) $longestCaption = strlen($entry->caption);
		}
		return max(10, ceil($longestCaption/2)+7);
	}

	/**
	 * Creates and returns the XML string of a menu window
	 *
	 * @param String $login
	 * @param FufiMenuEntry[] $entries
	 * @param int[] $ids
	 * @param String $caption
	 * @return String
	 */
	function getMenuWindow($login, $entries, $ids, $caption){

		$menuentry = $this->blocks['menuentry'];
		$menucaption = $this->blocks['menuwindowcaption'];
		$groupicon = $this->blocks['icon'];
		$indicatoricon = $this->blocks['indicator'];
		$y = -2;

		//display caption
		$xml = str_replace(array('%height%', '%labely%', '%caption%'),
		array(1.9, -0.7, trim($caption)), $menucaption);
		$result = $xml;

		foreach ($entries as $entry){
			if (in_array($entry->id, $ids)){
				$style = $this->styles['menuentryactive']['style'];
				$substyle = $this->styles['menuentryactive']['substyle'];
				$prefix = '$000';
			} else {
				$style = $this->styles['menuentry']['style'];
				$substyle = $this->styles['menuentry']['substyle'];
				$prefix = '$fff';
			}
			if (!($entry->type=='separator')){
				$xml = str_replace(array('%height%', '%y%', '%labely%', '%style%', '%substyle%', '%action%', '%caption%'),
				array(1.9, $y, $y-0.9, $style, $substyle, $this->manialinkID.$entry->id, $prefix.$entry->caption), $menuentry);
				if ($entry->isGroup() && !($entry->type=='help')){
					$xml .= str_replace(array('%x%', '%y%', '%style%', '%substyle%'), array('0.1', $y, $this->styles['menugroupicon']['style'], $this->styles['menugroupicon']['substyle']), $groupicon);
				} else {
					if ($entry->type=='help'){
						$xml .= str_replace(array('%x%', '%y%', '%style%', '%substyle%'), array('0.1', $y, $this->styles['menuhelpicon']['style'], $this->styles['menuhelpicon']['substyle']), $groupicon);
					} else {
						if (substr($entry->caption, 0, 3)!='...') $xml .= str_replace(array('%x%', '%y%', '%style%', '%substyle%'), array('0.3', $y-0.1, $this->styles['menuactionicon']['style'], $this->styles['menuactionicon']['substyle']), $groupicon);
					}
				}
				if ($entry->indicator!=''){
					if (function_exists($entry->indicator)){
						$params = array();
						if ($entry->params!=''){
							if (strstr($entry->params, ',')) $params = explode(',', $entry->params);
							else $params[] = $entry->params;
						}
						$params = array_merge(array($this->Aseco, $login), $params);
						$indicator = call_user_func_array($entry->indicator, $params);
						if ($indicator==0){
							$xml .= str_replace(array('%y%', '%style%', '%substyle%'), array($y, $this->styles['indicatorfalse']['style'], $this->styles['indicatorfalse']['substyle']), $indicatoricon);
						} else if ($indicator==1){
							$xml .= str_replace(array('%y%', '%style%', '%substyle%'), array($y, $this->styles['indicatortrue']['style'], $this->styles['indicatortrue']['substyle']), $indicatoricon);
						} else if ($indicator==2){
							$xml .= str_replace(array('%y%', '%style%', '%substyle%'), array($y, $this->styles['indicatoronhold']['style'], $this->styles['indicatoronhold']['substyle']), $indicatoricon);
						}
					} else $this->Aseco->console('FufiMenu: Indicator function "'.$entry->indicator.'" does not exist.');
				}
				$result .= $xml;
				$y -= 2;
			} else {
				$y -= $this->separatorheight;
			}
		}
		return $result;
	}

	/**
	 * Returns whether a group is empty or not
	 *
	 * @param FufiMenuEntry $entry
	 * @param String $login
	 * @return boolean
	 */
	function groupIsEmpty($entry, $login){
		foreach ($entry->getValidEntries($login) as $ent){
			if ($ent->type!='separator') return false;
		}
		return true;
	}

}

/**
 * The basic class for a menu entry
 *
 */
class FufiMenuEntry{

	private $entries;
	private $xmlbranch;
	var $caption, $indicator, $params, $chatcmd, $chatcmdparams, $type, $ability, $id, $parentid, $unique, $rights;

	/**
	 * Creates a new instance of FufiMenuEntry with the help of an XML branch
	 *
	 * @param String $xmlbranch
	 * @param int $parentid
	 * @return FufiMenuEntry
	 */
	function FufiMenuEntry($xmlbranch, $parentid){
		$this->entries = array();
		$this->xmlbranch = $xmlbranch;
		$this->parentid = $parentid;
		$this->getDetails();
		$this->loadEntries();
	}

	/**
	 * Internally used for adding an entry
	 *
	 * @param String $insertAfter
	 * @param FufiMenuEntry $entry
	 */
	function insertEntry($entryPoint, $insertAfter, $entry){
		global $aseco;
		$inserted = false;
		if (!$entryPoint){
			if ($insertAfter){
				$this->entries[] = $entry;
				$inserted = true;
			} else {
				$this->entries = array_merge(array($entry), $this->entries);
				$inserted = true;
			}
		} else {
			$entries = array();
			if ($insertAfter){
				foreach ($this->entries as $ent){
					$entries[] = $ent;
					if ($ent->unique == $entryPoint){
						$entries[] = $entry;
						$inserted = true;
					}
				}
				$this->entries = $entries;
			} else {
				foreach ($this->entries as $ent){
					if ($ent->unique == $entryPoint){
						$entries[] = $entry;
						$inserted = true;
					}
					$entries[] = $ent;
				}
				$this->entries = $entries;
			}
		}

		if (!$inserted){
			if ($insertAfter){
				$this->entries[] = $entry;
				$inserted = true;
				$str = 'beginning.';
			} else {
				$this->entries = array_merge(array($entry), $this->entries);
				$inserted = true;
				$str = 'end.';
			}

			$aseco->console('FufiMenu: External plugin tried to insert after an invalid key "'.$insertAfter.'", entry was inserted at the '.$str);
		}
	}

	/**
	 * Loads the entry details from the xml branch
	 *
	 */
	function getDetails(){
		global $fufiMenu;
		$this->caption = strval($this->xmlbranch['caption']);
		$this->indicator = strval($this->xmlbranch['indicator']);
		$this->params = strval($this->xmlbranch['params']);
		$this->chatcmd = strval($this->xmlbranch['chatcmd']);
		$this->chatcmdparams = strval($this->xmlbranch['chatcmdparams']);
		$this->type = strval($this->xmlbranch['type']);
		$this->ability = strval($this->xmlbranch['ability']);
		$this->unique = strval($this->xmlbranch['unique']);
		$this->id = $fufiMenu->getUniqueID();
		$this->rights = (strval(strtolower($this->xmlbranch['rights']) == 'tmuf'));
	}

	/**
	 * Loads the subentries from the xml branch
	 *
	 */
	function loadEntries(){
		global $fufiMenu;
		$entrycount = count($this->xmlbranch->entry);
		for ($i = 0; $i<$entrycount; $i++){
			$entry = $this->xmlbranch->entry[$i];
			if ($fufiMenu->dependenciesMet(strval($entry['dependencies']), strval($entry['globalvariable']))){
				$fmentry = new FufiMenuEntry($entry, $this->id);
				//$this->xmlbranch = null;
				$this->entries[] = $fmentry;
				$fufiMenu->entriesList[] = $fmentry;
			}
		}
	}

	/**
	 * Returns whether this entry is a group
	 *
	 * @return boolean
	 */
	function isGroup(){
		return count($this->entries)>0 || ($this->chatcmd=='');
	}

	/**
	 * Returns all entries in this submenu which can be accessed by the player with the given login, removes empty groups
	 *
	 * @param String $login
	 * @return FufiMenuEntry[]
	 */
	function getValidEntries($login){

		global $aseco, $fufiMenu;
		$player = $aseco->server->players->getPlayer($login);

		$result = array();
		foreach ($this->entries as $entry){
			if (($entry->rights == $player->rights) || (!$entry->rights)){
				if (strstr($entry->chatcmd, '/admin')){
					$params = explode(' ', $entry->chatcmd);
					$cmd = $params[1];
					if ($aseco->allowAbility($player, $cmd)){
						$result[] = $entry;
					}
				} else if (strstr($entry->chatcmd, '/jfreu')){
					if ($aseco->allowAbility($player, 'chat_jfreu')){
						$result[] = $entry;
					}
				} else {
					if ($entry->ability != ''){
						if ($aseco->allowAbility($player, $entry->ability)){
							$result[] = $entry;
						}
					} else {
						$result[] = $entry;
					}
				}
			}
		}
		//clean out empty groups
		$resultnew = array();
		foreach ($result as $entry){
			if (!($entry->isGroup()) || !$fufiMenu->groupIsEmpty($entry, $login) || $entry->type=='separator'){
				$resultnew[] = $entry;
			}
		}
		$result = $resultnew;
		return $result;
	}

}

function fufiMenu_playerConnect($aseco, $command){
	global $fufiMenu;
	if (!$fufiMenu->Aseco) $fufiMenu->Aseco = $aseco;
	$fufiMenu->sendMenuButtonToLogin($command->login);
}

function fufiMenu_handleClick($aseco, $command){
	global $fufiMenu;
	$fufiMenu->handleClick($command[0], $command[1], $command[2]);
}

function fufiMenu_startup($aseco){
	global $fufiMenu;
	if (!$fufiMenu->Aseco) $fufiMenu->Aseco = $aseco;
	$aseco->releaseEvent('onMenuLoaded', $fufiMenu);
}

function fufiMenu_newChallenge($aseco){
	global $fufiMenu;
	$fufiMenu->sendMenuButtonToLogin('');
}

//indicator functions - return 0 for a red icon, 1 for green, 2 for yellow and -1 for none

function fufi_getCPSIndicator($aseco, $login){
	global $checkpoints;
	return (isset($checkpoints[$login]) && $checkpoints[$login]->loclrec!=-1);
}

function fufi_getDediCPSIndicator($aseco, $login){
	global $checkpoints;
	return (isset($checkpoints[$login]) && $checkpoints[$login]->loclrec!=1 && $checkpoints[$login]->dedirec!=-1);
}

function fufi_getCPSSpecIndicator($aseco, $login){
	$player = $aseco->server->players->getPlayer($login);
	return ($player->speclogin != '');
}

function fufi_getGameModeIndicator($aseco, $login, $gamemode){
	global $fufiMenu;
	$currentgamemode = $fufiMenu->gameinfo['GameMode'];
	$nextgamemode = $fufiMenu->gameinfonext['GameMode'];
	if ($gamemode==$currentgamemode) return 1;
	else if ($gamemode == $nextgamemode) return 2;
	return -1;
}

function fufi_getRefModeIndicator($aseco, $login, $refmode){
	$aseco->client->query('GetRefereeMode');
	if ($aseco->client->isError()) return -1;
	$realrefmode = $aseco->client->getResponse();
	if ($realrefmode == $refmode) return 1;
	return -1;
}

function fufi_getChallengeDownloadIndicator($aseco, $login){
	$aseco->client->query('IsChallengeDownloadAllowed');
	if ($aseco->client->isError()) return -1;
	$acdl = $aseco->client->getResponse();
	return $acdl;
}

function fufi_getRespawnDisabledIndicator($aseco, $login){
	global $fufiMenu;
	$nextRespawn = $fufiMenu->gameinfonext['DisableRespawn'];
	return $nextRespawn;
}

function fufi_getForceShowAllIndicator($aseco, $login){
	global $fufiMenu;
	$nextOpp = $fufiMenu->gameinfonext['ForceShowAllOpponents'];
	return $nextOpp;
}

function fufi_getScorePanelIndicator($aseco, $login){
	global $auto_scorepanel;
	return $auto_scorepanel;
}

function fufi_getRoundsPanelIndicator($aseco, $login){
	global $rounds_finishpanel;
	return $rounds_finishpanel;
}

function fufi_getAutoTimeIndicator($aseco, $login){
	global $atl_active;
	return $atl_active;
}

function fufi_getDebugModeIndicator($aseco, $login){
	return $aseco->debug;
}

function fufi_getAutoChangeNameIndicator($aseco, $login){
	return $aseco->server->jfreu->autochangename;
}

function fufi_getRanklimitIndicator($aseco, $login){
	return $aseco->server->jfreu->ranklimit;
}

function fufi_getAutoRankIndicator($aseco, $login){
	return $aseco->server->jfreu->autorank;
}

function fufi_getAutoRankVIPIndicator($aseco, $login){
	return $aseco->server->jfreu->autorankvip;
}

function fufi_getKickHiRankIndicator($aseco, $login){
	return $aseco->server->jfreu->kickhirank;
}

function fufi_getBadwordsBotIndicator($aseco, $login){
	return $aseco->server->jfreu->badwords;
}

function fufi_getBadwordsBanIndicator($aseco, $login){
	return $aseco->server->jfreu->badwordsban;
}

function fufi_getJFreuVotesDisabledIndicator($aseco, $login){
	return $aseco->server->jfreu->novote;
}

function fufi_getJFreuUnspecEnabledIndicator($aseco, $login){
	return $aseco->server->jfreu->unspecvote;
}

function fufi_getJFreuInfosIndicator($aseco, $login, $info){
	if ($aseco->server->jfreu->infomessages == $info) return 1;
	return -1;
}

function fufi_getMatchEnabledIndicator($aseco, $login){
	global $MatchSettings;
	return $MatchSettings['enable'];
}

function fufi_getMatchOthersIndicator($aseco, $login){
	global $matchOthersCanScore;
	return $matchOthersCanScore;
}

function fufi_getMatchTeamforceIndicator($aseco, $login){
	global $MatchSettings;
	return $MatchSettings['teamForceEnabled'];
}

function fufi_getMatchTeamchatIndicator($aseco, $login){
	global $MatchSettings;
	return $MatchSettings['teamchatEnabled'];
}

function fufi_getMusicOverrideIndicator($aseco, $login){
	global $music_server;
	return $music_server->override;
}

function fufi_getMusicAutonextIndicator($aseco, $login){
	global $music_server;
	return $music_server->autonext;
}

function fufi_getMusicAutoshuffleIndicator($aseco, $login){
	global $music_server;
	return $music_server->autoshuffle;
}

function fufi_getMusicJukeboxIndicator($aseco, $login){
	global $music_server;
	return $music_server->allowjb;
}

function fufi_getMusicStripDirsIndicator($aseco, $login){
	global $music_server;
	return $music_server->stripdirs;
}

function fufi_getMusicStripExtsIndicator($aseco, $login){
	global $music_server;
	return $music_server->stripexts;
}


$fufiMenu = new FufiMenu('fufi_menu_config.xml');
$fufiMenu->init();

?>
