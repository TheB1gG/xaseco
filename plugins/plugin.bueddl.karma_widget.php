<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
/*
 * ----------------------------------------------------------------------------------
 * Author:	Bueddl
 * Version:	1.0.1
 * Date:	2012-02-27
 * Copyright:	2012 by Bueddl
 * ----------------------------------------------------------------------------------
 *
 * ----------------------------------------------------------------------------------
 *
 * Dependencies: plugin.rasp_karma.php
 */

Aseco::registerEvent('onPlayerConnect',				'bkm_onPlayerConnect');
Aseco::registerEvent('onNewChallenge',				'bkm_onNewChallenge');
Aseco::registerEvent('onEndRace',					'bkm_onEndRace');
Aseco::registerEvent('onKarmaChange',				'bkm_onKarmaChange');
Aseco::registerEvent('onPlayerManialinkPageAnswer',	'bkm_onPlayerManialinkPageAnswer');

function bkm_onPlayerManialinkPageAnswer($aseco, $call) {
//var_dump($call);
	$command = array(
		'author' => $aseco->server->players->getPlayer($call[1])
	);
	switch ($call[2]) {
		case 32037385:
			//-
			KarmaVote($aseco, $command, -1);
			break;
		case 32037386:
			//+
			KarmaVote($aseco, $command, 1);
			break;
	}
}

function bkm_onPlayerConnect($aseco, $player) {
	bkm_display($aseco, $player->login);
}

function bkm_onNewChallenge($aseco, $call) {
//foreach ($call as $a => $b) {
	//$aseco->console_text("$a => $b");
	//flush();
	//}
	bkm_display($aseco, NULL, $call->id);
}

function bkm_onEndRace($aseco, $call) {
	$xml = '<manialink version="0" id="32037384"></manialink>';
	$aseco->client->query('SendDisplayManialinkPage', $xml, 0, false);	
}

function bkm_onKarmaChange($aseco, $call) {
	bkm_display($aseco, NULL);
}


function bkm_display($aseco, $login = NULL, $cid = NULL) {
	if ($cid == $NULL)
		$cid = $aseco->server->challenge->id;
	$karma = getKarmaValues($cid);
	$karma['GoodPct'] = $karma['GoodPct']/100;
	
	
	foreach ($aseco->server->players->player_list as $login2 => $player) {
		//var_dump($player);
		$query = 'SELECT Score FROM rs_karma WHERE PlayerId=' . $player->id . ' AND ChallengeId=' . $cid;
		//echo $query."\n";

		$res = mysql_query($query);
		$myvote = 0;
		if (mysql_num_rows($res) > 0) {
			$row = mysql_fetch_object($res);
			//var_dump($row);
			$myvote = (int)$row->Score;
		}
		
		$posx = '15.3';
		$posy = '-4.3';
		$scale = '0.92';
		$style = 'BgsPlayerCard';
		$substyle = 'ProgressBar';
		
		//show widget
		$xml = '<manialink version="0" id="32037384">'.
			   '<frame posn="'.$posx.' '.$posy.'" scale="'.$scale.'">'.
			   '<format textsize="1.6"/>'.
			   '<quad posn="40 40 1" sizen="13 11" style="'.$style.'" substyle="'.$substyle.'"/>'.
			   '<label posn="41 39.3 1.75" sizen="12 1" text="$o$s$fffLocal Votes!"/>'.
			   '<quad posn="40.5 39.5 1.5" sizen="12 2" style="'.$style.'" substyle="'.$substyle.'"/>';
		if ($karma['Total'] != 0) {
			for ($i = 0; $i < round($karma['GoodPct']*10); $i++) {
				$xml.= '<quad posn="'.(40.5 + $i*1.2).' 37 1.5" sizen="1.2 2.4" image="http://kreipe.patrick.coolserverhosting.de/local/green.jpg"/>';
			}
			for ($i = round($karma['GoodPct']*10); $i < 10; $i++) {
				$xml.= '<quad posn="'.(40.5 + $i*1.2).' 37 1.5" sizen="1.2 2.4" image="http://kreipe.patrick.coolserverhosting.de/local/red.jpg"/>';
			}
		} else {
			for ($i = 0; $i < 10; $i++) {
				$xml.= '<quad posn="'.(40.5 + $i*1.2).' 37 1.5" sizen="1.2 2.4" image="http://kreipe.patrick.coolserverhosting.de/local/grey.jpg"/>';
			}
		}
			   
		$xml.= '<label posn="42.5 31.5 1.75" halign="center" sizen="3 1" text="$s$f00'.$karma['Bad'].'"/>'.
			   '<quad posn="40.5 35 1.75" sizen="4 4" style="Icons64x64_1" substyle="SliderCursor" action="32037385" />'.
			   '<label posn="42 34.8 1.8" textsize="4" sizen="2 2" text="$s$f00-" />'.
			   '<label posn="50.5 31.5 1.75" halign="center" sizen="3 1" text="$s$0f0'.$karma['Good'].'"/>'.
			   '<quad posn="48.5 35 1.75" sizen="4 4" style="Icons64x64_1" substyle="SliderCursor" action="32037386"/>'.
			   '<label posn="49.65 34.45 1.8" textsize="4" sizen="2 2" text="$s$0f0+" />'.
			   '<label posn="46.5 31.5 1.75" halign="center" sizen="5 1" text="$s$fff'.($karma['Total']!=0 ? number_format($karma['GoodPct']*100, 1) : '--,-').'%"/>';
		
		//var_dump($myvote);
		
		switch ($myvote) {
			case -1:
				$xml .= '<quad posn="43.5 33.95 1.85" sizen="2 2" style="Icons64x64_1" substyle="ArrowPrev"/>';
				break;
			case 1:
				$xml .= '<quad posn="47.5 33.95 1.85" sizen="2 2" style="Icons64x64_1" substyle="ArrowNext"/>';
				break;
		}
		$xml .='</frame>'.
			   '</manialink>';
			   
			   
		if($login == NULL || $login == $login2) {
			$aseco->client->query('SendDisplayManialinkPageToLogin', $login2, $xml, 0, false);
		}
	}
	


}


?>
