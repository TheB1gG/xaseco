<?php
/////////////////////////////////////////////////////////////////////////////////
////////////////////////STALKER's Tools plugin for XASECO. //////////////////////
////////////////////////          Version 1.1b             //////////////////////
////////////////////////      (c) 2010@STALKER-SVK.        //////////////////////
////////////////////////       All rights reserved.        //////////////////////
/////////////////////////////////////////////////////////////////////////////////

//requirements
require_once('plugins/stalker.actionIDs.php');
/////////////////////////////////////////////////////////////////////////////////

//Event registrations
Aseco::registerEvent('onStartup', 'st_startup');
Aseco::registerEvent('onStartup', 'init_st');
Aseco::registerEvent('onPlayerConnect', 'st_newplayer_init');
Aseco::registerEvent('onPlayerDisconnect', 'st_player_disconnect');
Aseco::registerEvent('onPlayerInfoChanged', 'st_playerinfochanged');
Aseco::registerEvent('onChat', 'st_log_all');
Aseco::registerEvent('onNewChallenge', 'st_close_manialinks');
Aseco::registerEvent('onShutdown', 'st_clean_chat');
Aseco::registerEvent('onShutdown', 'st_clean_manialinks');
Aseco::registerEvent('onShutdown', 'st_clean_music');
Aseco::registerEvent('onStartup', 'st_customui_refresh');
Aseco::registerEvent('onEverySecond', 'st_customui_refresh');
Aseco::registerEvent('onNewChallenge', 'st_customui_refresh');
Aseco::registerEvent('onEndRace', 'st_customui_refresh');
Aseco::registerEvent('onBeginRound', 'st_customui_refresh');
Aseco::registerEvent('onPlayerFinish', 'st_customui_refresh');
Aseco::registerEvent('onMenuLoaded', 'st_init_menu');
Aseco::registerEvent('onMainLoop', 'st_check_vote');
Aseco::registerEvent('onEcho', 'st_check_custom_vote');
Aseco::registerEvent('onVoteEnd', 'st_check_custom_vote_fail');
Aseco::registerEvent('onVote', 'st_protect_admins');
Aseco::registerEvent('onChat', 'st_fwdpms');
/////////////////////////////////////////////////////////////////////////////////

//Starting message
function init_st($aseco, $command)
{
    $message = '$z$s>> $w$s$f00S$f80T$ff0A$0F0L$0FFK$00FE$009R$z$fff$s\'s tools $z$s1.$s1b$fff $sloaded$z$s.';
    $aseco->client->query('ChatSendServerMessage', $message);
    $aseco->console_text('[STALKER\'s Tools] Starting...');
    $aseco->console_text('[STALKER\'s Tools] Running...');
}

/////////////////////////////////////////////////////////////////////////////////

//XASECO version checking
function st_startup($aseco)
{
    if ($aseco->server->getGame() != 'TMF') trigger_error('[STALKER\'s Tools] Unsupported TM version', E_USER_ERROR);
    array_unshift($aseco->events['onPlayerConnect'], 'st_clean_manialinks');
    global $st;
    $st = new stalkertools($aseco);
    $aseco->server->st =& $st;
}

/////////////////////////////////////////////////////////////////////////////////

//Class declaration
class stalkertools
{
    var $chat_ignored;
    private $check_vote_every, $nextvote_check, $vote_released_event;

    var $custom_vote;
    var $uservote_command, $uservote_fail_command;

    var $fstated;

    var $gamelog;

    var $player_data;

    var $aseco;

    function __construct($aseco)
    {
        $this->aseco =& $aseco;
        $this->chat_ignored = array();
        $this->check_vote_every = 0.5;
        $this->nextvote_check = microtime(true) + $this->check_vote_every;
        $this->vote_released_event = false;
        $this->custom_vote = array('callback' => '', 'caller' => '', 'passed' => false, 'running' => false);
        $this->uservote_command = '';
        $this->uservote_fail_command = '';
        $this->fstated = array();
        $this->gamelog = array();
        $this->player_data = array();
    }

    function set_st_playerinfo($player, $infos)
    {
        foreach ($infos as $key => $value) {
            if ($value == NULL) {
                unset($player->st[$key]);
                unset($this->player_data[$player->login][$key]);
            } else {
                $player->st[$key] = $value;
                $this->player_data[$player->login][$key] = $value;
            }
        }
    }


    function log_chat($aseco, $chat)
    {
        if ($chat[0] != $aseco->server->id && $chat[2] != '') {
            if (isset($chat[4])) if ($chat[4]) return false;
            if ($player = $aseco->server->players->getPlayer($chat[1]))
                $this->gamelog[] = array(1, array(array($player->login, str_ireplace('$w', '', $player->nickname)), $chat[2]), time());
        }
    }

    function clean_chat($aseco)
    {
        global $st;
        $query = false;
        foreach ($st->chat_ignored as $login => $badboy) {
            if (!$aseco->server->players->getPlayer($login)) continue;
            $query = true;
            for ($i = 1; $i <= 30; $i++) $aseco->client->addCall('ChatSendServerMessageToLogin', array('', $login));
        }
        if ($query) $aseco->client->multiquery();
    }


    function customui_refresh($aseco)
    {
        global $st;
        foreach ($st->chat_ignored as $badboy) {
            if (isset($badboy[1])) if ($badboy[1] <= time() && $badboy[1] > 0) {
                unset($st->chat_ignored[$badboy[0]]);
                $st->chat_ui_send($badboy[0], true);
                if (!$target = $aseco->server->players->getPlayer($badboy[0]))
                    continue;
                $message = formatText('{#server}>> {#admin} Chat was enabled for {#highlite}{1}$z$s{#admin} !',
                    str_ireplace('$w', '', $target->nickname));
                $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
                continue;
            }
            if (!$aseco->server->players->getPlayer($badboy[0]))
                continue;
            $st->chat_ui_send($badboy[0], false);
        }
    }

    function return_gamelog($options)
    {
        $chat = true;
        $formating = false;
        $colors = false;
        $commands = false;
        foreach ($options as $option) {
            if ($option == 'default') {
                $chat = true;
                $colors = false;
                $formating = false;
                $commands = false;
                continue;
            }
            if ($option == '-chat') {
                $chat = false;
                continue;
            }
            if ($option == '+formating') {
                $formating = true;
                continue;
            }
            if ($option == '+colors') {
                $colors = true;
                continue;
            }
            if ($option == '+coms' || $option == '+commands') {
                $commands = true;
                continue;
            }
            return false;
        }
        $returnlog = array();
        foreach ($this->gamelog as $log) {
            if ($log[0] == '1') if (!$chat) continue;
            if ($log[1][1][0] == '/') if (!$commands) continue;
            if ($log[0] == '1') {
                if ($formating) {
                    $returnlog[] = $log;
                    continue;
                }
                if ($colors) {
                    $log[1][0][1] = preg_replace('/\$(?:i|s|t|w|n|m|g|z|h|o|l)/i', '', stripSizes($log[1][0][1]));
                    $log[1][1] = preg_replace('/\$(?:i|s|t|w|n|m|g|z|h|o|l)/i', '', stripSizes($log[1][1]));
                    $returnlog[] = $log;
                    continue;
                }
                $log[1][0][1] = stripColors($log[1][0][1]);
                $log[1][1] = stripColors($log[1][1]);
                $returnlog[] = $log;
                continue;
            }

            $returnlog[] = $log;
            continue;
        }
        return $returnlog;
    }


    function check_vote($aseco)
    {
        global $st;
        if (microtime(true) < $st->nextvote_check) return false;
        $st->nextvote_check = microtime(true) + $st->check_vote_every;
        $rtn = $aseco->client->query('GetCurrentCallVote');
        if (!$rtn) trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
        $result_keys = $aseco->client->getResponse();
        $result = array();
        foreach ($result_keys as $item) $result[] = $item;
        if ($result[1] == '' && $st->vote_released_event) {
            $st->vote_released_event = false;
            $aseco->releaseEvent('onVoteEnd', NULL);
            return false;
        }
        if ($result[1] == '') return false;
        if ($st->vote_released_event) return false;
        $aseco->releaseEvent('onVote', $result);
        $st->vote_released_event = true;
    }


    function check_custom_vote($aseco, $data)
    {
        global $st;
        if ($data[0] != 'st_custom_vote') return false;
        if (!empty($st->custom_vote['callback'])) if (is_callable($st->custom_vote['callback'])) call_user_func($st->custom_vote['callback'], $aseco, true, $st->custom_vote['caller']);
        $st->custom_vote['callback'] = '';
        $st->custom_vote['passed'] = true;
        $st->custom_vote['running'] = false;
        $st->custom_vote['caller'] = '';
    }

    function check_custom_vote_fail($aseco)
    {
        global $st;
        if ($st->custom_vote['passed']) {
            $st->custom_vote['passed'] = false;
            return false;
        }
        if (!$st->custom_vote['running']) return false;
        if (is_callable($st->custom_vote['callback'])) call_user_func($st->custom_vote['callback'], $aseco, false, $st->custom_vote['caller']);
        $st->custom_vote['callback'] = '';
        $st->custom_vote['running'] = false;
        $st->custom_vote['passed'] = false;
        $st->custom_vote['caller'] = '';
    }

    function chat_ui_send($player, $state)
    {
        $custom_ui = preg_replace('/<chat visible="(true|false)"\/>/i', '<chat visible="' . strtolower(bool2text($state)) . '"/>', getCustomUIBlock());
        $xml = ' <manialinks>
  		<manialink id="0"><line></line></manialink>' .
            $custom_ui . '
  		</manialinks>';
        global $aseco;
        $aseco->client->addCall('SendDisplayManialinkPageToLogin', array($player, $xml, 0, false));
    }

    function custom_vote($text, $handler = '', $caller = '', $timeout = 0, $ratio = -1, $voters = 1)
    {
        global $aseco, $st;
        $caller = $aseco->server->players->getPlayer($caller);
        $timeout = (int)$timeout;
        $ratio = (double)$ratio;
        $voters = (int)$voters;
        if (!empty($handler)) $this->custom_vote['callback'] = $handler;
        $call = new IXR_Request('Echo', array($text, 'st_custom_vote'));
        $rtn = $aseco->client->query('CallVoteEx', $call->getXml(), (double)$ratio, (int)$timeout, (int)$voters);
        if (!$rtn) trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
        if (!$rtn) $message = '{#server}> {#error}' . $aseco->client->getErrorMessage();
        $message = trim($aseco->formatColors($message));
        $message .= (substr($message, -1) != '!' ? '!' : '');
        if ($caller && !$rtn) $aseco->client->query('ChatSendServerMessageToLogin', $message, $caller->login);
        if (!$rtn) return false;
        $st->custom_vote['running'] = true;
        $st->custom_vote['caller'] = ($caller !== false ? $caller->login : '');
        $st->custom_vote['callback'] = (!empty($handler) ? $handler : '');
        return true;
    }


}

/////////////////////////////////////////////////////////////////////////////////

//Check for enabled PM forwarding or forced state
function st_newplayer_init($aseco, $player)
{
    global $st;
    if (!isset($st->player_data[$player->login])) $st->player_data[$player->login] = array();
    $player->st = $st->player_data[$player->login];

    $player->st['spectarget'] = '';
    $player->st['fstate'] = 0;
    if (isset($st->fstated[$player->login])) $player->st['fstate'] = $st->fstated[$player->login];
    if (!isset($player->st['fwdpms'])) $player->st['fwdpms'] = false;
}

/////////////////////////////////////////////////////////////////////////////////

function st_player_disconnect($aseco, $player)
{
    global $st;
    if (empty($st->player_data[$player->login])) unset($st->player_data[$player->login]);
}

function st_playerinfochanged($aseco, $playerinfo)
{
    $status = $playerinfo['SpectatorStatus'];
    if (!$player = $aseco->server->players->getPlayer($playerinfo['Login'])) return false;
    if ($status == '0') {
        $player->st['spectarget'] = '';
    } else {
        $matches = array();
        preg_match('/(\d+)(\d)(\d)(\d)(\d)$/', $status, $matches);
        array_shift($matches);
        $status = array();
        foreach ($matches as $s) {
            $status[] = (int)$s;
        }
        $status = array_reverse($status);
        if ($status[4] == 255) $player->st['spectarget'] = '';
        else {
            $target = getPlayerFromId($status[4]);
            $player->st['spectarget'] = $target->login;
        }
    }

    if ($player->st['fstate'] == 0) return true;
    $fstate = ($player->st['fstate'] == 1 ? 1 : 2);
    $fbstate = $player->st['fstate'] == 1;
    if ($player->isspectator == $fbstate) return true;
    $rtn = $aseco->client->query('ForceSpectator', $player->login, $fstate);
    if (!$rtn) trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
}

//Log all chat
function st_log_all($aseco, $chat)
{
    $aseco->server->st->log_chat($aseco, $chat);
}

/////////////////////////////////////////////////////////////////////////////////

//Close manialinks on new challenge to avoid empty manialink windows
function st_close_manialinks($aseco, $data)
{
    foreach ($aseco->server->players->player_list as $player) {
        event_manialink($aseco, array(0, $player->login, 0));
    }
}

/////////////////////////////////////////////////////////////////////////////////

//Clean chat on XASECO shutdown
function st_clean_chat($aseco)
{
    $aseco->server->st->clean_chat($aseco);
}

/////////////////////////////////////////////////////////////////////////////////

//Hide all manialinks on XASECO shutdown
function st_clean_manialinks($aseco, $data = NULL)
{
    if (!$data) {
        $aseco->client->query('SendHideManialinkPage');
        return;
    }
    $aseco->client->query('SendHideManialinkPageToLogin', $data->player);
}

/////////////////////////////////////////////////////////////////////////////////

//Disable forced music on XASECO shutdown
function st_clean_music($aseco, $data = NULL)
{
    $aseco->client->query('SetForcedMusic', false, '');
}

/////////////////////////////////////////////////////////////////////////////////

//Custom UI refresh to avoid show chat panel when disabled
function st_customui_refresh($aseco, $data = NULL)
{
    $aseco->server->st->customui_refresh($aseco);
}

/////////////////////////////////////////////////////////////////////////////////

//Chatlog export
function return_gamelog($aseco, $options = array('default'))
{
    $aseco->server->st->return_gamelog($options);
}

/////////////////////////////////////////////////////////////////////////////////

//FuFi menu integration
function st_init_menu($aseco, $menu)
{
    $menu->addEntry('', 'jfreu', true, 'STALKER Tools', 'stalkertools', '', '', 'stalkertools');
    $menu->addEntry('stalkertools', '', true, 'Show full chat history', 'st_chatall', '/chatall');
    $menu->addEntry('stalkertools', '', true, 'Clear chatlog', 'st_clear_chatall', '/st cc all');
    $menu->addEntry('stalkertools', '', true, 'List chat-disabled players', 'st_listignores', '/st listignores');
    $menu->addEntry('stalkertools', '', true, 'Clear chat-disabled players', 'st_cleanignores', '/st cleanignores');
    $menu->addEntry('stalkertools', '', true, 'Show fstated players', 'st_fstates', '/st fstated');
    $menu->addEntry('stalkertools', '', true, 'Forward PMs to you', 'st_toggle_fwdpms', '/st fwdpms toggle', '', '', 'st_get_fwdpms_indicator');

    $menu->addSeparator('stalkertools', '', true, 'st_help_separator');
    $menu->addEntry('stalkertools', '', true, 'Help', 'st_help', '/st help', '', '', '', '', 'help');
}

/////////////////////////////////////////////////////////////////////////////////

//PM forwarding menu indicator
function st_get_fwdpms_indicator($aseco, $login)
{
    global $st;
    $player = $aseco->server->players->getPlayer($login);
    if (!$player->st['fwdpms']) return 0;
    if ($st->player_data[$player->login]['fwdpms']) return 1;
    if ($player->st['fwdpms'] && !$st->player_data[$login]['fwdpms']) return 2;
    return -1;
}

/////////////////////////////////////////////////////////////////////////////////

//Vote checking
function st_check_vote($aseco)
{
    $aseco->server->st->check_vote($aseco);
}

function st_check_custom_vote($aseco, $echo)
{
    $aseco->server->st->check_custom_vote($aseco, $echo);
}

function st_check_custom_vote_fail($aseco)
{
    $aseco->server->st->check_custom_vote_fail($aseco);
}

/////////////////////////////////////////////////////////////////////////////////

//Hide chat panel
function chat_ui_send($aseco, $player, $state = true)
{
    $aseco->server->st->chat_ui_send($player, $state);
}

/////////////////////////////////////////////////////////////////////////////////

//Admin protection against Kick/Ban callvotes
function st_protect_admins($aseco, $vote)
{
    if (!($vote[1] == 'Kick' || $vote[1] == 'Ban')) return false;
    if (!$target = $aseco->server->players->getPlayer($vote[2]))
        return false;
    if (!($aseco->allowAbility($target, 'stalkertools') || $aseco->allowAbility($target, 'st_vote_protect'))) return false;
    if (!$caller = $aseco->server->players->getPlayer($vote[0]))
        return false;
    $dokick = true;
    if ($aseco->allowAbility($caller, 'stalkertools')) $dokick = false;
    $warn_caller = !$dokick;
    $message = formatText('{#server}>> {#admin}{1}$z$s{#admin} tried to ' . strtolower($vote[1]) . ' {2}$z$s{#admin} !' . ($dokick ? ' {#error}[Kicked]' : ''),
        str_ireplace('$w', '', $caller->nickname), str_ireplace('$w', '', $target->nickname));
    $warnmessage = formatText('{#server}> {#admin}Don\'t be silly, {1}$z$s{#admin} ! ;)',
        str_ireplace('$w', '', $caller->nickname));
    $aseco->client->query('CancelVote');
    $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
    if ($warn_caller) $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($warnmessage), $caller->login);
    if ($dokick) $aseco->client->query('Kick', $caller->login);
}

/////////////////////////////////////////////////////////////////////////////////

//Forwarding PMs to you
function st_fwdpms($aseco, $chat)
{
    if ($chat[0] == $aseco->server->id || $chat[2] == '') return;
    if (trim(substr(trim($chat[2]), 1, 3)) != 'pm') return;
    $pm = explode(' ', trim(substr(trim($chat[2]), 3)), 2);
    if (!isset($pm[1])) return;

    if (!$sender = $aseco->server->players->getPlayer($chat[1]))
        return;
    if (!$receiver = $aseco->server->players->getPlayer($pm[0]))
        return;
    $plnick = str_ireplace('$w', '', $sender->nickname);
    $tgnick = str_ireplace('$w', '', $receiver->nickname);

    $msg = '$f00-PM- ' . $plnick . ' $z$s$0F0=>' . $tgnick . '$z$s$f00:$fff ' . $pm[1];
    $msg = $aseco->formatColors($msg);

    $doquery = false;
    foreach ($aseco->server->players->player_list as $player) {
        if (!$player->st['fwdpms']) continue;
        if ($player->login == $sender->login || $player->login == $receiver->login) continue;
        $aseco->client->addCall('ChatSendServerMessageToLogin', array($msg, $player->login));
        $doquery = true;
    }
    if ($doquery) if (!$aseco->client->multiquery()) {
        trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
    }


}

/////////////////////////////////////////////////////////////////////////////////

//Pass/fail messages called onVoteEnd
function st_custom_user_vote($aseco, $passed, $caller)
{
    global $st;
    $command = $st->uservote_command;
    $fail_command = $st->uservote_fail_command;
    $caller = $aseco->server->players->getPlayer($caller);
    if ($passed) {
        if ($caller) {
            $message = '{#server}> $0f0Vote passed!';
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $caller->login);
        }
        fake_server_chat($command);
    } else {
        if ($caller) {
            $message = '{#server}> $f00Vote failed!';
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $caller->login);
        }
        fake_server_chat($fail_command);
    }
    $st->uservote_command = '';
    $st->uservote_fail_command = '';
}

/////////////////////////////////////////////////////////////////////////////////

//Execute command on VoteEnd
function fake_server_chat($command)
{
    global $aseco;
    if (is_array($command)) {
        foreach ($command as $com) fake_server_chat($com);
        return true;
    }
    if ($command == '') return false;
    $player_item = new Player();
    $player_item->id = $aseco->server->id;
    $player_item->login = $aseco->server->serverlogin;
    $player_item->nickname = $aseco->server->name;
    $player_item->ip = $aseco->server->ip;
    $player_item->ipport = $player_item->ip . ':' . $aseco->server->port;
    $player_item->zone = $aseco->server->zone;
    $player_item->rights = $aseco->server->rights;
    $player_item->unlocked = true;
    $player_item->ladderrank = 0;
    $player_item->ladderscore = 0;
    $player_item->isofficial = true;
    $player_item->isspectator = true;
    $player_item->msgs = array();
    $player_item->created = time();
    $player_item->hasvoted = false;
    $player_item->wins = 0;
    $player_item->newwins = 0;
    $player_item->timeplayed = 0;
    $player_item->pmbuf = array();
    $player_item->mutelist = array();
    $player_item->mutebuf = array();
    $player_item->style = array();
    $player_item->panels = array();
    $player_item->spectarget = '';

    $aseco->masteradmin_list['TMLOGIN'][] = $aseco->server->serverlogin;
    $aseco->masteradmin_list['IPADDRESS'][] = '';
    $aseco->server->players->addPlayer($player_item);

    if ($command[0] != '/') $aseco->client->query('ChatSend', $command);
    $chat = array(0 => $player_item->id, $player_item->login, $command, false, true);
    $aseco->playerChat($chat);

    while ($aseco->server->players->removePlayer($aseco->server->serverlogin)) continue;
    $ids = array();
    foreach ($aseco->masteradmin_list['TMLOGIN'] as $id => $master) {
        if ($master == $aseco->server->serverlogin) $ids[] = $id;
    }
    foreach ($ids as $id) {
        unset($aseco->masteradmin_list['TMLOGIN'][$id]);
        unset($aseco->masteradmin_list['IPADDRESS'][$id]);
    }
}

/////////////////////////////////////////////////////////////////////////////////

//Get login instead of ID
function getPlayerFromId($id)
{
    global $aseco;
    foreach ($aseco->server->players->player_list as $player) {
        if ($player->id == $id) return $player;
    }
    return false;
}

/////////////////////////////////////////////////////////////////////////////////

//Extended chatlog integration
function chat_chatall($aseco, $command)
{
    global $st;
    $player = $command['author'];
    $admin = $command['author'];
    $login = $admin->login;

    $params = explode(' ', $command['params'], 2);
    $params = $params[0];
    if ($params == 'help') {
        $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('{#server}> {#error}Usage: /chatall [page]'), $player->login));
        if (!$aseco->client->multiquery()) {
            trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
        }
        return false;
    }
    if (!($aseco->allowAbility($admin, 'stalkertools') || $aseco->allowAbility($admin, 'st_chatall'))) {
        $aseco->console($login . ' tried to use STALKER Tools (no permission!): ' . $command['params']);
        $aseco->client->query('ChatSendToLogin', '$f00You don\'t have the required admin rights to do that!', $login);
        return false;
    }
    $st_log = $st->return_gamelog($aseco, array('+coms', '+colors', '+formating'));

    if ($params != '') if (!is_numeric($params)) {
        $message = "{#server}> {#error}{#highlite}\$i$params{#error} is not a number!";
        $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
        return false;
    }


    if (!empty($st_log)) {
        $head = 'Full chat history:';
        $msg = array();
        $lines = 0;
        $player->msgs = array();
        $player->msgs[0] = array(1, $head, array(1.2), array('Icons64x64_1', 'Outbox'));
        foreach ($st_log as $item) {
            $multi = explode(LF, wordwrap($item[1][1], 50 + 30, LF . '...'));
            foreach ($multi as $line) {
                $line = substr($line, 0, 50 + 33);
                $msg[] = array('$z' . ($aseco->settings['chatpmlog_times'] ? '<{#server}' . date('H:i:s', $item[2]) . '$z> ' : '') . '[{#black}' . $item[1][0][1] . '$z] ' . $line);
                $lines++;
                if ($lines > 14) {
                    $player->msgs[] = $msg;
                    $lines = 0;
                    $msg = array();
                }
            }
        }
        if (!empty($msg))
            $player->msgs[] = $msg;
        if ($params != '') {
            $page = (int)$params;
            $player->msgs[0][0] = $page;
        }
        display_manialink_multi($player);
    } else {
        $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}No chat history found!'), $login);
        return false;
    }
}

/////////////////////////////////////////////////////////////////////////////////

//Plugin commands
//
//Commands prefix
function chat_st($aseco, $command)
{
    global $st;
    $player = $command['author'];
    $admin = $command['author'];
    $login = $admin->login;

    $chattitle = '';
    if ($aseco->isMasterAdmin($admin)) {
        $chattitle = $aseco->titles['MASTERADMIN'][0];
    } else if ($aseco->isAdmin($admin) && $aseco->allowAdminAbility($command['params'][0])) {
        $chattitle = $aseco->titles['ADMIN'][0];
    } elseif ($aseco->isOperator($admin) && $aseco->allowOpAbility($command['params'][0])) {
        $chattitle = $aseco->titles['OPERATOR'][0];
    }

    if (!($aseco->allowAbility($admin, 'stalkertools' || $aseco->isMasterAdmin($admin)))) {
        $aseco->console($login . ' tried to use STALKER Tools (no permission!): ' . $command['params']);
        $aseco->client->query('ChatSendToLogin', '$f00You don\'t have the required admin rights to do that!', $login);
        return false;
    }
    $arglist = explode(' ', $command['params'], 2);
    if (!isset($arglist[1])) $arglist[1] = '';
    $command['params'] = explode(' ', preg_replace('/ +/', ' ', $command['params']));
    if (!isset($command['params'][1])) $command['params'][1] = '';
    $command['params'][0] = strtolower($command['params'][0]);
/////////////////////////////////////////////////////////////////////////////////

//Help
    if ($command['params'][0] == 'help' || $command['params'][0] == 'about') {
        $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('$w$s$f00S$f80T$ff0A$0F0L$0FFK$00FE$009R$z$s\'s tools:'), $player->login));
        $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('$z$s$ifwdpms, chatall, cc, ce, ignore, unignore, listignores, cleanignores, fstate, fstates, specme, vote'), $player->login));
        if (!$aseco->client->multiquery()) {
            trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
        }
    }
/////////////////////////////////////////////////////////////////////////////////

//PM forwarding
    elseif ($command['params'][0] == 'fwdpms') {
        $params = strtolower($arglist[1]);
        if ($params == 'help') {
            $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('{#server}> {#error}Usage: /st fwdpms <ON|OFF|permanent|toggle>'), $player->login));
            if (!$aseco->client->multiquery()) {
                trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
            }
            return false;
        } elseif ($params == 'on') {
            $st->set_st_playerinfo($player, array('fwdpms' => false));
            $player->st['fwdpms'] = true;
        } elseif ($params == 'off') {
            $st->set_st_playerinfo($player, array('fwdpms' => false));
        } elseif ($params == 'permanent') {
            $st->set_st_playerinfo($player, array('fwdpms' => true));
        } elseif ($params == 'toggle') {
            if ($player->st['fwdpms']) $st->set_st_playerinfo($player, array('fwdpms' => false));
            else $player->st['fwdpms'] = true;
        }

        $state = $player->st['fwdpms'];
        $message = '{#server}> {#message}Forwading private messages for you is: {#highlite}' . ($state ? 'ON' : 'OFF') . ($st->player_data[$player->login]['fwdpms'] ? ' (permanent)' : '') . '$z$s{#message}.';
        $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
    }
/////////////////////////////////////////////////////////////////////////////////

//Extended chatlog
    elseif ($command['params'][0] == 'chatall') {
        $params = explode(' ', $arglist[1], 2);
        $params = $params[0];

        $chat_command = array();
        $chat_command['author'] = $player;
        $chat_command['params'] = $params;
        chat_chatall($aseco, $chat_command);
    }
/////////////////////////////////////////////////////////////////////////////////

//Clear chatlog
    elseif ($command['params'][0] == 'cc') {
        global $chatbuf;
        if ($arglist[1] == 'all') {
            if (!($aseco->isMasterAdmin($admin))) {
                $aseco->console($login . ' tried to use STALKER tools (no permission!): ' . $command['params']);
                $aseco->client->query('ChatSendToLogin', '$f00You don\'t have the required admin rights to do that!', $login);
                return false;
            }

            $chatbuf = array();
            $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('{#server}> $0f0Public chatlog cleared!'), $player->login));
            if (isset($st->gamelog)) {
                $st->gamelog = array();
                $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('{#server}> $0f0Full chatlog cleared!'), $player->login));
            }
            if (!$aseco->client->multiquery()) {
                trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
            }
            return true;
        } elseif ($arglist[1] == '') {
            $chatbuf = array();
            $message = '{#server}> $0f0Public chatlog cleared!';
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
            return true;
        }
        $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('{#server}> {#error}Usage: "/st cc" or "/st cc all"'), $player->login));
        if (!$aseco->client->multiquery()) {
            trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
        }
    }
/////////////////////////////////////////////////////////////////////////////////

//Export chatlog
    elseif ($command['params'][0] == 'ce') {
        $params = explode(' ', $arglist[1], 2);
        if ($params[0] == '') {
            $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('{#server}> {#error}Usage: /st ce <file> [options]'), $player->login));
            $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('{#server}> {#error}Available options (can be combined) are: {#highlite}$iall$i $fc0(chatlog + commands), {#highlite}$i+coms$i $fc0(add commands), {#highlite}$i+colors$i $fc0(add color codes), {#highlite}$i+formating$i $fc0(add formating codes)'), $player->login));

            if (!$aseco->client->multiquery()) {
                trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
            }
            return false;
        }
        $options = explode(' ', $params[1]);
        $ocommands = false;
        $ocolors = false;
        $oformating = false;
        if ($params[1] != '')
            foreach ($options as $option) {
                if ($option == 'all') {
                    $ocommands = true;
                    continue;
                }
                if ($option == '+coms' || $option == '+commands') {
                    $ocommands = true;
                    continue;
                }
                if ($option == '+colors') {
                    $ocolors = true;
                    continue;
                }
                if ($option == '+formating') {
                    $oformating = true;
                    continue;
                }
                $message = "{#server}> {#error}Unkown parameter {#highlite}\$i$option{#error} !";
                $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
                return false;
            }
        if ((preg_match('/(?:[\\\\\/:\*\?"<>\|&])|(?:\.{2})|^(?:NUL{1,2}$)/i', $params[0], $match)) > 0) {
            $message = "{#server}> {#error}Forbidden character {#highlite}\$i$match[0]{#error} !";
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
            return false;
        }
        if (preg_match('/\./', $params[0], $match) < 1) $params[0] = $params[0] . '.txt';
        if (file_exists($params[0])) {
            $message = "{#server}> {#error}File {#highlite}\$i$params[0]{#error} already exists!";
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
            return false;
        }
        $oparams = array();
        if ($ocommands) $oparams[] = '+coms';
        if ($oformating) $oparams[] = '+formating';
        if ($ocolors) $oparams[] = '+colors';
        $st_chatbufall = $st->return_gamelog($oparams);
        @$file = fopen($params[0], 'wb');
        if (!($file)) {
            $message = "{#server}> {#error}Can't open file {#highlite}\$i$params[0]{#error} !";
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
            return false;
        }
        foreach ($st_chatbufall as $chat) {
            if ($chat[1][0] == '/') if (!$ocommands) continue;
            $text = '[' . date('Y/m/d H:i:s', $chat[2]) . '] ' . $chat[1][0][0] . ' : ' . $chat[1][1] . "\r\n";
            fwrite($file, $text);
        }
        fclose($file);
        $message = "{#server}> \$0f0Log exported to file {#highlite}$params[0] \$0f0!";
        $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
    }
/////////////////////////////////////////////////////////////////////////////////

//Hide chat panel
    elseif ($command['params'][0] == 'ignore') {
        $params = explode(' ', $arglist[1], 2);
        if ($params[0] == '' || $params[0] == 'help') {
            $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('{#server}> {#error}Usage: /st ignore <login> [time_in_minutes]'), $player->login));
            if (!$aseco->client->multiquery()) {
                trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
            }
            return false;
        }
        if (!$target = $aseco->getPlayerParam($admin, $params[0]))
            return false;
        if ($aseco->allowAbility($target, 'stalkertools')) {
            $message = '{#server}> {#error}Can\'t ignore!';
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
            return false;
        }
        if (isset($st->chat_ignored[$target->login])) {
            $message = '{#server}> {#error}Already on ignore list!';
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
            return false;
        }
        $duration = (float)0;
        if (isset($params[1])) {
            $params[1] = str_replace(',', '.', $params[1]);
            if (!is_numeric($params[1]) || preg_match('/[A-Fex\+-]/i', $params[1])) {
                $message = "{#server}> {#error}{#highlite}\$i$params[1]{#error} is not a number!";
                $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
                return false;
            }
            if ((float)$params[1] > 0)
                $duration = (((float)$params[1]) * 60) + time();
        }
        $st->chat_ignored[$target->login] = array($target->login, $duration);
        $st->chat_ui_send($aseco, $target->login, false);
        if ($duration == 0) $message = formatText('{#server}>> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} disables chat for {#highlite}{3}$z$s{#admin} !',
            $chattitle, $admin->nickname, str_ireplace('$w', '', $target->nickname));
        else $message = formatText('{#server}>> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} disables chat for {#highlite}{3}$z$s{#admin} [' . (float)$params[1] . ' minute' . ((float)$params[1] == 1.0 ? '' : 's') . '] !',
            $chattitle, $admin->nickname, str_ireplace('$w', '', $target->nickname));
        $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
    }
/////////////////////////////////////////////////////////////////////////////////

//Show chat panel
    elseif ($command['params'][0] == 'unignore') {
        $params = $arglist[1];
        if ($params == '' || $params == 'help') {
            $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('{#server}> {#error}Usage: /st unignore <login>'), $player->login));
            if (!$aseco->client->multiquery()) {
                trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
            }
            return false;
        }
        reset($st->chat_ignored);
        if (is_numeric($params)) {
            $ignores = array();
            $id = (int)$params;
            reset($st->chat_ignored);
            $tempid = 0;
            foreach ($st->chat_ignored as $badboy) {
                if ($aseco->getPlayerId($badboy[0]) == 0)
                    continue;
                $found = true;
                $tempid++;
                $ignores[$tempid] = $badboy[0];
            }
            if ($id > $tempid || $id < 1 || !$found) {
                $message = '{#server}> {#error}Invalid Player_ID (use /st listignores first) !';
                $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
                return false;
            }
            if (!$target = $aseco->getPlayerParam($admin, $ignores[$id], true))
                return false;
        }
        if (!isset($target)) if (!$target = $aseco->getPlayerParam($admin, $params, true))
            return false;
        if (!isset($st->chat_ignored[$target->login])) {
            $message = '{#server}> {#error}Not on ignore list!';
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
            return false;
        }
        if ($st->chat_ignored[$target->login][1] == -1) {
            $message = '{#server}> {#error}Can\'t unignore!';
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
            return false;
        }
        unset($st->chat_ignored[$target->login]);
        $st->chat_ui_send($target->login, true);
        $message = formatText('{#server}>> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} enables chat for {#highlite}{3}$z$s{#admin} !',
            $chattitle, $admin->nickname, str_ireplace('$w', '', $target->nickname));
        $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
    }
/////////////////////////////////////////////////////////////////////////////////

//List of chat-disabled players
    elseif ($command['params'][0] == 'listignores') {
        $params = $arglist[1];
        if ($params == 'help') {
            $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('{#server}> {#error}Usage: /st listignores [all]'), $player->login));
            if (!$aseco->client->multiquery()) {
                trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
            }
            return false;
        }
        $allplayers = false;
        if ($params == 'all') $allplayers = true;

        $found = false;
        $id = 0;
        foreach ($st->chat_ignored as $badboy) {
            if ($aseco->getPlayerId($badboy[0]) == 0)
                continue;
            $temp = $aseco->getPlayerParam(NULL, $badboy[0], true);
            if ($badboy[1] == -1) if (!$allplayers) continue;
            $found = true;
            $id++;
            $message = "{#server}> [" . $id . "] \$i" . str_ireplace('$w', '', $temp->nickname) . "\$z{#server}\$s\$fc0\$i (" . $temp->login;
            if (isset($badboy[1])) if ($badboy[1] != 0) {
                if ($badboy[1] == -1) $message = $message . ', permanent';
                else {
                    $left = abs(round(((time() - (float)$badboy[1]) / 60.0), 1));
                    $message = $message . ', ' . $left . ' minute' . ((float)$left == 1.0 ? '' : 's') . ' left';
                }
            }
            $message = $message . ')';
            $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors($message), $player->login));
        }
        if (!$found) {
            $message = '{#server}> {#error}No ignored player found!';
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
            return false;
        }
        if (!$aseco->client->multiquery()) {
            trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
        }

    }
/////////////////////////////////////////////////////////////////////////////////

//Clean chat-disabled player list
    elseif ($command['params'][0] == 'cleanignores') {
        $params = $arglist[1];
        if ($params == 'help') {
            $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('{#server}> {#error}Usage: /st cleanignores'), $player->login));
            if (!$aseco->client->multiquery()) {
                trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
            }
            return false;
        }
        $found = false;
        foreach ($st->chat_ignored as $badboy) {
            if ($badboy[1] == -1) continue;
            $found = true;
            unset($st->chat_ignored[$badboy[0]]);
            if (!$player = $aseco->server->players->getPlayer($badboy[0])) continue;
            $st->chat_ui_send($player->login, true);
        }
        if (!$found) {
            $message = '{#server}> {#error}No ignored players found!';
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
            return false;
        }

        $message = formatText('{#server}>> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} cleans chat-disabled players list!',
            $chattitle, $admin->nickname);
        $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
    }
/////////////////////////////////////////////////////////////////////////////////

//Force player state
    elseif ($command['params'][0] == 'fstate') {
        $params = explode(' ', $arglist[1], 4);
        if (!isset($params[1])) {
            $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('{#server}> {#error}Usage: /st fstate <login> <state> [target [camera]]'), $player->login));
            $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('{#server}> {#error}State can be: user (0), spec (1), player (2)'), $player->login));
            $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('{#server}> {#error}Camera mode can be: unchange (-1) - default, replay (0), follow (1), free (2)'), $player->login));
            if (!$aseco->client->multiquery()) {
                trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
            }
            return false;
        }
        $state = 0;
        $camera = -1;
        if ($params[1] == 'spec' || $params[1] == 'spectator' || $params[1] == '1') $state = 1;
        elseif ($params[1] == 'player' || $params[1] == '2') $state = 2;
        else {
            $message = "{#server}> {#error}Unkown parameter {#highlite}\$i$params[1]{#error} for state !";
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
            return false;
        }
        if ($params[2] != '') {
            if ($state == 2) {
                $message = '{#server}> {#error}Can\'t use target while forcing player mode!';
                $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
                return false;
            }
            if (!$lookto = $aseco->getPlayerParam($admin, $params[2]))
                return false;
            if ($params[3] != '') {
                if ($params[3] == 'unchange' || $params[3] == 'unchanged' || $params[3] == 'default' || $params[3] == '-1') $camera = -1;
                elseif ($params[3] == 'replay' || $params[3] == '0') $camera = 0;
                elseif ($params[3] == 'follow' || $params[3] == '1') $camera = 1;
                elseif ($params[3] == 'free' || $params[3] == '2') $camera = 2;
                else {
                    $message = "{#server}> {#error}Unkown parameter {#highlite}\$i$params[3]{#error} for camera mode !";
                    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
                    return false;
                }
            }
        }
        if ($target = $aseco->getPlayerParam($admin, $params[0], ($state == 0 && isset($st->fstated[$target->login])))) {
            if (!$lookto) {
                $rtn = $aseco->client->query('ForceSpectator', $target->login, $state);
                if ($state == 1) $aseco->client->addCall('ForceSpectatorTarget', array($target->login, '', 2));
            } else {
                $aseco->client->query('ForceSpectator', $target->login, 1);
                if ($state == 0) $rtn = $aseco->client->query('ForceSpectator', $target->login, 0);
                $aseco->client->addCall('ForceSpectatorTarget', array($target->login, $lookto->login, $camera));
            }
            if (!$rtn) {
                trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
            } else {
                if ($state == 1 || ($lookto && $state == 0)) $aseco->client->addCall('SpectatorReleasePlayerSlot', array($target->login));
            }
            $target->st['fstate'] = $state;
            if ($state != 0) $st->fstated[$target->login] = $state;
            else unset($st->fstated[$target->login]);
        }
    }
/////////////////////////////////////////////////////////////////////////////////

//List of players with forced state
    elseif ($command['params'][0] == 'fstates' || $command['params'][0] == 'fstated') {
        $params = $arglist[1];
        if ($params == 'help') {
            $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('{#server}> {#error}Usage: /st fstates [all]'), $player->login));
            if (!$aseco->client->multiquery()) {
                trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
            }
            return false;
        }
        $allplayers = $params == 'all';

        $found = false;
        $id = 0;
        foreach ($st->fstated as $login => $state) {
            if ($aseco->getPlayerId($login) == 0)
                continue;
            $temp = $aseco->server->players->getPlayer($login);
            if (!$temp) if (!$allplayers) continue;
            $temp = $aseco->getPlayerParam(NULL, $login, true);
            $found = true;
            $id++;
            $message = "{#server}> [" . $id . "] \$i" . str_ireplace('$w', '', $temp->nickname) . "\$z{#server}\$s\$fc0\$i (" . $temp->login . ')$z{#server}$s => {#highlite}' . $state;
            $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors($message), $player->login));
        }
        if (!$found) {
            $message = '{#server}> {#error}No fstated player found!';
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
            return false;
        }
        if (!$aseco->client->multiquery()) {
            trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
        }
    }
/////////////////////////////////////////////////////////////////////////////////

//Force player to spec you
    elseif ($command['params'][0] == 'specme') {
        $params = explode(' ', $arglist[1], 2);
        $params = $params[0];
        if ($params == '' || $params == 'help') {
            $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('{#server}> {#error}Usage: /st specme <login>'), $player->login));
            if (!$aseco->client->multiquery()) {
                trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
            }
            return false;
        }

        $chat_command = array();
        $chat_command['author'] = $player;
        $chat_command['params'] = 'fstate ' . $params . ' 0 ' . $player->login . ' 1';
        chat_st($aseco, $chat_command);
    }
/////////////////////////////////////////////////////////////////////////////////

//Custom vote
    elseif ($command['params'][0] == 'vote') {

        $command['params'] = preg_replace('/(?<!\\\\)\'/', '"', $command['params']);
        $command['params'] = str_replace('\\\'', '\\a', $command['params']);
        $command['params'] = str_replace(array('\\;', '\\:', '\\"'), array('\\s', '\\c', '\\q'), $command['params']);
        $args = array();
        $multistr = '';
        $in_multi = false;
        for ($i = 1; $i < count($command['params']); $i++) {
            if ($in_multi) {
                if (substr($command['params'][$i], -1) == '"') {
                    $args[] = $multistr . ' ' . substr($command['params'][$i], 0, -1);
                    $multistr = '';
                    $in_multi = false;
                } else {
                    $multistr .= ' ' . $command['params'][$i];
                }
            } else {
                if (substr($command['params'][$i], 0, 1) == '"' && substr($command['params'][$i], -1) == '"') {
                    $args[] = substr($command['params'][$i], 1, -1);
                    continue;
                }
                if (substr($command['params'][$i], 0, 1) == '"') {
                    $multistr = substr($command['params'][$i], 1);
                    $in_multi = true;
                } else {
                    $args[] = $command['params'][$i];
                }
            }
        }
        $params = str_replace(array('\\q', '\\a'), array('"', '\''), $args);

        if (!isset($params[0]) || $params[0] == '') {
            $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('{#server}> {#error}Usage: /st vote "message[:pass cmd[:fail cmd" [timeout [ratio]]'), $player->login));
            $aseco->client->addCall('ChatSendServerMessageToLogin', array($aseco->formatColors('{#server}> {#error}Special values: a timeout of \'0\' means default, \'1\' means indefinite'), $player->login));
            if (!$aseco->client->multiquery()) {
                trigger_error('[' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
            }
            return false;
        }
        $param = explode(':', $params[0]);
        $param = str_replace(array('\\c'), array(':'), $param);
        $params = str_replace(array('\\s', '\\c'), array(';', ':'), $params);
        $message = preg_replace('/\?+\s*$/', '', str_replace(array('\\s'), array(';'), $param[0]));
        $search = array();
        $replace = array();
        $search[] = $aseco->server->serverlogin;
        $replace[] = str_ireplace('$w', '', $aseco->server->name) . '$z';
        foreach ($aseco->server->players->player_list as $pl) {
            $search[] = $pl->login;
            $replace[] = str_ireplace('$w', '', $pl->nickname) . '$z';
        }
        $message = str_replace($search, $replace, $message);
        $message = trim($message);
        $message = ucfirst($message);
        if ($message == '') {
            $message = '{#server}> {#error}No message!';
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
            return false;
        }
        $message .= '$z';
        $st->uservote_command = (isset($param[1]) ? str_replace(array('\\s'), array(';'), explode(';', $param[1])) : '');
        $st->uservote_fail_command = (isset($param[2]) ? str_replace(array('\\s'), array(';'), explode(';', $param[2])) : '');
        $timeout = 0;
        if (isset($params[1])) if (!is_numeric($params[1])) {
            $message = "{#server}> {#error}{#highlite}\$i$params[1]{#error} is not a number!";
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
            return false;
        } else $timeout = (int)$params[1];
        if ($timeout != 1) $timeout = $timeout * 1000;
        $ratio = -1;
        if (isset($params[2])) $params[2] = str_replace(',', '.', $params[2]);
        if (isset($params[2])) if (!is_numeric($params[2])) {
            $message = "{#server}> {#error}{#highlite}\$i$params[2]{#error} is not a number!";
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
            return false;
        }
        if (isset($params[2])) $ratio = (double)$params[2];
        if ($ratio > 1) $ratio = $ratio / 100;
        $st->custom_vote($message, 'st_custom_user_vote', $player->login, $timeout, $ratio);
    }
/////////////////////////////////////////////////////////////////////////////////

//No command - only prefix
    elseif ($command['params'][0] == '') {
        $message = '{#server}> {#error}Use /st help for help!';
        $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
        return false;
    }
/////////////////////////////////////////////////////////////////////////////////

//Invalid command
    else {
        $message = '{#server}> {#error}Unknown command : {#highlite}$i ' . $arglist[0] . ' ' . $arglist[1];
        $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
    }
/////////////////////////////////////////////////////////////////////////////////
}


