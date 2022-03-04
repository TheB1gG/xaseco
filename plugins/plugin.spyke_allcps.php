<?php
/**	plugin.allcps.php
 *	Checkpoint info plugin for XAseco by Spyker, 2011
 *	Configuration file: spyke_allcps.xml
 *	Copy this file to xaseco plugins folder
 */
Aseco::registerEvent('onSync', 'undef_sync');	
Aseco::registerEvent('onCheckpoint', 'checkpoint');
Aseco::registerEvent('onNewChallenge', 'spyke_ingame_record');
Aseco::registerEvent('onBeginRound', 'spyke_ingame_record');
Aseco::registerEvent('onPlayerFinish', 'spyke_manialink_finish');
Aseco::registerEvent('onPlayerFinish', 'spyke_ingame_record');
Aseco::registerEvent('onEndRound', 'spyke_manialink_end');
Aseco::registerEvent('onStartup', 'info');


define('ALL_CPS_VERSION', '1.3');

function undef_sync($aseco){

$aseco->plugin_versions[] = array(
      'plugin'   => 'plugin.spyke_allcps.php',
      'author'   => 'Spyker',
      'version'   => ALL_CPS_VERSION
	);
}


function info($aseco, $command){

global $aseco, $info;

$info= new info_class();
$info->Aseco = $aseco;
$info->Startup();

} 


function spyke_ingame_record($aseco, $player)
{
global $dedi_db, $local, $info, 
$positive_cp_color, $negative_cp_color, 
$rank_color, $maxrecs, $show_time,
$show_custom_version, $show_dedimania;

		if (IN_XASECO){
			global $dedi_db;
		} else {
			$dedi_db = $aseco->Aseco->plugins['DediMania']->dedi_db;
		}

		$rank = 0;
						while ($rank <= count($dedi_db['Challenge']['Records'])){
							$dedidata['login']= $dedi_db['Challenge']['Records'][$rank]['Login'];
							$dediarray[$dedidata['login']] = array("rank" =>$rank, "checkpoints" => $dedi_db['Challenge']['Records'][$rank]['Checks']);
							$rank++;
							}
						
		$rank = 0;			
						while ($rank <= $aseco->server->records->count()){
							$localdata['login'] = $aseco->server->records->getRecord($rank)->player->login;
							$localarray[$localdata['login']] = array("rank" =>$rank, "checkpoints" => $aseco->server->records->getRecord($rank,true)->checks);
							$rank++;
							}

$local = $aseco->server->records->getRecord(0,true);
unset ($info->dedicheck, $info->localcheck);
$info->dedicheck = $dediarray;
$info->localcheck = $localarray;

$settings = simplexml_load_file('spyke_allcps.xml');
$positive_cp_color = $settings->cpcolor->positive_cp_color;
$negative_cp_color = $settings->cpcolor->negative_cp_color;
$rank_color = $settings->cpcolor->rank_color;
$show_time = $settings->show_time;
$show_custom_version = $settings->show_custom_version;
$show_dedimania = $settings->show_dedimania;
}


function checkpoint($aseco, $command) {
global $aseco, $dedi_db, $local, 
$positive_cp_color, $negative_cp_color, 
$info, $rank_color, $show_time, $maxrecs,
$show_custom_version, $show_dedimania;


unset($deditemp);
unset($localtemp);
unset ($dediperso);
unset ($localperso);
unset ($dedirank);
unset ($localrank);

$login = $command[1];
$timeref = $command[2];
$cp = $command[4];
$show_time = 0+$show_time;

$deditemp = $info->dedicheck[$login];
$dediperso = $deditemp['checkpoints'];

$localtemp = $info->localcheck[$login];
$localperso = $localtemp['checkpoints'];

$dedirank = $deditemp['rank'];
if($dedirank >= "0"){
$dedirank++;
}else{
$dedirank = "_";
}

$localrank = $localtemp['rank'];
if($localrank >="0"){
$localrank++;
}else{
$localrank = "_";
}


$maxrec = $rank_color.$maxrecs;
$localmaxrecs = "$"."fff"."/"."$".$maxrec;
$maxdedi = $dedi_db['MaxRecords'];
$maxdedi = $rank_color.$maxdedi;
$maxdedi = "$"."fff"."/"."$".$maxdedi;

if(empty($localperso))
{
$persolocalbest = 's$f70no record';
}else{
$timediff = $timeref - $localperso[$cp]; //individuallocaldiff
if ($timediff < 0){ 
$persolocalbest = "-".formatTime(abs($timediff));
$persolocalbest = $negative_cp_color.$persolocalbest;
}else{
$persolocalbest = "+".formatTime(abs($timediff));
$persolocalbest = $positive_cp_color.$persolocalbest;
} 
}

if(empty ($local->checks[$cp]))
{
$best = 's$f70no record';
}else{
$bestdiff = $timeref - $local->checks[$cp];//bestlocaldiff
if ($bestdiff < 0){
$best = "-".formatTime(abs($bestdiff));
$best = $negative_cp_color.$best;
}else{
$best = "+".formatTime(abs($bestdiff));
$best = $positive_cp_color.$best;
}
}

if(empty ($dedi_db['Challenge']['Records'][0]['Checks']))
{
$dedibestof = 's$f70no record';
}else{
$deditime = $dedi_db['Challenge']['Records'][0]['Checks'];//bestdedidiff
$dedidiff = $timeref - $deditime[$cp];
if ($dedidiff < 0){
$dedibestof = "-".formatTime(abs($dedidiff));
$dedibestof = $negative_cp_color.$dedibestof;
}else{
$dedibestof = "+".formatTime(abs($dedidiff)); 
$dedibestof = $positive_cp_color.$dedibestof;
}
}

if(empty($dediperso)){
$persodedibest = 's$f70no record';
}else{
$dedidiff = $timeref - $dediperso[$cp];//individualdedidiff
if ($dedidiff < 0){
$persodedibest = "-".formatTime(abs($dedidiff));
$persodedibest = $negative_cp_color.$persodedibest;
}else{
$persodedibest ="+".formatTime(abs($dedidiff));
$persodedibest = $positive_cp_color.$persodedibest;
} 
}

if($show_custom_version == "true"){
$xmltext = '<?xml version="1.0" encoding="UTF-8"?>';
$xmltext .= '<manialink id="'.$info->manialink_id1.'"></manialink>';
$aseco->client->query("SendDisplayManialinkPage", $xmltext, 0, false);
$xmlchrono = $info->getManialinkchrono($cp, $persolocalbest, $best, $dedibestof, $persodedibest, $localrank, $dedirank, $localmaxrecs, $maxdedi, $show_dedimania);
$aseco->client->query("SendDisplayManialinkPageToLogin", $login, $xmlchrono, $show_time, false); 
}
elseif($show_custom_version == "false"){
$xmlchrono = '<?xml version="1.0" encoding="UTF-8"?>';
$xmlchrono .= '<manialink id="'.$info->manialink_id2.'"></manialink>';
$aseco->client->query("SendDisplayManialinkPage", $xmlchrono, 0, false);
$xmltext = $info->getManialinktext($cp, $persolocalbest, $best, $dedibestof, $persodedibest, $localrank, $dedirank, $localmaxrecs, $maxdedi, $show_dedimania);
$aseco->client->query("SendDisplayManialinkPageToLogin", $login, $xmltext, $show_time, false); 
}
else{
$xml = '<?xml version="1.0" encoding="UTF-8"?>';
$xml .= '<manialink id="'.$info->manialink_id1.'"></manialink>';
$aseco->client->query("SendDisplayManialinkPageToLogin", $login, $xml, 0, false); 
$xml = '<?xml version="1.0" encoding="UTF-8"?>';
$xml .= '<manialink id="'.$info->manialink_id2.'"></manialink>';
$aseco->client->query("SendDisplayManialinkPageToLogin", $login, $xml, 0, false); 
}
}

function spyke_manialink_end($aseco, $command) {

global $info;
$xmltext = '<?xml version="1.0" encoding="UTF-8"?>';
$xmltext .= '<manialink id="'.$info->manialink_id1.'"></manialink>';
$aseco->client->query("SendDisplayManialinkPage", $xmltext, 0, false); 

$xmlchrono = '<?xml version="1.0" encoding="UTF-8"?>';
$xmlchrono .= '<manialink id="'.$info->manialink_id2.'"></manialink>';
$aseco->client->query("SendDisplayManialinkPage", $xmlchrono, 0, false); 

}

function spyke_manialink_finish($aseco, $command) {

global $info;
$login = $command->player->login;
$xmltext = '<?xml version="1.0" encoding="UTF-8"?>';
$xmltext .= '<manialink id="'.$info->manialink_id1.'"></manialink>';
$aseco->client->query("SendDisplayManialinkPageToLogin", $login, $xmltext, 0, false); 

$xmlchrono = '<?xml version="1.0" encoding="UTF-8"?>';
$xmlchrono .= '<manialink id="'.$info->manialink_id2.'"></manialink>';
$aseco->client->query("SendDisplayManialinkPageToLogin", $login, $xmlchrono, 0, false); 
}

class info_class
{
public $Aseco, $localcheck, $dedicheck, $manialink_id1, $manialink_id2;


	function Startup() {
		//settings from config-file
		$settings = simplexml_load_file('spyke_allcps.xml');
		
		$show_dedimania = $settings->show_dedimania;
		$this->frame_custom_local_title_posn_x = $settings->c_title_local->hx;
		$this->frame_custom_local_title_posn_y = $settings->c_title_local->vy;
		$this->frame1_custom_posn_x = $settings->c_left_top1_point->hx;
		$this->frame1_custom_posn_y = $settings->c_left_top1_point->vy;
		$this->frame2_custom_posn_x = $settings->c_left_top2_point->hx;
		$this->frame2_custom_posn_y = $settings->c_left_top2_point->vy;
		$this->frame_custom_dedi_title_posn_x = $settings->c_title_dedi->hx;
		$this->frame_custom_dedi_title_posn_y = $settings->c_title_dedi->vy;
		$this->frame3_custom_posn_x = $settings->c_left_top3_point->hx;
		$this->frame3_custom_posn_y = $settings->c_left_top3_point->vy;
		$this->frame4_custom_posn_x = $settings->c_left_top4_point->hx;
		$this->frame4_custom_posn_y = $settings->c_left_top4_point->vy;

		$this->frame_local_title_posn_x = $settings->title_local->hx;
		$this->frame_local_title_posn_y = $settings->title_local->vy;
		$this->frame1_posn_x = $settings->left_top1_point->hx;
		$this->frame1_posn_y = $settings->left_top1_point->vy;
		$this->frame2_posn_x = $settings->left_top2_point->hx;
		$this->frame2_posn_y = $settings->left_top2_point->vy;
		$this->frame_dedi_title_posn_x = $settings->title_dedi->hx;
		$this->frame_dedi_title_posn_y = $settings->title_dedi->vy;
		$this->frame3_posn_x = $settings->left_top3_point->hx;
		$this->frame3_posn_y = $settings->left_top3_point->vy;
		$this->frame4_posn_x = $settings->left_top4_point->hx;
		$this->frame4_posn_y = $settings->left_top4_point->vy;

		$this->text_color = $settings->cpcolor->text_color;
		$this->rank_color = $settings->cpcolor->rank_color;
		$this->manialink_id1 = $settings->manialink_id1;
		$this->manialink_id2 = $settings->manialink_id2;
		}

	function getManialinktext($cp, $persolocalbest, $best, $dedibestof, $persodedibest, $localrank, $dedirank, $localmaxrecs, $maxdedi, $show_dedimania) {
		$cp = $cp+1;
		$xmltext = '<?xml version="1.0" encoding="UTF-8"?>';
		$xmltext .= '<manialink id='.$this->manialink_id1.'>';

		$xmltext .= '<frame posn="'.$this->frame_local_title_posn_x.' '.$this->frame_local_title_posn_y.' 0.3">';
		$xmltext .= '<quad posn="0 19.1 0.3" sizen="16 4"/>';
		$xmltext .= '<label scale="0.8" posn="0 3.5 0.1" halign="center" valign="center" textsize="2" text="$fffLocal records"/>';
		$xmltext .= '</frame>';

		$xmltext .= '<frame posn="'.$this->frame1_posn_x.' '.$this->frame1_posn_y.' 0.3">';
		$xmltext .= '<quad posn="0 19.1 0.3" sizen="16 4"/>';		
		$xmltext .= '<label scale="0.8" posn="0 0 0.1" halign="center" valign="center" textsize="2" text="$'.$this->text_color.'individual ($'.$this->rank_color.''.$localrank.''.$localmaxrecs.'$fff)"/>';
		$xmltext .= '<label scale="0.6" posn="0 1.5 0.1" halign="center" valign="center" textsize="3" text="$o$'.$persolocalbest.'"/>';
		$xmltext .= '</frame>';
		
		$xmltext .= '<frame posn="'.$this->frame2_posn_x.' '.$this->frame2_posn_y.' 0.3">';
		$xmltext .= '<quad posn="0 19.1 0.3" sizen="16 4"/>';
		$xmltext .= '<label scale="0.8" posn="0 0 0.1" halign="center" valign="center" textsize="2" text="$'.$this->text_color.'leader"/>';
		$xmltext .= '<label scale="0.6" posn="0 1.5 0.1" halign="center" valign="center" textsize="3" text="$o$'.$best.'"/>';
		$xmltext .= '</frame>';
		
		if ($show_dedimania == "true"){

		$xmltext .= '<frame posn="'.$this->frame_dedi_title_posn_x.' '.$this->frame_dedi_title_posn_y.' 0.3">';
		$xmltext .= '<quad posn="0 19.1 0.3" sizen="16 4"/>';
		$xmltext .= '<label scale="0.8" posn="0 3.5 0.1" halign="center" valign="center" textsize="2" text="$fffDedimania records"/>';
		$xmltext .= '</frame>';

		$xmltext .= '<frame posn="'.$this->frame3_posn_x.' '.$this->frame3_posn_y.' 0.3">';
		$xmltext .= '<quad posn="0 19.1 0.3" sizen="16 4"/>';		
		$xmltext .= '<label scale="0.8" posn="0 0 0.1" halign="center" valign="center" textsize="2" text="$'.$this->text_color.'leader"/>';
		$xmltext .= '<label scale="0.6" posn="0 1.5 0.1" halign="center" valign="center" textsize="3" text="$o$'.$dedibestof.'"/>';
		$xmltext .= '</frame>';
		
		$xmltext .= '<frame posn="'.$this->frame4_posn_x.' '.$this->frame4_posn_y.' 0.3">';
		$xmltext .= '<quad posn="0 19.1 0.3" sizen="16 4"/>';
		$xmltext .= '<label scale="0.8" posn="0 0 0.1" halign="center" valign="center" textsize="2" text="$'.$this->text_color.'individual ($'.$this->rank_color.''.$dedirank.''.$maxdedi.'$fff)"/>';
		$xmltext .= '<label scale="0.6" posn="0 1.5 0.1" halign="center" valign="center" textsize="3" text="$o$'.$persodedibest.'"/>';
		$xmltext .= '</frame>';
		}else{}
		
		$xmltext .= '</manialink>';
		return $xmltext;
	}
	
	
	function getManialinkchrono($cp, $persolocalbest, $best, $dedibestof, $persodedibest, $localrank, $dedirank, $localmaxrecs, $maxdedi, $show_dedimania) {
		$cp = $cp+1;
		$xmlchrono = '<?xml version="1.0" encoding="UTF-8"?>';
		$xmlchrono .= '<manialink id='.$this->manialink_id2.'>';

		$xmlchrono .= '<frame posn="'.$this->frame_custom_local_title_posn_x.' '.$this->frame_custom_local_title_posn_y.' 0.3">';
		$xmlchrono .= '<quad posn="0 19.1 0.3" sizen="30 4"/>';
		$xmlchrono .= '<label scale="0.8" posn="0 4 0.1" halign="center" valign="center" textsize="4" text="$s$fffLocal records"/>';
		$xmlchrono .= '</frame>';

		$xmlchrono .= '<frame posn="'.$this->frame1_custom_posn_x.' '.$this->frame1_custom_posn_y.' 0.3">';
		$xmlchrono .= '<quad posn="0 19.1 0.3" sizen="20 4"/>';
		$xmlchrono .= '<label scale="0.7" posn="0 -1.5 0.1" halign="center" valign="center" textsize="3" text="$s$'.$this->text_color.'individual ($'.$this->rank_color.''.$localrank.''.$localmaxrecs.'$fff)"/>';
		$xmlchrono .= '<label scale="0.6" posn="0 1.2 0.1" halign="center" valign="center" style="TextRaceChrono" text="$s$'.$persolocalbest.'"/>';
		$xmlchrono .= '</frame>';
		
		$xmlchrono .= '<frame posn="'.$this->frame2_custom_posn_x.' '.$this->frame2_custom_posn_y.' 0.3">';
		$xmlchrono .= '<quad posn="0 19.1 0.3" sizen="20 4"/>';
		$xmlchrono .= '<label scale="0.7" posn="0 -1.5 0.1" halign="center" valign="center" textsize="3" text="$s$'.$this->text_color.'leader"/>';
		$xmlchrono .= '<label scale="0.6" posn="0 1.2 0.1" halign="center" valign="center" style="TextRaceChrono" text="$s$'.$best.'"/>';
		$xmlchrono .= '</frame>';
		
		if ($show_dedimania == "true"){

		$xmlchrono .= '<frame posn="'.$this->frame_custom_dedi_title_posn_x.' '.$this->frame_custom_dedi_title_posn_y.' 0.3">';
		$xmlchrono .= '<quad posn="0 19.1 0.3" sizen="30 4"/>';
		$xmlchrono .= '<label scale="0.8" posn="0 4 0.1" halign="center" valign="center" textsize="4" text="$s$fffDedimania records"/>';
		$xmlchrono .= '</frame>';

		$xmlchrono .= '<frame posn="'.$this->frame3_custom_posn_x.' '.$this->frame3_custom_posn_y.' 0.3">';
		$xmlchrono .= '<quad posn="0 19.1 0.3" sizen="20 4"/>';		
		$xmlchrono .= '<label scale="0.7" posn="0 -1.5 0.1" halign="center" valign="center" textsize="3" text="$s$'.$this->text_color.'leader"/>';
		$xmlchrono .= '<label scale="0.6" posn="0 1.2 0.1" halign="center" valign="center" style="TextRaceChrono" text="$s$'.$dedibestof.'"/>';
		$xmlchrono .= '</frame>';
		
		$xmlchrono .= '<frame posn="'.$this->frame4_custom_posn_x.' '.$this->frame4_custom_posn_y.' 0.3">';
		$xmlchrono .= '<quad posn="0 19.1 0.3" sizen="20 4"/>';
		$xmlchrono .= '<label scale="0.7" posn="0 -1.5 0.1" halign="center" valign="center" textsize="3" text="$s$'.$this->text_color.'individual ($'.$this->rank_color.''.$dedirank.''.$maxdedi.'$fff)"/>';
		$xmlchrono .= '<label scale="0.6" posn="0 1.2 0.1" halign="center" valign="center" style="TextRaceChrono" text="$s$'.$persodedibest.'"/>';
		$xmlchrono .= '</frame>';
		}else{}		

		$xmlchrono .= '</manialink>';
		return $xmlchrono;
	}
}

?>
