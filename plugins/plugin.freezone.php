<?php

/**
 * ManiaLive - Freezone Plugin
 *
 * @copyright   Copyright (c) 2009-2011 NADEO (http://www.nadeo.com)
 * @version     $Revision: 3705 $:
 * @author      $Author: philippe $:
 * @date        $Date: 2011-05-09 13:04:04 +0200 (lun., 09 mai 2011) $:
 */

require_once('freezone/fz-rest-client.php');

Aseco::registerEvent('onSync', 'freezone_sync');
Aseco::registerEvent('onChat', 'freezone_chat');
Aseco::registerEvent('onPlayerConnect', 'freezone_playerConnect');
Aseco::registerEvent('onPlayerDisconnect', 'freezone_playerDisconnect');
Aseco::registerEvent('onPlayerInfoChanged', 'freezone_playerInfoChanged');
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'freezone_actionHandler');
Aseco::registerEvent('onEverySecond', 'freezone_tick');
Aseco::registerEvent('onNewChallenge', 'freezone_newChallenge');
Aseco::registerEvent('onEndRace1', 'freezone_endRace');

define('FREEZONE_VERSION', '1.4');

class Freezone
{

    public $searchupdates;
    private $wsUser, $wsPassword, $testmode, $button, $messages, $translation, $Aseco, $interval, $MLVersion, $debuginfo, $debugmode, $notify, $gamestate;
    protected $wsInstance, $players, $spectators, $retired, $banned, $slangWords, $tick = 0, $slangUsers = array(), $usersWatched = array(), $store = array();

    protected static $MLHash = '6f116833b419fe7cb9c912fdaefb774845f60e79';
    protected static $chatPrefix = '$<$0f0$oFreeZone:$> ';
    protected static $maxPlayerGame = 5;
    protected static $maxSpectatorGame = 1;


    function __construct($aseco, $config)
    {
        $this->Aseco = $aseco;
        $this->xml = simplexml_load_file($config);
        $this->loadSettings();
        $this->translation = file_get_contents('http://files.manialive.com/freezonerules.xml');
        if (!$this->testmode) {
            $this->wsInstance = new Client($this->wsUser, $this->wsPassword, $this->debuginfo);
            if ($this->debugmode) {
                $this->wsInstance->setAPIURL('http://ws.localhost/');
            }
            try {
                $response = $this->wsInstance->execute('GET', '/manialive/version/check/239/index.json');
                $this->MLVersion = $response->version->revision;
            } catch (FreezoneException $ex) {
                if ($ex->getCode() == 401) {
                    trigger_error('[plugin.freezone.php] API Password wrong or server not registered for freezone! Check the freezone:servers manialink.', E_USER_ERROR);
                }
            }

        }
        $this->interval['ban_slang'] = 0; // every 6 hours
        $this->interval['stats'] = 0; // every hour
        $this->interval['rules'] = 0; // every 13 minutes
        $this->interval['free'] = 0; // every 13 minutes
        $this->interval['rules'] = 0; // every minute

        $this->players = array();
        $this->spectators = array();
        $this->retired = array();
        $this->banned = array();
        $this->store['forcedspectators'] = array();

        $this->slangWords = $this->getSlangList();
        $this->gamestate = 0;
    }

    function loadSettings()
    {
        global $re_config;
        $this->wsUser = strval(($this->xml->webservices->user != "" ? $this->xml->webservices->user : $this->Aseco->server->serverlogin));
        $this->wsPassword = strval($this->xml->webservices->password);
        $this->testmode = (strtoupper($this->xml->webservices->testmode) == 'TRUE' ? true : false);
        $this->debuginfo = (strtoupper($this->xml->webservices->debuginfo) == 'TRUE' ? true : false);
        $this->debugmode = (strtoupper($this->xml->webservices->debugmode) == 'TRUE' ? true : false);
        if ($this->wsPassword == "") {
            trigger_error('[plugin.freezone.php] Please set freezone.webservices.password in freezone.xml!', E_USER_ERROR);
        }
        $this->button['background_style'] = strval($this->xml->button->background_style);
        $this->button['background_substyle'] = strval($this->xml->button->background_substyle);
        $this->button['pos_x'] = strval($this->xml->button->pos_x);
        $this->button['pos_y'] = strval($this->xml->button->pos_y);
        $this->button['score_pos_x'] = strval($this->xml->button->score->pos_x);
        $this->button['score_pos_y'] = strval($this->xml->button->score->pos_y);

        $this->searchupdates = (strtoupper($this->xml->searchupdates) == 'TRUE' ? true : false);

        // Messages
        $frText = 'Pour profiter d\'un accès illimité à tous les serveurs en ligne, vous devez convertir votre compte à TrackMania United Forever. Clicker ici $hfreezone$h pour plus d\'informations';
        $deText = 'Für einen unbegrenzten Zugang zu allen Onlinepartien musst Du auf einen Trackmania United Forever Account upgraden. Klicke hier $hfreezone$h für mehr Informationen.';
        $enText = 'To enjoy unlimited access to all online games, you must upgrade to a TrackMania United Forever account. Click here $hfreezone$h for more information';
        $this->messages['freeaccount'][] = array('Lang' => 'fr', 'Text' => $frText);
        $this->messages['freeaccount'][] = array('Lang' => 'de', 'Text' => $deText);
        $this->messages['freeaccount'][] = array('Lang' => 'en', 'Text' => $enText);

        $frText = 'Nous expérimentons de nouvelles règles sur la FreeZone: les joueurs possédant un compte gratuit peuvent jouer sur 5 circuits d\'affilé avant d\'effectuer une partie en spectateur';
        $deText = 'Wir experimentieren mit neuen Regeln für die FreeZone: Spieler mit einem kostenlosen Account können 5 Strecken am Stück spielen bevor sie eine Runde als Zuschauer verbringen.';
        $enText = 'We are experimenting new rules on the FreeZone: players with a free account can play up to 5 tracks in a row before doing 1 match as spectator.';
        $this->messages['rules'][] = array('Lang' => 'fr', 'Text' => $frText);
        $this->messages['rules'][] = array('Lang' => 'de', 'Text' => $deText);
        $this->messages['rules'][] = array('Lang' => 'en', 'Text' => $enText);

        $this->messages['notify'] = array('mute' => strval($this->xml->notify->messages->mute), 'unmute' => strval($this->xml->notify->messages->unmute));
        $this->notify = intval($this->xml->notify->enable);
    }

    function showFreezoneButton($player = false)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
  <manialinks>
    <manialink id="1073741824">
    ' . $this->translation . '
    <frame posn="' . ($this->gamestate == 1 ? $this->button['score_pos_x'] : $this->button['pos_x']) . ' ' . ($this->gamestate == 1 ? $this->button['score_pos_y'] : $this->button['pos_y']) . ' -32">
      <quad sizen="27 4" style="' . $this->button['background_style'] . '" substyle="' . $this->button['background_substyle'] . '" manialink="freezone"/>
      <label posn="13.5 -1 0.1" sizen="27 3" halign="center" style="TextStaticSmall" textid="button"/>
    </frame>
  </manialink>
  <manialink id="2684354561">
    <frame posn="0 0 -32">
      <quad posn="100 -100 0" sizen="20 20" substyle="BgWindow2" action="268435457" actionkey="1"/>
      <quad posn="100 -100 0.1" sizen="20 20" substyle="BgWindow2" action="268435458" actionkey="2"/>
      <quad posn="100 -100 0.2" sizen="20 20" substyle="BgWindow2" action="268435459" actionkey="3"/>
    </frame>
  </manialink>
</manialinks>';

        if (!$player) {
            $players = array();
            foreach ($this->Aseco->server->players->player_list as $player) {
                if ($player->rights) {
                    continue;
                }
                $players[] = $player->login;
            }
            if (!empty($players)) {
                $this->Aseco->client->query('SendDisplayManialinkPageToLogin', implode(',', $players), $xml, 0, false);
            }
        } else {
            if (!$this->Aseco->server->players->player_list[$player]->rights) {
                $this->Aseco->client->query('SendDisplayManialinkPageToLogin', $player, $xml, 0, false);
            }
        }
    }

    function showSpectator($player)
    {
        if (!isset($this->Aseco->server->players->player_list[$player])) {
            return;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<manialinks>
  <manialink id="1073741824">
    <frame posn="0 0 -32">
      <quad posn="100 -100 0" sizen="20 20" substyle="BgWindow2" action="1073741825" actionkey="1"/>
      <quad posn="100 -100 0.1" sizen="20 20" substyle="BgWindow2" action="1073741826" actionkey="2"/>
      <quad posn="100 -100 0.2" sizen="20 20" substyle="BgWindow2" action="1073741827" actionkey="3"/>
    </frame>
  </manialink>
  ' . $this->getCustomUi(true) . '
  <manialink id="536870912">
    <frame posn="0 0 -32">
      <quad posn="100 -100 0" sizen="20 20" substyle="BgWindow2" action="536870913" actionkey="1"/>
      <quad posn="100 -100 0.1" sizen="20 20" substyle="BgWindow2" action="536870914" actionkey="2"/>
      <quad posn="100 -100 0.2" sizen="20 20" substyle="BgWindow2" action="536870915" actionkey="3"/>
    </frame>
  </manialink>
  <manialink id="1610612736">
    ' . $this->translation . '
    <frame posn="-25 23.5 -32">
      <frame>
        <quad sizen="50 35" style="Bgs1" substyle="BgWindow2"/>
        <frame posn="25 -1 0.1">
          <quad sizen="48 4" halign="center" style="Bgs1" substyle="BgTitle3_1"/>
          <quad posn="-24.5 -0.3 0.1" sizen="49 3.5" style="BgsPlayerCard" substyle="BgRacePlayerLine"/>
          <label posn="0 -1 0.2" sizen="46 3" halign="center" textsize="2.5" textcolor="fff" textid="description_title"/>
        </frame>
        <quad posn="2 -1.6 0.4" sizen="3 3" style="Icons64x64_1" substyle="Close" action="1610612737"/>
      </frame>
      <label posn="2 -6 0.5" sizen="46 3" textsize="2" textcolor="fff" textid="description" autonewline="1"/>
      <label posn="25 -33 0.6" sizen="26 4" halign="center" valign="bottom" style="CardButtonMedium" manialink="freezone" textid="description_button"/>
    </frame>
  </manialink>
  <manialink id="268435456">
    ' . $this->translation . '
    <frame posn="-63 -37 -32">
      <label sizen="55 3" style="TextStaticSmall" textid="message" autonewline="1"/>
    </frame>
  </manialink>
</manialinks>';

        $this->Aseco->client->query('SendDisplayManialinkPageToLogin', $player, $xml, 0, false);
        $message = formatText($this->messages['notify']['mute'], $this->Aseco->server->players->player_list[$player]->nickname);
        if ($this->notify == 2 && function_exists('send_window_message')) {
            send_window_message($this->Aseco, $message, false);
        } else if ($this->notify == 1) {
            $this->Aseco->client->query('ChatSendServerMessage', $this->Aseco->formatColors($message));
        }
    }

    function showPlayer($player)
    {
        if (!isset($this->Aseco->server->players->player_list[$player])) {
            return;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<manialinks>
  <manialink id="1073741824">
  </manialink>
  ' . $this->getCustomUi(false) . '
  <manialink id="536870912">
  </manialink>
  <manialink id="1610612736">
  </manialink>
  <manialink id="268435456">
  </manialink>
</manialinks>';

        $this->Aseco->client->query('SendDisplayManialinkPageToLogin', $player, $xml, 0, false);

        $message = formatText($this->messages['notify']['unmute'], $this->Aseco->server->players->player_list[$player]->nickname);
        if ($this->notify == 2 && function_exists('send_window_message')) {
            send_window_message($this->Aseco, $message, false);
        } else if ($this->notify == 1) {
            $this->Aseco->client->query('ChatSendServerMessage', $this->Aseco->formatColors($message));
        }
    }

    protected function getFreePlayers()
    {
        $players = array();
        foreach ($this->Aseco->server->players->player_list as $player) {
            if (!$player->rights) {
                $players[] = $player->login;
            }
        }
        return $players;
    }

    protected function getSlangList()
    {
        if (!$this->testmode) {
            try {
                return $this->wsInstance->execute('GET', '/freezone/slang/');
            } catch (FreezoneException $ex) {
                if ($ex->getCode() == 401) {
                    $this->badPasswordMessage();
                }
            }
        }
        return array();
    }

    protected function checkLanguage($playerUid, $login, $text, $isRegistredCmd)
    {
        $pattern = '/.*(?:^|\\s)(' . implode('|', $this->slangWords) . ')(?:$|\\s).*/i';
        $matches = array();

        if (preg_match($pattern, $text, $matches)) {
            $slangWord = $matches[1];
            if (array_key_exists($login, $this->slangUsers)) {
                $slangs = $this->slangUsers[$login]->matches;
                $totalMatch = 0;
                $find = false;
                $this->slangUsers[$login]->lastMatchTime = time();

                foreach ($slangs as $key => $matchCount) {
                    if ($matchCount[0] == $slangWord) {
                        $this->slangUsers[$login]->matches[$key][1]++;
                        $find = true;
                    }
                    $totalMatch += $matchCount[1];
                }

                if (!$find) {
                    $this->slangUsers[$login]->matches[] = array($slangWord, 1);
                    $totalMatch += 1;
                }

                $slangDuration = $this->slangUsers[$login]->lastMatchTime - $this->slangUsers[$login]->firstMatchTime;
                if ($totalMatch >= 10 && $slangDuration <= 3600) {
                    $this->usersWatched[$login] = $this->slangUsers[$login];
                }
            } else {
                $player = $this->Aseco->server->players->player_list[$login];
                $slangUser = new SlangUser();
                $slangUser->language = $player->language;
                $slangUser->matches[] = array($slangWord, 1);
                $this->slangUsers[$login] = $slangUser;
            }
        }
    }

    protected function cleanSlangUsersList()
    {
        foreach ($this->slangUsers as $key => $slangUser) {
            if (time() - $slangUser->firstMatchTime >= 3600) {
                unset($this->slangUsers[$key]);
            }
        }
    }

    protected function sendUsersWatched()
    {
        if (count($this->usersWatched)) {
            $datas = array();
            foreach ($this->usersWatched as $slangUser) {
                $user = array();
                $user['language'] = $slangUser->language;
                $user['matches'] = $slangUser->matches;
                $datas[] = (object)$user;
            }
            $this->usersWatched = array();

            try {
                $this->wsInstance->execute('PUT', '/freezone/abuses/reports/%s/', array($this->wsUser, $datas));
            } catch (FreezoneException $ex) {
                if ($ex->getCode() == 401) {
                    $this->badPasswordMessage();
                }
            }
        }
    }

    function badPasswordMessage()
    {
        foreach ($this->Aseco->server->players->player_list as $player) {
            if ($this->Aseco->isMasterAdmin($player)) {
                $this->sendChat('Bad API Password, please check your configuration file and the manialink $hfreezone:servers$h', $player->login);
            }
        }
    }

    function sendChat($message, $player = false)
    {
        if (!$player) {
            $this->Aseco->client->query('ChatSendServerMessage', self::$chatPrefix . $message);
        } else {
            $this->Aseco->client->query('ChatSendServerMessageToLogin', self::$chatPrefix . $message, $player);
        }
    }

    function getCustomUi($dis = false)
    {
        // From manialinks.inc.php
        global $ml_custom_ui;
        return '<custom_ui>' .
            '<notice visible="' . (!$dis ? bool2text($ml_custom_ui['notice']) : bool2text(false)) . '"/>' .
            '<challenge_info visible="' . bool2text($ml_custom_ui['challenge_info']) . '"/>' .
            '<net_infos visible="' . bool2text($ml_custom_ui['net_infos']) . '"/>' .
            '<chat visible="' . (!$dis ? bool2text($ml_custom_ui['chat']) : bool2text(false)) . '"/>' .
            '<checkpoint_list visible="' . bool2text($ml_custom_ui['checkpoint_list']) . '"/>' .
            '<round_scores visible="' . bool2text($ml_custom_ui['round_scores']) . '"/>' .
            '<scoretable visible="' . bool2text($ml_custom_ui['scoretable']) . '"/>' .
            '<global visible="' . bool2text($ml_custom_ui['global']) . '"/>' .
            '</custom_ui>';
    }

    /***** Events *****/
    function onPlayerConnect($player)
    {
        $this->Aseco->client->query('GetPlayerInfo', $player->login, 2);
        $info = $this->Aseco->client->getResponse();

        if (in_array($player->login, $this->banned)) {
            $this->Aseco->client->query('Kick', $player->login);
        } else {
            if (!$this->testmode) {
                try {
                    $result = $this->wsInstance->execute('GET', '/freezone/ban/status/' . $player->login . '/index.json');
                } catch (FreezoneException $ex) {
                    if ($ex->getCode() == 401) {
                        $this->badPasswordMessage();
                    }
                    $result = 0;
                }
            } else {
                $result = 0;
            }

            if ($result == 2) {
                $this->Aseco->client->query('Kick', $player->login);
                $this->banned[] = $player->login;
            } else {
                if (!$player->rights) {
                    if (key_exists($player->login, $this->retired)) {
                        if (key_exists($player->login, $this->players)) {
                            if ($this->Aseco->isSpectator($player)) {
                                $this->spectators[$player->login] = $this->players[$player->login];
                            } else {
                                $this->Aseco->client->query('ForceSpectator', $player->login, 2);
                                $this->store['forcedspectators'][$player->login] = 2;
                            }
                        } else if (key_exists($player->login, $this->spectators)) {
                            if (isset($this->retired[$player->login])) {
                                $this->showSpectator($player->login);
                                $this->spectators[$player->login] = self::$maxPlayerGame;
                                $this->Aseco->client->query('ForceSpectator', $player->login, 1);
                                $this->store['forcedspectators'][$player->login] = 1;
                            } else {
                                if (!$this->Aseco->isSpectator($player)) {
                                    if ($this->spectators[$login] > self::$maxPlayerGame) {
                                        $this->showSpectator($player->login);
                                        $this->Aseco->client->query('ForceSpectator', $player->login, 1);
                                        $this->store['forcedspectators'][$player->login] = 1;
                                    } else {
                                        $this->players[$login] = $this->spectators[$player->login];
                                    }
                                }
                            }
                        }
                        return;
                    }
                    $count = 0;
                    if (!$this->testmode) {
                        try {
                            $count = $this->wsInstance->execute('GET', '/freezone/rules/' . $player->login . '/index.json');
                        } catch (FreezoneException $ex) {
                            if ($ex->getCode() == 401) {
                                $this->badPasswordMessage();
                            }
                        }
                    }

                    if ($count >= self::$maxPlayerGame) {
                        $this->showSpectator($player->login);
                        $this->spectators[$player->login] = $count;
                        $this->Aseco->client->query('ForceSpectator', $player->login, 1);
                        $this->store['forcedspectators'][$player->login] = 1;
                        return;
                    }

                    if (!$this->Aseco->isSpectator($player)) {
                        $this->players[$player->login] = $count;
                        $this->Aseco->client->query('ForceSpectator', $player->login, 2);
                        $this->store['forcedspectators'][$player->login] = 2;
                    } else {
                        $this->spectators[$player->login] = $count;
                    }
                    $this->showFreezoneButton($player->login);
                }
            }
        }
    }

    function onPlayerChangeSide($login)
    {
        $player = $this->Aseco->server->players->player_list[$login];

        if (!$this->Aseco->isSpectator($player)) {
            $this->Aseco->client->query('GetPlayerInfo', $player->login, 2);
            $this->store['forcedspectators'][$player->login] = 2;
            $info = $this->Aseco->client->getResponse();
            if (!$player->rights && ($info['Flags'] % 10 == 0)) {
                $this->Aseco->client->query('ForceSpectator', $login, 2);
                $this->store['forcedspectators'][$player->login] = 2;
            }

            if (isset($this->spectators[$login])) {
                $this->players[$login] = $this->spectators[$login];
                unset($this->spectators[$login]);
            }

            $player->isSpectator = false;
            $this->showFreezoneButton($player->login);
        } elseif ($this->Aseco->isSpectator($player)) {
            if (isset($this->players[$login])) {
                $this->spectators[$login] = $this->players[$login];
                unset($this->players[$login]);
            }

            $player->isSpectator = true;
            $this->showFreezoneButton($player->login);
        }
    }

    function actionHandler($answer)
    {
        if ($answer[2] == 0) {
            return;
        }

        if ($answer[2] == 1610612737) {
            // Close SpectatorWindow
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <manialinks>
              <manialink id="1610612736">
              </manialink>
            </manialinks>';
            $this->Aseco->client->query('SendDisplayManialinkPageToLogin', $answer[1], $xml, 0, false);
        }
    }

    function onBeginRace($challenge)
    {

        foreach ($this->spectators as $login => $value) {
            if (!array_key_exists($login, $this->Aseco->server->players->player_list)) {
                Client::writedebug("$login is in plugin-intern spectatorlist but is offline!");
                unset($this->spectators[$login]);
            }
            $player = $this->Aseco->server->players->player_list[$login];
            $this->Aseco->client->query('GetPlayerInfo', $player->login, 2);
            $info = $this->Aseco->client->getResponse();
            if ($player && (($info['Flags'] % 10) == 1)) {
                if (!$this->Aseco->startup_phase) {
                    $this->spectators[$login]++;
                }
                if ($value >= self::$maxPlayerGame + self::$maxSpectatorGame) {
                    $this->Aseco->client->query('ForceSpectator', $login, 2);
                    $this->store['forcedspectators'][$login] = 2;
                    $this->showPlayer($player->login);
                    $this->spectators[$login] = 0;
                    if ($this->debuginfo) {
                        Client::writedebug("Forcing $login into player!");
                    }
                }
            }
        }

        foreach ($this->players as $login => $value) {
            $this->players[$login]++;
            if ($value >= self::$maxPlayerGame) {
                $player = $this->Aseco->server->players->player_list[$login];
                $this->showSpectator($login);
                $this->Aseco->client->query('ForceSpectator', $login, 1);
                $this->store['forcedspectators'][$login] = 1;
                if ($this->debuginfo) {
                    Client::writedebug("Forcing $login into spectator!");
                }
            }
        }
        $this->gamestate = 0;
        $this->showFreezoneButton();
    }

    function onEndChallenge($rankings, $challenge, $wasWarmUp, $matchContinuesOnNextChallenge, $restartChallenge)
    {
        if (!$wasWarmUp) {
            foreach ($this->retired as $login => $value) {
                if ($value) {
                    $count = self::$maxPlayerGame;
                    if (array_key_exists($login, $this->players)) {
                        $count = $this->players[$login];
                    } elseif (array_key_exists($login, $this->spectators)) {
                        $count = $this->spectators[$login];
                    }
                    $count++;
                    if (!$this->testmode) {
                        try {
                            $this->wsInstance->execute('PUT', '/freezone/rules/' . $login . '/index.json', array($count));
                        } catch (FreezoneException $ex) {
                            if ($ex->getCode() == 401) {
                                $this->badPasswordMessage();
                            }
                        }
                    }
                    unset($this->players[$login]);
                    unset($this->spectators[$login]);
                }
            }
            $this->retired = array();
            $this->gamestate = 1;
            $this->showFreezoneButton();
        }
        $this->printDebug();
    }

    function onRestartChallenge()
    {
        $this->gamestate = 0;
    }

    function onPlayerDisconnect($player)
    {
        $count = self::$maxPlayerGame;
        if (array_key_exists($player->login, $this->players)) {
            $count = $this->players[$player->login];
        } elseif (array_key_exists($player->login, $this->spectators)) {
            $count = $this->spectators[$player->login];
        }

        if (!$this->testmode) {
            try {
                $this->wsInstance->execute('PUT', '/freezone/rules/' . $player->login . '/index.json', array($count));
            } catch (FreezoneException $ex) {
                if ($ex->getCode() == 401) {
                    $this->badPasswordMessage();
                }
            }
        }

        if (isset($this->store['forcedspectators'][$player->login])) {
            if ($this->store['forcedspectators'][$player->login] != 0) {
                $this->retired[$player->login] = true;
            }
            unset($this->store['forcedspectators'][$player->login]);
        }
    }

    function onPlayerChat($playerUid, $login, $text, $isRegistredCmd)
    {
        $this->checkLanguage($playerUid, $login, $text, $isRegistredCmd);
    }

    function onTick()
    {
        $time = time();

        // every 6 hours - refresh ban- and clean slanglist
        if ($this->interval['ban_slang'] + 21600 <= $time) {
            $this->slangWords = $this->getSlangList();
            $this->banned = array();
            $this->interval['ban_slang'] = $time;

        }

        // every hour - send statistics to ManiaStudio
        if ($this->interval['stats'] + 3600 <= $time) {
            if (!$this->testmode) {
                $this->Aseco->client->query('GetHideServer');
                $hidestatus = $this->Aseco->client->getResponse();
                $data = array();
                $data['serverLogin'] = $this->wsUser;
                $data['serverName'] = $this->Aseco->server->name;
                $data['serverVersion'] = $this->Aseco->server->game . ',' . $this->Aseco->server->version . ',' . $this->Aseco->server->build;
                $data['manialiveVersion'] = $this->MLVersion;
                $data['maxPlayers'] = $this->Aseco->server->maxplay;
                $data['visibility'] = $hidestatus;
                $data['classHash'] = self::$MLHash;
                try {
                    $this->wsInstance->execute('POST', '/freezone/live/', array($data));
                } catch (FreezoneException $ex) {
                    if ($ex->getCode() == 401) {
                        $this->badPasswordMessage();
                    }
                }

                $this->sendUsersWatched();
            }
            $this->cleanSlangUsersList();
            $this->interval['stats'] = $time;
        }

        // every minute - testmode message
        if ($this->interval['rules'] + 60 <= $time) {
            if ($this->testmode) {
                foreach ($this->Aseco->server->players->player_list as $player) {
                    if ($this->Aseco->isMasterAdmin($player)) {
                        $this->sendChat('This server is in test Mode. This mode should be disable to respect the FreeZone Rules', $player->login);
                    }
                }
            }
            $this->interval['rules'] = $time;
        }

        // every 13 minutes - send rule message
        if ($this->interval['rules'] + 780 <= $time) {
            $players = $this->getFreePlayers();
            if (count($players)) {
                $this->Aseco->client->query('ChatSendServerMessageToLanguage', $this->messages['rules'], $players);
            }
            $this->interval['rules'] = $time;
        }

        // every 13 minutes - send free-account message
        if ($this->interval['free'] + 780 <= $time) {
            $players = $this->getFreePlayers();
            if (count($players)) {
                $this->Aseco->client->query('ChatSendServerMessageToLanguage', $this->messages['freeaccount'], $players);
            }
            $this->interval['free'] = $time;
        }
    }

    function printDebug()
    {
        echo '$this->players:' . "\n";
        print_r($this->players);
        echo '$this->spectators:' . "\n";
        print_r($this->spectators);
    }

}

function freezone_sync($aseco)
{
    global $freezone;
    $freezone = new Freezone($aseco, 'freezone.xml');
    // Register this to the global version pool (for up-to-date checks)
    $aseco->plugin_versions[] = array(
        'plugin' => 'plugin.freezone.php',
        'author' => 'ManiacTwister',
        'version' => FREEZONE_VERSION
    );
}

function freezone_chat($aseco, $chat)
{
    global $freezone;
    if ($chat[0] == $aseco->server->id) return;
    $freezone->onPlayerChat($chat[0], $chat[1], $chat[2], $chat[3]);
}

function freezone_playerConnect($aseco, $player)
{
    global $freezone;
    $freezone->onPlayerConnect($player);
    if ($aseco->isMasterAdmin($player) && $message = search_update()) {
        $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
    }
}

function freezone_playerDisconnect($aseco, $player)
{
    global $freezone;
    $freezone->onPlayerDisconnect($player);
}

function freezone_playerInfoChanged($aseco, $changes)
{
    global $freezone;
    $freezone->onPlayerChangeSide($changes['Login']);
}

function freezone_actionHandler($aseco, $answer)
{
    global $freezone;
    $freezone->actionHandler($answer);
}

function freezone_tick($aseco)
{
    global $freezone;
    $freezone->onTick();
}

function freezone_newChallenge($aseco, $challenge)
{
    global $freezone;
    $freezone->onBeginRace($challenge);
}

function freezone_endRace($aseco, $race)
{
    global $freezone;
    $freezone->onEndChallenge($race[0], $race[1], $race[2], $race[3], $race[4]);
}

function freezone_restartChallenge($aseco)
{
    global $freezone;
    $freezone->onRestartChallenge();
}

function search_update()
{
    global $freezone;
    $current = trim(http_get_file('http://xaseco.maniactwister.de/freezone/version'));
    if (!empty($current) && $freezone->searchupdates && $current != -1 && $current > FREEZONE_VERSION) {
        return formatText('{#server}>> {#message}New Freezone plugin version {#highlite}{1}{#message} available from {#highlite}{2}', $current, '$L[http://www.tm-forum.com/viewtopic.php?f=127&t=29748]TM-Forum');
    }
    return false;
}

$freezone = '';

class SlangUser
{
    public $language;
    public $firstMatchTime;
    public $lastMatchTime;
    public $matches = array();

    function __construct()
    {
        $this->firstMatchTime = time();
        $this->lastMatchTime = time();
    }
}

?>
