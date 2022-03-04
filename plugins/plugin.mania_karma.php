<?php

/*
 * Plugin: Mania-Karma
 * ~~~~~~~~~~~~~~~~~~~
 * For a detailed description and documentation, please refer to:
 * http://www.undef.name/XAseco1/Mania-Karma.php
 *
 * ----------------------------------------------------------------------------------
 * Author:		undef.de
 * Version:		2.0.1
 * Date:		2016-07-20
 * Copyright:		2009 - 2016 by undef.de
 * System:		XAseco/1.16+
 * Game:		Trackmania Forever (TMF)
 * ----------------------------------------------------------------------------------
 *
 * LICENSE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * ----------------------------------------------------------------------------------
 * Dependencies:	plugins/plugin.localdatabase.php
 * 			includes/web_access.inc.php
 */

/* The following manialink id's are used in this plugin (the 911 part of id can be changed on trouble in mk_onSync()):
 *
 * ManialinkID's
 * ~~~~~~~~~~~~~
 * 91101		id for manialink ReminderWindow and ManiaExchange-Link
 * 91102		id for manialink Windows
 * 91103		id for manialink Skeleton Widget
 * 91104		id for manialink Player-Marker for his/her Vote
 * 91105		id for manialink Cups, Karma-Value and Karma-Votes
 * 91106		id for manialink ConnectionStatus
 * 91107		id for manialink LoadingIndicator
 *
 * ActionID's
 * ~~~~~~~~~~
 * 91100		id for action close Window
 * 91101		id for action open HelpWindow
 * 91102		id for action open KarmaDetailsWindow
 * 91103		id for action open WhoKarmaWindow
 * 91110		id for action vote + (1)
 * 91111		id for action vote ++ (2)
 * 91112		id for action vote +++ (3)
 * 91113		id for action vote undecided (0)
 * 91114		id for action vote - (-1)
 * 91115		id for action vote -- (-2)
 * 91116		id for action vote --- (-3)
 * 91117		id for action on disabled (red) buttons, tell the Player to finish this Map x times
 * 91118		id for action that is ignored
 */

require_once('includes/web_access.inc.php');

Aseco::registerEvent('onSync',				'mk_onSync');
Aseco::registerEvent('onEverySecond',			'mk_onEverySecond');
Aseco::registerEvent('onChat',				'mk_onChat');
Aseco::registerEvent('onPlayerConnect',			'mk_onPlayerConnect');
Aseco::registerEvent('onPlayerDisconnect',		'mk_onPlayerDisconnect');
Aseco::registerEvent('onPlayerFinish',			'mk_onPlayerFinish');
Aseco::registerEvent('onNewChallenge',			'mk_onNewChallenge');
Aseco::registerEvent('onNewChallenge2',			'mk_onNewChallenge2');
Aseco::registerEvent('onRestartChallenge2',		'mk_onRestartChallenge2');
Aseco::registerEvent('onEndRace1',			'mk_onEndRace1');
Aseco::registerEvent('onPlayerManialinkPageAnswer',	'mk_onPlayerManialinkPageAnswer');
Aseco::registerEvent('onKarmaChange',			'mk_onKarmaChange');
Aseco::registerEvent('onShutdown',			'mk_onShutdown');

Aseco::addChatCommand('karma',				'Shows karma for the current Map (see: /karma help)');
Aseco::addChatCommand('+++',				'Set "Fantastic" karma for the current Map');
Aseco::addChatCommand('++',				'Set "Beautiful" karma for the current Map');
Aseco::addChatCommand('+',				'Set "Good" karma for the current Map');
Aseco::addChatCommand('-',				'Set "Bad" karma for the current Map');
Aseco::addChatCommand('--',				'Set "Poor" karma for the current Map');
Aseco::addChatCommand('---',				'Set "Waste" karma for the current Map');


global $mk_config, $karma;
$karma = array();
$mk_config = array();

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_onSync ($aseco) {
	global $mk_config, $karma;


	// Check for the right XAseco-Version
	$xaseco_min_version = '1.16';
	if ( defined('XASECO_VERSION') ) {
		$version = str_replace(
			array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i'),
			array('.1','.2','.3','.4','.5','.6','.7','.8','.9'),
			XASECO_VERSION
		);
		if ( version_compare($version, $xaseco_min_version, '<') ) {
			trigger_error('[plugin.mania_karma.php] Not supported XAseco version ('. XASECO_VERSION .')! Please update to min. version '. $xaseco_min_version .'!', E_USER_ERROR);
		}
	}
	else {
		trigger_error('[plugin.mania_karma.php] Can not identify the System, "XASECO_VERSION" is unset! This plugin runs only with XAseco/'. $xaseco_min_version .'+', E_USER_ERROR);
	}

	if ($aseco->server->getGame() != 'TMF') {
		trigger_error('[plugin.mania_karma.php] This plugin supports only TMF, can not start with a "'. $aseco->server->getGame() .'" Dedicated-Server!', E_USER_ERROR);
	}

	// Check for dependencies
	if ( !function_exists('ldb_loadSettings') ) {
		trigger_error('[plugin.mania_karma.php] Missing dependent plugin, please activate "plugin.localdatabase.php" in "plugins.xml" and restart.', E_USER_ERROR);
	}

	// Check for forbidden Plugins
	$forbidden = array(
		'plugin.rasp_karma.php'
	);
	foreach ($forbidden as $plugin) {
		foreach ($aseco->plugins as $installed_plugin) {
			if ($plugin == $installed_plugin) {
				// Found, trigger error
				trigger_error('[plugin.mania_karma.php] This Plugin can not run with "'. $plugin .'" together, you have to remove "'. $plugin .'" from plugins.xml!', E_USER_ERROR);
			}
		}
	}


	// Set internal Manialink ID
	$mk_config['manialink_id'] = '911';

	// Set version of this release
	$mk_config['version'] = '2.0.1';

	// Register this to the global version pool (for up-to-date checks)
	$aseco->plugin_versions[] = array(
		'plugin'	=> 'plugin.mania_karma.php',
		'author'	=> 'undef.de',
		'version'	=> $mk_config['version']
	);


	// http://en.wikipedia.org/wiki/ISO_3166-1			(Last-Modified: 11:29, 10 January 2010 Chanheigeorge)
	// http://en.wikipedia.org/wiki/List_of_countries_by_continent	(Last-Modified: 09:25, 21 January 2010 Anna Lincoln)
	//	ISO		Countries					Continent
	$iso3166Alpha3 = array(
		'ABW' => array("Aruba",						'NORTHAMERICA'),
		'AFG' => array("Afghanistan",					'ASIA'),
		'AGO' => array("Angola",					'AFRICA'),
		'AIA' => array("Anguilla",					'NORTHAMERICA'),
		'ALA' => array("Åland Islands",					'EUROPE'),
		'ALB' => array("Albania",					'EUROPE'),
		'AND' => array("Andorra",					'EUROPE'),
		'ANT' => array("Netherlands Antilles",				'NORTHAMERICA'),
		'ARE' => array("United Arab Emirates",				'ASIA'),
		'ARG' => array("Argentina",					'SOUTHAMERICA'),
		'ARM' => array("Armenia",					'ASIA'),
		'ASM' => array("American Samoa",				'OCEANIA'),
		'ATA' => array("Antarctica",					'WORLDWIDE'),
		'ATF' => array("French Southern Territories",			'WORLDWIDE'),
		'ATG' => array("Antigua and Barbuda",				'NORTHAMERICA'),
		'AUS' => array("Australia",					'OCEANIA'),
		'AUT' => array("Austria",					'EUROPE'),
		'AZE' => array("Azerbaijan",					'ASIA'),
		'BDI' => array("Burundi",					'AFRICA'),
		'BEL' => array("Belgium",					'EUROPE'),
		'BEN' => array("Benin",						'AFRICA'),
		'BFA' => array("Burkina Faso",					'AFRICA'),
		'BGD' => array("Bangladesh",					'ASIA'),
		'BGR' => array("Bulgaria",					'EUROPE'),
		'BHR' => array("Bahrain",					'ASIA'),
		'BHS' => array("Bahamas",					'NORTHAMERICA'),
		'BIH' => array("Bosnia and Herzegovina",			'EUROPE'),
		'BLM' => array("Saint Barthélemy",				'NORTHAMERICA'),
		'BLR' => array("Belarus",					'EUROPE'),
		'BLZ' => array("Belize",					'NORTHAMERICA'),
		'BMU' => array("Bermuda",					'NORTHAMERICA'),
		'BOL' => array("Bolivia",					'SOUTHAMERICA'),
		'BRA' => array("Brazil",					'SOUTHAMERICA'),
		'BRB' => array("Barbados",					'NORTHAMERICA'),
		'BRN' => array("Brunei Darussalam",				'ASIA'),
		'BTN' => array("Bhutan",					'ASIA'),
		'BVT' => array("Bouvet Island",					'WORLDWIDE'),
		'BWA' => array("Botswana",					'AFRICA'),
		'CAF' => array("Central African Republic",			'AFRICA'),
		'CAN' => array("Canada",					'NORTHAMERICA'),
		'CCK' => array("Cocos (Keeling) Islands",			'ASIA'),
		'CHE' => array("Switzerland",					'EUROPE'),
		'CHL' => array("Chile",						'SOUTHAMERICA'),
		'CHN' => array("China",						'ASIA'),
		'CIV' => array("Côte d'Ivoire",					'AFRICA'),
		'CMR' => array("Cameroon",					'AFRICA'),
		'COD' => array("Democratic Republic of Congo",			'AFRICA'),
		'COG' => array("Republic of Congo",				'AFRICA'),
		'COK' => array("Cook Islands",					'OCEANIA'),
		'COL' => array("Colombia",					'SOUTHAMERICA'),
		'COM' => array("Comoros",					'AFRICA'),
		'CPV' => array("Cape Verde",					'AFRICA'),
		'CRI' => array("Costa Rica",					'NORTHAMERICA'),
		'CUB' => array("Cuba",						'NORTHAMERICA'),
		'CXR' => array("Christmas Island",				'ASIA'),
		'CYM' => array("Cayman Islands",				'NORTHAMERICA'),
		'CYP' => array("Cyprus",					'ASIA'),
		'CZE' => array("Czech Republic",				'EUROPE'),
		'DEU' => array("Germany",					'EUROPE'),
		'DJI' => array("Djibouti",					'AFRICA'),
		'DMA' => array("Dominica",					'NORTHAMERICA'),
		'DNK' => array("Denmark",					'EUROPE'),
		'DOM' => array("Dominican Republic",				'NORTHAMERICA'),
		'DZA' => array("Algeria",					'AFRICA'),
		'ECU' => array("Ecuador",					'SOUTHAMERICA'),
		'EGY' => array("Egypt",						'AFRICA'),
		'ERI' => array("Eritrea",					'AFRICA'),
		'ESH' => array("Western Sahara",				'AFRICA'),
		'ESP' => array("Spain",						'EUROPE'),
		'EST' => array("Estonia",					'EUROPE'),
		'ETH' => array("Ethiopia",					'AFRICA'),
		'FIN' => array("Finland",					'EUROPE'),
		'FJI' => array("Fiji",						'OCEANIA'),
		'FLK' => array("Falkland Islands",				'SOUTHAMERICA'),
		'FRA' => array("France",					'EUROPE'),
		'FRO' => array("Faroe Islands",					'EUROPE'),
		'FSM' => array("Micronesia",					'OCEANIA'),
		'GAB' => array("Gabon",						'AFRICA'),
		'GBR' => array("United Kingdom",				'EUROPE'),
		'GEO' => array("Georgia",					'ASIA'),
		'GGY' => array("Guernsey",					'EUROPE'),
		'GHA' => array("Ghana",						'AFRICA'),
		'GIB' => array("Gibraltar",					'EUROPE'),
		'GIN' => array("Guinea",					'AFRICA'),
		'GLP' => array("Guadeloupe",					'NORTHAMERICA'),
		'GMB' => array("Gambia",					'AFRICA'),
		'GNB' => array("Guinea-Bissau",					'AFRICA'),
		'GNQ' => array("Equatorial Guinea",				'AFRICA'),
		'GRC' => array("Greece",					'EUROPE'),
		'GRD' => array("Grenada",					'NORTHAMERICA'),
		'GRL' => array("Greenland",					'NORTHAMERICA'),
		'GTM' => array("Guatemala",					'NORTHAMERICA'),
		'GUF' => array("French Guiana",					'SOUTHAMERICA'),
		'GUM' => array("Guam",						'OCEANIA'),
		'GUY' => array("Guyana",					'SOUTHAMERICA'),
		'HKG' => array("Hong Kong",					'ASIA'),
		'HMD' => array("Heard Island and McDonald Islands",		'WORLDWIDE'),
		'HND' => array("Honduras",					'NORTHAMERICA'),
		'HRV' => array("Croatia",					'EUROPE'),
		'HTI' => array("Haiti",						'NORTHAMERICA'),
		'HUN' => array("Hungary",					'EUROPE'),
		'IDN' => array("Indonesia",					'ASIA'),
		'IMN' => array("Isle of Man",					'EUROPE'),
		'IND' => array("India",						'ASIA'),
		'IOT' => array("British Indian Ocean Territory",		'ASIA'),
		'IRL' => array("Ireland",					'EUROPE'),
		'IRN' => array("Iran",						'ASIA'),
		'IRQ' => array("Iraq",						'ASIA'),
		'ISL' => array("Iceland",					'EUROPE'),
		'ISR' => array("Israel",					'ASIA'),
		'ITA' => array("Italy",						'EUROPE'),
		'JAM' => array("Jamaica",					'NORTHAMERICA'),
		'JEY' => array("Jersey",					'EUROPE'),
		'JOR' => array("Jordan",					'ASIA'),
		'JPN' => array("Japan",						'ASIA'),
		'KAZ' => array("Kazakhstan",					'ASIA'),
		'KEN' => array("Kenya",						'AFRICA'),
		'KGZ' => array("Kyrgyzstan",					'ASIA'),
		'KHM' => array("Cambodia",					'ASIA'),
		'KIR' => array("Kiribati",					'OCEANIA'),
		'KNA' => array("Saint Kitts and Nevis",				'NORTHAMERICA'),
		'KOR' => array("South Korea",					'ASIA'),
		'KWT' => array("Kuwait",					'ASIA'),
		'LAO' => array("Lao People's Democratic Republic",		'ASIA'),
		'LBN' => array("Lebanon",					'ASIA'),
		'LBR' => array("Liberia",					'AFRICA'),
		'LBY' => array("Libyan Arab Jamahiriya",			'AFRICA'),
		'LCA' => array("Saint Lucia",					'NORTHAMERICA'),
		'LIE' => array("Liechtenstein",					'EUROPE'),
		'LKA' => array("Sri Lanka",					'ASIA'),
		'LSO' => array("Lesotho",					'AFRICA'),
		'LTU' => array("Lithuania",					'EUROPE'),
		'LUX' => array("Luxembourg",					'EUROPE'),
		'LVA' => array("Latvia",					'EUROPE'),
		'MAC' => array("Macao",						'ASIA'),
		'MAF' => array("Saint Martin",					'NORTHAMERICA'),
		'MAR' => array("Morocco",					'AFRICA'),
		'MCO' => array("Monaco",					'EUROPE'),
		'MDA' => array("Moldova",					'EUROPE'),
		'MDG' => array("Madagascar",					'AFRICA'),
		'MDV' => array("Maldives",					'ASIA'),
		'MEX' => array("Mexico",					'NORTHAMERICA'),
		'MHL' => array("Marshall Islands",				'OCEANIA'),
		'MKD' => array("Macedonia",					'EUROPE'),
		'MLI' => array("Mali",						'AFRICA'),
		'MLT' => array("Malta",						'EUROPE'),
		'MMR' => array("Myanmar",					'ASIA'),
		'MNE' => array("Montenegro",					'EUROPE'),
		'MNG' => array("Mongolia",					'ASIA'),
		'MNP' => array("Northern Mariana Islands",			'OCEANIA'),
		'MOZ' => array("Mozambique",					'AFRICA'),
		'MRT' => array("Mauritania",					'AFRICA'),
		'MSR' => array("Montserrat",					'NORTHAMERICA'),
		'MTQ' => array("Martinique",					'NORTHAMERICA'),
		'MUS' => array("Mauritius",					'AFRICA'),
		'MWI' => array("Malawi",					'AFRICA'),
		'MYS' => array("Malaysia",					'ASIA'),
		'MYT' => array("Mayotte",					'AFRICA'),
		'NAM' => array("Namibia",					'AFRICA'),
		'NCL' => array("New Caledonia",					'OCEANIA'),
		'NER' => array("Niger",						'AFRICA'),
		'NFK' => array("Norfolk Island",				'OCEANIA'),
		'NGA' => array("Nigeria",					'AFRICA'),
		'NIC' => array("Nicaragua",					'NORTHAMERICA'),
		'NIU' => array("Niue",						'OCEANIA'),
		'NLD' => array("Netherlands",					'EUROPE'),
		'NOR' => array("Norway",					'EUROPE'),
		'NPL' => array("Nepal",						'ASIA'),
		'NRU' => array("Nauru",						'OCEANIA'),
		'NZL' => array("New Zealand",					'OCEANIA'),
		'OMN' => array("Oman",						'ASIA'),
		'PAK' => array("Pakistan",					'ASIA'),
		'PAN' => array("Panama",					'NORTHAMERICA'),
		'PCN' => array("Pitcairn Islands",				'OCEANIA'),
		'PER' => array("Peru",						'SOUTHAMERICA'),
		'PHL' => array("Philippines",					'ASIA'),
		'PLW' => array("Palau",						'OCEANIA'),
		'PNG' => array("Papua New Guinea",				'OCEANIA'),
		'POL' => array("Poland",					'EUROPE'),
		'PRI' => array("Puerto Rico",					'NORTHAMERICA'),
		'PRK' => array("North Korea",					'ASIA'),
		'PRT' => array("Portugal",					'EUROPE'),
		'PRY' => array("Paraguay",					'SOUTHAMERICA'),
		'PSE' => array("Palestinian Territory, Occupied",		'ASIA'),
		'PYF' => array("French Polynesia",				'OCEANIA'),
		'QAT' => array("Qatar",						'ASIA'),
		'REU' => array("Réunion",					'AFRICA'),
		'ROU' => array("Romania",					'EUROPE'),
		'RUS' => array("Russian Federation",				'RUSSIA'),
		'RWA' => array("Rwanda",					'AFRICA'),
		'SAU' => array("Saudi Arabia",					'ASIA'),
		'SDN' => array("Sudan",						'AFRICA'),
		'SEN' => array("Senegal",					'AFRICA'),
		'SGP' => array("Singapore",					'ASIA'),
		'SGS' => array("South Georgia and the South Sandwich Islands",	'WORLDWIDE'),
		'SHN' => array("Saint Helena",					'AFRICA'),
		'SJM' => array("Svalbard and Jan Mayen",			'EUROPE'),
		'SLB' => array("Solomon Islands",				'OCEANIA'),
		'SLE' => array("Sierra Leone",					'AFRICA'),
		'SLV' => array("El Salvador",					'NORTHAMERICA'),
		'SMR' => array("San Marino",					'EUROPE'),
		'SOM' => array("Somalia",					'AFRICA'),
		'SPM' => array("Saint Pierre and Miquelon",			'NORTHAMERICA'),
		'SRB' => array("Serbia",					'EUROPE'),
		'STP' => array("Sao Tome and Principe",				'AFRICA'),
		'SUR' => array("Suriname",					'SOUTHAMERICA'),
		'SVK' => array("Slovakia",					'EUROPE'),
		'SVN' => array("Slovenia",					'EUROPE'),
		'SWE' => array("Sweden",					'EUROPE'),
		'SWZ' => array("Swaziland",					'AFRICA'),
		'SYC' => array("Seychelles",					'AFRICA'),
		'SYR' => array("Syrian Arab Republic",				'ASIA'),
		'TCA' => array("Turks and Caicos Islands",			'NORTHAMERICA'),
		'TCD' => array("Chad",						'AFRICA'),
		'TGO' => array("Togo",						'AFRICA'),
		'THA' => array("Thailand",					'ASIA'),
		'TJK' => array("Tajikistan",					'ASIA'),
		'TKL' => array("Tokelau",					'OCEANIA'),
		'TKM' => array("Turkmenistan",					'ASIA'),
		'TLS' => array("Timor-Leste",					'ASIA'),
		'TON' => array("Tonga",						'OCEANIA'),
		'TTO' => array("Trinidad and Tobago",				'NORTHAMERICA'),
		'TUN' => array("Tunisia",					'AFRICA'),
		'TUR' => array("Turkey",					'ASIA'),
		'TUV' => array("Tuvalu",					'OCEANIA'),
		'TWN' => array("Taiwan",					'ASIA'),
		'TZA' => array("Tanzania",					'AFRICA'),
		'UGA' => array("Uganda",					'AFRICA'),
		'UKR' => array("Ukraine",					'EUROPE'),
		'UMI' => array("United States Minor Outlying Islands",		'WORLDWIDE'),
		'URY' => array("Uruguay",					'SOUTHAMERICA'),
		'USA' => array("United States of America",			'NORTHAMERICA'),
		'UZB' => array("Uzbekistan",					'ASIA'),
		'VAT' => array("Holy See (Vatican City State)",			'EUROPE'),
		'VCT' => array("Saint Vincent and the Grenadines",		'NORTHAMERICA'),
		'VEN' => array("Venezuela, Bolivarian Republic of",		'SOUTHAMERICA'),
		'VGB' => array("Virgin Islands, British",			'NORTHAMERICA'),
		'VIR' => array("Virgin Islands, U.S.",				'NORTHAMERICA'),
		'VNM' => array("Viet Nam",					'ASIA'),
		'VUT' => array("Vanuatu",					'OCEANIA'),
		'WLF' => array("Wallis and Futuna",				'OCEANIA'),
		'WSM' => array("Samoa",						'OCEANIA'),
		'YEM' => array("Yemen",						'ASIA'),
		'ZAF' => array("South Africa",					'AFRICA'),
		'ZMB' => array("Zambia",					'AFRICA'),
		'ZWE' => array("Zimbabwe",					'AFRICA')
	);


	// Load the mania_karma.xml
	libxml_use_internal_errors(true);
	if (!$xmlcfg = @simplexml_load_file('mania_karma.xml', null, LIBXML_COMPACT) ) {
		$aseco->console('[plugin.mania_karma.php] Could not read/parse config file "mania_karma.xml"!');
		foreach (libxml_get_errors() as $error) {
			$aseco->console("\t". $error->message);
		}
		libxml_clear_errors();
		trigger_error("[plugin.mania_karma.php] Please copy the 'mania_karma.xml' from this Package into the XAseco directory and do not forget to edit it!", E_USER_ERROR);
	}
	libxml_use_internal_errors(false);

	// Remove all comments
	unset($xmlcfg->comment);


	if ((string)$xmlcfg->urls->api_auth == '') {
		trigger_error("[plugin.mania_karma.php] <urls><api_auth> is empty in config file 'mania_karma.xml'!", E_USER_ERROR);
	}

	if ((string)$xmlcfg->nation == '') {
		trigger_error("[plugin.mania_karma.php] <nation> is empty in config file 'mania_karma.xml'!", E_USER_ERROR);
	}
	else if ((string)$xmlcfg->nation == 'YOUR_SERVER_NATION') {
		trigger_error("[plugin.mania_karma.php] <nation> is not set in config file 'mania_karma.xml'! Please change 'YOUR_SERVER_NATION' with your server nation code.", E_USER_ERROR);
	}
	else if (! $iso3166Alpha3[strtoupper((string)$xmlcfg->nation)][1] ) {
		trigger_error("[plugin.mania_karma.php] <nation> is not valid in config file 'mania_karma.xml'! Please change <nation> to valid ISO-3166 ALPHA-3 nation code!", E_USER_ERROR);
	}


	// create web access
	$mk_config['webaccess'] = new Webaccess();

	// Set Url for API-Call Auth
	$mk_config['urls']['api_auth'] = (string)$xmlcfg->urls->api_auth;

	// Check the given config timeouts and set defaults on too low or on empty timeouts
	if ( ((int)$xmlcfg->wait_timeout < 40) || ((int)$xmlcfg->wait_timeout == '') ) {
		$mk_config['wait_timeout'] = 40;
	}
	else {
		$mk_config['wait_timeout'] = (int)$xmlcfg->wait_timeout;
	}
	if ( ((int)$xmlcfg->connect_timeout < 30) || ((int)$xmlcfg->connect_timeout == '') ) {
		$mk_config['connect_timeout'] = 30;
	}
	else {
		$mk_config['connect_timeout'] = (int)$xmlcfg->connect_timeout;
	}
	if ( ((int)$xmlcfg->keepalive_min_timeout < 100) || ((int)$xmlcfg->keepalive_min_timeout == '') ) {
		$mk_config['keepalive_min_timeout'] = 100;
	}
	else {
		$mk_config['keepalive_min_timeout'] = (int)$xmlcfg->keepalive_min_timeout;
	}

	// Set connection status to 'all fine'
	$mk_config['retrytime'] = 0;

	// 15 min. wait until try to reconnect
	$mk_config['retrywait'] = (10 * 60);

	// Set login data
	$mk_config['account']['login']	= strtolower((string)$aseco->server->serverlogin);
	$mk_config['account']['nation']	= strtoupper((string)$xmlcfg->nation);

	// Create a User-Agent-Identifier for the authentication
	$mk_config['user_agent'] = 'XAseco/'. XASECO_VERSION .' mania-karma/'. $mk_config['version'] .' '. $aseco->server->game .'/'. $aseco->server->build .' php/'. phpversion() .' '. php_uname('s') .'/'. php_uname('r') .' '. php_uname('m');

	$aseco->console('************************(ManiaKarma)*************************');
	$aseco->console('plugin.mania_karma.php/'. $mk_config['version'] .' for XAseco');
	$aseco->console(' => Set Server location to "'. $iso3166Alpha3[$mk_config['account']['nation']][0] .'"');
	$aseco->console(' => Trying to authenticate with central database "'. $mk_config['urls']['api_auth'] .'"...');

	// Generate the url for the first Auth-Request
	$api_url = sprintf("%s?Action=Auth&login=%s&name=%s&game=%s&zone=%s&nation=%s",
		$mk_config['urls']['api_auth'],
		urlencode( $mk_config['account']['login'] ),
		base64_encode( $aseco->server->name ),
		urlencode( $aseco->server->game ),
		urlencode( $aseco->server->zone ),
		urlencode( $mk_config['account']['nation'] )
	);


	// Start an async GET request
	$response = $mk_config['webaccess']->request($api_url, null, 'none', false, $mk_config['keepalive_min_timeout'], $mk_config['connect_timeout'], $mk_config['wait_timeout'], $mk_config['user_agent']);
	if ($response['Code'] == 200) {
		// Read the response
		if (!$xml = @simplexml_load_string($response['Message'], null, LIBXML_COMPACT) ) {
			$mk_config['retrytime'] = (time() + $mk_config['retrywait']);
			$mk_config['account']['authcode'] = false;
			$mk_config['urls']['api'] = false;

			// Fake import done to do not ask a MasterAdmin to export
			$mk_config['import_done'] = true;

			$aseco->console(' => Could not read/parse response from mania-karma.com "'. $response['Message'] .'"!');
			$aseco->console(' => Connection failed with '. $response['Code'] .' ('. $response['Reason'] .') for url ['. $api_url .'], retry again later.');
			$aseco->console('**********************************************************');
		}
		else {
			if ((int)$xml->status == 200) {
				$mk_config['retrytime'] = 0;
				$mk_config['account']['authcode'] = (string)$xml->authcode;
				$mk_config['urls']['api'] = (string)$xml->api_url;

				$mk_config['import_done'] = ((strtoupper((string)$xml->import_done) == 'TRUE') ? true : false);

				$aseco->console(' => Successfully started with async communication.');
				$aseco->console(' => The API set the Request-URL to "'. $mk_config['urls']['api'] .'"');
				$aseco->console('**********************************************************');
			}
			else {
				$mk_config['retrytime'] = (time() + $mk_config['retrywait']);
				$mk_config['account']['authcode'] = false;
				$mk_config['urls']['api'] = false;

				// Fake import done to do not ask a MasterAdmin to export
				$mk_config['import_done'] = true;

				$aseco->console(' => Authentication failed with error code "'. $xml->status .'", votes are not possible!!!');
				$aseco->console('**********************************************************');
			}
		}
	}
	else {
		$mk_config['retrytime'] = (time() + $mk_config['retrywait']);
		$mk_config['account']['authcode'] = false;
		$mk_config['urls']['api'] = false;

		// Fake import done to do not ask a MasterAdmin to export
		$mk_config['import_done'] = true;

		$aseco->console(' => Connection failed with '. $response['Code'] .' ('. $response['Reason'] .') for url ['. $api_url .'], retry again later.');
		$aseco->console('**********************************************************');
	}

	// Erase $iso3166Alpha3
	unset($iso3166Alpha3);


	// Are the position configured?
	if ( !isset($xmlcfg->reminder_window->race->pos_x) ) {
		$mk_config['reminder_window']['race']['pos_x'] = -41;
	}
	else {
		$mk_config['reminder_window']['race']['pos_x'] = (float)$xmlcfg->reminder_window->race->pos_x;
	}
	if ( !isset($xmlcfg->reminder_window->race->pos_y) ) {
		$mk_config['reminder_window']['race']['pos_y'] = -29.35;
	}
	else {
		$mk_config['reminder_window']['race']['pos_y'] = (float)$xmlcfg->reminder_window->race->pos_y;
	}
	if ( !isset($xmlcfg->reminder_window->score->pos_x) ) {
		$mk_config['reminder_window']['score']['pos_x'] = -41;
	}
	else {
		$mk_config['reminder_window']['score']['pos_x'] = (float)$xmlcfg->reminder_window->score->pos_x;
	}
	if ( !isset($xmlcfg->reminder_window->score->pos_y) ) {
		$mk_config['reminder_window']['score']['pos_y'] = -29.35;
	}
	else {
		$mk_config['reminder_window']['score']['pos_y'] = (float)$xmlcfg->reminder_window->score->pos_y;
	}



	$gamemodes = array(
		'rounds'	=> Gameinfo::RNDS,
		'time_attack'	=> Gameinfo::TA,
		'team'		=> Gameinfo::TEAM,
		'laps'		=> Gameinfo::LAPS,
		'cup'		=> Gameinfo::CUP,
		'stunts'	=> Gameinfo::STNT,
		'score'		=> 7,
	);
	foreach ($gamemodes as $mode => $id) {
		if ( isset($xmlcfg->karma_widget->gamemode->$mode) ) {
			$mk_config['widget']['states'][$id]['enabled']	= ((strtoupper((string)$xmlcfg->karma_widget->gamemode->$mode->enabled) == 'TRUE') ? true : false);
			$mk_config['widget']['states'][$id]['pos_x']	= (float)($xmlcfg->karma_widget->gamemode->$mode->pos_x ? $xmlcfg->karma_widget->gamemode->$mode->pos_x : 0);
			$mk_config['widget']['states'][$id]['pos_y']	= (float)($xmlcfg->karma_widget->gamemode->$mode->pos_y ? $xmlcfg->karma_widget->gamemode->$mode->pos_y : 0);
			$mk_config['widget']['states'][$id]['scale']	= ($xmlcfg->karma_widget->gamemode->$mode->scale ? sprintf("%.1f", $xmlcfg->karma_widget->gamemode->$mode->scale) : 1.0);
		}
	}
	unset($gamemodes);


	// Set the current state for the KarmaWidget
	$mk_config['widget']['current_state']			= $aseco->server->gameinfo->mode;

	// Set the config
	$mk_config['urls']['website']				= (string)$xmlcfg->urls->website;
	$mk_config['show_welcome']				= ((strtoupper((string)$xmlcfg->show_welcome) == 'TRUE')		? true : false);
	$mk_config['allow_public_vote']				= ((strtoupper((string)$xmlcfg->allow_public_vote) == 'TRUE')		? true : false);
	$mk_config['show_at_start']				= ((strtoupper((string)$xmlcfg->show_at_start) == 'TRUE')		? true : false);
	$mk_config['show_details']				= ((strtoupper((string)$xmlcfg->show_details) == 'TRUE')		? true : false);
	$mk_config['show_votes']				= ((strtoupper((string)$xmlcfg->show_votes) == 'TRUE')			? true : false);
	$mk_config['show_karma']				= ((strtoupper((string)$xmlcfg->show_karma) == 'TRUE')			? true : false);
	$mk_config['require_finish']				= (int)$xmlcfg->require_finish;
	$mk_config['remind_to_vote']				= strtoupper((string)$xmlcfg->remind_to_vote);
	$mk_config['reminder_window']['display']		= strtoupper((string)$xmlcfg->reminder_window->display);
	$mk_config['score_mx_window']				= ((strtoupper((string)$xmlcfg->score_mx_window) == 'TRUE')		? true : false);
	$mk_config['messages_in_window']			= ((strtoupper((string)$xmlcfg->messages_in_window) == 'TRUE')		? true : false);
	$mk_config['show_player_vote_public']			= ((strtoupper((string)$xmlcfg->show_player_vote_public) == 'TRUE')	? true : false);
	$mk_config['save_karma_also_local']			= ((strtoupper((string)$xmlcfg->save_karma_also_local) == 'TRUE')	? true : false);
	$mk_config['sync_global_karma_local']			= ((strtoupper((string)$xmlcfg->sync_global_karma_local) == 'TRUE')	? true : false);
	$mk_config['images']['widget_open_left']		= (string)$xmlcfg->images->widget_open_left;
	$mk_config['images']['widget_open_right']		= (string)$xmlcfg->images->widget_open_right;
	$mk_config['images']['tmx_logo_normal']			= (string)$xmlcfg->images->tmx_logo_normal;
	$mk_config['images']['tmx_logo_focus']			= (string)$xmlcfg->images->tmx_logo_focus;
	$mk_config['images']['cup_gold']			= (string)$xmlcfg->images->cup_gold;
	$mk_config['images']['cup_silver']			= (string)$xmlcfg->images->cup_silver;
	$mk_config['images']['maniakarma_logo']			= (string)$xmlcfg->images->maniakarma_logo;
	$mk_config['images']['progress_indicator']		= (string)$xmlcfg->images->progress_indicator;
	$mk_config['uptodate_check']				= ((strtoupper((string)$xmlcfg->uptodate_check) == 'TRUE')		? true : false);
	$mk_config['uptodate_info']				= strtoupper((string)$xmlcfg->uptodate_info);

	$mk_config['karma_calculation_method']			= strtoupper((string)$xmlcfg->karma_calculation_method);

	// Config for Karma Lottery
	$mk_config['karma_lottery']['enabled']			= ((strtoupper((string)$xmlcfg->karma_lottery->enabled) == 'TRUE')	? true : false);
	$mk_config['karma_lottery']['minimum_players']		= ((int)$xmlcfg->karma_lottery->minimum_players ? (int)$xmlcfg->karma_lottery->minimum_players : 1);
	$mk_config['karma_lottery']['coppers_win']		= (int)$xmlcfg->karma_lottery->coppers_win;
	$mk_config['karma_lottery']['minimum_server_coppers']	= (int)$xmlcfg->karma_lottery->minimum_server_coppers;
	$mk_config['karma_lottery']['total_payout']		= 0;

	unset($xmlcfg->messages->comment);			// purge mem. usage

	// Misc. messages
	$mk_config['messages']['welcome']			= (string)$xmlcfg->messages->welcome;
	$mk_config['messages']['uptodate_ok']			= (string)$xmlcfg->messages->uptodate_ok;
	$mk_config['messages']['uptodate_new']			= (string)$xmlcfg->messages->uptodate_new;
	$mk_config['messages']['uptodate_failed']		= (string)$xmlcfg->messages->uptodate_failed;

	// Vote messages
	$mk_config['messages']['karma_message']			= (string)$xmlcfg->messages->karma_message;
	$mk_config['messages']['karma_your_vote']		= (string)$xmlcfg->messages->karma_your_vote;
	$mk_config['messages']['karma_not_voted']		= (string)$xmlcfg->messages->karma_not_voted;
	$mk_config['messages']['karma_details']			= (string)$xmlcfg->messages->karma_details;
	$mk_config['messages']['karma_done']			= (string)$xmlcfg->messages->karma_done;
	$mk_config['messages']['karma_change']			= (string)$xmlcfg->messages->karma_change;
	$mk_config['messages']['karma_voted']			= (string)$xmlcfg->messages->karma_voted;
	$mk_config['messages']['karma_remind']			= (string)$xmlcfg->messages->karma_remind;
	$mk_config['messages']['karma_require_finish']		= (string)$xmlcfg->messages->karma_require_finish;
	$mk_config['messages']['karma_no_public']		= (string)$xmlcfg->messages->karma_no_public;
	$mk_config['messages']['karma_list_help']		= (string)$xmlcfg->messages->karma_list_help;
	$mk_config['messages']['karma_help']			= (string)$xmlcfg->messages->karma_help;

	$mk_config['messages']['karma_reminder_at_score']	= (string)$xmlcfg->messages->karma_reminder_at_score;
	$mk_config['messages']['karma_vote_singular']		= (string)$xmlcfg->messages->karma_vote_singular;
	$mk_config['messages']['karma_vote_plural']		= (string)$xmlcfg->messages->karma_vote_plural;
	$mk_config['messages']['karma_you_have_voted']		= (string)$xmlcfg->messages->karma_you_have_voted;
	$mk_config['messages']['karma_fantastic']		= (string)$xmlcfg->messages->karma_fantastic;
	$mk_config['messages']['karma_beautiful']		= (string)$xmlcfg->messages->karma_beautiful;
	$mk_config['messages']['karma_good']			= (string)$xmlcfg->messages->karma_good;
	$mk_config['messages']['karma_undecided']		= (string)$xmlcfg->messages->karma_undecided;
	$mk_config['messages']['karma_bad']			= (string)$xmlcfg->messages->karma_bad;
	$mk_config['messages']['karma_poor']			= (string)$xmlcfg->messages->karma_poor;
	$mk_config['messages']['karma_waste']			= (string)$xmlcfg->messages->karma_waste;
	$mk_config['messages']['karma_show_opinion']		= (string)$xmlcfg->messages->karma_show_opinion;
	$mk_config['messages']['karma_show_undecided']		= (string)$xmlcfg->messages->karma_show_undecided;

	// Lottery messages
	$mk_config['messages']['lottery_mail_body']		= (string)$xmlcfg->messages->lottery_mail_body;
	$mk_config['messages']['lottery_player_won']		= (string)$xmlcfg->messages->lottery_player_won;
	$mk_config['messages']['lottery_low_coppers']		= (string)$xmlcfg->messages->lottery_low_coppers;
	$mk_config['messages']['lottery_to_few_players']	= (string)$xmlcfg->messages->lottery_to_few_players;
	$mk_config['messages']['lottery_total_player_win']	= (string)$xmlcfg->messages->lottery_total_player_win;
	$mk_config['messages']['lottery_help']			= (string)$xmlcfg->messages->lottery_help;

	// Widget specific
	$mk_config['widget']['buttons']['bg_positive_default']	= (string)$xmlcfg->widget_styles->vote_buttons->positive->bgcolor_default;
	$mk_config['widget']['buttons']['bg_positive_focus']	= (string)$xmlcfg->widget_styles->vote_buttons->positive->bgcolor_focus;
	$mk_config['widget']['buttons']['positive_text_color']	= (string)$xmlcfg->widget_styles->vote_buttons->positive->text_color;
	$mk_config['widget']['buttons']['bg_negative_default']	= (string)$xmlcfg->widget_styles->vote_buttons->negative->bgcolor_default;
	$mk_config['widget']['buttons']['bg_negative_focus']	= (string)$xmlcfg->widget_styles->vote_buttons->negative->bgcolor_focus;
	$mk_config['widget']['buttons']['negative_text_color']	= (string)$xmlcfg->widget_styles->vote_buttons->negative->text_color;
	$mk_config['widget']['buttons']['bg_vote']		= (string)$xmlcfg->widget_styles->vote_buttons->votes->bgcolor_vote;
	$mk_config['widget']['buttons']['bg_disabled']		= (string)$xmlcfg->widget_styles->vote_buttons->votes->bgcolor_disabled;
	$mk_config['widget']['race']['title']			= (string)$xmlcfg->widget_styles->race->title;
	$mk_config['widget']['race']['icon_style']		= (string)$xmlcfg->widget_styles->race->icon_style;
	$mk_config['widget']['race']['icon_substyle']		= (string)$xmlcfg->widget_styles->race->icon_substyle;
	$mk_config['widget']['race']['background_style']	= (string)$xmlcfg->widget_styles->race->background_style;
	$mk_config['widget']['race']['background_substyle']	= (string)$xmlcfg->widget_styles->race->background_substyle;
	$mk_config['widget']['race']['title_style']		= (string)$xmlcfg->widget_styles->race->title_style;
	$mk_config['widget']['race']['title_substyle']		= (string)$xmlcfg->widget_styles->race->title_substyle;
	$mk_config['widget']['score']['title']			= (string)$xmlcfg->widget_styles->score->title;
	$mk_config['widget']['score']['icon_style']		= (string)$xmlcfg->widget_styles->score->icon_style;
	$mk_config['widget']['score']['icon_substyle']		= (string)$xmlcfg->widget_styles->score->icon_substyle;
	$mk_config['widget']['score']['background_style']	= (string)$xmlcfg->widget_styles->score->background_style;
	$mk_config['widget']['score']['background_substyle']	= (string)$xmlcfg->widget_styles->score->background_substyle;
	$mk_config['widget']['score']['title_style']		= (string)$xmlcfg->widget_styles->score->title_style;
	$mk_config['widget']['score']['title_substyle']		= (string)$xmlcfg->widget_styles->score->title_substyle;


	// Define the formats for number_format()
	$mk_config['number_format'] = strtolower((string)$xmlcfg->number_format);
	$mk_config['NumberFormat'] = array(
		'english'	=> array(
			'decimal_sep'	=> '.',
			'thousands_sep'	=> ',',
		),
		'german'	=> array(
			'decimal_sep'	=> ',',
			'thousands_sep'	=> '.',
		),
		'french'	=> array(
			'decimal_sep'	=> ',',
			'thousands_sep'	=> ' ',
		),
	);

	// Load the templates
	$mk_config['Templates']		= mk_loadTemplates();

	// Get required data of Map
	$mk_config['CurrentMap']	= mk_getCurrentMapInfo();

	// Init
	if ($aseco->startup_phase == true) {
		$karma				= mk_setEmptyKarma(true);
		$karma['data']['uid']		= $mk_config['CurrentMap']['uid'];
		$karma['data']['id']		= $mk_config['CurrentMap']['id'];
		$karma['data']['name']		= $mk_config['CurrentMap']['name'];
		$karma['data']['author']	= $mk_config['CurrentMap']['author'];
		$karma['data']['env']		= $mk_config['CurrentMap']['environment'];
		$karma['data']['tmx']		= (isset($mk_config['CurrentMap']['mx']['id']) ? $mk_config['CurrentMap']['mx']['id'] : '');
		$karma['new']['players']	= array();
	}

	// Update the global/local $karma
	mk_calculateKarma(array('global','local'));

	// Prebuild the Widgets
	$mk_config['widget']['skeleton']['race'] 	= mk_buildKarmaWidget($mk_config['widget']['current_state']);
	$mk_config['widget']['skeleton']['score']	= mk_buildKarmaWidget(7);		// 7 = Score

	if ($mk_config['retrytime'] == 0) {
		// Update KarmaWidget for all connected Players
		if ($mk_config['widget']['current_state'] == 7) {
			mk_sendWidgetCombination(array('skeleton_score', 'cups_values'), false);
		}
		else {
			mk_sendWidgetCombination(array('skeleton_race', 'cups_values'), false);
		}
		foreach ($aseco->server->players->player_list as $player) {
			mk_sendWidgetCombination(array('player_marker'), $player);
		}

		// Hide connection status
		mk_sendConnectionStatus(true, false);
	}


	// Add "/karma lottery" to "/karma help" if lottery is enabled
	if ($mk_config['karma_lottery']['enabled'] == true) {
		$mk_config['messages']['karma_help'] .= $mk_config['messages']['lottery_help'];
	}

	// Split long message
	$mk_config['messages']['karma_help'] = str_replace('{br}', LF, $aseco->formatColors($mk_config['messages']['karma_help']));

	// Free mem.
	unset($xmlcfg);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_onEverySecond ($aseco) {
	global $mk_config;


	// trigger pending callbacks
	$read = array();
	$write = null;
	$except = null;
	$mk_config['webaccess']->select($read, $write, $except, 0);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_onShutdown ($aseco) {


	// Save all Votes into the global and local (if enabled) Database
	mk_storeKarmaVotes();
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_onChat ($aseco, $chat) {
	global $mk_config;


	// If server message, bail out immediately
	if ($chat[0] == $aseco->server->id) return;

	// Check if public vote is enabled
	if ($mk_config['allow_public_vote'] == true) {

		// Get Player-Object
		$player = $aseco->server->players->getPlayer($chat[1]);

		// check for possible public karma vote
		if ($chat[2] == '+++') {
			mk_handlePlayerVote($player, 3);
		}
		else if ($chat[2] == '++') {
			mk_handlePlayerVote($player, 2);
		}
		else if ($chat[2] == '+') {
			mk_handlePlayerVote($player, 1);
		}
		else if ($chat[2] == '-') {
			mk_handlePlayerVote($player, -1);
		}
		else if ($chat[2] == '--') {
			mk_handlePlayerVote($player, -2);
		}
		else if ($chat[2] == '---') {
			mk_handlePlayerVote($player, -3);
		}
	}
	else if ( ($chat[2] == '+++') || ($chat[2] == '++') || ($chat[2] == '+') || ($chat[2] == '-') || ($chat[2] == '--') || ($chat[2] == '---') ) {

		// Get Player-Object
		$player = $aseco->server->players->getPlayer($chat[1]);

		$message = formatText($mk_config['messages']['karma_no_public'], '/'. $chat[2]);
		if ( ($mk_config['messages_in_window'] == true) && (function_exists('send_window_message')) ) {
			send_window_message($aseco, $message, ($player->login ? $player : false));
		}
		else {
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function chat_karma ($aseco, $command) {
	global $mk_config, $karma;


	// Init
	$message = false;

	// Check optional parameter
	if ( (strtoupper($command['params']) == 'HELP') || (strtoupper($command['params']) == 'ABOUT') ) {
		mk_sendHelpAboutWindow($command['author'], $mk_config['messages']['karma_help']);
	}
	else if (strtoupper($command['params']) == 'DETAILS') {
		$message = formatText($mk_config['messages']['karma_details'],
			$karma['global']['votes']['karma'],
			$karma['global']['votes']['fantastic']['percent'],	$karma['global']['votes']['fantastic']['count'],
			$karma['global']['votes']['beautiful']['percent'],	$karma['global']['votes']['beautiful']['count'],
			$karma['global']['votes']['good']['percent'],		$karma['global']['votes']['good']['count'],
			$karma['global']['votes']['bad']['percent'],		$karma['global']['votes']['bad']['count'],
			$karma['global']['votes']['poor']['percent'],		$karma['global']['votes']['poor']['count'],
			$karma['global']['votes']['waste']['percent'],		$karma['global']['votes']['waste']['count']
		);
	}
	else if (strtoupper($command['params']) == 'RELOAD') {
		if ($aseco->isMasterAdmin($command['author'])) {
			$aseco->console('[plugin.mania_karma.php] MasterAdmin '. $command['author']->login .' reloads the configuration.');
			$message = '{#admin}Reloading the configuration "mania_karma.xml" now.';
			mk_onSync($aseco);
		}
	}
	else if (strtoupper($command['params']) == 'EXPORT') {
		if ($aseco->isMasterAdmin($command['author'])) {
			$aseco->console('[plugin.mania_karma.php] MasterAdmin '. $command['author']->login .' start the export of all local votes.');
			mk_exportVotes($command['author']);
		}
	}
	else if (strtoupper($command['params']) == 'UPTODATE') {
		if ($aseco->isMasterAdmin($command['author'])) {
			$aseco->console('[plugin.mania_karma.php] MasterAdmin '. $command['author']->login .' start the up-to-date check.');
			mk_uptodateCheck($command['author']);
		}
	}
	else if ( (strtoupper($command['params']) == 'LOTTERY') && ($mk_config['karma_lottery']['enabled'] == true) ) {
		if  ( (isset($command['author']->rights)) && ($command['author']->rights) ) {
			$message = formatText($mk_config['messages']['lottery_total_player_win'],
				$command['author']->data['ManiaKarma']['LotteryPayout']
			);
		}
	}
	else if (strtoupper($command['params']) == '') {
		$message = mk_createKarmaMessage($command['author']->login, true);
	}

	// Show message
	if ($message != false) {
		if ( ($mk_config['messages_in_window'] == true) && (function_exists('send_window_message')) && ($mk_config['widget']['current_state'] != 7) ) {
			send_window_message($aseco, $message, $command['author']);
		}
		else {
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function chat_plusplusplus ($aseco, $command) {
	global $mk_config;

	mk_handlePlayerVote($command['author'], 3);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function chat_plusplus ($aseco, $command) {
	global $mk_config;

	mk_handlePlayerVote($command['author'], 2);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function chat_plus ($aseco, $command) {
	global $mk_config;

	mk_handlePlayerVote($command['author'], 1);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function chat_dashdashdash ($aseco, $command) {
	global $mk_config;

	mk_handlePlayerVote($command['author'], -3);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function chat_dashdash ($aseco, $command) {
	global $mk_config;

	mk_handlePlayerVote($command['author'], -2);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function chat_dash ($aseco, $command) {
	global $mk_config;

	mk_handlePlayerVote($command['author'], -1);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_onKarmaChange ($aseco, $unused) {
	global $mk_config;


	// Update the KarmaWidget for all Players
	mk_sendWidgetCombination(array('cups_values'), false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_onPlayerConnect ($aseco, $player) {
	global $mk_config, $karma;


	// Show welcome message to the new player?
	if ($mk_config['show_welcome'] == true) {
		$message = formatText($mk_config['messages']['welcome'],
				'http://'. $mk_config['urls']['website'] .'/',
				$mk_config['urls']['website']
		);
		$message = str_replace('{br}', LF, $message);  // split long message
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
	}


	// Check for a MasterAdmin
	if ($aseco->isMasterAdmin($player)) {
		// Do UpToDate check?
		if ($mk_config['uptodate_check'] == true) {
			mk_uptodateCheck($player);
		}

		// Export already made?
		if ($mk_config['import_done'] == false) {
			$message = '{#server}> {#emotic}#################################################'. LF;
			$message .= '{#server}> {#emotic}Please start the export of your current local votes with the command "/karma export". Thanks!'. LF;
			$message .= '{#server}> {#emotic}#################################################'. LF;
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		}
	}


	// If karma lottery is enabled, then initialize (if player has related rights)
	if ($mk_config['karma_lottery']['enabled'] == true) {
		if ( (isset($player->rights)) && ($player->rights) ) {
			$player->data['ManiaKarma']['LotteryPayout'] = 0;
		}
	}

	// Init the 'KarmaWidgetStatus' and 'KarmaReminderWindow' to the defaults
	$player->data['ManiaKarma']['ReminderWindow'] = false;

	// Init
	$player->data['ManiaKarma']['FinishedMapCount'] = 0;

	// Get required data for Map
	if ($mk_config['CurrentMap']['id'] === false) {
		$mk_config['CurrentMap'] = mk_getCurrentMapInfo();
	}

	// Check if finishes are required
	if ($mk_config['require_finish'] > 0) {
		// Find the amount of finish for this Player
		mk_findPlayersLocalRecords($mk_config['CurrentMap']['id'], array($player));
	}

	// Do nothing at Startup!!
	if ($aseco->startup_phase == false) {
		// Check if Player is already in $karma,
		// for "unwished disconnects" and "reconnected" Players
		if ( ( !isset($karma['global']['players'][$player->login]) ) || ($aseco->server->challenge->uid != $karma['data']['uid']) ) {

			if ( !isset($karma['global']['players'][$player->login]) ) {
				$karma['global']['players'][$player->login]['vote']	= 0;
				$karma['global']['players'][$player->login]['previous']	= 0;
			}

			if ( !isset($karma['local']['players'][$player->login]) ) {
				// Get the local votes for this Player
				mk_getLocalVotes($mk_config['CurrentMap']['id'], $player->login);
			}

			// Get the Karma from remote for this Player
			mk_handleGetApiCall($mk_config['CurrentMap'], $player);

			// Check to see if it is required to sync global to local votes?
			if ($mk_config['sync_global_karma_local'] == true) {
				mk_syncGlobaAndLocalVotes('local', false);
			}
		}

		// Display the complete KarmaWidget only for connected Player
		if ($mk_config['widget']['current_state'] == 7) {
			mk_sendWidgetCombination(array('skeleton_score', 'cups_values', 'player_marker'), $player);
		}
		else {
			mk_sendWidgetCombination(array('skeleton_race', 'cups_values', 'player_marker'), $player);
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_onPlayerDisconnect ($aseco, $player) {
	global $mk_config;


	// Need to pay Coppers for lottery wins to this player?
	if ($mk_config['karma_lottery']['enabled'] == true) {
		if ( (isset($player->rights)) && ($player->rights) ) {
			if ($player->data['ManiaKarma']['LotteryPayout'] > 0) {
				// Pay Coppers to player
				$message = formatText($mk_config['messages']['lottery_mail_body'],
					$aseco->server->name,
					(int)$player->data['ManiaKarma']['LotteryPayout'],
					$mk_config['account']['login']
				);
				$message = str_replace('{br}', "%0A", $message);  // split long message

				$aseco->client->resetError();
				$aseco->client->query('Pay', (string)$player->login, (int)$player->data['ManiaKarma']['LotteryPayout'], (string)$aseco->formatColors($message) );
				$billid = $aseco->client->getResponse();

				// Is there an error on pay?
				if ( $aseco->client->isError() ) {
					$aseco->console('[plugin.mania_karma.php] (ManiaKarma lottery) Pay '. $player->data['ManiaKarma']['LotteryPayout'] .' Coppers to player "'. $player->login .'" failed: [' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage());
				}
				else {
					$aseco->console('[plugin.mania_karma.php] (ManiaKarma lottery) Pay '. $player->data['ManiaKarma']['LotteryPayout'] .' Coppers to player "'. $player->login .'" done. (BillId #'. $billid .')');
				}

				// Subtract payed amounts from total (on error too, because player leaved)
				$mk_config['karma_lottery']['total_payout'] -= (int)$player->data['ManiaKarma']['LotteryPayout'];
			}
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_onPlayerFinish ($aseco, $finish_item) {
	global $mk_config, $karma;


	// If no actual finish, bail out immediately
	if ($finish_item->score == 0) {
		return;
	}

	// Check if finishes are required
	if ($mk_config['require_finish'] > 0) {
		// Save that the player finished this map
		$finish_item->player->data['ManiaKarma']['FinishedMapCount'] += 1;

		// Enable the vote possibilities for this player
		mk_sendWidgetCombination(array('player_marker'), $finish_item->player);
	}

	// If no finish reminders, bail out too (does not need to check $player->data['ManiaKarma']['FinishedMapCount'], because actually finished ;)
	if ( ($mk_config['remind_to_vote'] == 'FINISHED') || ($mk_config['remind_to_vote'] == 'ALWAYS') ) {

		// Check whether player already voted
		if ( ($karma['global']['players'][$finish_item->player->login]['vote'] == 0) && ( ($mk_config['require_finish'] > 0) && ($mk_config['require_finish'] <= $finish_item->player->data['ManiaKarma']['FinishedMapCount']) ) ) {
			if ( ($mk_config['reminder_window']['display'] == 'FINISHED') || ($mk_config['reminder_window']['display'] == 'ALWAYS') ) {
				// Show reminder window
				mk_showReminderWindow($finish_item->player->login);
				$finish_item->data['ManiaKarma']['ReminderWindow'] = true;
			}
			else {
				// Show reminder message
				$message = $mk_config['messages']['karma_remind'];
				if ( ($mk_config['messages_in_window'] == true) && (function_exists('send_window_message')) ) {
					send_window_message($aseco, $message, $finish_item->player);
				}
				else {
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $finish_item->player->login);
				}
			}
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_onPlayerManialinkPageAnswer ($aseco, $answer) {
	global $mk_config;


	// If id = 0, bail out immediately
	if ($answer[2] == 0) {
		return;
	}

	// Get Player
	$command['author'] = $aseco->server->players->getPlayer($answer[1]);


	if ($answer[2] == $mk_config['manialink_id'] .'00') {			// Close HelpWindow
		$window = '<manialink id="'. $mk_config['manialink_id'] .'02"></manialink>';
		mk_sendWindow($command['author']->login, $window);
	}
	else if ($answer[2] == $mk_config['manialink_id'] .'01') {		// Open HelpWindow
		mk_sendHelpAboutWindow($command['author'], $mk_config['messages']['karma_help']);
	}
	else if ($answer[2] == $mk_config['manialink_id'] .'02') {		// Open KarmaDetailWindow
		$window = mk_buildKarmaDetailWindow($command['author']->login);
		mk_sendWindow($command['author']->login, $window);
	}
	else if ($answer[2] == $mk_config['manialink_id'] .'03') {		// Open WhoKarmaWindow
		$window = mk_buildWhoKarmaWindow($command['author']->login);
		mk_sendWindow($command['author']->login, $window);
	}
	else if ($answer[2] == $mk_config['manialink_id'] .'12') {		// Vote +++
		mk_handlePlayerVote($command['author'], 3);
	}
	else if ($answer[2] == $mk_config['manialink_id'] .'11') {		// Vote ++
		mk_handlePlayerVote($command['author'], 2);
	}
	else if ($answer[2] == $mk_config['manialink_id'] .'10') {		// Vote +
		mk_handlePlayerVote($command['author'], 1);
	}
	else if ($answer[2] == $mk_config['manialink_id'] .'13') {		// Vote undecided
		mk_showUndecidedMessage($command);
	}
	else if ($answer[2] == $mk_config['manialink_id'] .'14') {		// Vote -
		mk_handlePlayerVote($command['author'], -1);
}
	else if ($answer[2] == $mk_config['manialink_id'] .'15') {		// Vote --
		mk_handlePlayerVote($command['author'], -2);
	}
	else if ($answer[2] == $mk_config['manialink_id'] .'16') {		// Vote ---
		mk_handlePlayerVote($command['author'], -3);
	}
	else if ($answer[2] == $mk_config['manialink_id'] .'17') {		// Vote disabled on <require_finish> >= 1
		mk_handlePlayerVote($command['author'], 0);
	}
	else if ($answer[2] == $mk_config['manialink_id'] .'18') {		// No action, just ignore
		// do nothing
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_onNewChallenge ($aseco, $map) {
	global $mk_config;


	// Set $gamemode for the KarmaWidget
	$mk_config['widget']['current_state'] = $aseco->server->gameinfo->mode;

	// Close at all Players the reminder window
	mk_closeReminderWindow(false);

	// Remove all marker at all connected Players
	mk_sendWidgetCombination(array('hide_all'), false);

	// Save all Votes into the global and local (if enabled) Database
	mk_storeKarmaVotes();

	if ($mk_config['require_finish'] > 0) {
		// Remove the state that the player has finished this map (it is an new map now)
		// MUST placed here _BEFORE_ mk_handleGetApiCall() call, this sets
		// $player->data['ManiaKarma']['FinishedMapCount'] to true if the player has voted this map
		foreach ($aseco->server->players->player_list as $player) {
			$player->data['ManiaKarma']['FinishedMapCount'] = 0;
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_onNewChallenge2 ($aseco, $map) {
	global $mk_config, $karma;


	// Store current map infos
	$mk_config['CurrentMap'] 	= mk_getCurrentMapInfo();


	// Reset and Setup data for MultiVotes
	$karma = mk_setEmptyKarma(true);
	$karma['data']['uid']		= $map->uid;
	$karma['data']['id']		= $map->id;
	$karma['data']['name']		= $map->name;
	$karma['data']['author']	= $map->author;
	$karma['data']['env']		= $map->environment;
	$karma['data']['tmx']		= (isset($map->mx->id) ? $map->mx->id : '');
	$karma['new']['players']	= array();

	// If there no players, bail out
	if (count($aseco->server->players->player_list) == 0) {

		if ($mk_config['retrytime'] == 0) {
			// Start an async PING request
			// Generate the url for this Ping-Request
			$api_url = sprintf("%s?Action=Ping&login=%s&authcode=%s",
				$mk_config['urls']['api'],
				urlencode( $mk_config['account']['login'] ),
				urlencode( $mk_config['account']['authcode'] )
			);

			$mk_config['webaccess']->request($api_url, array('mk_handleWebaccess', 'PING', $api_url), 'none', false, $mk_config['keepalive_min_timeout'], $mk_config['connect_timeout'], $mk_config['wait_timeout'], $mk_config['user_agent']);
		}

		// Get the local karma
		mk_getLocalKarma($mk_config['CurrentMap']['id']);

		return;
	}

	// Get the local karma
	mk_getLocalKarma($mk_config['CurrentMap']['id']);

	// Get the local votes for all Players
	mk_getLocalVotes($mk_config['CurrentMap']['id'], false);

	// If <require_finish> is enabled
	if ($mk_config['require_finish'] > 0) {
		// Find the amount of finish for all Players
		mk_findPlayersLocalRecords($mk_config['CurrentMap']['id'], $aseco->server->players->player_list);
	}

	// Replace $karma from last Map with $karma of the current Map
	mk_handleGetApiCall($mk_config['CurrentMap'], false);

	// Check to see if it is required to sync global to local votes?
	if ($mk_config['sync_global_karma_local'] == true) {
		mk_syncGlobaAndLocalVotes('local', false);
	}

	// Rebuild the Widget, it is an new Map (and possible Gamemode)
	$mk_config['widget']['skeleton']['race']	= mk_buildKarmaWidget($aseco->server->gameinfo->mode);
	$mk_config['widget']['skeleton']['score']	= mk_buildKarmaWidget(7);		// 7 = Score

	mk_sendLoadingIndicator(true, $mk_config['widget']['current_state']);

	// Update KarmaWidget for all connected Players
	mk_sendWidgetCombination(array('skeleton_race', 'cups_values'), false);


	// Refresh the Player-Marker for all Players
	foreach ($aseco->server->players->player_list as $player) {
		// Display the Marker
		mk_sendWidgetCombination(array('player_marker'), $player);
	}

	// Display connection status
	if ($mk_config['retrytime'] > 0) {
		mk_sendConnectionStatus(false, $mk_config['widget']['current_state']);
	}

	// Before draw a lottery winner, check if players has already voted, if lottery is enabled and if players has related rights (TMU)
	if ($mk_config['karma_lottery']['enabled'] == true) {

		// Init message
		$message = false;

		// Is there not enough player on, bail out
		if (count($aseco->server->players->player_list) < $mk_config['karma_lottery']['minimum_players']) {
			// Show to few players message to all players
			$message = $mk_config['messages']['lottery_to_few_players'];
		}
		else {
			// Can all Player be paid with the new total? Add only Coppers if Server is over minimum.
			if ((mk_getServerCoppers() - $mk_config['karma_lottery']['minimum_server_coppers']) > ($mk_config['karma_lottery']['total_payout'] + $mk_config['karma_lottery']['coppers_win']) ) {

				// Init the lottery array
				$lottery_attendant = array();

				// Check all connected Players if they has voted
				foreach ($aseco->server->players->player_list as $player) {
					if ($karma['global']['players'][$player->login]['vote'] != 0) {
						array_push($lottery_attendant, $player->login);
					}
				}

				// Are enough TMU Players online and has voted?
				if (count($lottery_attendant) >= $mk_config['karma_lottery']['minimum_players']) {
					// Drawing of the lottery ("and the winner is")
					$winner = array_rand($lottery_attendant, 1);

					// If the Player is not already gone, go ahead
					$player = $aseco->server->players->getPlayer($lottery_attendant[$winner]);
					if ($player != false) {
						// Add to Players total
						$player->data['ManiaKarma']['LotteryPayout'] += $mk_config['karma_lottery']['coppers_win'];

						// Add to total payout
						$mk_config['karma_lottery']['total_payout'] += $mk_config['karma_lottery']['coppers_win'];

						// Show won message to all Players
						$message = formatText($mk_config['messages']['lottery_player_won'],
								$player->nickname,
								$mk_config['karma_lottery']['coppers_win']
						);
					}
					else {
						// Show to few Players message to all players
						$message = $mk_config['messages']['lottery_to_few_players'];
					}
				}
				else {
					// Show to few players message to all players
					$message = $mk_config['messages']['lottery_to_few_players'];
				}
			}
			else {
				// Show low Coppers message to all Players
				$message = $mk_config['messages']['lottery_low_coppers'];
			}
		}

		$message = str_replace('{br}', LF, $message);  // split long message
		if ( ($message !== false) && (function_exists('send_window_message')) ) {
			send_window_message($aseco, $message, false);
		}
		else {
			$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_onRestartChallenge2 ($aseco, $map) {
	global $mk_config;


	// Close at all Players the reminder window
	mk_closeReminderWindow(false);

	// Set $gamemode for the KarmaWidget
	$mk_config['widget']['current_state'] = $aseco->server->gameinfo->mode;


	// Make sure the Widget gets updated at all Players at Race
	mk_sendWidgetCombination(array('skeleton_race', 'cups_values'), false);


	// Display the Marker
	foreach ($aseco->server->players->player_list as $player) {
		// Update KarmaWidget only for given Player
		mk_sendWidgetCombination(array('player_marker'), $player);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_onEndRace1 ($aseco, $data) {
	global $mk_config, $karma;


	// If there no players, bail out immediately
	if (count($aseco->server->players->player_list) == 0) {
		return;
	}


	// Finished run, set 'SCORE' for the KarmaWidget
	$mk_config['widget']['current_state'] = 7;


	// Display connection status
	if ($mk_config['retrytime'] > 0) {
		mk_sendConnectionStatus(false, $mk_config['widget']['current_state']);
	}

	// Update KarmaWidget for all connected Players
	mk_sendWidgetCombination(array('hide_window', 'skeleton_score', 'cups_values'), false);

	// Refresh the Player-Marker for all Players
	foreach ($aseco->server->players->player_list as $player) {
		// Update KarmaWidget only for given Player
		mk_sendWidgetCombination(array('player_marker'), $player);
	}


	// If no end race reminders, bail out immediately
	if ( ($mk_config['remind_to_vote'] == 'SCORE') || ($mk_config['remind_to_vote'] == 'ALWAYS') ) {

		// Check all connected Players
		$players_reminder = array();
		foreach ($aseco->server->players->player_list as $player) {

			// Skip if Player did not finished the map but it is required to vote
			if ( ($mk_config['require_finish'] > 0) && ($player->data['ManiaKarma']['FinishedMapCount'] < $mk_config['require_finish']) ) {
				continue;
			}

			// Check whether Player already voted
			if ($karma['global']['players'][$player->login]['vote'] == 0) {
				$players_reminder[] = $player->login;
				$player->data['ManiaKarma']['ReminderWindow'] = true;
			}
			else if ($mk_config['score_mx_window'] == true) {
				// Show the MX-Link-Window
				mk_showManiaExchangeLinkWindow($player);
			}
		}

		if (count($players_reminder) > 0) {
			if ( ($mk_config['reminder_window']['display'] == 'SCORE') || ($mk_config['reminder_window']['display'] == 'ALWAYS') ) {
				// Show reminder Window
				mk_showReminderWindow(implode(',', $players_reminder));
			}
			else {
				// Show reminder message (not to the TMF-Message Window)
				$message = $mk_config['messages']['karma_remind'];
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), implode(',', $players_reminder));
			}
		}
		unset($players_reminder);

	}
	else if ($mk_config['score_mx_window'] == true) {
		// Check all connected Players
		foreach ($aseco->server->players->player_list as $player) {

			// Get current Player status and ignore Spectators
			if ( $aseco->isSpectator($player) ) {
				continue;
			}

			// Check whether Player already voted
			if ($karma['global']['players'][$player->login]['vote'] != 0) {
				// Show the MX-Link-Window
				mk_showManiaExchangeLinkWindow($player);
			}
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_sendWidgetCombination ($widgets, $player = false) {
	global $aseco, $mk_config;


	// If there no players, bail out immediately
	if (count($aseco->server->players->player_list) == 0) {
		return;
	}

	$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';

	// Possible parameters: 'skeleton_race', 'skeleton_score', 'cups_values', 'player_marker', 'hide_window' and 'hide_all'
	foreach ($widgets as $widget) {
		if ($widget == 'hide_all') {
			$xml .= '<manialink id="'. $mk_config['manialink_id'] .'02"></manialink>';	// Windows
			$xml .= '<manialink id="'. $mk_config['manialink_id'] .'03"></manialink>';	// SkeletonWidget
			$xml .= '<manialink id="'. $mk_config['manialink_id'] .'04"></manialink>';	// PlayerVoteMarker
			$xml .= '<manialink id="'. $mk_config['manialink_id'] .'05"></manialink>';	// KarmaCupsValue
			$xml .= '<manialink id="'. $mk_config['manialink_id'] .'06"></manialink>';	// ConnectionStatus
			$xml .= '<manialink id="'. $mk_config['manialink_id'] .'07"></manialink>';	// LoadingIndicator
			break;
		}

		if ($widget == 'hide_window') {
			$xml .= '<manialink id="'. $mk_config['manialink_id'] .'02"></manialink>';	// Windows
		}

		if ($mk_config['widget']['states'][$mk_config['widget']['current_state']]['enabled'] == true) {
			if ($widget == 'skeleton_race') {
				$xml .= $mk_config['widget']['skeleton']['race'];
			}
			else if ($widget == 'skeleton_score') {
				$xml .= $mk_config['widget']['skeleton']['score'];
			}
			else if ($widget == 'cups_values') {
				$xml .= mk_buildKarmaCupsValue($mk_config['widget']['current_state']);
			}
			else if ($widget == 'player_marker') {
				$xml .= mk_buildPlayerVoteMarker($player, $mk_config['widget']['current_state']);
			}
		}
		else {
			$xml .= '<manialink id="'. $mk_config['manialink_id'] .'03"></manialink>';	// SkeletonWidget
			$xml .= '<manialink id="'. $mk_config['manialink_id'] .'04"></manialink>';	// PlayerVoteMarker
			$xml .= '<manialink id="'. $mk_config['manialink_id'] .'05"></manialink>';	// KarmaCupsValue
		}
	}

	$xml .= '</manialinks>';


	if ($player != false) {
		$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);
	}
	else {
		$aseco->client->query('SendDisplayManialinkPage', $xml, 0, false);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_buildKarmaWidget ($gamemode) {
	global $aseco, $mk_config;


	// Bail out if map id was not found
	if ($mk_config['CurrentMap']['id'] === false) {
		return;
	}

	// No Placeholder here!
	$xml = '<manialink id="'. $mk_config['manialink_id'] .'03">';

	// MainWidget Frame
	$xml .= '<frame posn="'. $mk_config['widget']['states'][$gamemode]['pos_x'] .' '. $mk_config['widget']['states'][$gamemode]['pos_y'] .' 10">';
	if ($gamemode == 7) {
		// No action to open the full widget at 'Score'
		$xml .= '<quad posn="0 0 0.02" sizen="15.76 10.75" style="'. $mk_config['widget']['score']['background_style'] .'" substyle="'. $mk_config['widget']['score']['background_substyle'] .'"/>';
	}
	else {
		$xml .= '<quad posn="0 0 0.02" sizen="15.76 10.75" action="'. $mk_config['manialink_id'] .'02" style="'. $mk_config['widget']['race']['background_style'] .'" substyle="'. $mk_config['widget']['race']['background_substyle'] .'"/>';
		if ($mk_config['widget']['states'][$gamemode]['pos_x'] > 0) {
			$xml .= '<quad posn="-0.3 -7.4 0.05" sizen="3.5 3.5" image="'. $mk_config['images']['widget_open_left'] .'"/>';
		}
		else {
			$xml .= '<quad posn="12.46 -7.4 0.05" sizen="3.5 3.5" image="'. $mk_config['images']['widget_open_right'] .'"/>';
		}
	}

	// Vote Frame, different offset on default widget
	$xml .= '<frame posn="0 0 0">';


	// Window title
	if ($gamemode == 7) {
		$xml .= '<quad posn="0.4 -0.3 3" sizen="14.96 2" url="http://'. $mk_config['urls']['website'] .'/goto?uid='. $mk_config['CurrentMap']['uid'] .'&amp;env='. $mk_config['CurrentMap']['environment'] .'&amp;game='. $aseco->server->game .'" style="'. $mk_config['widget']['score']['title_style'] .'" substyle="'. $mk_config['widget']['score']['title_substyle'] .'"/>';
	}
	else {
		$xml .= '<quad posn="0.4 -0.3 3" sizen="14.96 2" url="http://'. $mk_config['urls']['website'] .'/goto?uid='. $mk_config['CurrentMap']['uid'] .'&amp;env='. $mk_config['CurrentMap']['environment'] .'&amp;game='. $aseco->server->game .'" style="'. $mk_config['widget']['race']['title_style'] .'" substyle="'. $mk_config['widget']['race']['title_substyle'] .'"/>';
	}

	if ($gamemode == 7) {
		$title = $mk_config['widget']['score']['title'];
		$icon_style = $mk_config['widget']['score']['icon_style'];
		$icon_substyle = $mk_config['widget']['score']['icon_substyle'];
	}
	else {
		$title = $mk_config['widget']['race']['title'];
		$icon_style = $mk_config['widget']['race']['icon_style'];
		$icon_substyle = $mk_config['widget']['race']['icon_substyle'];
	}

	if ($mk_config['widget']['states'][$gamemode]['pos_x'] > 0) {
		// Position from icon and title to left
		$xml .= '<quad posn="0.6 -0.15 3.1" sizen="2.3 2.3" style="'. $icon_style .'" substyle="'. $icon_substyle .'"/>';
		$xml .= '<label posn="3.2 -0.6 3.2" sizen="10 0" halign="left" textsize="1" text="'. $title .'"/>';
	}
	else {
		// Position from icon and title to right
		$xml .= '<quad posn="13.1 -0.15 3.1" sizen="2.3 2.3" style="'. $icon_style .'" substyle="'. $icon_substyle .'"/>';
		$xml .= '<label posn="12.86 -0.6 3.2" sizen="10 0" halign="right" textsize="1" text="'. $title .'"/>';
	}

	// BG for Buttons to prevent flicker of the widget background (clickable too)
	$xml .= '<frame posn="1.83 -8.3 1">';
	$xml .= '<quad posn="0.2 -0.08 0.1" sizen="11.8 1.4" action="'. $mk_config['manialink_id'] .'18" bgcolor="0000"/>';
	$xml .= '</frame>';

	// Button +++
	$xml .= '<frame posn="1.83 -8.5 1">';
	$xml .= '<label posn="0.2 -0.08 0.2" sizen="1.8 1.4" action="'. $mk_config['manialink_id'] .'12" focusareacolor1="'. $mk_config['widget']['buttons']['bg_positive_default'] .'" focusareacolor2="'. $mk_config['widget']['buttons']['bg_positive_focus'] .'" text=" "/>';
	$xml .= '<label posn="1.12 -0.25 0.4" sizen="1.8 0" textsize="1" scale="0.8" halign="center" textcolor="'. $mk_config['widget']['buttons']['positive_text_color'] .'" text="+++"/>';
	$xml .= '</frame>';

	// Button ++
	$xml .= '<frame posn="3.83 -8.5 1">';
	$xml .= '<label posn="0.2 -0.08 0.2" sizen="1.8 1.4" action="'. $mk_config['manialink_id'] .'11" focusareacolor1="'. $mk_config['widget']['buttons']['bg_positive_default'] .'" focusareacolor2="'. $mk_config['widget']['buttons']['bg_positive_focus'] .'" text=" "/>';
	$xml .= '<label posn="1.12 -0.25 0.4" sizen="1.8 0" textsize="1" scale="0.8" halign="center" textcolor="'. $mk_config['widget']['buttons']['positive_text_color'] .'" text="++"/>';
	$xml .= '</frame>';

	// Button +
	$xml .= '<frame posn="5.83 -8.5 1">';
	$xml .= '<label posn="0.2 -0.08 0.2" sizen="1.8 1.4" action="'. $mk_config['manialink_id'] .'10" focusareacolor1="'. $mk_config['widget']['buttons']['bg_positive_default'] .'" focusareacolor2="'. $mk_config['widget']['buttons']['bg_positive_focus'] .'" text=" "/>';
	$xml .= '<label posn="1.12 -0.25 0.4" sizen="1.8 0" textsize="1" scale="0.8" halign="center" textcolor="'. $mk_config['widget']['buttons']['positive_text_color'] .'" text="+"/>';
	$xml .= '</frame>';

	// Button -
	$xml .= '<frame posn="7.83 -8.5 1">';
	$xml .= '<label posn="0.2 -0.08 0.2" sizen="1.8 1.4" action="'. $mk_config['manialink_id'] .'14" focusareacolor1="'. $mk_config['widget']['buttons']['bg_negative_default'] .'" focusareacolor2="'. $mk_config['widget']['buttons']['bg_negative_focus'] .'" text=" "/>';
	$xml .= '<label posn="1.12 0 0.4" sizen="1.8 0" textsize="1" scale="0.9" halign="center" textcolor="'. $mk_config['widget']['buttons']['negative_text_color'] .'" text="-"/>';
	$xml .= '</frame>';

	// Button --
	$xml .= '<frame posn="9.83 -8.5 1">';
	$xml .= '<label posn="0.2 -0.08 0.2" sizen="1.8 1.4" action="'. $mk_config['manialink_id'] .'15" focusareacolor1="'. $mk_config['widget']['buttons']['bg_negative_default'] .'" focusareacolor2="'. $mk_config['widget']['buttons']['bg_negative_focus'] .'" text=" "/>';
	$xml .= '<label posn="1.12 0 0.4" sizen="1.8 0" textsize="1" scale="0.9" halign="center" textcolor="'. $mk_config['widget']['buttons']['negative_text_color'] .'" text="--"/>';
	$xml .= '</frame>';

	// Button ---
	$xml .= '<frame posn="11.83 -8.5 1">';
	$xml .= '<label posn="0.2 -0.08 0.2" sizen="1.8 1.4" action="'. $mk_config['manialink_id'] .'16" focusareacolor1="'. $mk_config['widget']['buttons']['bg_negative_default'] .'" focusareacolor2="'. $mk_config['widget']['buttons']['bg_negative_focus'] .'" text=" "/>';
	$xml .= '<label posn="1.12 0 0.4" sizen="1.8 0" textsize="1" scale="0.9" halign="center" textcolor="'. $mk_config['widget']['buttons']['negative_text_color'] .'" text="---"/>';
	$xml .= '</frame>';


	$xml .= '</frame>'; // Vote Frame
	$xml .= '</frame>'; // MainWidget Frame
	$xml .= '</manialink>';

	return $xml;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_buildKarmaCupsValue ($gamemode) {
	global $aseco, $mk_config, $karma;

	$total_cups = 10;
	$cup_offset = array(
		0.8,
		0.85,
		0.85,
		0.875,
		0.90,
		0.925,
		0.95,
		0.975,
		1.0,
		1.025,
	);

	$cup_gold_amount = 0;
	if ($karma['global']['votes']['karma'] > 0) {
		if ($mk_config['karma_calculation_method'] == 'RASP') {
			$positive = $karma['global']['votes']['fantastic']['count'] + $karma['global']['votes']['beautiful']['count'] + $karma['global']['votes']['good']['count'];
			$cup_gold_amount = round($positive / $karma['global']['votes']['total'] * $total_cups);
		}
		else {
			$cup_gold_amount = intval($karma['global']['votes']['karma'] / $total_cups);
		}
	}
	else if ($karma['local']['votes']['karma'] > 0) {
		if ($mk_config['karma_calculation_method'] == 'RASP') {
			$positive = $karma['local']['votes']['fantastic']['count'] + $karma['local']['votes']['beautiful']['count'] + $karma['local']['votes']['good']['count'];
			$cup_gold_amount = round($positive / $karma['local']['votes']['total'] * $total_cups);
		}
		else {
			$cup_gold_amount = intval($karma['local']['votes']['karma'] / $total_cups);
		}
	}
	$cup_silver = '<quad posn="%x% 0 %z%" sizen="%width% %height%" valign="bottom" image="'. $mk_config['images']['cup_silver'] .'"/>';
	$cup_gold = '<quad posn="%x% 0 %z%" sizen="%width% %height%" valign="bottom" image="'. $mk_config['images']['cup_gold'] .'"/>';
	$cups_result = '';
	for ($i = 0 ; $i < $total_cups ; $i ++) {
		$layer = sprintf("0.%02d", ($i+1));
		$width = 1.1 + ($i / $total_cups) * $cup_offset[$i];
		$height = 1.5 + ($i / $total_cups) * $cup_offset[$i];
		if ($i < $cup_gold_amount) {
			$award = $cup_gold;
		}
		else {
			$award = $cup_silver;
		}
		$cups_result .= str_replace(array('%width%', '%height%', '%x%', '%z%'), array($width, $height, ($cup_offset[$i]*$i), $layer), $award);
	}


	$xml  = '<manialink id="'. $mk_config['manialink_id'] .'05">';
	$xml .= '<frame posn="'. $mk_config['widget']['states'][$gamemode]['pos_x'] .' '. $mk_config['widget']['states'][$gamemode]['pos_y'] .' 10">';

	// Cups
	$xml .= '<frame posn="2.23 -4.95 0.01">';
	$xml .= $cups_result;
	$xml .= '</frame>';

	// Global Value and Votes
	$globalcolor = 'FFFF';
	if ($mk_config['karma_calculation_method'] == 'DEFAULT') {
		if ( ($karma['global']['votes']['karma'] >= 0) && ($karma['global']['votes']['karma'] <= 30) ) {
			$globalcolor = 'D00F';
		}
		else if ( ($karma['global']['votes']['karma'] >= 31) && ($karma['global']['votes']['karma'] <= 60) ) {
			$globalcolor = 'DD0F';
		}
		else if ( ($karma['global']['votes']['karma'] >= 61) && ($karma['global']['votes']['karma'] <= 100) ) {
			$globalcolor = '0D0F';
		}
	}

	// Local Value and Votes
	$localcolor = 'FFFF';
	if ($mk_config['karma_calculation_method'] == 'DEFAULT') {
		if ( ($karma['local']['votes']['karma'] >= 0) && ($karma['local']['votes']['karma'] <= 30) ) {
			$localcolor = 'F00F';
		}
		else if ( ($karma['local']['votes']['karma'] >= 31) && ($karma['local']['votes']['karma'] <= 60) ) {
			$localcolor = 'FF0F';
		}
		else if ( ($karma['local']['votes']['karma'] >= 61) && ($karma['local']['votes']['karma'] <= 100) ) {
			$localcolor = '0F0F';
		}
	}

	// Global values and votes
	$xml .= '<frame posn="2.1 -5.35 0">';
	$xml .= '<quad posn="0 -0.1 1" sizen="0.1 2.85" bgcolor="FFF5"/>';
	$xml .= '<label posn="0.3 -0.1 1" sizen="4 1.1" textsize="1" scale="0.65" textcolor="FFFF" text="GLOBAL"/>';
	$xml .= '<label posn="3.3 0 1" sizen="3 1.4" textsize="1" scale="0.9" textcolor="'. $globalcolor .'" text="$O'. $karma['global']['votes']['karma'] .'"/>';
	$xml .= '<label posn="0.3 -1.3 1" sizen="6.6 1.2" textsize="1" scale="0.85" textcolor="0F3F" text="'. number_format($karma['global']['votes']['total'], 0, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .' '. (($karma['global']['votes']['total'] == 1) ? $mk_config['messages']['karma_vote_singular'] : $mk_config['messages']['karma_vote_plural']) .'"/>';
	$xml .= '</frame>';

	// Local values and votes
	$xml .= '<frame posn="8.75 -5.35 0">';
	$xml .= '<quad posn="0 -0.1 1" sizen="0.1 2.85" bgcolor="FFF5"/>';
	$xml .= '<label posn="0.3 -0.1 1" sizen="4 1.1" textsize="1" scale="0.65" textcolor="FFFF" text="LOCAL "/>';
	$xml .= '<label posn="3 0 1" sizen="3 1.4" textsize="1" scale="0.9" textcolor="'. $localcolor .'" text="$O'. $karma['local']['votes']['karma'] .'"/>';
	$xml .= '<label posn="0.3 -1.3 1" sizen="6.6 1.2" textsize="1" scale="0.85" textcolor="0F3F" text="'. number_format($karma['local']['votes']['total'], 0, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .' '. (($karma['local']['votes']['total'] == 1) ? $mk_config['messages']['karma_vote_singular'] : $mk_config['messages']['karma_vote_plural']) .'"/>';
	$xml .= '</frame>';


	$xml .= '</frame>';
	$xml .= '</manialink>';

	return $xml;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_sendWindow ($login, $window) {
	global $aseco;


	$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= $window;
	$xml .= '</manialinks>';

	$aseco->client->query('SendDisplayManialinkPageToLogin', $login, $xml, 0, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_buildKarmaDetailWindow ($login) {
	global $aseco, $mk_config, $karma;


	// Frame for Previous/Next Buttons
	$buttons = '<frame posn="67.05 -53.2 0">';

	// Reload button
	$buttons .= '<quad posn="1.65 0 0.12" sizen="3.2 3.2" action="'. $mk_config['manialink_id'] .'02" style="Icons64x64_1" substyle="Refresh"/>';

	// Previous button
	$buttons .= '<quad posn="4.95 0 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
	$buttons .= '<quad posn="4.95 0 0.13" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';

	// Next button
	$buttons .= '<quad posn="8.25 0 0.12" sizen="3.2 3.2" action="'. $mk_config['manialink_id'] .'03" style="Icons64x64_1" substyle="ArrowNext"/>';
	$buttons .= '</frame>';

	$xml = str_replace(
		array(
			'%window_title%',
			'%prev_next_buttons%'
		),
		array(
			'ManiaKarma detailed vote statistic',
			$buttons
		),
		$mk_config['Templates']['WINDOW']['HEADER']
	);


	// Build Karma Headline

	// Global Karma
	$color = '$FFF';
	if ($mk_config['karma_calculation_method'] == 'DEFAULT') {
		if ( ($karma['global']['votes']['karma'] >= 0) && ($karma['global']['votes']['karma'] <= 30) ) {
			$color = '$D00';
		}
		else if ( ($karma['global']['votes']['karma'] >= 31) && ($karma['global']['votes']['karma'] <= 60) ) {
			$color = '$DD0';
		}
		else if ( ($karma['global']['votes']['karma'] >= 61) && ($karma['global']['votes']['karma'] <= 100) ) {
			$color = '$0D0';
		}
	}
	$xml .= '<label posn="9.6 -6.5 0.03" sizen="20 0" textsize="2" scale="0.9" text="$FFFGlobal Karma: $O'. $color . $karma['global']['votes']['karma'] .'"/>';
	$xml .= '<label posn="37.6 -6.5 0.03" sizen="20 0" textsize="2" scale="0.9" halign="right" text="$FFF'. number_format($karma['global']['votes']['total'], 0, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .' '. (($karma['global']['votes']['total'] == 1) ? $mk_config['messages']['karma_vote_singular'] : $mk_config['messages']['karma_vote_plural']) .'"/>';

	// Local Karma
	$color = '$FFF';
	if ($mk_config['karma_calculation_method'] == 'DEFAULT') {
		if ( ($karma['local']['votes']['karma'] >= 0) && ($karma['local']['votes']['karma'] <= 30) ) {
			$color = '$F00';
		}
		else if ( ($karma['local']['votes']['karma'] >= 31) && ($karma['local']['votes']['karma'] <= 60) ) {
			$color = '$FF0';
		}
		else if ( ($karma['local']['votes']['karma'] >= 61) && ($karma['local']['votes']['karma'] <= 100) ) {
			$color = '$0F0';
		}
	}
	$xml .= '<label posn="46.6 -6.5 0.03" sizen="20 0" textsize="2" scale="0.9" text="$FFFLocal Karma: $O'. $color . $karma['local']['votes']['karma'] .'"/>';
	$xml .= '<label posn="74.6 -6.5 0.03" sizen="20 0" textsize="2" scale="0.9" halign="right" text="$FFF'. number_format($karma['local']['votes']['total'], 0, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .' '. (($karma['local']['votes']['total'] == 1) ? $mk_config['messages']['karma_vote_singular'] : $mk_config['messages']['karma_vote_plural']) .'"/>';




	// BEGIN: Global vote frame
	$xml .= '<frame posn="2.6 -0.6 0.01">';
	$xml .= '<format textsize="1" textcolor="FFFF"/>';

	$xml .= '<label posn="4.7 -11.35 0.03" sizen="3 0" halign="right" scale="0.8" text="100%"/>';
	$xml .= '<quad posn="5.5 -12 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -12 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<label posn="4.7 -14.35 0.03" sizen="3 0" halign="right" scale="0.8" text="90%"/>';
	$xml .= '<quad posn="5.5 -15 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -15 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<label posn="4.7 -17.35 0.03" sizen="3 0" halign="right" scale="0.8" text="80%"/>';
	$xml .= '<quad posn="5.5 -18 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -18 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<label posn="4.7 -20.35 0.03" sizen="3 0" halign="right" scale="0.8" text="70%"/>';
	$xml .= '<quad posn="5.5 -21 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -21 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<label posn="4.7 -23.35 0.03" sizen="3 0" halign="right" scale="0.8" text="60%"/>';
	$xml .= '<quad posn="5.5 -24 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -24 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<label posn="4.7 -26.35 0.03" sizen="3 0" halign="right" scale="0.8" text="50%"/>';
	$xml .= '<quad posn="5.5 -27 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -27 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<label posn="4.7 -29.35 0.03" sizen="3 0" halign="right" scale="0.8" text="40%"/>';
	$xml .= '<quad posn="5.5 -30 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -30 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<label posn="4.7 -32.35 0.03" sizen="3 0" halign="right" scale="0.8" text="30%"/>';
	$xml .= '<quad posn="5.5 -33 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -33 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<label posn="4.7 -35.35 0.03" sizen="3 0" halign="right" scale="0.8" text="20%"/>';
	$xml .= '<quad posn="5.5 -36 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -36 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<label posn="4.7 -38.35 0.03" sizen="3 0" halign="right" scale="0.8" text="10%"/>';
	$xml .= '<quad posn="5.5 -39 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -39 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<quad posn="7.1 -42 0.04" sizen="28 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7 -12 0.03" sizen="0.1 30" bgcolor="FFFD"/>';

	$height['fantastic']	= (($karma['global']['votes']['fantastic']['percent'] != 0) ? sprintf("%.2f", ($karma['global']['votes']['fantastic']['percent'] / 3.3333333333)) : 0);
	$height['beautiful']	= (($karma['global']['votes']['beautiful']['percent'] != 0) ? sprintf("%.2f", ($karma['global']['votes']['beautiful']['percent'] / 3.3333333333)) : 0);
	$height['good']		= (($karma['global']['votes']['good']['percent'] != 0) ? sprintf("%.2f", ($karma['global']['votes']['good']['percent'] / 3.3333333333)) : 0);
	$height['bad']		= (($karma['global']['votes']['bad']['percent'] != 0) ? sprintf("%.2f", ($karma['global']['votes']['bad']['percent'] / 3.3333333333)) : 0);
	$height['poor']		= (($karma['global']['votes']['poor']['percent'] != 0) ? sprintf("%.2f", ($karma['global']['votes']['poor']['percent'] / 3.3333333333)) : 0);
	$height['waste']	= (($karma['global']['votes']['waste']['percent'] != 0) ? sprintf("%.2f", ($karma['global']['votes']['waste']['percent'] / 3.3333333333)) : 0);

	$xml .= '<label posn="10.2 -'. (40 - $height['fantastic']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['global']['votes']['fantastic']['percent'], 2, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'%"/>';
	$xml .= '<label posn="14.7 -'. (40 - $height['beautiful']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['global']['votes']['beautiful']['percent'], 2, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'%"/>';
	$xml .= '<label posn="19.2 -'. (40 - $height['good']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['global']['votes']['good']['percent'], 2, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'%"/>';

	$xml .= '<quad posn="10 -'. (42 - $height['fantastic']) .' 0.02" sizen="4 '. $height['fantastic'] .'" halign="center" bgcolor="170F"/>';
	$xml .= '<quad posn="14.5 -'. (42 - $height['beautiful']) .' 0.02" sizen="4 '. $height['beautiful'] .'" halign="center" bgcolor="170F"/>';
	$xml .= '<quad posn="19 -'. (42 - $height['good']) .' 0.02" sizen="4 '. $height['good'] .'" halign="center" bgcolor="170F"/>';

	$xml .= '<quad posn="10 -'. (42 - $height['fantastic']) .' 0.03" sizen="4 '. $height['fantastic'] .'" halign="center" style="BgRaceScore2" substyle="CupFinisher"/>';
	$xml .= '<quad posn="14.5 -'. (42 - $height['beautiful']) .' 0.03" sizen="4 '. $height['beautiful'] .'" halign="center" style="BgRaceScore2" substyle="CupFinisher"/>';
	$xml .= '<quad posn="19 -'. (42 - $height['good']) .' 0.03" sizen="4 '. $height['good'] .'" halign="center" style="BgRaceScore2" substyle="CupFinisher"/>';

	$xml .= '<quad posn="10 -'. (42 - $height['fantastic']) .' 0.035" sizen="4.4 '. (($height['fantastic'] < 3) ? $height['fantastic'] : 3) .'" halign="center" style="BgsPlayerCard" substyle="BgRacePlayerLine"/>';
	$xml .= '<quad posn="14.5 -'. (42 - $height['beautiful']) .' 0.035" sizen="4.4 '. (($height['beautiful'] < 3) ? $height['beautiful'] : 3) .'" halign="center" style="BgsPlayerCard" substyle="BgRacePlayerLine"/>';
	$xml .= '<quad posn="19 -'. (42 - $height['good']) .' 0.035" sizen="4.4 '. (($height['good'] < 3) ? $height['good'] : 3) .'" halign="center" style="BgsPlayerCard" substyle="BgRacePlayerLine"/>';

	$xml .= '<label posn="23.7 -'. (40 - $height['bad']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['global']['votes']['bad']['percent'], 2, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'%"/>';
	$xml .= '<label posn="28.2 -'. (40 - $height['poor']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['global']['votes']['poor']['percent'], 2, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'%"/>';
	$xml .= '<label posn="32.7 -'. (40 - $height['waste']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['global']['votes']['waste']['percent'], 2, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'%"/>';

	$xml .= '<quad posn="23.5 -'. (42 - $height['bad']) .' 0.02" sizen="4 '. $height['bad'] .'" halign="center" bgcolor="701F"/>';
	$xml .= '<quad posn="28 -'. (42 - $height['poor']) .' 0.02" sizen="4 '. $height['poor'] .'" halign="center" bgcolor="701F"/>';
	$xml .= '<quad posn="32.5 -'. (42 - $height['waste']) .' 0.02" sizen="4 '. $height['waste'] .'" halign="center" bgcolor="701F"/>';

	$xml .= '<quad posn="23.5 -'. (42 - $height['bad']) .' 0.03" sizen="4 '. $height['bad'] .'" halign="center" style="BgRaceScore2" substyle="CupPotentialFinisher"/>';
	$xml .= '<quad posn="28 -'. (42 - $height['poor']) .' 0.03" sizen="4 '. $height['poor'] .'" halign="center" style="BgRaceScore2" substyle="CupPotentialFinisher"/>';
	$xml .= '<quad posn="32.5 -'. (42 - $height['waste']) .' 0.03" sizen="4 '. $height['waste'] .'" halign="center" style="BgRaceScore2" substyle="CupPotentialFinisher"/>';

	$xml .= '<quad posn="23.5 -'. (42 - $height['bad']) .' 0.035" sizen="4.4 '. (($height['bad'] < 3) ? $height['bad'] : 3) .'" halign="center" style="BgsPlayerCard" substyle="BgRacePlayerLine"/>';
	$xml .= '<quad posn="28 -'. (42 - $height['poor']) .' 0.035" sizen="4.4 '. (($height['poor'] < 3) ? $height['poor'] : 3) .'" halign="center" style="BgsPlayerCard" substyle="BgRacePlayerLine"/>';
	$xml .= '<quad posn="32.5 -'. (42 - $height['waste']) .' 0.035" sizen="4.4 '. (($height['waste'] < 3) ? $height['waste'] : 3) .'" halign="center" style="BgsPlayerCard" substyle="BgRacePlayerLine"/>';


	$xml .= '<label posn="3 -43 0.03" sizen="6 0" textcolor="FFFF" text="Votes:"/>';

	$xml .= '<label posn="10 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['global']['votes']['fantastic']['count'], 0, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="14.5 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['global']['votes']['beautiful']['count'], 0, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="19 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['global']['votes']['good']['count'], 0, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="23.5 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['global']['votes']['bad']['count'], 0, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="28 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['global']['votes']['poor']['count'], 0, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="32.5 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['global']['votes']['waste']['count'], 0, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'"/>';

	$xml .= '<label posn="10 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$6C0'. ucfirst($mk_config['messages']['karma_fantastic']) .'"/>';
	$xml .= '<label posn="14.5 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$6C0'. ucfirst($mk_config['messages']['karma_beautiful']) .'"/>';
	$xml .= '<label posn="19 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$6C0'. ucfirst($mk_config['messages']['karma_good']) .'"/>';
	$xml .= '<label posn="23.5 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$F02'. ucfirst($mk_config['messages']['karma_bad']) .'"/>';
	$xml .= '<label posn="28 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$F02'. ucfirst($mk_config['messages']['karma_poor']) .'"/>';
	$xml .= '<label posn="32.5 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$F02'. ucfirst($mk_config['messages']['karma_waste']) .'"/>';

	$xml .= '<label posn="10 -46.05 0.03" sizen="10 0" halign="center" text="$6C0+++"/>';
	$xml .= '<label posn="14.5 -46.05 0.03" sizen="10 0" halign="center" text="$6C0++"/>';
	$xml .= '<label posn="19 -46.05 0.03" sizen="10 0" halign="center" text="$6C0+"/>';
	$xml .= '<label posn="23.5 -46.05 0.03" sizen="10 0" halign="center" text="$F02-"/>';
	$xml .= '<label posn="28 -46.05 0.03" sizen="10 0" halign="center" text="$F02--"/>';
	$xml .= '<label posn="32.5 -46.05 0.03" sizen="10 0" halign="center" text="$F02---"/>';

	$xml .= '</frame>';
	// END: Global vote frame





	// BEGIN: Local vote frame
	$xml .= '<frame posn="39.6 -0.6 0.01">';
	$xml .= '<format textsize="1" textcolor="FFFF"/>';

	$xml .= '<label posn="4.7 -11.35 0.03" sizen="3 0" halign="right" scale="0.8" text="100%"/>';
	$xml .= '<quad posn="5.5 -12 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -12 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<label posn="4.7 -14.35 0.03" sizen="3 0" halign="right" scale="0.8" text="90%"/>';
	$xml .= '<quad posn="5.5 -15 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -15 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<label posn="4.7 -17.35 0.03" sizen="3 0" halign="right" scale="0.8" text="80%"/>';
	$xml .= '<quad posn="5.5 -18 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -18 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<label posn="4.7 -20.35 0.03" sizen="3 0" halign="right" scale="0.8" text="70%"/>';
	$xml .= '<quad posn="5.5 -21 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -21 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<label posn="4.7 -23.35 0.03" sizen="3 0" halign="right" scale="0.8" text="60%"/>';
	$xml .= '<quad posn="5.5 -24 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -24 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<label posn="4.7 -26.35 0.03" sizen="3 0" halign="right" scale="0.8" text="50%"/>';
	$xml .= '<quad posn="5.5 -27 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -27 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<label posn="4.7 -29.35 0.03" sizen="3 0" halign="right" scale="0.8" text="40%"/>';
	$xml .= '<quad posn="5.5 -30 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -30 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<label posn="4.7 -32.35 0.03" sizen="3 0" halign="right" scale="0.8" text="30%"/>';
	$xml .= '<quad posn="5.5 -33 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -33 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<label posn="4.7 -35.35 0.03" sizen="3 0" halign="right" scale="0.8" text="20%"/>';
	$xml .= '<quad posn="5.5 -36 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -36 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<label posn="4.7 -38.35 0.03" sizen="3 0" halign="right" scale="0.8" text="10%"/>';
	$xml .= '<quad posn="5.5 -39 0.04" sizen="1.5 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7.1 -39 0.04" sizen="28 0.1" bgcolor="FFF5"/>';

	$xml .= '<quad posn="7.1 -42 0.04" sizen="28 0.1" bgcolor="FFFD"/>';
	$xml .= '<quad posn="7 -12 0.03" sizen="0.1 30" bgcolor="FFFD"/>';

	$height['fantastic']	= (($karma['local']['votes']['fantastic']['percent'] != 0) ? sprintf("%.2f", ($karma['local']['votes']['fantastic']['percent'] / 3.3333333333)) : 0);
	$height['beautiful']	= (($karma['local']['votes']['beautiful']['percent'] != 0) ? sprintf("%.2f", ($karma['local']['votes']['beautiful']['percent'] / 3.3333333333)) : 0);
	$height['good']		= (($karma['local']['votes']['good']['percent'] != 0) ? sprintf("%.2f", ($karma['local']['votes']['good']['percent'] / 3.3333333333)) : 0);
	$height['bad']		= (($karma['local']['votes']['bad']['percent'] != 0) ? sprintf("%.2f", ($karma['local']['votes']['bad']['percent'] / 3.3333333333)) : 0);
	$height['poor']		= (($karma['local']['votes']['poor']['percent'] != 0) ? sprintf("%.2f", ($karma['local']['votes']['poor']['percent'] / 3.3333333333)) : 0);
	$height['waste']	= (($karma['local']['votes']['waste']['percent'] != 0) ? sprintf("%.2f", ($karma['local']['votes']['waste']['percent'] / 3.3333333333)) : 0);

	$xml .= '<label posn="10.2 -'. (40 - $height['fantastic']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['local']['votes']['fantastic']['percent'], 2, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'%"/>';
	$xml .= '<label posn="14.7 -'. (40 - $height['beautiful']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['local']['votes']['beautiful']['percent'], 2, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'%"/>';
	$xml .= '<label posn="19.2 -'. (40 - $height['good']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['local']['votes']['good']['percent'], 2, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'%"/>';

	$xml .= '<quad posn="10 -'. (42 - $height['fantastic']) .' 0.02" sizen="4 '. $height['fantastic'] .'" halign="center" bgcolor="170F"/>';
	$xml .= '<quad posn="14.5 -'. (42 - $height['beautiful']) .' 0.02" sizen="4 '. $height['beautiful'] .'" halign="center" bgcolor="170F"/>';
	$xml .= '<quad posn="19 -'. (42 - $height['good']) .' 0.02" sizen="4 '. $height['good'] .'" halign="center" bgcolor="170F"/>';

	$xml .= '<quad posn="10 -'. (42 - $height['fantastic']) .' 0.03" sizen="4 '. $height['fantastic'] .'" halign="center" style="BgRaceScore2" substyle="CupFinisher"/>';
	$xml .= '<quad posn="14.5 -'. (42 - $height['beautiful']) .' 0.03" sizen="4 '. $height['beautiful'] .'" halign="center" style="BgRaceScore2" substyle="CupFinisher"/>';
	$xml .= '<quad posn="19 -'. (42 - $height['good']) .' 0.03" sizen="4 '. $height['good'] .'" halign="center" style="BgRaceScore2" substyle="CupFinisher"/>';

	$xml .= '<quad posn="10 -'. (42 - $height['fantastic']) .' 0.035" sizen="4.4 '. (($height['fantastic'] < 3) ? $height['fantastic'] : 3) .'" halign="center" style="BgsPlayerCard" substyle="BgRacePlayerLine"/>';
	$xml .= '<quad posn="14.5 -'. (42 - $height['beautiful']) .' 0.035" sizen="4.4 '. (($height['beautiful'] < 3) ? $height['beautiful'] : 3) .'" halign="center" style="BgsPlayerCard" substyle="BgRacePlayerLine"/>';
	$xml .= '<quad posn="19 -'. (42 - $height['good']) .' 0.035" sizen="4.4 '. (($height['good'] < 3) ? $height['good'] : 3) .'" halign="center" style="BgsPlayerCard" substyle="BgRacePlayerLine"/>';

	$xml .= '<label posn="23.7 -'. (40 - $height['bad']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['local']['votes']['bad']['percent'], 2, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'%"/>';
	$xml .= '<label posn="28.2 -'. (40 - $height['poor']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['local']['votes']['poor']['percent'], 2, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'%"/>';
	$xml .= '<label posn="32.7 -'. (40 - $height['waste']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['local']['votes']['waste']['percent'], 2, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'%"/>';

	$xml .= '<quad posn="23.5 -'. (42 - $height['bad']) .' 0.02" sizen="4 '. $height['bad'] .'" halign="center" bgcolor="701F"/>';
	$xml .= '<quad posn="28 -'. (42 - $height['poor']) .' 0.02" sizen="4 '. $height['poor'] .'" halign="center" bgcolor="701F"/>';
	$xml .= '<quad posn="32.5 -'. (42 - $height['waste']) .' 0.02" sizen="4 '. $height['waste'] .'" halign="center" bgcolor="701F"/>';

	$xml .= '<quad posn="23.5 -'. (42 - $height['bad']) .' 0.03" sizen="4 '. $height['bad'] .'" halign="center" style="BgRaceScore2" substyle="CupPotentialFinisher"/>';
	$xml .= '<quad posn="28 -'. (42 - $height['poor']) .' 0.03" sizen="4 '. $height['poor'] .'" halign="center" style="BgRaceScore2" substyle="CupPotentialFinisher"/>';
	$xml .= '<quad posn="32.5 -'. (42 - $height['waste']) .' 0.03" sizen="4 '. $height['waste'] .'" halign="center" style="BgRaceScore2" substyle="CupPotentialFinisher"/>';

	$xml .= '<quad posn="23.5 -'. (42 - $height['bad']) .' 0.035" sizen="4.4 '. (($height['bad'] < 3) ? $height['bad'] : 3) .'" halign="center" style="BgsPlayerCard" substyle="BgRacePlayerLine"/>';
	$xml .= '<quad posn="28 -'. (42 - $height['poor']) .' 0.035" sizen="4.4 '. (($height['poor'] < 3) ? $height['poor'] : 3) .'" halign="center" style="BgsPlayerCard" substyle="BgRacePlayerLine"/>';
	$xml .= '<quad posn="32.5 -'. (42 - $height['waste']) .' 0.035" sizen="4.4 '. (($height['waste'] < 3) ? $height['waste'] : 3) .'" halign="center" style="BgsPlayerCard" substyle="BgRacePlayerLine"/>';


	$xml .= '<label posn="3 -43 0.03" sizen="6 0" textcolor="FFFF" text="Votes:"/>';

	$xml .= '<label posn="10 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['local']['votes']['fantastic']['count'], 0, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="14.5 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['local']['votes']['beautiful']['count'], 0, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="19 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['local']['votes']['good']['count'], 0, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="23.5 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['local']['votes']['bad']['count'], 0, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="28 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['local']['votes']['poor']['count'], 0, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="32.5 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['local']['votes']['waste']['count'], 0, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) .'"/>';

	$xml .= '<label posn="10 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$6C0'. ucfirst($mk_config['messages']['karma_fantastic']) .'"/>';
	$xml .= '<label posn="14.5 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$6C0'. ucfirst($mk_config['messages']['karma_beautiful']) .'"/>';
	$xml .= '<label posn="19 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$6C0'. ucfirst($mk_config['messages']['karma_good']) .'"/>';
	$xml .= '<label posn="23.5 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$F02'. ucfirst($mk_config['messages']['karma_bad']) .'"/>';
	$xml .= '<label posn="28 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$F02'. ucfirst($mk_config['messages']['karma_poor']) .'"/>';
	$xml .= '<label posn="32.5 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$F02'. ucfirst($mk_config['messages']['karma_waste']) .'"/>';

	$xml .= '<label posn="10 -46.05 0.03" sizen="10 0" halign="center" text="$6C0+++"/>';
	$xml .= '<label posn="14.5 -46.05 0.03" sizen="10 0" halign="center" text="$6C0++"/>';
	$xml .= '<label posn="19 -46.05 0.03" sizen="10 0" halign="center" text="$6C0+"/>';
	$xml .= '<label posn="23.5 -46.05 0.03" sizen="10 0" halign="center" text="$F02-"/>';
	$xml .= '<label posn="28 -46.05 0.03" sizen="10 0" halign="center" text="$F02--"/>';
	$xml .= '<label posn="32.5 -46.05 0.03" sizen="10 0" halign="center" text="$F02---"/>';

	$xml .= '</frame>';
	// END: Local vote frame



	// BEGIN: Place Player marker, if Player has already voted
	if ( isset($karma['global']['players'][$login]) ) {
		// BEGIN: Global vote frame
		$xml .= '<frame posn="2.6 -48.5 0.02">';
		if ($karma['global']['players'][$login]['vote'] == 3) {
			$xml .= '<quad posn="10 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
			$xml .= '<label posn="10 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
		}
		else if ($karma['global']['players'][$login]['vote'] == 2) {
			$xml .= '<quad posn="14.5 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
			$xml .= '<label posn="14.5 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
		}
		else if ($karma['global']['players'][$login]['vote'] == 1) {
			$xml .= '<quad posn="19 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
			$xml .= '<label posn="19 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
		}
		else if ($karma['global']['players'][$login]['vote'] == -1) {
			$xml .= '<quad posn="23.5 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
			$xml .= '<label posn="23.5 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
		}
		else if ($karma['global']['players'][$login]['vote'] == -2) {
			$xml .= '<quad posn="28 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
			$xml .= '<label posn="28 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
		}
		else if ($karma['global']['players'][$login]['vote'] == -3) {
			$xml .= '<quad posn="32.5 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
			$xml .= '<label posn="32.5 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
		}
		$xml .= '</frame>';
		// END: Global vote frame
	}

	if ( isset($karma['local']['players'][$login]) ) {
		// BEGIN: Local vote frame
		$xml .= '<frame posn="39.6 -48.5 0.02">';
		if ($karma['local']['players'][$login]['vote'] == 3) {
			$xml .= '<quad posn="10 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
			$xml .= '<label posn="10 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
		}
		else if ($karma['local']['players'][$login]['vote'] == 2) {
			$xml .= '<quad posn="14.5 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
			$xml .= '<label posn="14.5 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
		}
		else if ($karma['local']['players'][$login]['vote'] == 1) {
			$xml .= '<quad posn="19 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
			$xml .= '<label posn="19 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
		}
		else if ($karma['local']['players'][$login]['vote'] == -1) {
			$xml .= '<quad posn="23.5 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
			$xml .= '<label posn="23.5 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
		}
		else if ($karma['local']['players'][$login]['vote'] == -2) {
			$xml .= '<quad posn="28 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
			$xml .= '<label posn="28 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
		}
		else if ($karma['local']['players'][$login]['vote'] == -3) {
			$xml .= '<quad posn="32.5 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
			$xml .= '<label posn="32.5 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
		}
		$xml .= '</frame>';
		// END: Local vote frame
	}
	// END: Place Player marker


	// Website-Link Frame
	$xml .= '<frame posn="28.6 -53.35 0.10">';
	$xml .= '<quad posn="0 0 0.01" sizen="21.5 2.5" url="http://'. $mk_config['urls']['website'] .'/goto?uid='. $karma['data']['uid'] .'&amp;env='. $karma['data']['env'] .'&amp;game='. $aseco->server->game .'" style="Bgs1" substyle="BgIconBorder"/>';
	$xml .= '<label posn="1.5 -0.65 0.01" sizen="30 1" textsize="1" scale="0.8" textcolor="000F" text="MORE INFO ON MANIA-KARMA.COM"/>';
	$xml .= '</frame>';

	$xml .= $mk_config['Templates']['WINDOW']['FOOTER'];
	return $xml;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_buildWhoKarmaWindow () {
	global $aseco, $mk_config, $karma;


	// Frame for Previous/Next Buttons
	$buttons = '<frame posn="67.05 -53.2 0">';

	// Reload button
	$buttons .= '<quad posn="1.65 0 0.12" sizen="3.2 3.2" action="'. $mk_config['manialink_id'] .'03" style="Icons64x64_1" substyle="Refresh"/>';

	// Previous button
	$buttons .= '<quad posn="4.95 0 0.12" sizen="3.2 3.2" action="'. $mk_config['manialink_id'] .'02" style="Icons64x64_1" substyle="ArrowPrev"/>';

	// Next button
	$buttons .= '<quad posn="8.25 0 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
	$buttons .= '<quad posn="8.25 0 0.13" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
	$buttons .= '</frame>';

	$xml = str_replace(
		array(
			'%window_title%',
			'%prev_next_buttons%'
		),
		array(
			'ManiaKarma who voted what',
			$buttons
		),
		$mk_config['Templates']['WINDOW']['HEADER']
	);


	$xml .= '<frame posn="2.6 -6.5 0.05">';
	$xml .= '<format textsize="1" textcolor="FFFF"/>';

	$xml .= '<quad posn="0 0.8 0.02" sizen="17.75 46.88" style="BgsPlayerCard" substyle="BgRacePlayerName"/>';
	$xml .= '<quad posn="19.05 0.8 0.02" sizen="17.75 46.88" style="BgsPlayerCard" substyle="BgRacePlayerName"/>';
	$xml .= '<quad posn="38.1 0.8 0.02" sizen="17.75 46.88" style="BgsPlayerCard" substyle="BgRacePlayerName"/>';
	$xml .= '<quad posn="57.15 0.8 0.02" sizen="17.75 46.88" style="BgsPlayerCard" substyle="BgRacePlayerName"/>';

	$players = array();
	foreach ($aseco->server->players->player_list as $player) {
		$players[] = array(
			'id'		=> $player->id,
			'nickname'	=> mk_handleSpecialChars($player->nickname),
			'vote'		=> (($karma['global']['players'][$player->login]['vote'] == 0) ? -4 : $karma['global']['players'][$player->login]['vote']),
		);
	}

	// Build the arrays for sorting
	$votes = array();
	$ids = array();
	foreach ($players as $key => $row) {
		$votes[$key]	= $row['vote'];
		$ids[$key]	= $row['id'];
	}

	// Sort by Votes and PlayerId
	array_multisort($votes, SORT_NUMERIC, SORT_DESC, $ids, SORT_NUMERIC, SORT_ASC, $players);
	unset($ids, $votes);


	$vote_index = array(
		3	=> '+++',
		2	=> '++',
		1	=> '+',
		-1	=> '-',
		-2	=> '--',
		-3	=> '---',
		-4	=> 'none',	// Normaly 0, but that was bad for sorting...
	);


	$rank = 1;
	$line = 0;
	$offset = 0;
	foreach ($players as $player) {
		$xml .= '<quad posn="'. ($offset + 0.4) .' '. (((1.83 * $line - 0.2) > 0) ? -(1.83 * $line - 0.2) : 0.2) .' 0.03" sizen="16.95 1.83" style="BgsPlayerCard" substyle="BgCardSystem"/>';
		$xml .= '<label posn="'. (1 + $offset) .' -'. (1.83 * $line) .' 0.04" sizen="14 1.7" scale="0.9" text="'. $player['nickname'] .'"/>';
		$xml .= '<label posn="'. (16.6 + $offset) .' -'. (1.83 * $line) .' 0.04" sizen="3 1.7" halign="right" scale="0.9" textcolor="FFFF" text="'. $vote_index[$player['vote']] .'"/>';

		$line ++;
		$rank ++;

		// Reset lines
		if ($line >= 25) {
			$offset += 19.05;
			$line = 0;
		}

		// Display max. 100 entries, count start from 1
		if ($rank >= 101) {
			break;
		}
	}
	unset($item);
	$xml .= '</frame>';


	// Website-Link Frame
	$xml .= '<frame posn="28.6 -53.35 0.10">';
	$xml .= '<quad posn="0 0 0.01" sizen="21.5 2.5" url="http://'. $mk_config['urls']['website'] .'/goto?uid='. $karma['data']['uid'] .'&amp;env='. $karma['data']['env'] .'&amp;game='. $aseco->server->game .'" style="Bgs1" substyle="BgIconBorder"/>';
	$xml .= '<label posn="1.5 -0.65 0.01" sizen="30 1" textsize="1" scale="0.8" textcolor="000F" text="MORE INFO ON MANIA-KARMA.COM"/>';
	$xml .= '</frame>';

	$xml .= $mk_config['Templates']['WINDOW']['FOOTER'];
	return $xml;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_buildPlayerVoteMarker ($player, $gamemode) {
	global $mk_config, $karma;


	// Bail out if Player is already disconnected
	if ( !isset($player->login) ) {
		return;
	}

	// Build the colors for Player vote marker (RGBA)
	$preset = array();
	$preset['fantastic']['bgcolor']		= '0000';
	$preset['fantastic']['action']		= 17;
	$preset['beautiful']['bgcolor']		= '0000';
	$preset['beautiful']['action']		= 17;
	$preset['good']['bgcolor']		= '0000';
	$preset['good']['action']		= 17;
	$preset['bad']['bgcolor']		= '0000';
	$preset['bad']['action']		= 17;
	$preset['poor']['bgcolor']		= '0000';
	$preset['poor']['action']		= 17;
	$preset['waste']['bgcolor']		= '0000';
	$preset['waste']['action']		= 17;

	// Fantastic
	if ($karma['global']['players'][$player->login]['vote'] == 3) {
		$preset['fantastic']['bgcolor'] = $mk_config['widget']['buttons']['bg_disabled'];
		$preset['fantastic']['action'] = 18;
	}
	else if ( ($karma['global']['players'][$player->login]['vote'] == 0) && (($mk_config['require_finish'] > 0) && ($player->data['ManiaKarma']['FinishedMapCount'] < $mk_config['require_finish'])) ) {
		$preset['fantastic']['bgcolor'] = $mk_config['widget']['buttons']['bg_vote'];
	}

	// Beautiful
	if ($karma['global']['players'][$player->login]['vote'] == 2) {
		$preset['beautiful']['bgcolor'] = $mk_config['widget']['buttons']['bg_disabled'];
		$preset['beautiful']['action'] = 18;
	}
	else if ( ($karma['global']['players'][$player->login]['vote'] == 0) && (($mk_config['require_finish'] > 0) && ($player->data['ManiaKarma']['FinishedMapCount'] < $mk_config['require_finish'])) ) {
		$preset['beautiful']['bgcolor'] = $mk_config['widget']['buttons']['bg_vote'];
	}

	// Good
	if ($karma['global']['players'][$player->login]['vote'] == 1) {
		$preset['good']['bgcolor'] = $mk_config['widget']['buttons']['bg_disabled'];
		$preset['good']['action'] = 18;
	}
	else if ( ($karma['global']['players'][$player->login]['vote'] == 0) && (($mk_config['require_finish'] > 0) && ($player->data['ManiaKarma']['FinishedMapCount'] < $mk_config['require_finish'])) ) {
		$preset['good']['bgcolor'] = $mk_config['widget']['buttons']['bg_vote'];
	}


	// Bad
	if ($karma['global']['players'][$player->login]['vote'] == -1) {
		$preset['bad']['bgcolor'] = $mk_config['widget']['buttons']['bg_disabled'];
		$preset['bad']['action'] = 18;
	}
	else if ( ($karma['global']['players'][$player->login]['vote'] == 0) && (($mk_config['require_finish'] > 0) && ($player->data['ManiaKarma']['FinishedMapCount'] < $mk_config['require_finish'])) ) {
		$preset['bad']['bgcolor'] = $mk_config['widget']['buttons']['bg_vote'];
	}

	// Poor
	if ($karma['global']['players'][$player->login]['vote'] == -2) {
		$preset['poor']['bgcolor'] = $mk_config['widget']['buttons']['bg_disabled'];
		$preset['poor']['action'] = 18;
	}
	else if ( ($karma['global']['players'][$player->login]['vote'] == 0) && (($mk_config['require_finish'] > 0) && ($player->data['ManiaKarma']['FinishedMapCount'] < $mk_config['require_finish'])) ) {
		$preset['poor']['bgcolor'] = $mk_config['widget']['buttons']['bg_vote'];
	}

	// Waste
	if ($karma['global']['players'][$player->login]['vote'] == -3) {
		$preset['waste']['bgcolor'] = $mk_config['widget']['buttons']['bg_disabled'];
		$preset['waste']['action'] = 18;
	}
	else if ( ($karma['global']['players'][$player->login]['vote'] == 0) && (($mk_config['require_finish'] > 0) && ($player->data['ManiaKarma']['FinishedMapCount'] < $mk_config['require_finish'])) ) {
		$preset['waste']['bgcolor'] = $mk_config['widget']['buttons']['bg_vote'];
	}


	// Init Marker
	$marker = false;


	// Button +++
	if ($preset['fantastic']['bgcolor'] != '0000') {
		// Mark current vote or disable the vote possibility
		$marker .= '<frame posn="1.83 -8.5 1">';
		$marker .= '<quad posn="0.2 -0.08 0.3" sizen="1.8 1.4" action="'. $mk_config['manialink_id'] . $preset['fantastic']['action'] .'" bgcolor="'. $preset['fantastic']['bgcolor'] .'"/>';
		$marker .= '</frame>';
	}

	// Button ++
	if ($preset['beautiful']['bgcolor'] != '0000') {
		// Mark current vote or disable the vote possibility
		$marker .= '<frame posn="3.83 -8.5 1">';
		$marker .= '<quad posn="0.2 -0.08 0.3" sizen="1.8 1.4" action="'. $mk_config['manialink_id'] . $preset['beautiful']['action'] .'" bgcolor="'. $preset['beautiful']['bgcolor'] .'"/>';
		$marker .= '</frame>';
	}

	// Button +
	if ($preset['good']['bgcolor'] != '0000') {
		// Mark current vote or disable the vote possibility
		$marker .= '<frame posn="5.83 -8.5 1">';
		$marker .= '<quad posn="0.2 -0.08 0.3" sizen="1.8 1.4" action="'. $mk_config['manialink_id'] . $preset['good']['action'] .'" bgcolor="'. $preset['good']['bgcolor'] .'"/>';
		$marker .= '</frame>';
	}

	// Button -
	if ($preset['bad']['bgcolor'] != '0000') {
		// Mark current vote or disable the vote possibility
		$marker .= '<frame posn="7.83 -8.5 1">';
		$marker .= '<quad posn="0.2 -0.08 0.3" sizen="1.8 1.4" action="'. $mk_config['manialink_id'] . $preset['bad']['action'] .'" bgcolor="'. $preset['bad']['bgcolor'] .'"/>';
		$marker .= '</frame>';
	}

	// Button --
	if ($preset['poor']['bgcolor'] != '0000') {
		// Mark current vote or disable the vote possibility
		$marker .= '<frame posn="9.83 -8.5 1">';
		$marker .= '<quad posn="0.2 -0.08 0.3" sizen="1.8 1.4" action="'. $mk_config['manialink_id'] . $preset['poor']['action'] .'" bgcolor="'. $preset['poor']['bgcolor'] .'"/>';
		$marker .= '</frame>';
	}

	// Button ---
	if ($preset['waste']['bgcolor'] != '0000') {
		// Mark current vote or disable the vote possibility
		$marker .= '<frame posn="11.83 -8.5 1">';
		$marker .= '<quad posn="0.2 -0.08 0.3" sizen="1.8 1.4" action="'. $mk_config['manialink_id'] . $preset['waste']['action'] .'" bgcolor="'. $preset['waste']['bgcolor'] .'"/>';
		$marker .= '</frame>';
	}


	$xml = '<manialink id="'. $mk_config['manialink_id'] .'04">';

	// Send/Build MainWidget Frame only when required, if empty then the player can vote
	if ($marker != false) {
		$xml .= '<frame posn="'. $mk_config['widget']['states'][$gamemode]['pos_x'] .' '. $mk_config['widget']['states'][$gamemode]['pos_y'] .' 10">';
		$xml .= $marker;
		$xml .= '</frame>';
	}
	$xml .= '</manialink>';

	return $xml;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_sendConnectionStatus ($status = true, $gamemode) {
	global $aseco, $mk_config;


	$xml = '<manialink id="'. $mk_config['manialink_id'] .'06">';
	if ($status === false) {
		mk_sendLoadingIndicator(false, $gamemode);
		$xml .= '<frame posn="'. $mk_config['widget']['states'][$gamemode]['pos_x'] .' '. $mk_config['widget']['states'][$gamemode]['pos_y'] .' 20">';
		$xml .= '<quad posn="0.5 -5.2 0.9" sizen="1.4 1.4" style="Icons128x128_1" substyle="Multiplayer"/>';
//		$xml .= '<label posn="-0.4 -5.2 0.9" sizen="20 1.4" textsize="1" halign="right" scale="0.7" textcolor="FFF8" text="Lost connection, retrying later."/>';
		$xml .= '</frame>';
	}
	$xml .= '</manialink>';

	$aseco->client->query('SendDisplayManialinkPage', $xml, 0, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_sendLoadingIndicator ($status = true, $gamemode) {
	global $aseco, $mk_config;


	$xml = '<manialink id="'. $mk_config['manialink_id'] .'07">';
	if ($status === true) {
		$xml .= '<frame posn="'. $mk_config['widget']['states'][$gamemode]['pos_x'] .' '. $mk_config['widget']['states'][$gamemode]['pos_y'] .' 20">';
		$xml .= '<quad posn="0.5 -5.2 0.9" sizen="1.4 1.4" image="'. $mk_config['images']['progress_indicator'] .'"/>';
		$xml .= '</frame>';
	}
	$xml .= '</manialink>';

	$aseco->client->query('SendDisplayManialinkPage', $xml, 0, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_handlePlayerVote ($player, $vote) {
	global $aseco, $mk_config, $karma;


	// Do nothing at Startup!!
	if ($aseco->startup_phase == true) {
		return;
	}


	// Close reminder-window if there is one for this Player
	mk_closeReminderWindow($player);


	// Register vote for plugin.mxkarma.php
	if ( function_exists('MxKarma_handleVote') ) {
		$mxValue = $vote;
		if ($mxValue < 0)
			$mxValue += 1;
		$mxValue += 2;
		if ($mxValue * 20 >= 0 && $mxValue * 20 <= 100) {
			MxKarma_handleVote($aseco, $player->login, $mxValue * 20);
		}
	}


	// Check if finishes are required
	if ( ($mk_config['require_finish'] > 0) && ($mk_config['require_finish'] > $player->data['ManiaKarma']['FinishedMapCount']) ) {

		// Show chat message
		$message = formatText($mk_config['messages']['karma_require_finish'],
					$mk_config['require_finish'],
					($mk_config['require_finish'] == 1 ? '' : 's')
		);
		if ( ($mk_config['messages_in_window'] == true) && (function_exists('send_window_message')) && ($mk_config['widget']['current_state'] != 7) ) {
			send_window_message($aseco, $message, $player);
		}
		else {
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		}
		return;
	}


	// $vote is "0" when the Player clicks on a red (no vote possible) button, bail out now.
	if ($vote == 0) {
		return;
	}


	// Before call the remote API, check if player has the same already voted
	if ($karma['global']['players'][$player->login]['vote'] == $vote) {
		// Same vote, does not need to call remote API, bail out immediately
		$message = $mk_config['messages']['karma_voted'];
		if ( ($mk_config['messages_in_window'] == true) && (function_exists('send_window_message')) ) {
			send_window_message($aseco, $message, $player);
		}
		else {
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		}
		return;
	}


	// Store the new vote for send them later with "MultiVote",
	// but only if the global is different to the current vote
	if ( (isset($karma['global']['players'][$player->login]['vote'])) && ($karma['global']['players'][$player->login]['vote'] != $vote) ) {
		$karma['new']['players'][$player->login] = $vote;
	}


//	// Check if connection was failed
//	if ($mk_config['retrytime'] == 0) {
		// Remove the previous global Vote
		if ( isset($karma['global']['players'][$player->login]['vote']) ) {
			switch ($karma['global']['players'][$player->login]['vote']) {
				case 3:
					$karma['global']['votes']['fantastic']['count'] -= 1;
					break;
				case 2:
					$karma['global']['votes']['beautiful']['count'] -= 1;
					break;
				case 1:
					$karma['global']['votes']['good']['count'] -= 1;
					break;
				case -1:
					$karma['global']['votes']['bad']['count'] -= 1;
					break;
				case -2:
					$karma['global']['votes']['poor']['count'] -= 1;
					break;
				case -3:
					$karma['global']['votes']['waste']['count'] -= 1;
					break;
				default:
					// Do nothing
					break;
			}

			// Store previous vote
			$karma['global']['players'][$player->login]['previous'] = $karma['global']['players'][$player->login]['vote'];
		}
		else {
			// Set state "no previous vote"
			$karma['global']['players'][$player->login]['previous'] = 0;
		}
//	}

	// Remove the previous local Vote
	if ( isset($karma['local']['players'][$player->login]['vote']) ) {
		switch ($karma['local']['players'][$player->login]['vote']) {
			case 3:
				$karma['local']['votes']['fantastic']['count'] -= 1;
				break;
			case 2:
				$karma['local']['votes']['beautiful']['count'] -= 1;
				break;
			case 1:
				$karma['local']['votes']['good']['count'] -= 1;
				break;
			case -1:
				$karma['local']['votes']['bad']['count'] -= 1;
				break;
			case -2:
				$karma['local']['votes']['poor']['count'] -= 1;
				break;
			case -3:
				$karma['local']['votes']['waste']['count'] -= 1;
				break;
			default:
				// Do nothing
				break;
		}
	}

//	// Check if connection was failed, and store the current Vote (only local or both)
//	if ($mk_config['retrytime'] == 0) {
		$karma['global']['players'][$player->login]['vote'] = $vote;
//	}
	$karma['local']['players'][$player->login]['vote'] = $vote;


//	// Check if connection was failed
//	if ($mk_config['retrytime'] == 0) {
		// Add the new Vote into the counts (global/local)
		switch ($vote) {
			case 3:
				$karma['global']['votes']['fantastic']['count'] += 1;
				$karma['local']['votes']['fantastic']['count'] += 1;
				break;
			case 2:
				$karma['global']['votes']['beautiful']['count'] += 1;
				$karma['local']['votes']['beautiful']['count'] += 1;
				break;
			case 1:
				$karma['global']['votes']['good']['count'] += 1;
				$karma['local']['votes']['good']['count'] += 1;
				break;
			case -1:
				$karma['global']['votes']['bad']['count'] += 1;
				$karma['local']['votes']['bad']['count'] += 1;
				break;
			case -2:
				$karma['global']['votes']['poor']['count'] += 1;
				$karma['local']['votes']['poor']['count'] += 1;
				break;
			case -3:
				$karma['global']['votes']['waste']['count'] += 1;
				$karma['local']['votes']['waste']['count'] += 1;
				break;
			default:
				// Do nothing
				break;
		}
//	}
//	else {
//		// Add the new Vote into the counts (only local)
//		switch ($vote) {
//			case 3:
//				$karma['local']['votes']['fantastic']['count'] += 1;
//				break;
//			case 2:
//				$karma['local']['votes']['beautiful']['count'] += 1;
//				break;
//			case 1:
//				$karma['local']['votes']['good']['count'] += 1;
//				break;
//			case -1:
//				$karma['local']['votes']['bad']['count'] += 1;
//				break;
//			case -2:
//				$karma['local']['votes']['poor']['count'] += 1;
//				break;
//			case -3:
//				$karma['local']['votes']['waste']['count'] += 1;
//				break;
//			default:
//				// Do nothing
//				break;
//		}
//	}


//	// Check if connection was failed
//	if ($mk_config['retrytime'] == 0) {
		// Update the global/local $karma
		mk_calculateKarma(array('global','local'));
//	}
//	else {
//		// Update only the local $karma
//		mk_calculateKarma(array('local'));
//	}


	// Show the MX-Link-Window (if enabled and we are at Score)
	if ($mk_config['score_mx_window'] == true) {
		mk_showManiaExchangeLinkWindow($player);
	}


	// Tell the player the result for his/her vote
	if ($karma['global']['players'][$player->login]['previous'] == 0) {
		$message = formatText($mk_config['messages']['karma_done'], stripColors($aseco->server->challenge->name) );
		if ( ($mk_config['messages_in_window'] == true) && (function_exists('send_window_message')) ) {
			send_window_message($aseco, $message, $player);
		}
		else {
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		}

	}
	else if ($karma['global']['players'][$player->login]['previous'] != $vote) {
		$message = formatText($mk_config['messages']['karma_change'], stripColors($aseco->server->challenge->name) );
		if ( ($mk_config['messages_in_window'] == true) && (function_exists('send_window_message')) ) {
			send_window_message($aseco, $message, $player);
		}
		else {
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		}
	}


	// Show Map Karma (with details?)
	$message = mk_createKarmaMessage($player->login, false);
	if ($message != false) {
		if ( ($mk_config['messages_in_window'] == true) && (function_exists('send_window_message')) ) {
			send_window_message($aseco, $message, $player);
		}
		else {
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		}
	}


	// Update the KarmaWidget for given Player
	mk_sendWidgetCombination(array('player_marker'), $player);


	// Should all other player (except the vote given player) be informed/asked?
	if ($mk_config['show_player_vote_public'] == true) {
		$logins = array();
		foreach ($aseco->server->players->player_list as $pl) {

			// Don't ask/tell the player that give the vote
			if ($pl->login == $player->login) {
				continue;
			}

			// Don't ask/tell Players they did not reached the <require_finish> limit
			if ( ($mk_config['require_finish'] > 0) && ($mk_config['require_finish'] > $pl->data['ManiaKarma']['FinishedMapCount']) ) {
				continue;
			}

			// Don't ask/tell players if she/he has already voted!
			if ($karma['global']['players'][$pl->login]['vote'] != 0) {
				continue;
			}

			// Don't ask/tell Spectator's
			if ( $aseco->isSpectator($pl) ) {
				continue;
			}

			// All other becomes this message.
			$logins[] = $pl->login;
		}
        $player_voted = '';
		// Build the message and send out
		if ($vote == 1) {
			$player_voted = $mk_config['messages']['karma_good'];
		}
		else if ($vote == 2) {
			$player_voted = $mk_config['messages']['karma_beautiful'];
		}
		else if ($vote == 3) {
			$player_voted = $mk_config['messages']['karma_fantastic'];
		}
		else if ($vote == -1) {
			$player_voted = $mk_config['messages']['karma_bad'];
		}
		else if ($vote == -2) {
			$player_voted = $mk_config['messages']['karma_poor'];
		}
		else if ($vote == -3) {
			$player_voted = $mk_config['messages']['karma_waste'];
		}
		$message = formatText($mk_config['messages']['karma_show_opinion'],
				stripColors($player->nickname),
				$player_voted
		);
		$message = str_replace('{br}', LF, $message);  // split long message
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), implode(',', $logins));
		unset($logins);
	}


	// Release a KarmaChange Event
	$aseco->releaseEvent('onKarmaChange',
		array(
			'Karma'			=> $karma['global']['votes']['karma'],
			'Total'			=> $karma['global']['votes']['total'],
			'FantasticCount'	=> $karma['global']['votes']['fantastic']['count'],
			'FantasticPercent'	=> $karma['global']['votes']['fantastic']['percent'],
			'BeautifulCount'	=> $karma['global']['votes']['beautiful']['count'],
			'BeautifulPercent'	=> $karma['global']['votes']['beautiful']['percent'],
			'GoodCount'		=> $karma['global']['votes']['good']['count'],
			'GoodPercent'		=> $karma['global']['votes']['good']['percent'],
			'BadCount'		=> $karma['global']['votes']['bad']['count'],
			'BadPercent'		=> $karma['global']['votes']['bad']['percent'],
			'PoorCount'		=> $karma['global']['votes']['poor']['count'],
			'PoorPercent'		=> $karma['global']['votes']['poor']['percent'],
			'WasteCount'		=> $karma['global']['votes']['waste']['count'],
			'WastePercent'		=> $karma['global']['votes']['waste']['percent'],
		)
	);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_sendMapKarmaMessage ($login) {
	global $aseco, $mk_config;


	// Create message
	$message = mk_createKarmaMessage($login, false);

	// Show message
	if ($message != false) {
		if ($login) {
			if ( ($mk_config['messages_in_window'] == true) && (function_exists('send_window_message')) ) {
				$player = $aseco->server->players->getPlayer($login);
				send_window_message($aseco, $message, $player);
			}
			else {
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
			}
		}
		else {
			if ( ($mk_config['messages_in_window'] == true) && (function_exists('send_window_message')) ) {
				send_window_message($aseco, $message, false);
			}
			else {
				$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
			}
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_createKarmaMessage ($login, $force_display = false) {
	global $aseco, $mk_config, $karma;


	// Init
	$message = false;

	// Show default Karma message
	if ( ($mk_config['show_karma'] == true) || ($force_display == true) ) {
		$message = formatText($mk_config['messages']['karma_message'],
			stripColors($mk_config['CurrentMap']['name']),
			$karma['global']['votes']['karma']
		);
	}

	// Optionally show player's actual vote
	if ( ($mk_config['show_votes'] == true) || ($force_display == true) ) {
		if ($karma['global']['players'][$login]['vote'] == 1) {
			$message .= formatText($mk_config['messages']['karma_your_vote'], $mk_config['messages']['karma_good'], '/+');
		}
		else if ($karma['global']['players'][$login]['vote'] == 2) {
			$message .= formatText($mk_config['messages']['karma_your_vote'], $mk_config['messages']['karma_beautiful'], '/++');
		}
		else if ($karma['global']['players'][$login]['vote'] == 3) {
			$message .= formatText($mk_config['messages']['karma_your_vote'], $mk_config['messages']['karma_fantastic'], '/+++');
		}
		else if ($karma['global']['players'][$login]['vote'] == -1) {
			$message .= formatText($mk_config['messages']['karma_your_vote'], $mk_config['messages']['karma_bad'], '/-');
		}
		else if ($karma['global']['players'][$login]['vote'] == -2) {
			$message .= formatText($mk_config['messages']['karma_your_vote'], $mk_config['messages']['karma_poor'], '/--');
		}
		else if ($karma['global']['players'][$login]['vote'] == -3) {
			$message .= formatText($mk_config['messages']['karma_your_vote'], $mk_config['messages']['karma_waste'], '/---');
		}
		else {
			$message .= $mk_config['messages']['karma_not_voted'];
		}

	}

	// Optionally show vote counts & percentages
	if ( ($mk_config['show_details'] == true) || ($force_display == true) ) {
		$message .= formatText(LF. $mk_config['messages']['karma_details'],
			$karma['global']['votes']['karma'],
			$karma['global']['votes']['fantastic']['percent'],	$karma['global']['votes']['fantastic']['count'],
			$karma['global']['votes']['beautiful']['percent'],	$karma['global']['votes']['beautiful']['count'],
			$karma['global']['votes']['good']['percent'],		$karma['global']['votes']['good']['count'],
			$karma['global']['votes']['bad']['percent'],		$karma['global']['votes']['bad']['count'],
			$karma['global']['votes']['poor']['percent'],		$karma['global']['votes']['poor']['count'],
			$karma['global']['votes']['waste']['percent'],		$karma['global']['votes']['waste']['count']
		);
	}

	return $message;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// This shows the undecided message to other players.
function mk_showUndecidedMessage ($command) {
	global $aseco, $mk_config, $karma;


	// Should all other player (except the vote given player) be informed/asked?
	if ($mk_config['show_player_vote_public'] == true) {
		foreach ($aseco->server->players->player_list as $player) {

			// Show only to players that did not voted yes
			if ($karma['global']['players'][$player->login]['vote'] == 0) {
				// Don't ask/tell the player that give the vote
				if ($player->login == $command['author']->login) {
					continue;
				}

				// Don't ask/tell Spectator's
				if ( $aseco->isSpectator($player) ) {
					continue;
				}

				$message = formatText($mk_config['messages']['karma_show_undecided'],
						stripColors($command['author']->nickname)
				);
				$message = str_replace('{br}', LF, $message);  // split long message
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
			}
		}
	}

	// Close reminder-window if there is one for this Player
	if ($command['author']->data['ManiaKarma']['ReminderWindow'] == true) {
		mk_closeReminderWindow($command['author']);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// This shows the reminder window to the given Players (comma seperated list)
function mk_showReminderWindow ($players) {
	global $aseco, $mk_config;

	$gamestate = 'race';
	if ($mk_config['widget']['current_state'] == 7) {
		$gamestate = 'score';
	}

	$content =  '<?xml version="1.0" encoding="UTF-8"?>';
	$content .= '<manialinks>';
	$content .= '<manialink id="'. $mk_config['manialink_id'] .'01">';
	$content .= '<frame posn="'. $mk_config['reminder_window'][$gamestate]['pos_x'] .' '. $mk_config['reminder_window'][$gamestate]['pos_y'] .' 2">';
	$content .= '<quad posn="0 1 0" sizen="81.8 4.5" style="Bgs1InRace" substyle="NavButton"/>';
	$content .= '<label posn="16.5 0.3 1" sizen="18 1.8" textsize="2" scale="0.8" halign="right" textcolor="FFFF" text="'. $mk_config['messages']['karma_reminder_at_score'] .'"/>';
	$content .= '<label posn="16.5 -1.5 1" sizen="14 0.2" textsize="1" scale="0.8" halign="right" textcolor="FFFF" text="powered by mania-karma.com"/>';

	$content .= '<frame posn="19.2 0.45 1">';
	$content .= '<quad posn="0 0.15 0" sizen="7.5 3.75" action="'. $mk_config['manialink_id'] .'12" style="Bgs1InRace" substyle="BgIconBorder"/>';
	$content .= '<label posn="3.75 -0.5 0" sizen="7 0" halign="center" textsize="1" text="$390'. ucfirst($mk_config['messages']['karma_fantastic']) .'"/>';
	$content .= '<label posn="3.75 -1.8 0" sizen="10 0" halign="center" textsize="1" text="$390+++"/>';
	$content .= '</frame>';

	$content .= '<frame posn="27.9 0.45 1">';
	$content .= '<quad posn="0 0.15 0" sizen="7.5 3.75" action="'. $mk_config['manialink_id'] .'11" style="Bgs1InRace" substyle="BgIconBorder"/>';
	$content .= '<label posn="3.75 -0.5 0" sizen="7 0" halign="center" textsize="1" text="$390'. ucfirst($mk_config['messages']['karma_beautiful']) .'"/>';
	$content .= '<label posn="3.75 -1.8 0" sizen="10 0" halign="center" textsize="1" text="$390++"/>';
	$content .= '</frame>';

	$content .= '<frame posn="36.6 0.45 1">';
	$content .= '<quad posn="0 0.15 0" sizen="7.5 3.75" action="'. $mk_config['manialink_id'] .'10" style="Bgs1InRace" substyle="BgIconBorder"/>';
	$content .= '<label posn="3.75 -0.5 0" sizen="7 0" halign="center" textsize="1" text="$390'. ucfirst($mk_config['messages']['karma_good']) .'"/>';
	$content .= '<label posn="3.75 -1.8 0" sizen="10 0" halign="center" textsize="1" text="$390+"/>';
	$content .= '</frame>';

	$content .= '<frame posn="45.3 0.45 1">';
	$content .= '<quad posn="0 0.15 0" sizen="7.5 3.75" action="'. $mk_config['manialink_id'] .'13" style="Bgs1InRace" substyle="BgIconBorder"/>';
	$content .= '<label posn="3.75 -0.5 0" sizen="7 0" halign="center" textsize="1" text="$888'. ucfirst($mk_config['messages']['karma_undecided']) .'"/>';
	$content .= '<label posn="3.75 -2 0" sizen="10 0" halign="center" textsize="1" scale="0.7" text="$888???"/>';
	$content .= '</frame>';

	$content .= '<frame posn="54 0.45 1">';
	$content .= '<quad posn="0 0.15 0" sizen="7.5 3.75" action="'. $mk_config['manialink_id'] .'14" style="Bgs1InRace" substyle="BgIconBorder"/>';
	$content .= '<label posn="3.75 -0.5 0" sizen="7 0" halign="center" textsize="1" text="$D02'. ucfirst($mk_config['messages']['karma_bad']) .'"/>';
	$content .= '<label posn="3.75 -1.7 0" sizen="14 0" halign="center" textsize="1" text="$D02-"/>';
	$content .= '</frame>';

	$content .= '<frame posn="62.7 0.45 1">';
	$content .= '<quad posn="0 0.15 0" sizen="7.5 3.75" action="'. $mk_config['manialink_id'] .'15" style="Bgs1InRace" substyle="BgIconBorder"/>';
	$content .= '<label posn="3.75 -0.5 0" sizen="7 0" halign="center" textsize="1" text="$D02'. ucfirst($mk_config['messages']['karma_poor']) .'"/>';
	$content .= '<label posn="3.75 -1.7 0" sizen="14 0" halign="center" textsize="1" text="$D02--"/>';
	$content .= '</frame>';

	$content .= '<frame posn="71.4 0.45 1">';
	$content .= '<quad posn="0 0.15 0" sizen="7.5 3.75" action="'. $mk_config['manialink_id'] .'16" style="Bgs1InRace" substyle="BgIconBorder"/>';
	$content .= '<label posn="3.75 -0.5 0" sizen="7 0" halign="center" textsize="1" text="$D02'. ucfirst($mk_config['messages']['karma_waste']) .'"/>';
	$content .= '<label posn="3.75 -1.7 0" sizen="14 0" halign="center" textsize="1" text="$D02---"/>';
	$content .= '</frame>';

	$content .= '</frame>';
	$content .= '</manialink>';
	$content .= '</manialinks>';

	$aseco->client->query('SendDisplayManialinkPageToLogin', $players, $content, 0, true);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// This shows the MX-Link Window to the given Player
function mk_showManiaExchangeLinkWindow ($player) {
	global $aseco, $mk_config, $karma;

    $cmd = '';
    $voted = '';
	// Bail out immediately if not at Score
	if ($mk_config['widget']['current_state'] != 7) {
		return;
	}

	// Find the Player vote
	switch ($karma['global']['players'][$player->login]['vote']) {
		case 3:
			$voted = '$390'. ucfirst($mk_config['messages']['karma_fantastic']);
			$cmd = '$390+++';
			break;
		case 2:
			$voted = '$390'. ucfirst($mk_config['messages']['karma_beautiful']);
			$cmd = '$390++';
			break;
		case 1:
			$voted = '$390'. ucfirst($mk_config['messages']['karma_good']);
			$cmd = '$390+';
			break;
		case -1:
			$voted = '$D02'. ucfirst($mk_config['messages']['karma_bad']);
			$cmd = '$D02-';
			break;
		case -2:
			$voted = '$D02'. ucfirst($mk_config['messages']['karma_poor']);
			$cmd = '$D02--';
			break;
		case -3:
			$voted = '$D02'. ucfirst($mk_config['messages']['karma_waste']);
			$cmd = '$D02---';
			break;
	}

	$gamestate = 'race';
	if ($mk_config['widget']['current_state'] == 7) {
		$gamestate = 'score';
	}

	$content =  '<?xml version="1.0" encoding="UTF-8"?>';
	$content .= '<manialinks>';
	$content .= '<manialink id="'. $mk_config['manialink_id'] .'01">';
	$content .= '<frame posn="'. $mk_config['reminder_window'][$gamestate]['pos_x'] .' '. $mk_config['reminder_window'][$gamestate]['pos_y'] .' 2">';
	$content .= '<quad posn="0 1 0" sizen="81.8 4.5" style="Bgs1InRace" substyle="NavButton"/>';
	$content .= '<label posn="16.5 0.3 1" sizen="18 1.8" textsize="2" scale="0.8" halign="right" textcolor="FFFF" text="'. $mk_config['messages']['karma_reminder_at_score'] .'"/>';
	$content .= '<label posn="16.5 -1.5 1" sizen="14 0.2" textsize="1" scale="0.8" halign="right" textcolor="FFFF" text="powered by mania-karma.com"/>';

	$content .= '<frame posn="19.2 0.45 1">';
	$content .= '<quad posn="0 0.15 0" sizen="7.5 3.75" style="Bgs1InRace" substyle="BgIconBorder"/>';
	$content .= '<label posn="3.75 -0.5 0" sizen="7 0" textsize="1" halign="center" text="'. $voted .'"/>';
	$content .= '<label posn="3.75 -1.8 0" sizen="10 0" textsize="1" halign="center" text="'. $cmd .'"/>';
	$content .= '</frame>';

	if ( isset($aseco->server->challenge->tmx->pageurl) ) {
		// Show link direct to the last map
		$content .= '<frame posn="33 0.2 1">';
		$content .= '<label posn="40.5 -1.3 0" sizen="50 0" halign="right" textsize="1" text="$000Visit &#187; '. preg_replace('/\$S/i', '', $aseco->server->challenge->name) .'$Z$000 &#171; at"/>';
		$content .= '<quad posn="41.25 0.08 0" sizen="7 4" image="'. $mk_config['images']['tmx_logo_normal'] .'" imagefocus="'. $mk_config['images']['tmx_logo_focus'] .'" url="'. preg_replace('/(&)/', '&amp;', $aseco->server->challenge->tmx->pageurl) .'"/>';
		$content .= '</frame>';
	}
	else {
		// Show link to xxx.tm-exchange.com
		$content .= '<frame posn="33 0.3 1">';
		$content .= '<quad posn="41.25 0.08 0" sizen="7 4" image="'. $mk_config['images']['tmx_logo_normal'] .'" imagefocus="'. $mk_config['images']['tmx_logo_focus'] .'" url="http://www.tm-exchange.com/"/>';
		$content .= '</frame>';
	}

	$content .= '</frame>';
	$content .= '</manialink>';
	$content .= '</manialinks>';

	$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $content, 0, false);
	$player->data['ManiaKarma']['ReminderWindow'] = true;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// This close the reminder window from given Player or all Players
function mk_closeReminderWindow ($player = false) {
	global $aseco, $mk_config;


	// If there no players, bail out immediately
	if (count($aseco->server->players->player_list) == 0) {
		return;
	}

	// Build the Manialink
	$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $mk_config['manialink_id'] .'01"></manialink>';
	$xml .= '</manialinks>';

	if ($player != false) {
		if ($player->data['ManiaKarma']['ReminderWindow'] == true) {
			$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);
			$player->data['ManiaKarma']['ReminderWindow'] = false;
		}
	}
	else {
		// Reset state at all Players
		foreach ($aseco->server->players->player_list as $player) {
			if ($player->data['ManiaKarma']['ReminderWindow'] == true) {
				$player->data['ManiaKarma']['ReminderWindow'] = false;
			}
		}

		// Send manialink to all Player
		$aseco->client->query('SendDisplayManialinkPage', $xml, 0, false);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_sendHelpAboutWindow ($player, $message) {
	global $aseco, $mk_config;


	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';

	$buttons = '';
	$xml .= str_replace(
		array(
			'%window_title%',
			'%prev_next_buttons%'
		),
		array(
			'$L[http://'. $mk_config['urls']['website'] .']ManiaKarma$L/'. $mk_config['version'] .' for XAseco',
			$buttons
		),
		$mk_config['Templates']['WINDOW']['HEADER']
	);

	$xml .= '<frame posn="3 -6 0">';
	$xml .= '<quad posn="54 4 0.05" sizen="23 23" image="'. $mk_config['images']['maniakarma_logo'] .'" url="http://www.mania-karma.com"/>';
	$xml .= '<label posn="0 0 0.05" sizen="57 0" autonewline="1" textsize="1" textcolor="FF0F" text="'. $message .'"/>';
	$xml .= '</frame>';

	$xml .= $mk_config['Templates']['WINDOW']['FOOTER'];
	$xml .= '</manialinks>';

	$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_storeKarmaVotes () {
	global $aseco, $mk_config, $karma;


	// Send the new vote from the last Map to the central database and store them local (if enabled)
	if ( (isset($karma['new']['players'])) && (count($karma['new']['players']) > 0) ) {

		// Check if connection was failed
		if ( ($mk_config['retrytime'] > 0) && (time() >= $mk_config['retrytime']) ) {
			// Reconnect to the database
			mk_onSync($aseco);
		}

		if ($mk_config['retrytime'] == 0) {

			// Check for all required parameters for an remote API Call
			if ( (empty($karma['data']['uid'])) || (empty($karma['data']['name'])) || (empty($karma['data']['author'])) || (empty($karma['data']['env'])) ) {
				$aseco->console('[plugin.mania_karma.php] Could not do a remote API Call "Vote", one of the required parameter missed! uid:'. $karma['data']['uid'] .' name:'. $karma['data']['name'] .' author:'. $karma['data']['author'] .' env:'. $karma['data']['env']);
				return;
			}

			// Build the Player/Vote pairs
			$pairs = array();
			foreach ($karma['new']['players'] as $login => $vote) {
				$pairs[] = urlencode($login) .'='. $vote;

			}


			// Generate the url for this Votes
			$authortime = 0;
			$authorscore = 0;
			if ($aseco->server->gameinfo->mode == Gameinfo::STNT) {
				$authorscore = $mk_config['CurrentMap']['authorscore'];
			}
			else {
				$authortime = $mk_config['CurrentMap']['authortime'];
			}
			$api_url = sprintf("%s?Action=Vote&login=%s&authcode=%s&uid=%s&map=%s&author=%s&atime=%s&ascore=%s&nblaps=%s&nbchecks=%s&mood=%s&env=%s&votes=%s&tmx=%s",
				$mk_config['urls']['api'],
				urlencode( $mk_config['account']['login'] ),
				urlencode( $mk_config['account']['authcode'] ),
				urlencode( $karma['data']['uid'] ),
				base64_encode( $karma['data']['name'] ),
				urlencode( $karma['data']['author'] ),
				$authortime,
				$authorscore,
				urlencode( $mk_config['CurrentMap']['nblaps'] ),
				urlencode( $mk_config['CurrentMap']['nbchecks'] ),
				urlencode( $mk_config['CurrentMap']['mood'] ),
				urlencode( $karma['data']['env'] ),
				implode('|', $pairs),
				$karma['data']['tmx']
			);

			// Start an async VOTE request
			$mk_config['webaccess']->request($api_url, array('mk_handleWebaccess', 'VOTE', $api_url), 'none', false, $mk_config['keepalive_min_timeout'], $mk_config['connect_timeout'], $mk_config['wait_timeout'], $mk_config['user_agent']);
		}


		// Check if karma should saved local also
		if ($mk_config['save_karma_also_local'] == true) {

			$logins = array();
			foreach ($karma['new']['players'] as $login => $vote) {
				$logins[] = "'". $login ."'";
			}

			$query = "
			SELECT
				`p`.`Login` AS `Login`,
				`k`.`Id` AS `VoteId`
			FROM `rs_karma` AS `k`
			LEFT JOIN `players` AS `p` ON `p`.`Id`=`k`.`PlayerId`
			WHERE `p`.`Login` IN (". implode(',', $logins) .")
			AND `k`.`ChallengeId`='". $karma['data']['id'] ."';
			";

			$updated = array();
			$res = mysql_query($query);
			if ($res) {
				if (mysql_num_rows($res) > 0) {
					while ($row = mysql_fetch_object($res)) {
						if ($row->VoteId > 0) {
							$query2 = "UPDATE `rs_karma` SET `Score`='". $karma['new']['players'][$row->Login] ."' WHERE `Id`='". $row->VoteId ."';";
							$result = mysql_query($query2);
							if (!$result) {
								$aseco->console('[plugin.mania_karma.php] Could not UPDATE karma vote for "'. $row->Login .'" [for statement "'. $query2 .'"]!');
							}
						}

						// Mark for Updated
						$updated[$row->Login] = true;
					}
				}
				mysql_free_result($res);
			}

			// INSERT all other Player they did not vote before
			$query2 = "INSERT INTO `rs_karma` (`Score`, `PlayerId`, `ChallengeId`) VALUES ";
			$values = array();
			foreach ($karma['new']['players'] as $login => $vote) {
				if ( !isset($updated[$login]) ) {
					$playerid = $aseco->getPlayerId($login);
					if ($playerid > 0) {
						// Add only Players with an PlayerId
						$values[] = "('". $vote ."', '". $playerid ."', '". $karma['data']['id'] ."')";
					}
				}
			}

			if (count($values) > 0) {
				$result = mysql_query($query2 . implode(',', $values));
				if (!$result) {
					$aseco->console('[plugin.mania_karma.php] Could not INSERT karma votes... [for statement "'. $query2 . implode(',', $values) .'"]!');
				}
			}
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_syncGlobaAndLocalVotes ($source, $setup_global = false) {
	global $aseco, $mk_config, $karma;


	// Switch source and destination if required
	$destination = 'local';
	if ($source == 'local') {
		$destination = 'global';
	}

	// Bailout if unset
	if ( !isset($karma[$source]['players']) ) {
		return;
	}


	$found = false;
	foreach ($karma[$source]['players'] as $login => $votes) {
		// Skip "no vote" (value "0") from sync
		if ($votes['vote'] == 0) {
			continue;
		}

		// Is the votes are different, then replace $source with the $destination vote
		if ( (isset($karma[$destination]['players'][$login])) && ($karma[$destination]['players'][$login]['vote'] != $votes['vote']) ) {

			// Set to true to rebuild the $destination Karma and the Widget (Cups/Values)
			$found = true;

			// Set the $destination to the $source vote
			$karma[$destination]['players'][$login]['vote'] = $votes['vote'];

			// Set the sync'd vote as a new vote to store them into the database at onBeginMap
			if ($setup_global === true) {
				$karma['new']['players'][$login] = $votes['vote'];
			}

			// Count the vote too
			switch ($votes['vote']) {
				case 3:
					$karma[$destination]['votes']['fantastic']['count'] += 1;
					break;
				case 2:
					$karma[$destination]['votes']['beautiful']['count'] += 1;
					break;
				case 1:
					$karma[$destination]['votes']['good']['count'] += 1;
					break;
				case -1:
					$karma[$destination]['votes']['bad']['count'] += 1;
					break;
				case -2:
					$karma[$destination]['votes']['poor']['count'] += 1;
					break;
				case -3:
					$karma[$destination]['votes']['waste']['count'] += 1;
					break;
				default:
					// Do nothing
					break;
			}
		}
	}

	if ($found == true) {
		// Update the $destination $karma
		mk_calculateKarma(array($destination));

		// Update the KarmaWidget for all Players
		mk_sendWidgetCombination(array('cups_values'), false);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_handleGetApiCall ($map, $target = false) {
	global $aseco, $mk_config, $karma;


	// If there no players, bail out immediately
	if (count($aseco->server->players->player_list) == 0) {
		return;
	}

	// Bail out if map id was not found
	if ($map['id'] === false) {
		return;
	}

	// Check if connection was failed and try to reconnect
	if ( ($mk_config['retrytime'] > 0) && (time() >= $mk_config['retrytime']) ) {
		// Reconnect to the database
		mk_onSync($aseco);
	}

	if ($mk_config['retrytime'] > 0) {
		// Connect failed, try again later
		return;
	}

	// Check for all required parameters for an remote API Call
	if ( (empty($map['uid'])) || (empty($map['name'])) || (empty($map['author'])) || (empty($map['environment'])) ) {
		$aseco->console('[plugin.mania_karma.php] Could not do a remote API Call "Get", one of the required parameter missed! uid:'. $map['uid'] .' name:'. $map['name'] .' author:'. $map['author'] .' env:'. $map['environment']);
		return;
	}

	$players = array();
	if ($target !== false) {
		// Get Karma for ONE Player
		$player_list = array($target);
	}
	else {
		// Get Karma for ALL Players
		$player_list = $aseco->server->players->player_list;
	}
	foreach ($player_list as $player) {
		$players[] = urlencode($player->login);
	}

	// Generate the url for this Map-Karma-Request
	$api_url = sprintf("%s?Action=Get&login=%s&authcode=%s&uid=%s&map=%s&author=%s&env=%s&player=%s",
		$mk_config['urls']['api'],
		urlencode( $mk_config['account']['login'] ),
		urlencode( $mk_config['account']['authcode'] ),
		urlencode( $map['uid'] ),
		base64_encode( $map['name'] ),
		urlencode( $map['author'] ),
		urlencode( $map['environment'] ),
		implode('|', $players)					// Already Url-Encoded
	);

	// Start an async GET request
	$mk_config['webaccess']->request($api_url, array('mk_handleWebaccess', 'GET', $api_url, $target), 'none', false, $mk_config['keepalive_min_timeout'], $mk_config['connect_timeout'], $mk_config['wait_timeout'], $mk_config['user_agent']);

	// Return an empty set, get replaced with mk_handleWebaccess()
	return;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_handleWebaccess ($response, $type, $url, $target = false) {
	global $aseco, $mk_config, $karma;

	if (empty($response['Error'])) {
		if ($response['Code'] == 200) {
			if ($type == 'GET') {
				// Read the response
				if (!$xml = @simplexml_load_string($response['Message'], null, LIBXML_COMPACT) ) {
					$aseco->console('[plugin.mania_karma.php] mk_handleWebaccess() on type "'. $type .'": Could not read/parse response from mania-karma.com "'. $response['Message'] .'"!');
					$mk_config['retrytime'] = (time() + $mk_config['retrywait']);
					mk_sendConnectionStatus(false, $mk_config['widget']['current_state']);
					mk_sendLoadingIndicator(false, $mk_config['widget']['current_state']);
				}
				else {
					if ($xml->status == 200) {
						mk_sendConnectionStatus(true, $mk_config['widget']['current_state']);

						$karma['global']['votes']['fantastic']['percent']	= (float)$xml->votes->fantastic['percent'];
						$karma['global']['votes']['fantastic']['count']		= (int)$xml->votes->fantastic['count'];
						$karma['global']['votes']['beautiful']['percent']	= (float)$xml->votes->beautiful['percent'];
						$karma['global']['votes']['beautiful']['count']		= (int)$xml->votes->beautiful['count'];
						$karma['global']['votes']['good']['percent']		= (float)$xml->votes->good['percent'];
						$karma['global']['votes']['good']['count']		= (int)$xml->votes->good['count'];

						$karma['global']['votes']['bad']['percent']		= (float)$xml->votes->bad['percent'];
						$karma['global']['votes']['bad']['count']		= (int)$xml->votes->bad['count'];
						$karma['global']['votes']['poor']['percent']		= (float)$xml->votes->poor['percent'];
						$karma['global']['votes']['poor']['count']		= (int)$xml->votes->poor['count'];
						$karma['global']['votes']['waste']['percent']		= (float)$xml->votes->waste['percent'];
						$karma['global']['votes']['waste']['count']		= (int)$xml->votes->waste['count'];

						$karma['global']['votes']['karma']			= (int)$xml->votes->karma;
						$karma['global']['votes']['total']			= ($karma['global']['votes']['fantastic']['count'] + $karma['global']['votes']['beautiful']['count'] + $karma['global']['votes']['good']['count'] + $karma['global']['votes']['bad']['count'] + $karma['global']['votes']['poor']['count'] + $karma['global']['votes']['waste']['count']);

						// Insert the votes for every Player
						foreach ($aseco->server->players->player_list as $player) {
							foreach ($xml->players->player as $pl) {
								if ($player->login == $pl['login']) {
									$karma['global']['players'][$player->login]['vote']	= (int)$pl['vote'];
									$karma['global']['players'][$player->login]['previous']	= (int)$pl['previous'];
								}
							}
						}

						// If <require_finish> is enabled
						if ($mk_config['require_finish'] > 0) {
							// Has the Player already vote this Map? If true, set to 9999 for max.
							foreach ($aseco->server->players->player_list as $player) {
								foreach ($xml->players->player as $pl) {
									if ( ($player->login == $pl['login']) && ((int)$pl['vote'] != 0) ) {
										// Set the state of finishing this map, if not already has a setup of a != 0 value
										if ($player->data['ManiaKarma']['FinishedMapCount'] == 0) {
											$player->data['ManiaKarma']['FinishedMapCount'] = 9999;
										}
									}
								}
							}
						}

						// Check to see if it is required to sync global to local votes?
						if ($mk_config['sync_global_karma_local'] == true) {
							mk_syncGlobaAndLocalVotes('local', true);
						}

						// Now sync local votes to global votes (e.g. on connection lost...)
						mk_syncGlobaAndLocalVotes('global', true);

						if ($mk_config['karma_calculation_method'] == 'RASP') {
							// Update the global/local $karma
							mk_calculateKarma(array('global','local'));
						}

						// Display the Karma value of Map?
						if ($mk_config['show_at_start'] == true) {
							// Show players' actual votes, or global karma message?
							if ($mk_config['show_votes'] == true) {
								// Send individual player messages
								if ($target === false) {
									foreach ($aseco->server->players->player_list as $player) {
										mk_sendMapKarmaMessage($player->login);
									}
								}
								else {
									mk_sendMapKarmaMessage($target->login);
								}
							}
							else {
								// Send more efficient global message
								mk_sendMapKarmaMessage(false);
							}
						}

						if ($target === false) {
							mk_sendLoadingIndicator(false, $mk_config['widget']['current_state']);

							// Extract the MapImage and store them at the API
							if (strtoupper($xml->image_present) == 'FALSE') {
								mk_transmitMapImage();
							}
						}
					}
					else {
						$aseco->console('[plugin.mania_karma.php] mk_handleWebaccess() on type "'. $type .'": Connection failed with "'. $xml->status .'" for url ['. $url .']');
						$mk_config['retrytime'] = (time() + $mk_config['retrywait']);
						mk_sendConnectionStatus(false, $mk_config['widget']['current_state']);
						mk_sendLoadingIndicator(false, $mk_config['widget']['current_state']);
					}

					if ($target === false) {
						// Update KarmaWidget for all connected Players
						if ($mk_config['widget']['current_state'] == 7) {
							mk_sendWidgetCombination(array('skeleton_score', 'cups_values'), false);
						}
						else {
							mk_sendWidgetCombination(array('skeleton_race', 'cups_values'), false);
						}
						foreach ($aseco->server->players->player_list as $player) {
							mk_sendWidgetCombination(array('player_marker'), $player);
						}
					}
					else {
						// Update KarmaWidget only for current Player
						if ($mk_config['widget']['current_state'] == 7) {
							mk_sendWidgetCombination(array('skeleton_score', 'cups_values', 'player_marker'), $target);
						}
						else {
							mk_sendWidgetCombination(array('skeleton_race', 'cups_values', 'player_marker'), $target);
						}
					}
				}
			}
			else if ($type == 'VOTE') {
				// Read the response
				if ($xml = @simplexml_load_string($response['Message'], null, LIBXML_COMPACT) ) {
					if (!$xml->status == 200) {
						$aseco->console('[plugin.mania_karma.php] mk_handleWebaccess() on type "'. $type .'":  Storing votes failed with returncode "'. $xml->status .'"');
					}
					unset($xml);
				}
				else {
					$aseco->console('[plugin.mania_karma.php] mk_handleWebaccess() on type "'. $type .'": Could not read/parse response from mania-karma.com "'. $response['Message'] .'"!');
					$mk_config['retrytime'] = (time() + $mk_config['retrywait']);
					mk_sendConnectionStatus(false, $mk_config['widget']['current_state']);
				}
			}
			else if ($type == 'UPTODATE') {
				// Read the response
				if ($xml = @simplexml_load_string($response['Message'], null, LIBXML_COMPACT) ) {
					$current_release = $xml->xaseco1xx;
					if ( version_compare($current_release, $mk_config['version'], '>') ) {
						$release_url = 'http://'. $mk_config['urls']['website'] .'/Downloads/';
						$message = formatText($mk_config['messages']['uptodate_new'],
							$current_release,
							'$L[' . $release_url . ']' . $release_url . '$L'
						);
						$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $target->login);
					}
					else {
						if ($mk_config['uptodate_info'] == 'DEFAULT') {
							$message = formatText($mk_config['messages']['uptodate_ok'],
								$mk_config['version']
							);
							$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $target->login);
						}
					}
				}
				else {
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($mk_config['messages']['uptodate_failed']), $target->login);
					$aseco->console('[plugin.mania_karma.php] mk_handleWebaccess() on type "'. $type .'": Could not read/parse xml response!');
					$mk_config['retrytime'] = (time() + $mk_config['retrywait']);
					mk_sendConnectionStatus(false, $mk_config['widget']['current_state']);
				}
			}
			else if ($type == 'EXPORT') {
				if ($response['Code'] == 200) {
					$mk_config['import_done'] = true;		// Set to true, otherwise only after restart XAseco knows that
					$message = '{#server}>> {#admin}Export done. Thanks for supporting mania-karma.com!';
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $target->login);
				}
				else if ($response['Code'] == 406) {
					$message = '{#server}>> {#error}Export rejected! Please check your <login> and <nation> in config file "mania_karma.xml"!';
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $target->login);
				}
				else if ($response['Code'] == 409) {
					$message = '{#server}>> {#error}Export rejected! Export was already done, allowed only one time!';
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $target->login);
				}
				else {
					$message = '{#server}>> {#error}Connection failed with '. $response['Code'] .' ('. $response['Reason'] .') for url ['. $url .']' ."\n\r";
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $target->login);
				}
			}
			else if ($type == 'PING') {
				$mk_config['retrytime'] = 0;
			}
			else if ($type == 'STOREIMAGE') {
				// Do nothing
			}
		}
		else {
			$aseco->console('[plugin.mania_karma.php] mk_handleWebaccess() connection failed with "'. $response['Code'] .' - '. $response['Reason'] .'" for url ['. $url .']');
			$mk_config['retrytime'] = (time() + $mk_config['retrywait']);
			mk_sendConnectionStatus(false, $mk_config['widget']['current_state']);
		}
	}
	else {
		$aseco->console('[plugin.mania_karma.php] mk_handleWebaccess() connection failed '. $response['Error'] .' for url ['. $url .']');
		$mk_config['retrytime'] = (time() + $mk_config['retrywait']);
		mk_sendConnectionStatus(false, $mk_config['widget']['current_state']);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_findPlayersLocalRecords ($map_id, $player_list) {
	global $aseco;


	// Bail out if map id was not found
	if ($map_id === false) {
		return;
	}

	$player_ids = array();
	foreach ($player_list as $player) {
		$player_ids[] = $player->id;
	}

	$query = "
	SELECT
		`p`.`Login` AS `login`,
		COUNT(`t`.`Id`) AS `count`
	FROM `rs_times` AS `t`
	LEFT JOIN `players` AS `p` ON `p`.`Id`=`t`.`playerID`
	WHERE `t`.`playerID` IN (". implode(',', $player_ids) .")
	AND `t`.`ChallengeId`='". $map_id ."'
	GROUP BY `p`.`Login`;
	";
	$res = mysql_query($query);

	if ($res) {
		if (mysql_num_rows($res) > 0) {
			while ($row = mysql_fetch_object($res)) {
				foreach ($aseco->server->players->player_list as $player) {
					if ($player->login == $row->login) {
						$player->data['ManiaKarma']['FinishedMapCount'] = (int)$row->count;
					}
				}
			}
		}
		mysql_free_result($res);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_getCurrentMapInfo () {
	global $aseco, $mk_config;

	// Init
	$map['id']		= false;
	$map['uid']		= false;
	$map['name']		= false;
	$map['author']		= false;
	$map['authortime']	= 0;
	$map['authorscore']	= 0;
	$map['nblaps']		= 0;
	$map['nbchecks']	= 0;
	$map['mood']		= false;
	$map['environment']	= false;
	$map['filename']	= false;
	$map['mx']['id']	= false;


	// Within StartUp of XAseco2 and on Event 'onPlayerConnect'
	// is '$aseco->server->map->uid' always not set,
	// the Event 'onPlayerConnect' is fired too early.

	// Name, UId, FileName, Author, Environnement, Mood, BronzeTime, SilverTime, GoldTime, AuthorTime, CopperPrice, LapRace, NbLaps and NbCheckpoints
	$aseco->client->query('GetCurrentChallengeInfo');
	$response = $aseco->client->getResponse();
	$uid		= $response['UId'];
	$filename	= $response['FileName'];


	// Parse the GBX Mapfile
	$gbx = new GBXChallMapFetcher(true, false, false);
	try {
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			$gbx->processFile($aseco->server->trackdir . iconv('UTF-8', 'ISO-8859-1//TRANSLIT', mk_stripBOM($filename)));
		}
		else {
			$gbx->processFile($aseco->server->trackdir . mk_stripBOM($filename));
		}
	}
	catch (Exception $e) {
		trigger_error('[plugin.mania_karma.php] Could not read Map ['. $aseco->server->trackdir . mk_stripBOM($filename) .'] at mk_getCurrentMapInfo(): '. $e->getMessage(), E_USER_WARNING);

		return $map;
	}

	$map['id']		= false;
	$map['uid']		= $gbx->uid;
	$map['name']		= $gbx->name;
	$map['author']		= $gbx->author;
	if ($aseco->server->gameinfo->mode == Gameinfo::STNT) {
		$map['authortime']	= 0;
		$map['authorscore']	= $gbx->authorScore;
	}
	else {
		$map['authortime']	= $gbx->authorTime;
		$map['authorscore']	= 0;
	}
	$map['nblaps']		= $gbx->nbLaps;
	$map['nbchecks']	= (($gbx->nbChecks == $response['NbCheckpoints']) ? $gbx->nbChecks : $response['NbCheckpoints']);
	$map['mood']		= $gbx->mood;
	$map['environment']	= $gbx->envir;
	$map['filename']	= $filename;

	$query = "
	SELECT
		`Id`
	FROM `challenges`
	WHERE `Uid`='". mysql_real_escape_string($map['uid']) ."'
	LIMIT 1;
	";
	$res = mysql_query($query);
	if ($res) {
		if (mysql_num_rows($res) == 1) {
			$row = mysql_fetch_object($res);
			$map['id'] = $row->Id;
		}
		mysql_free_result($res);
	}

	return $map;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_getLocalKarma ($MapId = false) {
	global $aseco, $mk_config, $karma;


	// Bail out if $MapId is not given
	if ($MapId == false) {
		return;
	}

	$karma['local']['votes']['fantastic']['percent']	= 0;
	$karma['local']['votes']['fantastic']['count']		= 0;
	$karma['local']['votes']['beautiful']['percent']	= 0;
	$karma['local']['votes']['beautiful']['count']		= 0;
	$karma['local']['votes']['good']['percent']		= 0;
	$karma['local']['votes']['good']['count']		= 0;

	$karma['local']['votes']['bad']['percent']		= 0;
	$karma['local']['votes']['bad']['count']		= 0;
	$karma['local']['votes']['poor']['percent']		= 0;
	$karma['local']['votes']['poor']['count']		= 0;
	$karma['local']['votes']['waste']['percent']		= 0;
	$karma['local']['votes']['waste']['count']		= 0;

	$query = "
		SELECT
		(
		  SELECT COUNT(`Score`)
		  FROM `rs_karma`
		  WHERE `ChallengeId`='$MapId'
		  AND `Score`='3'
		) AS `FantasticCount`,
		(
		  SELECT COUNT(`Score`)
		  FROM `rs_karma`
		  WHERE `ChallengeId`='$MapId'
		  AND `Score`='2'
		) AS `BeautifulCount`,
		(
		  SELECT COUNT(`Score`)
		  FROM `rs_karma`
		  WHERE `ChallengeId`='$MapId'
		  AND `Score`='1'
		) AS `GoodCount`,
		(
		  SELECT COUNT(`Score`)
		  FROM `rs_karma`
		  WHERE `ChallengeId`='$MapId'
		  AND `Score`='-1'
		) AS `BadCount`,
		(
		  SELECT COUNT(`Score`)
		  FROM `rs_karma`
		  WHERE `ChallengeId`='$MapId'
		  AND `Score`='-2'
		) AS `PoorCount`,
		(
		  SELECT COUNT(`Score`)
		  FROM `rs_karma`
		  WHERE `ChallengeId`='$MapId'
		  AND `Score`='-3'
		) AS `WasteCount`;
	";

	$res = mysql_query($query);
	if ($res) {
		if (mysql_num_rows($res) > 0) {
			$row = mysql_fetch_object($res);

			$karma['local']['votes']['fantastic']['count']		= $row->FantasticCount;
			$karma['local']['votes']['beautiful']['count']		= $row->BeautifulCount;
			$karma['local']['votes']['good']['count']		= $row->GoodCount;
			$karma['local']['votes']['bad']['count']		= $row->BadCount;
			$karma['local']['votes']['poor']['count']		= $row->PoorCount;
			$karma['local']['votes']['waste']['count']		= $row->WasteCount;
		}
		mysql_free_result($res);
	}

	// Update the local $karma
	mk_calculateKarma(array('local'));
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_getLocalVotes ($MapId, $login = false) {
	global $aseco, $mk_config, $karma;


	// Bail out if $MapId is not given
	if ($MapId == false) {
		return;
	}


	// Build the Player votes Array
	$logins = array();
	if ($login == false) {
		// Add all Players
		foreach ($aseco->server->players->player_list as $player) {
			$logins[] = "'". $player->login ."'";
		}
	}
	else {
		// Add only given Player
		$logins[] = "'". $login ."'";
	}

	// Request the Player votes
	$votes = array();
	$query = "
	SELECT
		`p`.`Login`,
		`k`.`Score`
	FROM `rs_karma` AS `k`
	LEFT JOIN `players` AS `p` ON `p`.`Id`=`k`.`PlayerId`
	WHERE `k`.`ChallengeId`='$MapId'
	AND `p`.`Login` IN (". implode(',', $logins) .");
	";

	$res = mysql_query($query);
	if ($res) {
		if (mysql_num_rows($res) > 0) {
			while ($row = mysql_fetch_object($res)) {
				$karma['local']['players'][$row->Login]['vote'] = (int)$row->Score;
			}
		}
		mysql_free_result($res);
	}

	if ($login == false) {
		// If some Players has not vote this Map, we need to add them with Vote=0
		foreach ($aseco->server->players->player_list as $player) {
			if ( !isset($karma['local']['players'][$player->login]) ) {
				$karma['local']['players'][$player->login]['vote'] = 0;
			}
		}
	}
	else if ( !isset($karma['local']['players'][$login]) ) {
		$karma['local']['players'][$login]['vote'] = 0;
	}


	// Find out which Player already vote this Map? If true, set to 9999 for max.
	if ($mk_config['require_finish'] > 0) {
		foreach ($aseco->server->players->player_list as $player) {
			if ($karma['local']['players'][$player->login]['vote'] != 0) {
				// Set the state of finishing this map, if not already has a setup of a != 0 value
				if ($player->data['ManiaKarma']['FinishedMapCount'] == 0) {
					$player->data['ManiaKarma']['FinishedMapCount'] = 9999;
				}
			}
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// Returns the current amount of server Coppers
function mk_getServerCoppers () {
	global $aseco, $mk_config;


	$aseco->client->resetError();
	$aseco->client->query('GetServerCoppers');
	$coppers = $aseco->client->getResponse();

	if ( $aseco->client->isError() ) {
		$aseco->console('[plugin.mania_karma.php] Error getting the amount of server Coppers: [' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage());
		return 0;
	}
	else {
		return $coppers;
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_setEmptyKarma ($reset_locals = false) {
	global $aseco, $karma;


	$empty = array();
	$empty['data']['uid']					= false;

	$empty['global']['votes']['karma']			= 0;
	$empty['global']['votes']['total']			= 0;

	$empty['global']['votes']['fantastic']['percent']	= 0;
	$empty['global']['votes']['fantastic']['count']		= 0;
	$empty['global']['votes']['beautiful']['percent']	= 0;
	$empty['global']['votes']['beautiful']['count']		= 0;
	$empty['global']['votes']['good']['percent']		= 0;
	$empty['global']['votes']['good']['count']		= 0;

	$empty['global']['votes']['bad']['percent']		= 0;
	$empty['global']['votes']['bad']['count']		= 0;
	$empty['global']['votes']['poor']['percent']		= 0;
	$empty['global']['votes']['poor']['count']		= 0;
	$empty['global']['votes']['waste']['percent']		= 0;
	$empty['global']['votes']['waste']['count']		= 0;

	$empty['global']['players']				= array();

	if ($reset_locals === true) {
		$empty['local']['votes']['karma']			= 0;
		$empty['local']['votes']['total']			= 0;

		$empty['local']['votes']['fantastic']['percent']	= 0;
		$empty['local']['votes']['fantastic']['count']		= 0;
		$empty['local']['votes']['beautiful']['percent']	= 0;
		$empty['local']['votes']['beautiful']['count']		= 0;
		$empty['local']['votes']['good']['percent']		= 0;
		$empty['local']['votes']['good']['count']		= 0;

		$empty['local']['votes']['bad']['percent']		= 0;
		$empty['local']['votes']['bad']['count']		= 0;
		$empty['local']['votes']['poor']['percent']		= 0;
		$empty['local']['votes']['poor']['count']		= 0;
		$empty['local']['votes']['waste']['percent']		= 0;
		$empty['local']['votes']['waste']['count']		= 0;

		$empty['local']['players']				= array();
	}

	foreach ($aseco->server->players->player_list as $player) {
		$empty['global']['players'][$player->login]['vote']	= 0;
		$empty['global']['players'][$player->login]['previous']	= 0;

		if ($reset_locals === true) {
			$empty['local']['players'][$player->login]['vote']	= 0;
			$empty['local']['players'][$player->login]['previous']	= 0;
		}
	}

	// Copy current $karma['local'] into the new array
	if ($reset_locals === false) {
		$empty['local'] = $karma['local'];
	}

	return $empty;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// Checks plugin version at MasterAdmin connect
function mk_uptodateCheck ($player) {
	global $aseco, $mk_config;


	// Check if connection was failed and try to reconnect
	if ( ($mk_config['retrytime'] > 0) && (time() >= $mk_config['retrytime']) ) {
		// Reconnect to the database
		mk_onSync($aseco);
	}

	if ($mk_config['retrytime'] > 0) {
		// Connect failed, try again later
		return;
	}

	// Start an async UPTODATE request
	$url = 'http://'. $mk_config['urls']['website'] .'/api/plugin-releases.xml';
	$mk_config['webaccess']->request($url, array('mk_handleWebaccess', 'UPTODATE', $url, $player), 'none', false, $mk_config['keepalive_min_timeout'], $mk_config['connect_timeout'], $mk_config['wait_timeout'], $mk_config['user_agent']);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_calculateKarma ($which) {
	global $mk_config, $karma;


	// Calculate the Global/Local-Karma
	foreach ($which as $location) {

		// Prevent negativ vote counts
		foreach (array('fantastic', 'beautiful', 'good', 'bad', 'poor', 'waste') as $type) {
			if ($karma[$location]['votes'][$type]['count'] < 0) {
				$karma[$location]['votes'][$type]['count'] = 0;
			}
		}

		$totalvotes = ($karma[$location]['votes']['fantastic']['count'] + $karma[$location]['votes']['beautiful']['count'] + $karma[$location]['votes']['good']['count'] + $karma[$location]['votes']['bad']['count'] + $karma[$location]['votes']['poor']['count'] + $karma[$location]['votes']['waste']['count']);

		// Prevention of "illegal division by zero"
		if ($totalvotes == 0) {
			$totalvotes = 0.0000000000001;
		}

		$karma[$location]['votes']['fantastic']['percent']	= sprintf("%.2f", ($karma[$location]['votes']['fantastic']['count']	/ $totalvotes * 100));
		$karma[$location]['votes']['beautiful']['percent']	= sprintf("%.2f", ($karma[$location]['votes']['beautiful']['count']	/ $totalvotes * 100));
		$karma[$location]['votes']['good']['percent']		= sprintf("%.2f", ($karma[$location]['votes']['good']['count']		/ $totalvotes * 100));
		$karma[$location]['votes']['bad']['percent']		= sprintf("%.2f", ($karma[$location]['votes']['bad']['count']		/ $totalvotes * 100));
		$karma[$location]['votes']['poor']['percent']		= sprintf("%.2f", ($karma[$location]['votes']['poor']['count']		/ $totalvotes * 100));
		$karma[$location]['votes']['waste']['percent']		= sprintf("%.2f", ($karma[$location]['votes']['waste']['count']		/ $totalvotes * 100));

		$good_votes = (
			($karma[$location]['votes']['fantastic']['count'] * 100) +
			($karma[$location]['votes']['beautiful']['count'] * 80) +
			($karma[$location]['votes']['good']['count'] * 60)
		);
		$bad_votes = (
			($karma[$location]['votes']['bad']['count'] * 40) +
			($karma[$location]['votes']['poor']['count'] * 20) +
			($karma[$location]['votes']['waste']['count'] * 0)
		);

		if ($mk_config['karma_calculation_method'] == 'RASP') {
			$karma[$location]['votes']['karma'] = floor(
				($karma[$location]['votes']['fantastic']['count'] * 3) +
				($karma[$location]['votes']['beautiful']['count'] * 2) +
				($karma[$location]['votes']['good']['count'] * 1) +
				-($karma[$location]['votes']['bad']['count'] * 1) +
				-($karma[$location]['votes']['poor']['count'] * 2) +
				-($karma[$location]['votes']['waste']['count'] * 3)
			);
		}
		else {
			$karma[$location]['votes']['karma'] = floor( ($good_votes + $bad_votes) / $totalvotes);
		}

		$karma[$location]['votes']['total'] = intval($totalvotes);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_exportVotes ($player) {
	global $aseco, $mk_config;


	if ($mk_config['import_done'] != false) {
		$message = "{#server}>> {#admin}Export of local votes already done, skipping...";
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		return;
	}

	$message = "{#server}>> {#admin}Collecting players with their votes on Maps...";
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);

	// Generate the content for this export
	$csv = false;
	$query = "
	SELECT
		`c`.`Uid`,
		`c`.`Name`,
		`c`.`Author`,
		`c`.`Environment`,
		`p`.`Login`,
		`rs`.`Score`
	FROM `rs_karma` AS `rs`
	LEFT JOIN `challenges` AS `c` ON `c`.`Id`=`rs`.`ChallengeId`
	LEFT JOIN `players` AS `p` ON `p`.`Id`=`rs`.`PlayerId`
	ORDER BY `c`.`Uid`;
	";
    $count = 1;
	$res = mysql_query($query);
	if ($res) {
		if (mysql_num_rows($res) > 0) {
			$count = 1;
			while ($row = mysql_fetch_object($res)) {
				if ( $row->Uid ) {
					$csv .= sprintf("%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n",
						$row->Uid,
						$row->Name,
						$row->Author,
						$row->Environment,
						$mk_config['account']['login'],
						$mk_config['account']['authcode'],
						$mk_config['account']['nation'],
						$row->Login,
						$row->Score
					);
				}
				$count ++;
			}
		}
		mysql_free_result($res);
	}

	$message = "{#server}>> {#admin}Found ". number_format($count, 0, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) ." votes in database.";
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);


	// gzip the CSV
	$message = "{#server}>> {#admin}Compressing collected data...";
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
	$csv = gzencode($csv, 9, FORCE_GZIP);


	// Encode them Base64
	$message = "{#server}>> {#admin}Encoding data...";
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
	$csv = base64_encode($csv);


	$message = "{#server}>> {#admin}Sending now the export with size of ". number_format(strlen($csv), 0, $mk_config['NumberFormat'][$mk_config['number_format']]['decimal_sep'], $mk_config['NumberFormat'][$mk_config['number_format']]['thousands_sep']) ." bytes...";
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);

	// Generate the url for the Import-Request
	$api_url = sprintf("%s?Action=Import&login=%s&authcode=%s&nation=%s",
		$mk_config['urls']['api'],
		urlencode( $mk_config['account']['login'] ),
		urlencode( $mk_config['account']['authcode'] ),
		urlencode( $mk_config['account']['nation'] )
	);

	// Start an async EXPORT request
	$mk_config['webaccess']->request($api_url, array('mk_handleWebaccess', 'EXPORT', $api_url, $player), $csv, false, $mk_config['keepalive_min_timeout'], $mk_config['connect_timeout'], $mk_config['wait_timeout'], $mk_config['user_agent']);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_transmitMapImage () {
	global $aseco, $mk_config;


	$gbx = new GBXChallMapFetcher(true, true, false);
	try {
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			$gbx->processFile($aseco->server->trackdir . iconv('UTF-8', 'ISO-8859-1//TRANSLIT', mk_stripBOM($mk_config['CurrentMap']['filename'])));
		}
		else {
			$gbx->processFile($aseco->server->trackdir . mk_stripBOM($mk_config['CurrentMap']['filename']));
		}
	}
	catch (Exception $e) {
		trigger_error('[plugin.mania_karma.php] Could not read Map ['. $aseco->server->trackdir . mk_stripBOM($mk_config['CurrentMap']['filename']) .'] at mk_transmitMapImage(): '. $e->getMessage(), E_USER_WARNING);
		return;
	}

	// Generate the url for this Map-Karma-Request
	$api_url = sprintf("%s?Action=StoreImage&login=%s&authcode=%s&uid=%s&env=%s&game=%s",
		$mk_config['urls']['api'],
		urlencode( $mk_config['account']['login'] ),
		urlencode( $mk_config['account']['authcode'] ),
		urlencode( $mk_config['CurrentMap']['uid'] ),
		urlencode( $mk_config['CurrentMap']['environment'] ),
		urlencode( $aseco->server->game )
	);

	// Start an async STOREIMAGE request
	$mk_config['webaccess']->request($api_url, array('mk_handleWebaccess', 'STOREIMAGE', $api_url), base64_encode($gbx->thumbnail), false, $mk_config['keepalive_min_timeout'], $mk_config['connect_timeout'], $mk_config['wait_timeout'], $mk_config['user_agent']);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_stripBOM ($string) {
	// Remove BOM-header, see http://en.wikipedia.org/wiki/Byte_order_mark
	return str_replace("\xEF\xBB\xBF", '', $string);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_handleSpecialChars ($string) {
	global $re_config;


	// Remove links, e.g. "$(L|H|P)[...]...$(L|H|P)"
	$string = preg_replace('/\${1}(L|H|P)\[.*?\](.*?)\$(L|H|P)/i', '$2', $string);
	$string = preg_replace('/\${1}(L|H|P)\[.*?\](.*?)/i', '$2', $string);
	$string = preg_replace('/\${1}(L|H|P)(.*?)/i', '$2', $string);

	// Remove $S (shadow)
	// Remove $H (manialink)
	// Remove $W (wide)
	// Remove $I (italic)
	// Remove $L (link)
	// Remove $O (bold)
	// Remove $N (narrow)
	$string = preg_replace('/\${1}[SHWILON]/i', '', $string);


	if ($re_config['FEATURES'][0]['ILLUMINATE_NAMES'][0] == true) {
		// Replace too dark colors with lighter ones
		$string = preg_replace('/\${1}(000|111|222|333|444|555)/i', '\$AAA', $string);
	}


	// Convert &
	// Convert "
	// Convert '
	// Convert >
	// Convert <
	$string = str_replace(
			array(
				'&',
				'"',
				"'",
				'>',
				'<'
			),
			array(
				'&amp;',
				'&quot;',
				'&apos;',
				'&gt;',
				'&lt;'
			),
			$string
	);
	$string = stripNewlines($string);	// stripNewlines() from basic.inc.php

	return validateUTF8String($string);	// validateUTF8String() from basic.inc.php
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function mk_loadTemplates () {
	global $mk_config;


	//--------------------------------------------------------------//
	// BEGIN: Window						//
	//--------------------------------------------------------------//
	// %window_title%
	// %prev_next_buttons%
	$header  = '<manialink id="'. $mk_config['manialink_id'] .'02">';
	// Window
	$header .= '<frame posn="-40.1 30.45 -3">';	// BEGIN: Window Frame
	$header .= '<quad posn="0.8 -0.8 0.01" sizen="78.4 53.7" bgcolor="001B"/>';
	$header .= '<quad posn="-0.2 0.2 0.09" sizen="80.4 55.7" style="Bgs1InRace" substyle="BgCard3"/>';

	// Header Line
	$header .= '<quad posn="0.8 -1.3 0.02" sizen="78.4 3" bgcolor="09FC"/>';
	$header .= '<quad posn="0.8 -4.3 0.03" sizen="78.4 0.1" bgcolor="FFF9"/>';
	$header .= '<quad posn="1.8 -1.4 0.10" sizen="2.8 2.8" style="Icons128x128_1" substyle="NewTrack"/>';
	$header .= '<label posn="5.5 -1.8 0.10" sizen="74 0" halign="left" textsize="2" scale="0.9" textcolor="FFFF" text="%window_title%"/>';

	// Link and About
	$header .= '<quad posn="2.7 -54.1 0.12" sizen="14.5 1" action="'. $mk_config['manialink_id'] .'01" bgcolor="0000"/>';
	$header .= '<label posn="2.7 -54.1 0.12" sizen="30 1" halign="left" textsize="1" scale="0.7" textcolor="000F" text="MANIA-KARMA/'. $mk_config['version'] .'"/>';

	// Close Button
	$header .= '<frame posn="77.4 1.3 0">';
	$header .= '<quad posn="0 0 0.10" sizen="4 4" style="Icons64x64_1" substyle="ArrowDown"/>';
	$header .= '<quad posn="1.1 -1.35 0.11" sizen="1.8 1.75" bgcolor="EEEF"/>';
	$header .= '<quad posn="0.65 -0.7 0.12" sizen="2.6 2.6" action="'. $mk_config['manialink_id'] .'00" style="Icons64x64_1" substyle="Close"/>';
	$header .= '</frame>';

	// Navigation-Buttons
	$header .= '%prev_next_buttons%';

	// Footer
	$footer  = '</frame>';				// END: Window Frame
	$footer .= '</manialink>';

	$templates['WINDOW']['HEADER'] = $header;
	$templates['WINDOW']['FOOTER'] = $footer;

	unset($header, $footer);
	//--------------------------------------------------------------//
	// END: Window							//
	//--------------------------------------------------------------//


	return $templates;
}

?>