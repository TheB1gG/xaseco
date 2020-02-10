<?php
/*******************************************************************************************************************
 * DBTools v1.0.7 by vni aka jimpower
 * This version is compatible to XAseco v1.1.4 / XAseco2 v1.0.0
 * Attention: This plugin comes with absolutely no warranty. Use it at your own risk. Always make a backup
 * 			  of your database before doing anything here! Copied that? Thanks.
 ********************************************************************************************************************/

/***********************************
 * Xaseco-Events
 ***********************************/
	Aseco::registerEvent('onSync',							'dbtools_event_sync');
    Aseco::addChatCommand('dbtools',  						'DBTools Commands');
	Aseco::registerEvent('onEverySecond',					'dbtools_tick');
	Aseco::registerEvent('onPlayerManialinkPageAnswer',		'dbtools_mlanswer');
/***********************************
 * Initialization
 ***********************************/
	global $dbtools;
    $dbtools['version_string'] = '1.0.7';
	$dbtools['chatprefix'] = '$F00DBTools$F90>> $FFF ';
	$dbtools['commandprefix'] = '$0F0Command > $FF0 ';
	
	$dbtools['console']['maxlines'] = 31;
	$dbtools['console']['lines'] = array();
	
/***********************************
 * Manialinks
 ***********************************/
	$dbtools['manialinks']['console']['id'] = '562040001';
	$dbtools['manialinks']['console']['closeanswer'] = '562050001';
	$dbtools['manialinks']['console']['x'] = -32;
	$dbtools['manialinks']['console']['y'] = 45;
	$dbtools['manialinks']['console']['width'] = 64;
	$dbtools['manialinks']['console']['height'] = 70;
	$dbtools['manialinks']['console']['padding'] = 2;
	$dbtools['manialinks']['console']['style'] = 'BgsPlayerCard';
	$dbtools['manialinks']['console']['substyle'] = 'BgPlayerName';
	$dbtools['manialinks']['console']['fontsize'] = 1;
	$dbtools['manialinks']['console']['lineheight'] = 2;
	$dbtools['manialinks']['console']['textcolor'] = 'EEEF';
	$dbtools['manialinks']['console']['headline']['fontsize'] = 2;
	$dbtools['manialinks']['console']['headline']['textcolor'] = 'FF0F';
	$dbtools['manialinks']['console']['headline']['text'] = 'DBTools v'.$dbtools['version_string'].' by vni - Console Window (type $FFF/dbtools help$FF0 for info)';
	
/***********************************
 * Synchronisation
 ***********************************/
function dbtools_event_sync($aseco)
{
	global $dbtools;
	
	// Register this to the global version pool (for up-to-date checks)
	$aseco->plugin_versions[] = array(
		'plugin'	=> 'plugin.dbtools.php',
		'author'	=> 'vni',
		'version'	=> $dbtools['version_string']
	);
	
	$aseco->console('[DBTOOLS] DBTools v'.$dbtools['version_string'].' by vni aka jimpower');
	//Parse config if exists
	if (file_exists('dbtools.xml'))
	{
		$aseco->console('[DBTOOLS] Parsing configuration file');
		$settings = simplexml_load_file('dbtools.xml');

		//Security Settings
		$dbtools['settings']['enable_backups'] = 'true'; //(string)$settings->enable_backups[0];
		$dbtools['settings']['allow_customfilenames'] = 'false'; (string)$settings->allow_customfilenames[0];
		$dbtools['settings']['console_window'] = (string)$settings->console_window[0];
		$dbtools['settings']['blocked_commands'] = ','.(string)$settings->blocked_commands[0].',';
		
		//Backup-Settings
		$dbtools['settings']['backup_default_path'] = (string)$settings->backup_default_path[0];
		$dbtools['settings']['backup_default_filename'] = (string)$settings->backup_default_filename[0];
		$dbtools['settings']['backup_timestamp_pattern'] = (string)$settings->backup_timestamp_pattern[0];
		$dbtools['settings']['backup_local_prune_after'] = (string)$settings->backup_local_prune_after[0];
		$dbtools['settings']['backup_location'] = (string)$settings->backup_location[0];
		
		//FTP-Settings
		$dbtools['settings']['ftp_upload'] = (strtoupper($dbtools['settings']['backup_location']) == 'FTP' ? 'true' : 'false'); //(string)$settings->ftp_upload[0];
		$dbtools['settings']['ftp_hostname'] = (string)$settings->ftp_hostname[0];
		$dbtools['settings']['ftp_username'] = (string)$settings->ftp_username[0];
		$dbtools['settings']['ftp_password'] = (string)$settings->ftp_password[0];
		$dbtools['settings']['ftp_directory'] = (string)$settings->ftp_directory[0];
		$dbtools['settings']['ftp_timeout'] = (int)$settings->ftp_timeout[0];
		$dbtools['settings']['ftp_port'] = (int)$settings->ftp_port[0];
		$dbtools['settings']['ftp_deletelocalfile'] = 'true'; //(string)$settings->ftp_deletelocalfile[0];
		$dbtools['settings']['ftp_deletelocalfile_if_upload_fails'] = 'true'; //(string)$settings->ftp_deletelocalfile_if_upload_fails[0];
		$dbtools['settings']['ftp_prune_after'] = (string)$settings->ftp_prune_after[0];
		
		$aseco->console('[DBTOOLS] Mode is set to '.strtoupper($dbtools['settings']['backup_location']));
		
		//Scheduling Settings
		$dbtools['settings']['scheduling_enabled'] = (string)$settings->scheduling_enabled[0];
		
		if (dbtools_setting($dbtools['settings']['scheduling_enabled']))
		{
			$scheduling = $settings->scheduling->item;
			$dbtools['scheduling'] = array();
			$i=0;
			foreach ($scheduling as $key => $value)
			{
				$aseco->console('[DBTOOLS] Parsing scheduled command "'.(string)$value->command[0].'" on day '.(string)$value->day[0].' at '.(string)$value->time[0].'h');
				$dbtools['scheduling'][$i]['command'] = (string)$value->command[0];
				$dbtools['scheduling'][$i]['week'] = (string)$value->week[0];
				$dbtools['scheduling'][$i]['day'] = (string)$value->day[0];
				$dbtools['scheduling'][$i]['time'] = (string)$value->time[0];
				$dbtools['lastperform'][$i] = 1;
				$i++;
				
				if (((int)$value->day[0] < 1) || ((int)$value->day[0] > 7))
				{
					$aseco->console('[DBTOOLS] Error! The Schedule-Day must be between 1 and 7. 1=Monday, 2=Tuesday,...,7=Sunday');	
				}
			}
		}
		else
		{
			$aseco->console('[DBTOOLS] Scheduling is disabled.');	
		}
		
		if (dbtools_setting($dbtools['settings']['ftp_upload'])) 
		{
			if (!$dbtools['settings']['ftp_hostname'] || !$dbtools['settings']['ftp_username'] || !$dbtools['settings']['ftp_password'])
			{
				$aseco->console('[DBTOOLS] Error! You have set ftp_upload to true but no FTP-Server is configured! FTP-Uploads were disabled.');	
				$dbtools['settings']['ftp_upload'] = false;
			}
			else
			{
				$aseco->console('[DBTOOLS] FTP-Upload is on. Uploading to: '.$dbtools['settings']['ftp_username'].'@'.$dbtools['settings']['ftp_hostname'].':'.$dbtools['settings']['ftp_port']);
			}
		}
	}
	else
	{
		$aseco->console('[DBTOOLS] Missing configuration file! Can not find dbtools.xml');	
		die();
	}
	
	if (strtoupper($dbtools['settings']['backup_location']) == 'FTP')
	{
		$aseco->console('[DBTOOLS] Checking FTP-Connection to server '.$dbtools['settings']['ftp_hostname'].':'.$dbtools['settings']['ftp_port'].' ...');	
		$connection_id = ftp_connect($dbtools['settings']['ftp_hostname'],$dbtools['settings']['ftp_port'],$dbtools['settings']['ftp_timeout']);
		$aseco->console('[DBTOOLS] FTP: Connection ok.');	
		if ($connection_id)
		{	
			$login_result = ftp_login($connection_id, $dbtools['settings']['ftp_username'], $dbtools['settings']['ftp_password']);
			if ($login_result)
			{
				$aseco->console('[DBTOOLS] FTP: Authentication ok.');
				if ($dbtools['settings']['ftp_directory'])
				{
					$directory_result = ftp_chdir ( $connection_id , $dbtools['settings']['ftp_directory'] );
					if (!$directory_result)
					{
						$aseco->console('[DBTOOLS] FTP: Directory '.$dbtools['settings']['ftp_directory'].' does not exist or is not writable!');
						ftp_close($connection_id);
						die();
					}
					else
					{
						$aseco->console('[DBTOOLS] FTP: Directory ok.');	
					}
				}
			}
			else
			{
				$aseco->console('[DBTOOLS] FTP: Authentication failed! Invalid username/password!');	
				ftp_close($connection_id);
				die();
			}
		}
		else
		{
			$aseco->console('[DBTOOLS] FTP: Connection failed! Host not reachable!');	
			ftp_close($connection_id);
			die();
		}
	}
	$aseco->console('[DBTOOLS] Sync complete. Type "/dbtools" to start. ');	
}	

/***********************************
 * Chat/Log
 ***********************************/
function dbtools_chat($aseco, $message, $login, $log=false)
{
	global $dbtools;
	if ($login)
	{
		if (dbtools_setting($dbtools['settings']['console_window']))
		{
			dbtools_console_line($aseco, $message, $login);
		}
		else
		{
			$aseco->client->query('ChatSendServerMessageToLogin', $dbtools['chatprefix'].$message , $login);
		}
	}
	if (!$login || $log) $aseco->console('[DBTOOLS LOG] '.$message);	
}

/***********************************
 * Show console window
 ***********************************/
function dbtools_console_line($aseco, $message, $login)
{
	global $dbtools;
	$dbtools['console']['lines'][$login][] = $message;

	if (count($dbtools['console']['lines'][$login]) > $dbtools['console']['maxlines'])
	{
		$dbtools['console']['lines'][$login] = array_slice($dbtools['console']['lines'][$login], $dbtools['console']['maxlines']*-1);
	}
	
	dbtools_console_show($aseco, $login);
}

/***********************************
 * Show console window
 ***********************************/
function dbtools_console_show($aseco, $login)
{
	global $dbtools;
	
	$xml  = '';
	$xml .= '<manialink id="'.$dbtools['manialinks']['console']['id'].'"><frame posn="'.$dbtools['manialinks']['console']['x'].' '.$dbtools['manialinks']['console']['y'].' 0">';
    $xml .= '<quad posn="0 0 0" sizen="'.$dbtools['manialinks']['console']['width'].' '.$dbtools['manialinks']['console']['height'].'" style="'.$dbtools['manialinks']['console']['style'].'" substyle="'.$dbtools['manialinks']['console']['substyle'].'" action="'.$dbtools['manialinks']['console']['closeanswer'].'" />';
	
	if ($dbtools['console']['lines'][$login])
	{
		$y=-$dbtools['manialinks']['console']['padding'];
		
		$xml .= '<label posn="'.$dbtools['manialinks']['console']['padding'].' '.$y.' 0" sizen="'.($dbtools['manialinks']['console']['width']-$dbtools['manialinks']['console']['padding']).' '.($dbtools['manialinks']['console']['headline']['fontsize']+0.5).'" halign="left" valign="top" scale="0.9" textsize="'.$dbtools['manialinks']['console']['headline']['fontsize'].'" textcolor="'.$dbtools['manialinks']['console']['headline']['textcolor'].'" text="'.$dbtools['manialinks']['console']['headline']['text'].'"></label>';
		$y -= $dbtools['manialinks']['console']['headline']['fontsize']+1;
		foreach ($dbtools['console']['lines'][$login] as $message)
		{
			$xml .= '<label posn="'.$dbtools['manialinks']['console']['padding'].' '.$y.' 0" sizen="'.($dbtools['manialinks']['console']['width']-$dbtools['manialinks']['console']['padding']).' '.($dbtools['manialinks']['console']['fontsize']+0.5).'" halign="left" valign="top" scale="0.9" textsize="'.$dbtools['manialinks']['console']['fontsize'].'" textcolor="'.$dbtools['manialinks']['console']['textcolor'].'" text="'.$message.'"></label>';
			$y -= $dbtools['manialinks']['console']['lineheight'];
		}
		//$xml .= '<label posn="'.$dbtools['manialinks']['console']['padding'].' '.$y.' 0" sizen="'.($dbtools['manialinks']['console']['width']-$dbtools['manialinks']['console']['padding']).' '.($dbtools['manialinks']['console']['fontsize']+0.5).'" halign="left" valign="top" scale="0.9" textsize="'.$dbtools['manialinks']['console']['fontsize'].'" textcolor="'.$dbtools['manialinks']['console']['textcolor'].'" text="'.$dbtools['commandprefix'].'"></label>';
	}
	
	$xml .= '</frame></manialink>';

	$aseco->client->query('SendDisplayManialinkPageToLogin', $login, $xml, 0, false);
}

/***********************************
 * ManiaLink answer
 ***********************************/
function dbtools_mlanswer($aseco, $answer)
{
	global $dbtools;
	$player = $aseco->server->players->player_list[$answer[1]];
	
	if ($answer[2] == $dbtools['manialinks']['console']['closeanswer'])
	{
		$xml = '<manialink id="'.$dbtools['manialinks']['console']['id'].'">
		</manialink>';
		$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);
	}
}

/***********************************
 * Tick - OnEverySecond
 ***********************************/
function dbtools_tick($aseco)
{
	global $dbtools;
	
	if (!dbtools_setting($dbtools['settings']['scheduling_enabled'])) return;
	
	if ($dbtools['scheduling'])
	{
		$i=0;
		foreach ($dbtools['scheduling'] as $sched)
		{
			list($hour,$minute) = explode(':',$sched['time']);
			
			$current_week 	= (int)strftime('%V',time());
			$current_day 	= (int)strftime('%u',time());
			$current_hour 	= (int)strftime('%H',time());
			$current_minute = (int)strftime('%M',time());
			
			if (strtoupper($sched['day']) == 'ALL') $sched['day'] = $current_day;
			if (strtoupper($sched['week']) == 'ALL') $sched['week'] = $current_week;
			
			if (($current_week == (int)$sched['week']) && ($current_day == (int)$sched['day']) && ($current_hour == (int)$hour) && ($current_minute == (int)$minute) && ($dbtools['lastperform'][$i] < time()-70))
			{
				$command['author'] = new dbtools_dummy;
				$command['params'] = $sched['command'];
				$dbtools['lastperform'][$i]=time();
				chat_dbtools($aseco, $command, true);
			}
			$i++;
		}
	}
}

/***********************************
 * returns if a setting is true or not
 ***********************************/
function dbtools_setting($strg)
{
	if (strtolower(chop($strg)) == 'true') return true;
	return false;
}

/***********************************
 * Query the database
 ***********************************/
function dbtools_sql($query)
{
	$result_sql = @mysql_query($query);
	return @mysql_fetch_assoc($result_sql);
}

/***********************************
 * Find last backup
 ***********************************/
function dbtools_findlastbackup($backup_number = 0)
{
	global $dbtools;
	
	$files = glob($dbtools['settings']['backup_default_path'].'*.sql');
	// Sort files by modified time, latest to earliest
	// Use SORT_ASC in place of SORT_DESC for earliest to latest
	array_multisort(
		array_map( 'filemtime', $files ),
		SORT_NUMERIC,
		SORT_DESC,
		$files
	);
	// Return the latest backup
	if (!$backup_number)
	{
		if (count($files) > 0) return $files[0];
	}
	// Return the backup with ID $backup_number
	else
	{
		if (!isset($files[$backup_number])) return false;
		return $files[$backup_number];
	}
}

/***********************************
 * Commands
 ***********************************/
function chat_dbtools($aseco, $command, $schedule=false)
{
    global $dbtools;
	if ($aseco->isMasterAdmin($command['author']) || $schedule)
    {
		$param = explode(' ',$command['params']);
		
		if (dbtools_setting($dbtools['settings']['console_window']) && $command['author']->login) dbtools_console_line($aseco, $dbtools['commandprefix'].'/dbtools '.$command['params'], $command['author']->login);

		if (stristr(strtolower($dbtools['settings']['blocked_commands']),','.strtolower(chop($param[0])).','))
		{
			dbtools_chat($aseco, 'Sorry, this command is forbidden at the <blocked_commands> configuration.', $command['author']->login);
			return;
		}
		
		
		if (!isset($param[0])) $param[0] = '';
		if (!isset($param[1])) $param[1] = '';
		if (!isset($param[2])) $param[2] = '';
		if (!isset($param[3])) $param[3] = '';
		
		if (!$param[0]) $param[0] = 'help';
		
		/***********************************
		 * help
		 ***********************************/
        if (strtolower($param[0]) == 'help')
        {
			if (!$param[1])
			{
				dbtools_chat($aseco, '$w$sGeneral:', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools help [<COMMAND>] $FFF- This info, or detailed help on a specific command', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools info $FFF- Detailed information about this plugin', $command['author']->login);
				dbtools_chat($aseco, '$w$sPerformance:', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools cleanupdb $FFF- Removes old tracks/records from the database', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools offsetdelete <OFFSET> $FFF- Removes records behind position X (<OFFSET>) on all tracks', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools optimize $FFF- Optimizes your database', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools repair $FFF- Repairs your database', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools wipedb $FFF- Wipes the complete database', $command['author']->login);
				dbtools_chat($aseco, '$w$sBackup:', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools backup $FFF- Creates a backup of the database (Local or on FTP - Depending on your mode)', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools restore <ID> $FFF- Restores a database-backup (Local or from FTP - Depending on your mode)', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools listbackups $FFF- Lists all available backups (Local or on FTP - Depending on your mode)', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools deletebackup <ID> $FFF- Deletes a backup (Local or on FTP - Depending on your mode)', $command['author']->login);
				dbtools_chat($aseco, '$w$sDedimania-Sync:', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools cheaters ban $FFF- Adds Dedimania blacklisted players to your server blacklist', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools cheaters remove $FFF- Deletes records of Dedimania blacklisted players', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools cheaters removeandban $FFF- Deletes records of Dedimania blacklisted players and adds them to your server blacklist', $command['author']->login);
				dbtools_chat($aseco, '$w$sMaintenance:', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools removelogin <LOGIN> $FFF- Removed all records of <LOGIN>', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools removethis $FFF- Removes all records/votes of the current track', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools karmaclear $FFF- Clears the karma votes of all tracks', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools karmaclearthis $FFF- Clears the karma votes of the current track', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools karmaclearlogin <LOGIN> $FFF- Clears the karma votes of a specific login', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools clearstyles $FFF- Resets all custom styling', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools clearplaytime [<LOGIN>] $FFF- Clears the playtime of all or of <LOGIN>', $command['author']->login);
				dbtools_chat($aseco, '  $ADF/dbtools clearwins [<LOGIN>] $FFF- Clears the wins of all or of <LOGIN>', $command['author']->login);
			}
			elseif (strtolower($param[1]) == 'backup')
			{
				dbtools_chat($aseco, '$CCCHELP: $F00backup [<FILENAME>]', $command['author']->login);
				dbtools_chat($aseco, 'This command creates a backup of your database. If you do not specify a filename, the file dbtools_dump.sql will be created. If you enabled', $command['author']->login);
				dbtools_chat($aseco, '<ftp_upload> at the configuration file, the backup will be uploaded to your external FTP-Server right after the creation.', $command['author']->login);
				dbtools_chat($aseco, '$CCCUSAGE:', $command['author']->login);
				dbtools_chat($aseco, '/dbtools backup', $command['author']->login);
				dbtools_chat($aseco, '/dbtools backup filename.sql', $command['author']->login);
			}
			elseif (strtolower($param[1]) == 'restore')
			{
				dbtools_chat($aseco, '$CCCHELP: $F00restore [<FILENAME>]', $command['author']->login);
				dbtools_chat($aseco, 'This command restores a backup. If you do not specify a filename, it will try to import the file dbtools_dump.sql.', $command['author']->login);
				dbtools_chat($aseco, 'Attention: On restoring a backup, all previous data gets lost. Handle this with care.', $command['author']->login);
				dbtools_chat($aseco, '$CCCUSAGE:', $command['author']->login);
				dbtools_chat($aseco, '/dbtools restore', $command['author']->login);
				dbtools_chat($aseco, '/dbtools restore filename.sql', $command['author']->login);
			}
			elseif (strtolower($param[1]) == 'cleanupdb')
			{
				dbtools_chat($aseco, '$CCCHELP: $F00cleanupdb', $command['author']->login);
				dbtools_chat($aseco, 'This command removes all tracks, votes, records, checkpointtimes of tracks from your database, that are no more in the current', $command['author']->login);
				dbtools_chat($aseco, 'tracklist. After cleaning the database, you are forced to restart Xaseco.', $command['author']->login);
				dbtools_chat($aseco, '$CCCUSAGE:', $command['author']->login);
				dbtools_chat($aseco, '/dbtools cleanupdb', $command['author']->login);
			}
			elseif (strtolower($param[1]) == 'optimize')
			{
				dbtools_chat($aseco, '$CCCHELP: $F00optimize', $command['author']->login);
				dbtools_chat($aseco, 'This optimizes all Xaseco default tables and defragments the datafile. This feature increases the performance sometimes significantly.', $command['author']->login);
				dbtools_chat($aseco, '$CCCUSAGE:', $command['author']->login);
				dbtools_chat($aseco, '/dbtools optimize', $command['author']->login);
			}
			elseif (strtolower($param[1]) == 'repair')
			{
				dbtools_chat($aseco, '$CCCHELP: $F00repair', $command['author']->login);
				dbtools_chat($aseco, 'This repairs corrupted tables. If you get MySQL-Errors, try a repair. (Works only on MyISAM Databases)', $command['author']->login);
				dbtools_chat($aseco, '$CCCUSAGE:', $command['author']->login);
				dbtools_chat($aseco, '/dbtools repair', $command['author']->login);
			}
			elseif (strtolower($param[1]) == 'wipedb')
			{
				dbtools_chat($aseco, '$CCCHELP: $F00wipedb', $command['author']->login);
				dbtools_chat($aseco, 'This clears the complete database. All data gets lost! Always create a backup first!', $command['author']->login);
				dbtools_chat($aseco, '$CCCUSAGE:', $command['author']->login);
				dbtools_chat($aseco, '/dbtools wipedb', $command['author']->login);
			}
			elseif (strtolower($param[1]) == 'removelogin')
			{
				dbtools_chat($aseco, '$CCCHELP: $F00removelogin <LOGIN>', $command['author']->login);
				dbtools_chat($aseco, 'This removes all records and votes of a specific login. Example: /dbtools removelogin jackiechan', $command['author']->login);
				dbtools_chat($aseco, '$CCCUSAGE:', $command['author']->login);
				dbtools_chat($aseco, '/dbtools removelogin jackiechan', $command['author']->login);
			}
			elseif (strtolower($param[1]) == 'removethis')
			{
				dbtools_chat($aseco, '$CCCHELP: $F00removethis', $command['author']->login);
				dbtools_chat($aseco, 'This removes all records and votes of the current track. This command forces you to restart your servertool.', $command['author']->login);
				dbtools_chat($aseco, '$CCCUSAGE:', $command['author']->login);
				dbtools_chat($aseco, '/dbtools removethis', $command['author']->login);
			}
			elseif (strtolower($param[1]) == 'karmaclear')
			{
				dbtools_chat($aseco, '$CCCHELP: $F00karmaclear', $command['author']->login);
				dbtools_chat($aseco, 'This removes all karma votes for every single track from your database. I suggest restarting the servertool after executing this command.', $command['author']->login);
				dbtools_chat($aseco, '$CCCUSAGE:', $command['author']->login);
				dbtools_chat($aseco, '/dbtools karmaclear', $command['author']->login);
			}
			elseif (strtolower($param[1]) == 'karmaclearthis')
			{
				dbtools_chat($aseco, '$CCCHELP: $F00karmaclearthis', $command['author']->login);
				dbtools_chat($aseco, 'This removes all karma votes for the current track from your database. I suggest restarting the servertool after executing this command.', $command['author']->login);
				dbtools_chat($aseco, '$CCCUSAGE:', $command['author']->login);
				dbtools_chat($aseco, '/dbtools karmaclearthis', $command['author']->login);
			}
			elseif (strtolower($param[1]) == 'karmaclearlogin')
			{
				dbtools_chat($aseco, '$CCCHELP: $F00karmaclearlogin <LOGIN>', $command['author']->login);
				dbtools_chat($aseco, 'This removes all karma votes from the login you specify from the database. I suggest restarting the servertool after executing this command.', $command['author']->login);
				dbtools_chat($aseco, '$CCCUSAGE:', $command['author']->login);
				dbtools_chat($aseco, '/dbtools karmaclearlogin jackiechan', $command['author']->login);
			}
			elseif (strtolower($param[1]) == 'clearstyles')
			{
				dbtools_chat($aseco, '$CCCHELP: $F00clearstyles', $command['author']->login);
				dbtools_chat($aseco, 'This resets all custom Styles made by users to the default one (including your own).', $command['author']->login);
				dbtools_chat($aseco, '$CCCUSAGE:', $command['author']->login);
				dbtools_chat($aseco, '/dbtools clearstyles', $command['author']->login);
			}
			elseif (strtolower($param[1]) == 'clearplaytime')
			{
				dbtools_chat($aseco, '$CCCHELP: $F00clearplaytime [<LOGIN>]', $command['author']->login);
				dbtools_chat($aseco, 'This resets the timeplayed to 0 for <LOGIN>, or for all users if you do not specify a login.', $command['author']->login);
				dbtools_chat($aseco, '$CCCUSAGE:', $command['author']->login);
				dbtools_chat($aseco, '/dbtools clearplaytime', $command['author']->login);
			}
			elseif (strtolower($param[1]) == 'clearwins')
			{
				dbtools_chat($aseco, '$CCCHELP: $F00clearplaytime [<LOGIN>]', $command['author']->login);
				dbtools_chat($aseco, 'This resets the player wins to 0 for <LOGIN>, or for all users if you do not specify a login.', $command['author']->login);
				dbtools_chat($aseco, '$CCCUSAGE:', $command['author']->login);
				dbtools_chat($aseco, '/dbtools clearwins', $command['author']->login);
			}
			elseif (strtolower($param[1]) == 'cheaters')
			{
				dbtools_chat($aseco, '$CCCHELP: $F00cheaters', $command['author']->login);
				dbtools_chat($aseco, 'This feature synchronizes your server with the Dedimania-Blacklist. You can use the following options', $command['author']->login);
				dbtools_chat($aseco, '$CCCUSAGE:', $command['author']->login);
				dbtools_chat($aseco, '/dbtools cheaters ban - This will add all players from the Dedi-Blacklist to your local Blacklist and save the blacklist.txt', $command['author']->login);
				dbtools_chat($aseco, '/dbtools cheaters remove - This will remove all players from the Dedi-Blacklist from your local database', $command['author']->login);
				dbtools_chat($aseco, '/dbtools cheaters removeandban - This performs both upper commands at one time.', $command['author']->login);
			}
			elseif (strtolower($param[1]) == 'offsetdelete')
			{
				dbtools_chat($aseco, '$CCCHELP: $F00offsetdelete <OFFSET>', $command['author']->login);
				dbtools_chat($aseco, 'This feature deletes all records behind a defined position on all tracks. Using this increases the performance and reduces the RAM-Usage', $command['author']->login);
				dbtools_chat($aseco, '$CCCUSAGE:', $command['author']->login);
				dbtools_chat($aseco, '/dbtools offsetdelete 30 - This removes all records on each track behind the 30th position', $command['author']->login);
			}
			else
			{
				dbtools_chat($aseco, 'No detailed help available for '.$param[1], $command['author']->login);
			}
        }
		/***********************************
		 * listbackups
		 ***********************************/
        elseif (strtolower($param[0]) == 'listbackups')
        {
			//List Backups on FTP-Server
			if (strtoupper($dbtools['settings']['backup_location'])=='FTP')
			{
				dbtools_chat($aseco, 'Receiving Backup-List from FTP-Server...', $command['author']->login, true);
				$connection_id = ftp_connect($dbtools['settings']['ftp_hostname'],$dbtools['settings']['ftp_port'],$dbtools['settings']['ftp_timeout']);
				if ($connection_id)
				{	
					$login_result = ftp_login($connection_id, $dbtools['settings']['ftp_username'], $dbtools['settings']['ftp_password']);
					if ($login_result)
					{
						$remotefiles = ftp_nlist($connection_id, $dbtools['settings']['ftp_directory']);
						$remotefiles = array_reverse($remotefiles);
						$i=0;
						if ($remotefiles)
						{
							foreach ($remotefiles as $remotefile)
							{
								if ((strtolower(substr($remotefile,strlen($remotefile)-4,4)) == ".sql")) 
								{
									dbtools_chat($aseco, '$FF0'.($i+1).'.$ADF '.basename($remotefile).'$FFF (Type $0FF/dbtools restore '.($i+1).' $FFFto restore)', $command['author']->login);
									$i++;
								}
							}
							dbtools_chat($aseco, 'Done. Found '.($i).' Backups.', $command['author']->login);
						}
						
						if (!$i)
						{
							dbtools_chat($aseco, 'No Backups found on your FTP-Server', $command['author']->login, true);
						}
					}
					else
					{
						dbtools_chat($aseco, 'FTP-Authentication failed. Check your settings!', $command['author']->login, true);
					}
				}
				else
				{
					dbtools_chat($aseco, 'Connection to FTP-Server failed. Check your settings!', $command['author']->login, true);
				}
				@ftp_quit($connection_id);
				
			}
			//List local Backups
			else
			{
				$files = glob($dbtools['settings']['backup_default_path'].'*.sql');
				array_multisort(
					array_map( 'filemtime', $files ),
					SORT_NUMERIC,
					SORT_DESC,
					$files
				);
				if ($files)
				{
					$i=0;
					foreach ($files as $file)
					{
						dbtools_chat($aseco, '$FF0'.($i+1).'.$ADF '.basename($file).'$FFF (Type $0FF/dbtools restore '.($i+1).' $FFFto restore)', $command['author']->login);
						$i++;
					}
					dbtools_chat($aseco, 'Done. Found '.($i).' Backups.', $command['author']->login);
				}
				else
				{
					dbtools_chat($aseco, 'No backups found.', $command['author']->login);
				}
			}
       }
		/***********************************
		 * deletebackup
		 ***********************************/
        elseif (strtolower($param[0]) == 'deletebackup')
        {
			
			if (strtoupper($dbtools['settings']['backup_location'])=='FTP')
			{
				if (!is_numeric($param[1]))
				{
					dbtools_chat($aseco, 'Error: You must specify a Backup-ID! Type $0FF/dbtools listbackups', $command['author']->login, true);
					return;
				}
				
				dbtools_chat($aseco, 'Receiving Backup-List from FTP-Server...', $command['author']->login, true);
				$connection_id = ftp_connect($dbtools['settings']['ftp_hostname'],$dbtools['settings']['ftp_port'],$dbtools['settings']['ftp_timeout']);
				if ($connection_id)
				{	
					$login_result = ftp_login($connection_id, $dbtools['settings']['ftp_username'], $dbtools['settings']['ftp_password']);
					if ($login_result)
					{
						$remotefiles = ftp_nlist($connection_id, $dbtools['settings']['ftp_directory']);
						$remotefiles = array_reverse($remotefiles);
						$i=0;
						if ($remotefiles)
						{
							foreach ($remotefiles as $remotefile)
							{
								if ((strtolower(substr($remotefile,strlen($remotefile)-4,4)) == ".sql")) 
								{
									if (($i+1) == $param[1])
									{
										//Delete File
										dbtools_chat($aseco, 'Deleting '.$remotefile, $command['author']->login);
										$delete_result = ftp_delete($connection_id, $dbtools['settings']['ftp_directory'].'/'.$remotefile);
										if ($delete_result)
										{
											dbtools_chat($aseco, 'Done.', $command['author']->login);
											@ftp_quit($connection_id);
											return;
										}
										else
										{
											dbtools_chat($aseco, 'Error: File can not be deleted. Check permissions.', $command['author']->login);
											@ftp_quit($connection_id);
											return;
										}
									}
									$i++;
								}
							}
							dbtools_chat($aseco, 'Error: Invalid Backup-ID! Type $0FF/dbtools listbackups', $command['author']->login, true);
						}
						
						if (!$i)
						{
							dbtools_chat($aseco, 'No Backups found on your FTP-Server', $command['author']->login, true);
						}
					}
					else
					{
						dbtools_chat($aseco, 'FTP-Authentication failed. Check your settings!', $command['author']->login, true);
					}
				}
				else
				{
					dbtools_chat($aseco, 'Connection to FTP-Server failed. Check your settings!', $command['author']->login, true);
				}
				@ftp_quit($connection_id);
				
			}
			else
			{
				if (is_numeric($param[1]))
				{
					$filename = dbtools_findlastbackup(((int)$param[1]-1));
				}
				else
				{
					dbtools_chat($aseco, 'Error: You must specify a Backup-ID! Type $0FF/dbtools listbackups', $command['author']->login, true);
					return;
				}
				
				if ($filename)
				{
					dbtools_chat($aseco, 'Deleting backup '.$filename, $command['author']->login);
					unlink($filename);
					dbtools_chat($aseco, 'Done.', $command['author']->login);
				}
				else
				{
					dbtools_chat($aseco, 'Not found! Use $0FF/dbtools listbackups$FFF to find the ID!'.$filename, $command['author']->login);
				}
			}
		}
		/***********************************
		 * info
		 ***********************************/
        elseif (strtolower($param[0]) == 'info')
        {
			dbtools_chat($aseco, 'DBTools v'.$dbtools['version_string'], $command['author']->login);
			dbtools_chat($aseco, 'by vni aka jimpower', $command['author']->login);
			dbtools_chat($aseco, 'For more information see the official topic here: $0FF$lhttp://www.tm-forum.com/viewtopic.php?f=127&t=29349$l', $command['author']->login);
			dbtools_chat($aseco, 'This plugin is used for performing maintenance on your database.', $command['author']->login);
        }
		/***********************************
		 * offsetdelete
		 ***********************************/
        elseif (strtolower($param[0]) == 'offsetdelete')
        {
			if (!$param[1])
			{
				dbtools_chat($aseco, 'Error: You must define an offset. Usage: /dbtools offsetdelete <OFFSET>', $command['author']->login);
				return;
			}
			
			dbtools_chat($aseco, 'Fetching tracks from database...', $command['author']->login);
			if ($aseco->server->getGame() == 'MP') $query = 'SELECT * FROM maps';
			else $query = 'SELECT * FROM challenges';
			
			
			$result_sql = mysql_query($query);
			$i=0;
			$challenges = array();
			while($result_assoc_line = mysql_fetch_assoc($result_sql))
			{
				foreach($result_assoc_line as $key => $value)
				{
					$challenges[$i][$key]=$value;
				};
				$i++;
			}
			dbtools_chat($aseco, 'Found '.($i-1).' tracks in the database. Lets go!', $command['author']->login);

			if ($challenges)
			{
				foreach ($challenges as $challenge)
				{
					dbtools_chat($aseco, '...Fetching records of track '.$challenge['Name'].'...', $command['author']->login);
					if ($aseco->server->getGame() == 'MP') $lastrecord = dbtools_sql('SELECT * FROM records WHERE MapId='.(int)$challenge['Id'].' ORDER BY Score ASC LIMIT '.(int)$param[1].',1');
					else $lastrecord = dbtools_sql('SELECT * FROM records WHERE ChallengeId='.(int)$challenge['Id'].' ORDER BY Score ASC LIMIT '.(int)$param[1].',1');
					if ($lastrecord)
					{
						dbtools_chat($aseco, '...Deleting records behind score: '.$lastrecord['Score'], $command['author']->login);
						//Delete from records Table
						if ($aseco->server->getGame() == 'MP') $result = dbtools_sql('DELETE FROM records WHERE MapId = '.$challenge['Id'].' AND Score >= '.(int)$lastrecord['Score']);
						else  $result = dbtools_sql('DELETE FROM records WHERE ChallengeId = '.$challenge['Id'].' AND Score >= '.(int)$lastrecord['Score']);
						//Delete from rs_times Table
						if ($aseco->server->getGame() == 'MP') $result = dbtools_sql('DELETE FROM rs_times WHERE MapId = '.$challenge['Id'].' AND Score >= '.(int)$lastrecord['Score']);
						else  $result = dbtools_sql('DELETE FROM rs_times WHERE ChallengeId = '.$challenge['Id'].' AND Score >= '.(int)$lastrecord['Score']);
					}
					else
					{
						dbtools_chat($aseco, '...Not enough records. Skipping this track.', $command['author']->login);
					}
				}
				dbtools_chat($aseco, 'Done.', $command['author']->login);
			}
			else
			{
				dbtools_chat($aseco, 'No tracks in the database, aborting.', $command['author']->login);
			}
			
        }
		/***********************************
		 * karmaclearthis
		 ***********************************/
        elseif (strtolower($param[0]) == 'karmaclearthis')
        {
			$aseco->client->query('GetCurrentChallengeInfo');
			$challengeinfo = $aseco->client->getResponse();
			dbtools_chat($aseco, 'Clearing local karma of Track '.$challengeinfo['Name'], $command['author']->login);
			
			if ($aseco->server->getGame() == 'MP') $challengedata = dbtools_sql('SELECT * FROM maps WHERE Uid = "'.mysql_real_escape_string($challengeinfo['UId']).'"');
			else $challengedata = dbtools_sql('SELECT * FROM challenges WHERE Uid = "'.mysql_real_escape_string($challengeinfo['UId']).'"');
			
			if ($challengedata)
			{
				dbtools_sql('DELETE FROM rs_karma WHERE MapId='.(int)$challengedata['Id']);
				dbtools_sql('DELETE FROM rs_karma WHERE ChallengeId='.(int)$challengedata['Id']);
				dbtools_chat($aseco, 'Karma of current track was cleared.', $command['author']->login);
			}
			else
			{
				dbtools_chat($aseco, 'No challengedata available. Oops?', $command['author']->login);
			}
			
        }
		/***********************************
		 * karmaclear
		 ***********************************/
        elseif (strtolower($param[0]) == 'karmaclear')
        {
			dbtools_sql('TRUNCATE TABLE rs_karma');
			dbtools_chat($aseco, 'Done. All karma votes were cleared.', $command['author']->login);
        }
		/***********************************
		 * karmaclearlogin
		 ***********************************/
        elseif (strtolower($param[0]) == 'karmaclearlogin')
        {
			if ($param[1])
			{
				$playerdata = dbtools_sql('SELECT * FROM players WHERE Login = "'.mysql_real_escape_string($param[1]).'"');
				
				if ($playerdata)
				{
					dbtools_chat($aseco, 'Removing Karma-Votes of '.$param[1], $command['author']->login);
					dbtools_sql('DELETE FROM rs_karma WHERE PlayerId='.(int)$playerdata['Id']);
					dbtools_chat($aseco, 'Done.', $command['author']->login);
				}
				else
				{
					dbtools_chat($aseco, 'Player '.$param[1].' not found.', $command['author']->login);
					return;
				}
			}
			else
			{
				dbtools_chat($aseco, 'You must specify a login: /dbtools karmaclearlogin <LOGIN>', $command['author']->login);
			}
        }
		/***********************************
		 * cheaters
		 ***********************************/
        elseif (strtolower($param[0]) == 'cheaters')
        {
			if ($aseco->server->getGame() == 'MP')
			{
				dbtools_chat($aseco, 'Sorry, the Dedimania-Sync is currently avail for TMF-Servers only.', $command['author']->login);
				return;
			}
			
			if (($param[1] != 'ban') && ($param[1] != 'remove') && ($param[1] != 'removeandban'))
			{
				dbtools_chat($aseco, 'Missing parameter (ban/remove/removeandban). See $FF0/dbtools help$FFF for more info.', $command['author']->login);
				return;
			}
			
			dbtools_chat($aseco, 'Fetching blacklist from Dedimania-Server...Please wait...', $command['author']->login);
			
			$dedi_blacklist_xml = file_get_contents('http://www.gamers.org/tmf/dedimania_blacklist.txt');
			if (!$dedi_blacklist_xml)
			{
				dbtools_chat($aseco, 'The blacklist is not available at the moment. Try again later.', $command['author']->login);
				return;
			}
			$dedi_blacklist = simplexml_load_string($dedi_blacklist_xml);
			$blacklist = $dedi_blacklist->player;
			print_r($blacklist);
			if ($blacklist)
			{
				$i=0;
				dbtools_chat($aseco, 'Synchronizing '.count($blacklist).' blacklisted players - This can take some minutes! Please wait... ', $command['author']->login);
				$i=0;
				foreach ($blacklist as $key => $value)
				{
					if ($i % 70 == 0) dbtools_chat($aseco, 'In progress - '.$i.' done - Please wait...', $command['author']->login);
					$i++;
					
					//Add to blacklist
					if ((strtolower($param[1]) == 'ban') || (strtolower($param[1]) == 'removeandban'))
					{
						$aseco->client->query('BlackList',(string)$value->login[0]);
						$dummy = $aseco->client->getResponse();
					}
					
					//Remove from database
					if ((strtolower($param[1]) == 'remove') || (strtolower($param[1]) == 'removeandban'))
					{
						$fakecommand['author'] = new dbtools_dummy;
						$fakecommand['params'] = 'removelogin '.(string)$value->login[0];
						chat_dbtools($aseco, $fakecommand, true);
					}
				}
				dbtools_chat($aseco, 'All '.count($blacklist).' players done.', $command['author']->login);

				if ((strtolower($param[1]) == 'ban') || (strtolower($param[1]) == 'removeandban'))
				{
					dbtools_chat($aseco, 'Saving blacklist...', $command['author']->login);
					$aseco->client->query('SaveBlackList','blacklist.txt');
					$dummy = $aseco->client->getResponse();
					dbtools_chat($aseco, 'Done.', $command['author']->login);
				}
			}
        }
		/***********************************
		 * wipedb
		 ***********************************/
        elseif (strtolower($param[0]) == 'wipedb')
        {
			dbtools_chat($aseco, 'Wiping the database...', $command['author']->login);
			dbtools_sql('TRUNCATE TABLE maps');
			dbtools_sql('TRUNCATE TABLE challenges');
			dbtools_sql('TRUNCATE TABLE players');
			dbtools_sql('TRUNCATE TABLE players_extra');
			dbtools_sql('TRUNCATE TABLE records');
			dbtools_sql('TRUNCATE TABLE rs_karma');
			dbtools_sql('TRUNCATE TABLE rs_rank');
			dbtools_sql('TRUNCATE TABLE rs_times');
			dbtools_chat($aseco, 'Wipe complete. You should restart Xaseco now.', $command['author']->login);
        }
		/***********************************
		 * optimize
		 ***********************************/
        elseif (strtolower($param[0]) == 'optimize')
        {
			dbtools_chat($aseco, 'Optimizing the database...', $command['author']->login);
			dbtools_sql('OPTIMIZE TABLE maps');
			dbtools_sql('OPTIMIZE TABLE challenges');
			dbtools_sql('OPTIMIZE TABLE players');
			dbtools_sql('OPTIMIZE TABLE players_extra');
			dbtools_sql('OPTIMIZE TABLE records');
			dbtools_sql('OPTIMIZE TABLE rs_karma');
			dbtools_sql('OPTIMIZE TABLE rs_rank');
			dbtools_sql('OPTIMIZE TABLE rs_times');
			dbtools_chat($aseco, 'Optimization complete.', $command['author']->login);
        }
		/***********************************
		 * repair
		 ***********************************/
        elseif (strtolower($param[0]) == 'repair')
        {
			dbtools_chat($aseco, 'Repairing the database...', $command['author']->login);
			dbtools_sql('REPAIR TABLE maps');
			dbtools_sql('REPAIR TABLE challenges');
			dbtools_sql('REPAIR TABLE players');
			dbtools_sql('REPAIR TABLE players_extra');
			dbtools_sql('REPAIR TABLE records');
			dbtools_sql('REPAIR TABLE rs_karma');
			dbtools_sql('REPAIR TABLE rs_rank');
			dbtools_sql('REPAIR TABLE rs_times');
			dbtools_chat($aseco, 'Repair complete.', $command['author']->login);
        }
		/***********************************
		 * backup
		 ***********************************/
        elseif (strtolower($param[0]) == 'backup')
        {
			if (!file_exists($dbtools['settings']['backup_default_path']))
			{
				dbtools_chat($aseco, 'Error: The default backup path does not exist. Check your configuration!', $command['author']->login, true);
				return;
			}
			
			if (!is_writable($dbtools['settings']['backup_default_path']))
			{
				dbtools_chat($aseco, 'Error: The default backup path is not writable. Set the permissions to 755 or higher!', $command['author']->login, true);
				return;
			}
			
			if (!dbtools_setting($dbtools['settings']['enable_backups']))
			{
				dbtools_chat($aseco, 'You must enable backups at the dbtools.xml to use this feature!', $command['author']->login, true);
				return;
			}
			
			if (!dbtools_setting($dbtools['settings']['allow_customfilenames']) && $param[1]) 
			{
				$param[1]='';
				dbtools_chat($aseco, 'Custom filenames are forbidden. Falling back to default filename.', $command['author']->login, true);
			}
			
			if (ini_get('safe_mode'))
			{
				dbtools_chat($aseco, 'Sorry, the PHP-Safe-Mode is on - It is not possible to create a backup', $command['author']->login, true);
			}
			else
			{
				if (strtoupper(substr(PHP_OS, 0, 5)) != 'LINUX') 
				{
					dbtools_chat($aseco, 'Sorry, the backup feature is available on Linux-Systems only', $command['author']->login, true);
				}			
				else
				{
					//Load the database settings
					$localdatabase = simplexml_load_file('localdatabase.xml');
					
					if ((strtolower($localdatabase->mysql_server) != 'localhost') && (strtolower($localdatabase->mysql_server) != '127.0.0.1'))
					{
						dbtools_chat($aseco, 'Sorry, the backup feature is available for local databases only', $command['author']->login, true);
					}
					else
					{
						//Create the backup
						dbtools_chat($aseco, 'Creating Backup...', $command['author']->login);
						
						if (!chop($param[1])) $filename = $dbtools['settings']['backup_default_filename'];
						else $filename = basename(chop($param[1]));
						
						//Insert Timestamp
						$filename = str_replace('#DATE#',strftime($dbtools['settings']['backup_timestamp_pattern'],time()),$filename);
						
						//Delete spaces
						$filename = str_replace(' ','',$filename);
						
						//Add SQL-Extension if other ext is set
						if ((strtolower(substr($filename,strlen($filename)-4,4)) != ".sql")) $filename.= '.sql';
						
						exec ('mysqldump -h localhost -u '.$localdatabase->mysql_login.' -p'.$localdatabase->mysql_password.' '.$localdatabase->mysql_database.' >'.$dbtools['settings']['backup_default_path'].$filename);
						
						//Check for success
						if (!file_exists($dbtools['settings']['backup_default_path'].$filename))
						{
							dbtools_chat($aseco, 'Error: Something went wrong - The backup was NOT created. Check your settings!', $command['author']->login, true);
							return;
						}
						dbtools_chat($aseco, 'Backup created. Filename: '.$dbtools['settings']['backup_default_path'].$filename, $command['author']->login, true);
						
						//Upload the file to FTP
						if (dbtools_setting($dbtools['settings']['ftp_upload']))
						{
							dbtools_chat($aseco, 'Connecting to the FTP-Server...', $command['author']->login, true);
									
							$connection_id = ftp_connect($dbtools['settings']['ftp_hostname'],$dbtools['settings']['ftp_port'],$dbtools['settings']['ftp_timeout']);
							if (!$connection_id)
							{
								dbtools_chat($aseco, 'Error: Connection to FTP-Server failed', $command['author']->login, true);
								if (dbtools_setting($dbtools['settings']['ftp_deletelocalfile_if_upload_fails']))
								{
									unlink($dbtools['settings']['backup_default_path'].$filename);
								}
								return;
							}
							dbtools_chat($aseco, 'Connection established. Authenticating...', $command['author']->login, true);
							$login_result = ftp_login($connection_id, $dbtools['settings']['ftp_username'], $dbtools['settings']['ftp_password']);
							if (!$login_result)
							{
								dbtools_chat($aseco, 'Error: Invalid FTP Username or Password', $command['author']->login, true);
								ftp_quit($connection_id);
								if (dbtools_setting($dbtools['settings']['ftp_deletelocalfile_if_upload_fails']))
								{
									unlink($dbtools['settings']['backup_default_path'].$filename);
								}
								return;
							}
							dbtools_chat($aseco, 'Authentication successful. Sending file...', $command['author']->login, true);
							$upload = ftp_put ($connection_id, $dbtools['settings']['ftp_directory'].'/'.basename($filename), $dbtools['settings']['backup_default_path'].$filename, FTP_BINARY);
							if (!$upload)
							{
								dbtools_chat($aseco, 'Error: Upload to FTP-Server failed', $command['author']->login, true);
								ftp_quit($connection_id);
								if (dbtools_setting($dbtools['settings']['ftp_deletelocalfile_if_upload_fails']))
								{
									unlink($dbtools['settings']['backup_default_path'].$filename);
								}
								return;
							}
							dbtools_chat($aseco, 'Upload complete. Disconnecting.', $command['author']->login, true);
							
							//Prune FTP-backups
							if ($dbtools['settings']['ftp_prune_after'] > 0)
							{
								dbtools_chat($aseco, 'Checking for outdated backups to prune...', $command['author']->login, true);
								$remotefiles = ftp_nlist($connection_id, $dbtools['settings']['ftp_directory']);
								if ($remotefiles)
								{
									foreach ($remotefiles as $remotefile)
									{
										if ((strtolower(substr($remotefile,strlen($remotefile)-4,4)) == ".sql")) 
										{
											$filetime = ftp_mdtm($connection_id, $dbtools['settings']['ftp_directory'].'/'.$remotefile);
											if ($filetime == -1)
											{
												dbtools_chat($aseco, 'ERROR: Your FTP-Server does not support the Auto-Prune feature.', $command['author']->login, true);
												break;
											}
											else
											{
												if ($filetime < (time()-($dbtools['settings']['ftp_prune_after']*86400)))
												{
													dbtools_chat($aseco, 'Pruning '.basename($file).'...', $command['author']->login, true);
													ftp_delete ($connection_id , $dbtools['settings']['ftp_directory'].'/'.$remotefile);
												}
											}
											
										}
									}
								}
							}
							
							ftp_quit($connection_id);
							
							//Delete local file if true
							if (dbtools_setting($dbtools['settings']['ftp_deletelocalfile']))
							{
								unlink($dbtools['settings']['backup_default_path'].$filename);
							}
							
							dbtools_chat($aseco, 'Done.', $command['author']->login, true);
							
						}
						
						//Prune local backups
						if ($dbtools['settings']['backup_local_prune_after']>0)
						{
							dbtools_chat($aseco, 'Pruning old files', $command['author']->login, true);
							$files = glob($dbtools['settings']['backup_default_path'].'*.sql');
							array_multisort(
								array_map( 'filemtime', $files ),
								SORT_NUMERIC,
								SORT_DESC,
								$files
							);
							$i=0;
							if ($files)
							{
								foreach ($files as $file)
								{
									$filetime = filemtime($file);
									if ($filetime < (time()-($dbtools['settings']['backup_local_prune_after']*86400)))
									{
										dbtools_chat($aseco, 'Pruning '.basename($file).'...', $command['author']->login, true);
										unlink($file);
										$i++;
									}
									
								}
							}
							
							if (!$i) dbtools_chat($aseco, 'No files to prune.', $command['author']->login, true);
							
						}
						
					}
				}
			}		
        }
		/***********************************
		 * restore
		 ***********************************/
        elseif (strtolower($param[0]) == 'restore')
        {
			$filename='';
			
			if (!dbtools_setting($dbtools['settings']['enable_backups']))
			{
				dbtools_chat($aseco, 'You must enable backups at the dbtools.xml to use this feature!', $command['author']->login, true);
				return;
			}
			
			//if (!dbtools_setting($dbtools['settings']['allow_customfilenames'])) $param[1]='';

			if (ini_get('safe_mode'))
			{
				dbtools_chat($aseco, 'Sorry, the PHP-Safe-Mode is on - It is not possible to restore a backup', $command['author']->login);
			}
			else
			{
				if (strtoupper(substr(PHP_OS, 0, 5)) != 'LINUX') 
				{
					dbtools_chat($aseco, 'Sorry, the backup feature is available on Linux-Systems only', $command['author']->login);
				}			
				else
				{
					//Load the database settings
					$localdatabase = simplexml_load_file('localdatabase.xml');
					
					if ((strtolower($localdatabase->mysql_server) != 'localhost') && (strtolower($localdatabase->mysql_server) != '127.0.0.1'))
					{
						dbtools_chat($aseco, 'Sorry, the backup feature is available for local databases only', $command['author']->login);
					}
					else
					{
						
						//First get the file from the FTP-Server
						if (strtoupper($dbtools['settings']['backup_location'])=='FTP')
						{
							if (!is_numeric($param[1]))
							{
								dbtools_chat($aseco, 'Error: You must specify a Backup-ID! Type $0FF/dbtools listbackups', $command['author']->login, true);
								return;
							}
							
							dbtools_chat($aseco, 'Receiving Backup from FTP-Server...', $command['author']->login, true);
							$connection_id = ftp_connect($dbtools['settings']['ftp_hostname'],$dbtools['settings']['ftp_port'],$dbtools['settings']['ftp_timeout']);
							if ($connection_id)
							{	
								$login_result = ftp_login($connection_id, $dbtools['settings']['ftp_username'], $dbtools['settings']['ftp_password']);
								if ($login_result)
								{
									$remotefiles = ftp_nlist($connection_id, $dbtools['settings']['ftp_directory']);
									$remotefiles = array_reverse($remotefiles);
									$i=0;
									$tempfilename=$dbtools['settings']['backup_default_path'].'temp'.time().'.sql';
									if ($remotefiles)
									{
										foreach ($remotefiles as $remotefile)
										{
											if ((strtolower(substr($remotefile,strlen($remotefile)-4,4)) == ".sql")) 
											{
												if (($i+1) == $param[1])
												{
													//Download File
													dbtools_chat($aseco, 'Downloading the Backup to temporary file '.$tempfilename, $command['author']->login);
													$download_result = ftp_get($connection_id, $tempfilename , $dbtools['settings']['ftp_directory'].'/'.$remotefile , FTP_BINARY);
													if (!$download_result)
													{
														@unlink($tempfilename);
														dbtools_chat($aseco, 'Download failed. Check your settings.', $command['author']->login);
														return;
													}
													$filename = basename($tempfilename);
													break;
												}
												$i++;
											}
										}
									}
									
									if (!$i)
									{
										dbtools_chat($aseco, 'No Backups found on your FTP-Server', $command['author']->login, true);
									}
								}
								else
								{
									dbtools_chat($aseco, 'FTP-Authentication failed. Check your settings!', $command['author']->login, true);
								}
							}
							else
							{
								dbtools_chat($aseco, 'Connection to FTP-Server failed. Check your settings!', $command['author']->login, true);
							}
							@ftp_quit($connection_id);
							
						}
						else
						{
							//Local file
							if (is_numeric($param[1]))
							{
								$filename = dbtools_findlastbackup(((int)$param[1]-1));
							}
							else
							{
								dbtools_chat($aseco, 'Error: You must specify a Backup-ID! Type $0FF/dbtools listbackups', $command['author']->login, true);
								return;
							}
						}
						
						if (!$filename)
						{
							dbtools_chat($aseco, 'Error: Invalid Backup-ID! Type $0FF/dbtools listbackups', $command['author']->login);
							return;
						}
						
						if (!file_exists($filename))
						{
							dbtools_chat($aseco, 'The file '.$filename.' can not be found.', $command['author']->login);
						}
						else
						{
							//Everything ok, start the restore. Wipe before to prevent double IDs
							dbtools_chat($aseco, 'Wiping the current database...', $command['author']->login);
							dbtools_sql('TRUNCATE TABLE maps');
							dbtools_sql('TRUNCATE TABLE challenges');
							dbtools_sql('TRUNCATE TABLE players');
							dbtools_sql('TRUNCATE TABLE players_extra');
							dbtools_sql('TRUNCATE TABLE records');
							dbtools_sql('TRUNCATE TABLE rs_karma');
							dbtools_sql('TRUNCATE TABLE rs_rank');
							dbtools_sql('TRUNCATE TABLE rs_times');
							dbtools_chat($aseco, 'Restoring Backup...', $command['author']->login, true);
							exec ('mysql --verbose --user='.$localdatabase->mysql_login.' --password='.$localdatabase->mysql_password.' '.$localdatabase->mysql_database.' < '.$filename);
							$dbtools['settings']['console_window'] = 'false';
							if ($tempfilename) 
							{
								dbtools_chat($aseco, 'Removing temporary file '.$tempfilename, $command['author']->login, true);
								@unlink($tempfilename);
							}
							dbtools_chat($aseco, 'Backup restored (Filename: '.$filename.'). Restart XAseco!', $command['author']->login, true);
							$aseco->client->query('SendHideManialinkPage');
							die("Closed by DBTool-Restore.");
						}
					}
				}
			}		
        }
		/***********************************
		 * clearplaytime
		 ***********************************/
        elseif (strtolower($param[0]) == 'clearplaytime')
        {
			if ($param[1])
			{
				dbtools_sql('UPDATE players SET TimePlayed = 0 WHERE Login="'.mysql_real_escape_string($param[1]).'"');
				dbtools_chat($aseco, 'Playtime cleared for login '.$param[1], $command['author']->login);
			}
			else
			{
				dbtools_sql('UPDATE players SET TimePlayed = 0');
				dbtools_chat($aseco, 'Playtime cleared for all logins.', $command['author']->login);
			}
        }
		/***********************************
		 * clearstyles
		 ***********************************/
        elseif (strtolower($param[0]) == 'clearstyles')
        {
			dbtools_sql('UPDATE players_extra SET Style="NavBlueBlur", Panels="AdminRightCorner/DonateBelowCPList/RecordsRightCorner/VoteBottomCenter", PanelBG="PanelBGCard"');
			dbtools_chat($aseco, 'Styles cleared.', $command['author']->login);
        }
		/***********************************
		 * clearwins
		 ***********************************/
        elseif (strtolower($param[0]) == 'clearwins')
        {
			if ($param[1])
			{
				dbtools_sql('UPDATE players SET Wins = 0 WHERE Login="'.mysql_real_escape_string($param[1]).'"');
				dbtools_chat($aseco, 'Wins cleared for login '.$param[1], $command['author']->login);
			}
			else
			{
				dbtools_sql('UPDATE players SET Wins = 0');
				dbtools_chat($aseco, 'Wins cleared for all logins.', $command['author']->login);
			}
        }
		/***********************************
		 * removethis
		 ***********************************/
        elseif (strtolower($param[0]) == 'removethis')
        {
			$aseco->client->query('GetCurrentChallengeInfo');
			$challengeinfo = $aseco->client->getResponse();
			
			if ($aseco->server->getGame() == 'MP') $challengedata = dbtools_sql('SELECT * FROM maps WHERE Uid = "'.mysql_real_escape_string($challengeinfo['UId']).'"');
			else $challengedata = dbtools_sql('SELECT * FROM challenges WHERE Uid = "'.mysql_real_escape_string($challengeinfo['UId']).'"');
			
			if ($challengedata)
			{
				dbtools_chat($aseco, 'Removing challenge '.$challengedata['Name'], $command['author']->login);
				
				//TM2
				dbtools_sql('DELETE FROM records WHERE MapId='.(int)$challengedata['Id']);
				dbtools_sql('DELETE FROM rs_karma WHERE MapId='.(int)$challengedata['Id']);
				dbtools_sql('DELETE FROM rs_times WHERE MapId='.(int)$challengedata['Id']);
				//TMF
				dbtools_sql('DELETE FROM records WHERE ChallengeId='.(int)$challengedata['Id']);
				dbtools_sql('DELETE FROM rs_karma WHERE ChallengeId='.(int)$challengedata['Id']);
				dbtools_sql('DELETE FROM rs_times WHERE ChallengeId='.(int)$challengedata['Id']);


				if ($aseco->server->getGame() == 'MP') dbtools_sql('DELETE FROM maps WHERE Id='.(int)$challengedata['Id']);
				else dbtools_sql('DELETE FROM challenges WHERE Id='.(int)$challengedata['Id']);
				dbtools_chat($aseco, 'Challenge removed. You should restart Xaseco now.', $command['author']->login);
			}
			else
			{
				dbtools_chat($aseco, 'No challengedata available. Oops?', $command['author']->login);
			}
			
        }
		/***********************************
		 * removelogin
		 ***********************************/
        elseif (strtolower($param[0]) == 'removelogin')
        {
			if ($param[1])
			{
				$playerdata = dbtools_sql('SELECT * FROM players WHERE Login = "'.mysql_real_escape_string($param[1]).'"');
				
				if ($playerdata)
				{
					dbtools_chat($aseco, 'Removing all records of player '.$param[1], $command['author']->login);
					dbtools_chat($aseco, 'Player '.$param[1].' found (Id='.$playerdata['Id'].').', $command['author']->login);
					
					dbtools_chat($aseco, 'Removing records of '.$param[1], $command['author']->login);
					dbtools_sql('DELETE FROM records WHERE PlayerId='.(int)$playerdata['Id']);
					
					dbtools_chat($aseco, 'Removing Karma-Votes of '.$param[1], $command['author']->login);
					dbtools_sql('DELETE FROM rs_karma WHERE PlayerId='.(int)$playerdata['Id']);
					
					dbtools_chat($aseco, 'Removing Ranks of '.$param[1], $command['author']->login);
					dbtools_sql('DELETE FROM rs_rank WHERE PlayerId='.(int)$playerdata['Id']);
					
					dbtools_chat($aseco, 'Removing detailed times of '.$param[1], $command['author']->login);
					dbtools_sql('DELETE FROM rs_times WHERE PlayerId='.(int)$playerdata['Id']);
					
					dbtools_chat($aseco, 'Removing Player-Data of '.$param[1], $command['author']->login);
					dbtools_sql('DELETE FROM players WHERE Id='.(int)$playerdata['Id']);
					dbtools_sql('DELETE FROM players_extra WHERE PlayerId='.(int)$playerdata['Id']);
					
					dbtools_chat($aseco, 'Done. You should restart Xaseco now.', $command['author']->login);
				}
				else
				{
					dbtools_chat($aseco, 'Player '.$param[1].' not found.', $command['author']->login);
					return;
				}
			}
			else
			{
				dbtools_chat($aseco, 'You must specify a login: /dbtools removelogin <LOGIN>', $command['author']->login);
			}
        }
		/***********************************
		 * cleanupdb
		 ***********************************/
        elseif (strtolower($param[0]) == 'cleanupdb')
        {
			dbtools_chat($aseco, 'Cleaning the database...', $command['author']->login);
			
			//Challenges in the challengelist
				$aseco->client->query('GetChallengeList', 2650, 0);
				$serverchallenges = $aseco->client->getResponse();
				$sc_array = array();
				foreach ($serverchallenges as $challenge)
				{
					$sc_array[$challenge['UId']] = $challenge;
				}

			//Challenges in the database
				if ($aseco->server->getGame() == 'MP') $query = 'SELECT * FROM maps;';
				else $query = 'SELECT * FROM challenges;';
				
				
				$result_sql = mysql_query($query);
				$i=0;
				while($result_assoc_line = mysql_fetch_assoc($result_sql))
				{
					foreach($result_assoc_line as $key => $value)
					{
						$dbchallenges[$i][$key]=$value;
					};
					$i++;
				}
				
				foreach ($dbchallenges as $challenge)
				{
					if (!$sc_array[$challenge['Uid']])
					{
						dbtools_chat($aseco, 'Cleaning Track ID #'.$challenge['Id'].': '.$challenge['Name'], $command['author']->login);

						if ($aseco->server->getGame() == 'MP') $query = 'DELETE FROM maps WHERE Uid="'.$challenge['Uid'].'";';
						else $query = 'DELETE FROM challenges WHERE Uid="'.$challenge['Uid'].'";';
						$result_sql = mysql_query($query);
						
						if ($aseco->server->getGame() == 'MP') $query = 'DELETE FROM records WHERE MapId="'.$challenge['Id'].'";';
						else $query = 'DELETE FROM records WHERE ChallengeId="'.$challenge['Id'].'";';
						$result_sql = mysql_query($query);
						
						if ($aseco->server->getGame() == 'MP') $query = 'DELETE FROM rs_karma WHERE MapId="'.$challenge['Id'].'";';
						else $query = 'DELETE FROM rs_karma WHERE ChallengeId="'.$challenge['Id'].'";';
						$result_sql = mysql_query($query);
						
						if ($aseco->server->getGame() == 'MP') $query = 'DELETE FROM rs_times WHERE MapId="'.$challenge['Id'].'";';
						else $query = 'DELETE FROM rs_times WHERE ChallengeId="'.$challenge['Id'].'";';
						$result_sql = mysql_query($query);
					}
				}
				$dbtools['settings']['console_window'] = 'false';
				dbtools_chat($aseco, 'Cleanup done. XAseco is now shutting down. Restart XAseco!', $command['author']->login);
				$aseco->client->query('SendHideManialinkPage');
				die("Closed by DBTool-Cleanup.");
        }
        else
        {
			dbtools_chat($aseco, 'Command not found! Type $FF0/dbtools help$FFF for more information '.$param[1], $command['author']->login);
        }
    }
    else
	{
		//User is not MasterAdmin
		dbtools_chat($aseco, 'This command is available to MasterAdmins only!', $command['author']->login);
    }
}

/*****************************************************************
 * Just a dummy to fake the command object for the chat function
 *****************************************************************/
class dbtools_dummy {
	var $login;
}
?>