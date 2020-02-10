<?php
/* ReplayCharge v1.22
 *
 * Plugin by Leigham. With thanks to Xymph, from whom I stole some code :)
 *
 * Important: This plugin will only work on a TMUF server. 
 * The server must have at least a small amount of coppers for
 * the plugin to work.
 *
 * Scroll down to configure plugin settings.
 */

Aseco::registerEvent('onSync',                      'replaychargeVersion');
Aseco::registerEvent('onStartup',                   'replaychargeSetup');
Aseco::registerEvent('onPlayerConnect',             'replaychargeConnect');
Aseco::registerEvent('onNewChallenge',              'replaychargeCheck');
Aseco::registerEvent('onEndRace',                   'replaychargeOff');
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'replaychargeClick');
Aseco::registerEvent('onBillUpdated',               'replaychargeBill');
Aseco::registerEvent('onEverySecond',               'replaychargeSecond');

function replaychargeVersion($aseco) { // Register this to the global version pool (for up-to-date checks)
  $aseco->plugin_versions[] = array(
     'plugin'   => 'plugin.replaycharge.php',
     'author'   => 'Leigham',
     'version'   => '1.22'
  );
}

function replaychargeSetup($aseco) {
	global $replaycharge, $replaybills;

	$replaycharge = array();
// Configuration settings below
	if ($config = $aseco->xml_parser->parseXml('replaycharge.xml', true)) {
    $config = $config['SETTINGS'];
    $posx = $config['POSX'][0];
    $posy = $config['POSY'][0];
    $replaycharge['coppers']= intval($config['CHARGE'][0]);
		$replaycharge['position'] = $posx.' '.$posy.' 1';   
		$replaycharge['blink'] = ((strtolower($config['BLINK'][0]) == 'true') ? true : false);
		$replaycharge['max_val'] = intval($config['MAX_REPLAYS'][0]);
		$replaycharge['replays'] = 0;		
		$replaycharge['true'] = false;
		$replaycharge['success'] = false;
		$replaycharge['score'] = false;
		$replaycharge['max'] = false;
		$replaybills = array();
		} else {
     // could not parse XML file
     trigger_error('[swearbox] Could not read/parse settings file swearbox.xml!', E_USER_WARNING);
     return false;
  }
}

function replaychargeConnect($aseco) {
	global $replaycharge;
	
	if (!$replaycharge['score']) {
		if (!$replaycharge['true']) {
			if (!$replaycharge['max']) {
				replaychargeOn($aseco);
			} else {
				replaychargeMax($aseco);
			}
		} else {
			replaychargeSuccess1($aseco);
		}
	}
}

function replaychargeCheck($aseco) {
	global $replaycharge;
	
	if ($replaycharge['true']) {
		if ($replaycharge['replays'] >= $replaycharge['max_val']) {
			$replaycharge['max'] = true;
			replaychargeMax($aseco);
		} else {
			replaychargeOn($aseco);
			$replaycharge['max'] = false;
		}
	} else {
		$replaycharge['replays'] = 0;
		$replaycharge['max'] = false;
		replaychargeOn($aseco);
	}
}

function replaychargeOn($aseco) {
	global $replaycharge;
	
	$replaycharge['true'] = false;
	$replaycharge['score'] = false;
	
	$xml = '<manialink id="1234512301">
    <frame posn="'.$replaycharge['position'].'">
        <quad posn="0 0 0" sizen="4.6 6.5" style="BgsPlayerCard" substyle="BgCardSystem" action="234561"></quad>
        <label posn="2.2 -0.75 0.1" sizen="3.65 2" halign="center"  textsize="1" scale="0.9" textcolor="FFFF" text="PAY '.$replaycharge['coppers'].'"></label>
        <label posn="2.3 -2.1 0.1" sizen="6.65 2" halign="center"  textsize="1" scale="0.6" textcolor="FC0F" text="COPPERS"></label>
        <label posn="2.3 -3.55 0.1" sizen="3.65 2" halign="center"  textsize="1" scale="0.9" textcolor="FFFF" text="FOR"></label>
        <label posn="2.3 -4.9 0.1" sizen="6.65 2" halign="center"  textsize="1" scale="0.6" textcolor="FC0F" text="REPLAY"></label>
    </frame>
	</manialink>';
	$aseco->client->addCall('SendDisplayManialinkPage', array($xml, 0, false));
}

function replaychargeMax($aseco) {
	global $replaycharge;
	
	$replaycharge['true'] = false;
	$replaycharge['score'] = false;
	
	$xml = '<manialink id="1234512301">
    <frame posn="'.$replaycharge['position'].'">
        <quad posn="0 0 0" sizen="4.6 6.5" style="BgsPlayerCard" substyle="BgCardSystem"></quad>
        <label posn="2.2 -0.75 0.1" sizen="3.65 2" halign="center"  textsize="1" scale="0.9" textcolor="FFFF" text="MAXIMUM"></label>
        <label posn="2.3 -2.1 0.1" sizen="6.65 2" halign="center"  textsize="1" scale="0.6" textcolor="FC0F" text="REPLAY"></label>
        <label posn="2.3 -3.55 0.1" sizen="3.65 2" halign="center"  textsize="1" scale="0.9" textcolor="FFFF" text="LIMIT"></label>
        <label posn="2.3 -4.9 0.1" sizen="6.65 2" halign="center"  textsize="1" scale="0.6" textcolor="FC0F" text="REACHED!"></label>
    </frame>
	</manialink>';
	$aseco->client->addCall('SendDisplayManialinkPage', array($xml, 0, false));
}

function replaychargeSuccess1($aseco) {
	global $replaycharge;
	
	$replaycharge['success'] = true;
	$replaycharge['blink1'] = true;
	
	$xml = '<manialink id="1234512301">
    <frame posn="'.$replaycharge['position'].'">
        <quad posn="0 0 0" sizen="4.6 6.5" style="BgsPlayerCard" substyle="BgCardSystem"></quad>
        <label posn="2.2 -0.75 0.1" sizen="3.65 2" halign="center"  textsize="1" scale="0.9" textcolor="FFFF" text="CHALLENGE"></label>
        <label posn="2.3 -2.1 0.1" sizen="6.65 2" halign="center"  textsize="1" scale="0.6" textcolor="FC0F" text="WILL BE"></label>
        <label posn="2.3 -3.55 0.1" sizen="3.65 2" halign="center"  textsize="1" scale="0.9" textcolor="FFFF" text="REPLAYED"></label>
        <label posn="2.3 -4.9 0.1" sizen="6.65 2" halign="center"  textsize="1" scale="0.6" textcolor="FC0F" text="NEXT!"></label>
    </frame>
	</manialink>';
	$aseco->client->addCall('SendDisplayManialinkPage', array($xml, 0, false));
}

function replaychargeSuccess2($aseco) {
	global $replaycharge;
	
	$replaycharge['blink1'] = false;
	
	$xml = '<manialink id="1234512301">
    <frame posn="'.$replaycharge['position'].'">
        <quad posn="0 0 0" sizen="4.6 6.5" style="BgsPlayerCard" substyle="BgCardSystem"></quad>
    </frame>
	</manialink>';
	$aseco->client->addCall('SendDisplayManialinkPage', array($xml, 0, false));
}

function replaychargeOff($aseco) {
	global $replaycharge;
	
	$replaycharge['success'] = false;
	$replaycharge['score'] = true;
		
	$xml = '<manialink id="1234512301">
		<frame>
		</frame>
	</manialink>';
	$aseco->client->addCall('SendDisplayManialinkPage', array($xml, 0, false));
}

function replaychargeSecond($aseco) {
	global $replaycharge;
	
	if ($replaycharge['blink']) {
		if ($replaycharge['success']) {
			if ($replaycharge['blink1']) {
				replaychargeSuccess2($aseco);
			} else {
				replaychargeSuccess1($aseco);
			}
		}
	}
}

function replaychargeClick($aseco, $command) {
	global $replaycharge, $replaybills;
	
	$coppers = $replaycharge['coppers'];
	$playerid = $command[0];
	$login = $command[1];
	$answer = $command[2].'';
	$aseco->client->query('GetDetailedPlayerInfo', $login);
	$player = $aseco->client->getResponse();
	$nickname = $player['NickName'];
		if ($answer == '234561') {
		$aseco->client->query('GetCurrentChallengeInfo');
		$thistrack = $aseco->client->getResponse();
		$aseco->client->query('GetNextChallengeInfo');
		$nexttrack = $aseco->client->getResponse();
		// Check if already being replayed
		if ($thistrack['FileName'] != $nexttrack['FileName']) {	
			//	Check for TMF server
			if ($aseco->server->getGame() == 'TMF') {
				// check for TMUF server
				if ($aseco->server->rights) {
					// check for TMUF player
					if ($player['OnlineRights'] == 3) {
						// start the transaction
						$message = 'You need to pay '.$coppers.' coppers to replay the track';
						$aseco->client->query('SendBill', $login, $coppers, $message, '');
						$replaybillid = $aseco->client->getResponse();
						$replaybills[$replaybillid] = array($login, $nickname, $coppers);
					} else {
					$message = formatText($aseco->getChatMessage('UNITED_ONLY'), 'account');
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
					}
				} else {
				$message = formatText($aseco->getChatMessage('UNITED_ONLY'), 'server');
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
				}
			} else {
			$message = $aseco->getChatMessage('FOREVER_ONLY');
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
			}
		} else {
		$message = '>$f00 This track is already being replayed';
		$aseco->client->query('ChatSendServerMessageToLogin', $message, $login);
		}
	}
}
		// [0]=BillId, [1]=State, [2]=StateName, [3]=TransactionId
function replaychargeBill($aseco, $replaybill) {
	global $replaybills, $jukebox, $replaycharge, $atl_restart;
	$replaybillid = $replaybill[0];
	// check for known bill ID
	if (array_key_exists($replaybillid, $replaybills)) {
		// get bill info
		$login = $replaybills[$replaybillid][0];
		$nickname = $replaybills[$replaybillid][1];
		$coppers = $replaybills[$replaybillid][2];
				// check bill state
		switch($replaybill[1]) {
		case 4:  // Payed (Paid)
			if (!$replaycharge['score']) {
				$uid = $aseco->server->challenge->uid;
				$jukebox = array_reverse($jukebox, true);
				$jukebox[$uid]['FileName'] = $aseco->server->challenge->filename;
				$jukebox[$uid]['Name'] = $aseco->server->challenge->name;
				$jukebox[$uid]['Env'] = $aseco->server->challenge->environment;
				$jukebox[$uid]['Login'] = $login;
				$jukebox[$uid]['Nick'] = $nickname;
				$jukebox[$uid]['source'] = 'ReplayCharge';
				$jukebox[$uid]['tmx'] = false;
				$jukebox[$uid]['uid'] = $uid;
				$jukebox = array_reverse($jukebox, true);
			
				$aseco->releaseEvent('onJukeboxChanged', array('replay', $jukebox[$uid]));
			} else {
				if (isset($atl_restart)) $atl_restart = true;
				$aseco->client->query('ChallengeRestart');
			}				
			$message = '$f90Player $z'.$nickname.'$z$f90  pays '.$coppers.' coppers and queues challenge for replay!';
			$aseco->client->query('ChatSendServerMessage', $message);
			$aseco->console('Player {1} paid {2} coppers to replay the current track', $login, $coppers);
			unset($replaybills[$replaybillid]);
			$replaycharge['true'] = true;
			$replaycharge['replays']++;
			replaychargeSuccess1($aseco);
			break;
		case 5:  // Refused
			$message = '{#server}> {#error}Transaction refused!';
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
			unset($replaybills[$replaybillid]);
			break;
		case 6:  // Error
			$message = '{#server}> {#error}Transaction failed: {#highlite}$i ' . $replaybill[2];
			if ($login != '')
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
			else
				$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
			unset($replaybills[$replaybillid]);
			break;
		default:  // CreatingTransaction/Issued/ValidatingPay(e)ment
			break;
		}
	}
}
?>