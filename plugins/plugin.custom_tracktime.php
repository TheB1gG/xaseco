<?php
/* 
* Custom Timelimit for tracks 
*
* usage /timeset min:sek for example 5min 30sek timelimit for current track
* /timeset 5:30
*
* remember to create databases from custom_tracktimes.sql
* Created by reaby
*/

Aseco::registerEvent('onEndRace', 'customtimelimit');
Aseco::addChatCommand('timeset', 'Sets custom timelimit for next track');


function chat_timeset($aseco, $command) {
global $TimeAdmins;
$player = $command['author'];
$login = $player->login;
$TimeAdmins = array("the-big-gll.");  // ADMINIT
if (!in_array($login,$TimeAdmins) ) return; // if you are not admin, bail out
		
	global $CustomTimeLimit;
	if (trim($command['params']) != "") 
	{
	$challenge_now = get_trackinfo($aseco,0); // get current challenge info 
	$uid = $challenge_now->uid;

	
	}
	$message = "Admin sets current tracktime to ".trim($command['params']."min");
	$aseco->client->query("ChatSendServerMessage", $message);
		
}

function chat_trackinfo($aseco, $command) {
$nextChallenge = get_trackinfo($aseco,1);
$uid=$nextChallenge['uid'];
}
$result=""
function customtimelimit($aseco, $data) {
$Challenge = get_trackinfo($aseco,1); // get next challenge info 

$timelimit = split(":",trim($result[0]['tracktime']));
$CustomTimeLimit=(($timelimit[0] * 60) + $timelimit[1])*1000;

$newtime = $CustomTimeLimit;
$aseco->client->addcall('SetTimeAttackLimit', array($newtime));
}

function get_trackinfo($aseco, $offset) {

	// get current/next track using /nextmap algorithm
	if ($aseco->server->getGame() != 'TMF') {
		$aseco->client->query('GetCurrentChallengeIndex');
		$trkid = $aseco->client->getResponse();
		$trkid += $offset;
		$aseco->client->resetError();
		$rtn = $aseco->client->query('GetChallengeList', 1, $trkid);
		$track = $aseco->client->getResponse();
		if ($aseco->client->isError()) {
			// get first track
			$rtn = $aseco->client->query('GetChallengeList', 1, 0);
			$track = $aseco->client->getResponse();
		}
	} else {  // TMF
		if ($offset == 1)
			$aseco->client->query('GetNextChallengeIndex');
		else
			$aseco->client->query('GetCurrentChallengeIndex');
		$trkid = $aseco->client->getResponse();
		$rtn = $aseco->client->query('GetChallengeList', 1, $trkid);
		$track = $aseco->client->getResponse();
	}

	// get track info
	$rtn = $aseco->client->query('GetChallengeInfo', $track[0]['FileName']);
	$trackinfo = $aseco->client->getResponse();
	return new Challenge($trackinfo);
}  // get_trackinfo

?>