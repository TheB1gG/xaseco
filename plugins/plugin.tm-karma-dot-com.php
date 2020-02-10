<?php

/*
 * Plugin: TM-Karma.com
 * ~~~~~~~~~~~~~~~~~~~~
 * For a detailed description and documentation, please refer to:
 * http://labs.undef.de/XAseco1/TM-Karma.com.php
 *
 * ----------------------------------------------------------------------------------
 * Author:		undef.de
 * Version:		1.0.7
 * Date:		2012-01-08
 * Copyright:		2009 - 2012 by undef.de
 * System:		XAseco/1.14+
 * Game:		Trackmania Forever (TMF)
 * ----------------------------------------------------------------------------------
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * ----------------------------------------------------------------------------------
 * Dependencies:	plugins/plugin.localdatabase.php
 */

/* The following manialink id's are used in this plugin (the 911 part of id can be changed on trouble in tmkarma_onSync()):
 *
 * ManialinkID's
 * ~~~~~~~~~~~~~
 * 91101		id for manialink ReminderWindow and TMX-Link
 * 91102		id for manialink HelpWindow
 * 91104		id for manialink Skeleton Widget
 * 91105		id for manialink DetailWindow
 * 91106		id for manialink Player-Marker for his/her Vote
 * 91107		id for manialink Cups, Karma-Value and Karma-Votes
 *
 * ActionID's
 * ~~~~~~~~~~
 * 91102		id for action open HelpWindow
 * 91103		id for action close HelpWindow
 * 91108		id for action extended size of KarmaWidget
 * 91109		id for action default size of KarmaWidget
 * 91110		id for action vote + (1)
 * 91111		id for action vote ++ (2)
 * 91112		id for action vote +++ (3)
 * 91113		id for action vote undecided (0)
 * 91114		id for action vote - (-1)
 * 91115		id for action vote -- (-2)
 * 91116		id for action vote --- (-3)
 * 91117		id for action on disabled (red) buttons, tell the Player to finish this Track x times
 * 91118		id for (reserved for later use)
 * 91119		id for (reserved for later use)
 * 91120		id for (reserved for later use)
 */

Aseco::registerEvent('onSync',				'tmkarma_onSync');
Aseco::registerEvent('onChat',				'tmkarma_onChat');
Aseco::registerEvent('onPlayerConnect',			'tmkarma_onPlayerConnect');
Aseco::registerEvent('onPlayerDisconnect',		'tmkarma_onPlayerDisconnect');
Aseco::registerEvent('onPlayerFinish',			'tmkarma_onPlayerFinish');
Aseco::registerEvent('onNewChallenge',			'tmkarma_onNewChallenge');
Aseco::registerEvent('onNewChallenge2',			'tmkarma_onNewChallenge2');
Aseco::registerEvent('onRestartChallenge2',		'tmkarma_onRestartChallenge2');
Aseco::registerEvent('onEndRace1',			'tmkarma_onEndRace1');
Aseco::registerEvent('onPlayerManialinkPageAnswer',	'tmkarma_onPlayerManialinkPageAnswer');
Aseco::registerEvent('onKarmaChange',			'tmkarma_onKarmaChange');
Aseco::registerEvent('onShutdown',			'tmkarma_onShutdown');

Aseco::addChatCommand('karma',				'Shows karma for the current track (see: /karma help)');
Aseco::addChatCommand('+++',				'Set "Fantastic" karma for the current track');
Aseco::addChatCommand('++',				'Set "Beautiful" karma for the current track');
Aseco::addChatCommand('+',				'Set "Good" karma for the current track');
Aseco::addChatCommand('-',				'Set "Bad" karma for the current track');
Aseco::addChatCommand('--',				'Set "Poor" karma for the current track');
Aseco::addChatCommand('---',				'Set "Waste" karma for the current track');


global $tmkarma_config, $karma;
$karma = array();
$tmkarma_config = array();

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onSync
function tmkarma_onSync ($aseco) {
	global $tmkarma_config;


	// Check for the right XAseco-Version
	$xaseco_min_version = '1.14';
	if ( defined('XASECO_VERSION') ) {
		if ( version_compare(XASECO_VERSION, $xaseco_min_version, '<') ) {
			trigger_error('[plugin.tm-karma-dot-com.php] Not supported XAseco version ('. XASECO_VERSION .')! Please update to min. version '. $xaseco_min_version .'!', E_USER_ERROR);
		}
	}
	else {
		trigger_error('[plugin.tm-karma-dot-com.php] Can not identify the System, "XASECO_VERSION" is unset! This plugin runs only with XAseco/'. $xaseco_min_version .'+', E_USER_ERROR);
	}

	if ($aseco->server->getGame() != 'TMF') {
		trigger_error('[plugin.tm-karma-dot-com.php] This plugin supports only TMF, can not start with a "'. $aseco->server->getGame() .'" Dedicated-Server!', E_USER_ERROR);
	}

	// Check for dependencies
	if ( !function_exists('ldb_loadSettings') ) {
		trigger_error('[plugin.tm-karma-dot-com.php] Missing dependent plugin, please activate "plugin.localdatabase.php" in "plugins.xml" and restart.', E_USER_ERROR);
	}

	// Check for forbidden Plugins
	$forbidden = array(
		'plugin.rasp_karma.php'
	);
	foreach ($forbidden as &$plugin) {
		foreach ($aseco->plugins as &$installed_plugin) {
			if ($plugin == $installed_plugin) {
				// Found, trigger error
				trigger_error('[plugin.tm-karma-dot-com.php] This Plugin can not run with "'. $plugin .'" together, you have to remove "'. $plugin .'" from plugins.xml!', E_USER_ERROR);
			}
		}
	}
	unset($forbidden);


	// Set internal Manialink ID
	$tmkarma_config['manialink_id'] = '911';

	// Set version of this release
	$tmkarma_config['version'] = '1.0.7';

	// Register this to the global version pool (for up-to-date checks)
	$aseco->plugin_versions[] = array(
		'plugin'	=> 'plugin.tm-karma-dot-com.php',
		'author'	=> 'undef.de',
		'version'	=> $tmkarma_config['version']
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


	// Load the tmkarma.xml
	libxml_use_internal_errors(true);
	if (!$xmlcfg = @simplexml_load_file('tmkarma.xml', null, LIBXML_COMPACT) ) {
		$aseco->console("[plugin.tm-karma-dot-com.php] Could not read/parse config file 'tmkarma.xml'!");
		foreach (libxml_get_errors() as $error) {
			$aseco->console("\t". $error->message);
		}
		libxml_clear_errors();
		trigger_error("[plugin.tm-karma-dot-com.php] Please copy the 'tmkarma.xml' from this Package into the XAseco directory and do not forget to edit it!", E_USER_ERROR);
	}
	libxml_use_internal_errors(false);

	// Remove all comments
	unset($xmlcfg->comment);


	if ((string)$xmlcfg->urls->api_auth == '') {
		trigger_error("[plugin.tm-karma-dot-com.php] <urls><api_auth> is empty in config file 'tmkarma.xml'!", E_USER_ERROR);
	}

	if ((string)$xmlcfg->masterserver_account->login == '') {
		trigger_error("[plugin.tm-karma-dot-com.php] <login> is empty in config file 'tmkarma.xml'!", E_USER_ERROR);
	}
	else if ((string)$xmlcfg->masterserver_account->login == 'YOUR_SERVER_LOGIN') {
		trigger_error("[plugin.tm-karma-dot-com.php] <login> is not set in config file 'tmkarma.xml'! Please change 'YOUR_SERVER_LOGIN' with your server login.", E_USER_ERROR);
	}
	else if (strtolower($aseco->server->serverlogin) != strtolower((string)$xmlcfg->masterserver_account->login)) {
		trigger_error("[plugin.tm-karma-dot-com.php] <login> in config file 'tmkarma.xml' did not match the dedicated server login! Please set the server login from Config/dedicated_cfg.txt <dedicated><masterserver_account><login>.", E_USER_ERROR);
	}

	if ((string)$xmlcfg->masterserver_account->nation == '') {
		trigger_error("[plugin.tm-karma-dot-com.php] <nation> is empty in config file 'tmkarma.xml'!", E_USER_ERROR);
	}
	else if ((string)$xmlcfg->masterserver_account->nation == 'YOUR_SERVER_NATION') {
		trigger_error("[plugin.tm-karma-dot-com.php] <nation> is not set in config file 'tmkarma.xml'! Please change 'YOUR_SERVER_NATION' with your server nation code.", E_USER_ERROR);
	}
	else if (! $iso3166Alpha3[strtoupper((string)$xmlcfg->masterserver_account->nation)][1] ) {
		trigger_error("[plugin.tm-karma-dot-com.php] <nation> is not valid in config file 'tmkarma.xml'! Please change <nation> to valid ISO-3166 ALPHA-3 nation code!", E_USER_ERROR);
	}


	// Set Url for API-Call Auth
	$tmkarma_config['urls']['api_auth'] = (string)$xmlcfg->urls->api_auth;

	// Set connection status to 'all fine'
	$tmkarma_config['retrytime'] = 0;

	// 15 min. wait until try to reconnect
	$tmkarma_config['retrywait'] = (15 * 60);


	// Check the given config timeouts and set defaults on too low or on empty timeouts
	if ( ((int)$xmlcfg->wait_timeout < 60) || ((int)$xmlcfg->wait_timeout == '') ) {
		$tmkarma_config['wait_timeout'] = 60;
	}
	else {
		$tmkarma_config['wait_timeout'] = (int)$xmlcfg->wait_timeout;
	}
	if ( ((int)$xmlcfg->connect_timeout < 30) || ((int)$xmlcfg->connect_timeout == '') ) {
		$tmkarma_config['connect_timeout'] = 30;
	}
	else {
		$tmkarma_config['connect_timeout'] = (int)$xmlcfg->connect_timeout;
	}

	// Set login data
	$tmkarma_config['account']['login']	= strtolower((string)$aseco->server->serverlogin);
	$tmkarma_config['account']['nation']	= strtoupper((string)$xmlcfg->masterserver_account->nation);

	// Create a User-Agent-Identifier for the authentication
	$tmkarma_config['user_agent'] = 'XAseco/'. XASECO_VERSION .' tmkarma/'. $tmkarma_config['version'] .' '. $aseco->server->game .'/'. $aseco->server->build .' php/'. phpversion() .' '. php_uname('s') .'/'. php_uname('r') .' '. php_uname('m');

	$aseco->console('**********************(tm-karma.com)**********************');
	$aseco->console('plugin.tm-karma-dot-com.php/'. $tmkarma_config['version'] .' for XAseco');
	$aseco->console('Set Server location to "'. $iso3166Alpha3[$tmkarma_config['account']['nation']][0] .'"');
	$aseco->console('Trying to authenticate with central database '. $tmkarma_config['urls']['api_auth'] .' ...');

	// Generate the url for the first Auth-Request
	$api_url = sprintf("%s?Action=Auth&login=%s&name=%s&game=%s&zone=%s&nation=%s",
		$tmkarma_config['urls']['api_auth'],
		urlencode( $tmkarma_config['account']['login'] ),
		base64_encode( $aseco->server->name ),
		urlencode( $aseco->server->game ),
		urlencode( $aseco->server->zone ),
		urlencode( $tmkarma_config['account']['nation'] )
	);

	// Get the debug status
	$tmkarma_config['debug'] = ((strtoupper((string)$xmlcfg->debug) == 'TRUE') ? true : false);;

	$response = tmkarma_httpConnect($api_url, 'GET', false, $tmkarma_config['user_agent']);
	if ($response['Code'] == 200) {
		// Read the response
		if (!$xml = @simplexml_load_string($response['Message'], null, LIBXML_COMPACT) ) {
			$aseco->console('[plugin.tm-karma-dot-com.php] Could not read/parse response from tm-karma.com "'. $response['Message'] .'"!');
			$aseco->console('Connection failed with '. $response['Code'] .' ('. $response['Reason'] .') for url ['. $api_url .'], retry again later.');
			$aseco->console('**********************************************************');

			$tmkarma_config['retrytime'] = (time() + $tmkarma_config['retrywait']);

			$tmkarma_config['account']['authcode'] = false;

			// Fake import done
			$tmkarma_config['import_done'] = true;
		}
		else {
			if ((int)$xml->status == 200) {
				$tmkarma_config['account']['authcode'] = (string)$xml->authcode;
				$tmkarma_config['connected'] = true;
				$tmkarma_config['import_done'] = ((strtoupper((string)$xml->import_done) == 'TRUE') ? true : false);

				$aseco->console('Successfully started.');

				$tmkarma_config['urls']['api'] = (string)$xml->api_url;
				$aseco->console('The API set the Request-URL to "'. $tmkarma_config['urls']['api'] .'"');
				$aseco->console('**********************************************************');
			}
			else {
				$tmkarma_config['account']['authcode'] = false;
				$tmkarma_config['connected'] = false;

				// Fake import done
				$tmkarma_config['import_done'] = true;

				$aseco->console('[plugin.tm-karma-dot-com.php] Authentication failed with error code "'. $xml->status .'", votes are not possible!!!');
				$aseco->console('**********************************************************');
			}
		}
	}
	else {
		$aseco->console('Connection failed with '. $response['Code'] .' ('. $response['Reason'] .') for url ['. $api_url .'], retry again later.');
		$aseco->console('**********************************************************');

		$tmkarma_config['retrytime'] = (time() + $tmkarma_config['retrywait']);

		$tmkarma_config['account']['authcode'] = false;
		$tmkarma_config['connected'] = false;

		// Fake import done
		$tmkarma_config['import_done'] = true;
	}

	// Erase $iso3166Alpha3
	unset($iso3166Alpha3);


	// Is the position configured?
	if ( !isset($xmlcfg->reminder_window_pos_x) ) {
		$tmkarma_config['reminder_window_pos_x'] = -47.9;
	}
	else {
		$tmkarma_config['reminder_window_pos_x'] = (float)$xmlcfg->reminder_window_pos_x;
	}
	if ( !isset($xmlcfg->reminder_window_pos_y) ) {
		$tmkarma_config['reminder_window_pos_y'] = -25;
	}
	else {
		$tmkarma_config['reminder_window_pos_y'] = (float)$xmlcfg->reminder_window_pos_y;
	}


	$gamemodes = array(
		'rounds'	=> 0,
		'time_attack'	=> 1,
		'team'		=> 2,
		'laps'		=> 3,
		'stunts'	=> 4,
		'cup'		=> 5,
		'score'		=> 6,
	);
	foreach ($gamemodes as $mode => $id) {
		if ( isset($xmlcfg->karma_widget->gamemode->$mode) ) {
			$tmkarma_config['widget']['states'][$id]['enabled']		= ((strtoupper((string)$xmlcfg->karma_widget->gamemode->$mode->enabled) == 'TRUE') ? true : false);
			$tmkarma_config['widget']['states'][$id]['widget_pos_x']	= (float)($xmlcfg->karma_widget->gamemode->$mode->widget_pos_x ? $xmlcfg->karma_widget->gamemode->$mode->widget_pos_x : 0);
			$tmkarma_config['widget']['states'][$id]['widget_pos_y']	= (float)($xmlcfg->karma_widget->gamemode->$mode->widget_pos_y ? $xmlcfg->karma_widget->gamemode->$mode->widget_pos_y : 0);
		}
	}
	unset($gamemodes);


	// At startup do not send any Widgets, only after onNewChallenge2
	$tmkarma_config['widget']['startup_phase']			= true;

	// Set the current state for the KarmaWidget
	$tmkarma_config['widget']['current_state']			= $aseco->server->gameinfo->mode;

	// Set the config
	$tmkarma_config['urls']['website']				= (string)$xmlcfg->urls->website;
	$tmkarma_config['show_welcome']					= ((strtoupper((string)$xmlcfg->show_welcome) == 'TRUE')		? true : false);
	$tmkarma_config['allow_public_vote']				= ((strtoupper((string)$xmlcfg->allow_public_vote) == 'TRUE')		? true : false);
	$tmkarma_config['show_at_start']				= ((strtoupper((string)$xmlcfg->show_at_start) == 'TRUE')		? true : false);
	$tmkarma_config['show_details']					= ((strtoupper((string)$xmlcfg->show_details) == 'TRUE')		? true : false);
	$tmkarma_config['show_votes']					= ((strtoupper((string)$xmlcfg->show_votes) == 'TRUE')			? true : false);
	$tmkarma_config['show_karma']					= ((strtoupper((string)$xmlcfg->show_karma) == 'TRUE')			? true : false);
	$tmkarma_config['require_finish']				= (int)$xmlcfg->require_finish;
	$tmkarma_config['remind_to_vote']				= strtoupper((string)$xmlcfg->remind_to_vote);
	$tmkarma_config['reminder_window']				= strtoupper((string)$xmlcfg->reminder_window);
	$tmkarma_config['score_tmx_window']				= ((strtoupper((string)$xmlcfg->score_tmx_window) == 'TRUE')		? true : false);
	$tmkarma_config['messages_in_window']				= ((strtoupper((string)$xmlcfg->messages_in_window) == 'TRUE')		? true : false);
	$tmkarma_config['show_player_vote_public']			= ((strtoupper((string)$xmlcfg->show_player_vote_public) == 'TRUE')	? true : false);
	$tmkarma_config['save_karma_also_local']			= ((strtoupper((string)$xmlcfg->save_karma_also_local) == 'TRUE')	? true : false);
	$tmkarma_config['sync_global_karma_local']			= ((strtoupper((string)$xmlcfg->sync_global_karma_local) == 'TRUE')	? true : false);
	$tmkarma_config['images']['widget_open_left']			= (string)$xmlcfg->images->widget_open_left;
	$tmkarma_config['images']['widget_open_right']			= (string)$xmlcfg->images->widget_open_right;
	$tmkarma_config['images']['tmx_logo_normal']			= (string)$xmlcfg->images->tmx_logo_normal;
	$tmkarma_config['images']['tmx_logo_focus']			= (string)$xmlcfg->images->tmx_logo_focus;
	$tmkarma_config['images']['tmkarma_logo']			= (string)$xmlcfg->images->tmkarma_logo;
	$tmkarma_config['uptodate_check']				= ((strtoupper((string)$xmlcfg->uptodate_check) == 'TRUE')		? true : false);
	$tmkarma_config['uptodate_info']				= strtoupper((string)$xmlcfg->uptodate_info);

	// Config for Karma Lottery
	$tmkarma_config['karma_lottery']['enabled']			= ((strtoupper((string)$xmlcfg->karma_lottery->enabled) == 'TRUE')	? true : false);
	$tmkarma_config['karma_lottery']['minimum_players']		= ((int)$xmlcfg->karma_lottery->minimum_players ? (int)$xmlcfg->karma_lottery->minimum_players : 1);
	$tmkarma_config['karma_lottery']['coppers_win']			= (int)$xmlcfg->karma_lottery->coppers_win;
	$tmkarma_config['karma_lottery']['minimum_server_coppers']	= (int)$xmlcfg->karma_lottery->minimum_server_coppers;
	$tmkarma_config['karma_lottery']['total_payout']		= 0;

	// Check the Server Rights for TMU
	if ($tmkarma_config['karma_lottery']['enabled'] == true) {
		// Check if this is a TMU Server (false = nations account; true = united account)
		if ($aseco->server->rights == true) {
			$tmkarma_config['karma_lottery']['enabled'] = false;	// Turn lottery off on TMN
			$aseco->console('[plugin.tm-karma-dot-com.php] Lottery disabled, not possible with a TMN server account!');
		}
		else {
			$tmkarma_config['karma_lottery']['enabled'] = false;		// Turn lottery off on TMN/TMO/TMS
			$aseco->console('[plugin.tm-karma-dot-com.php] Lottery disabled, not possible with a TMN/TMO/TMS server account!');
		}
	}

	unset($xmlcfg->messages->comment);				// purge mem. usage

	// Misc. messages
	$tmkarma_config['messages']['welcome']				= (string)$xmlcfg->messages->welcome;
	$tmkarma_config['messages']['uptodate_ok']			= (string)$xmlcfg->messages->uptodate_ok;
	$tmkarma_config['messages']['uptodate_new']			= (string)$xmlcfg->messages->uptodate_new;
	$tmkarma_config['messages']['uptodate_failed']			= (string)$xmlcfg->messages->uptodate_failed;

	// Vote messages
	$tmkarma_config['messages']['karma_message']			= (string)$xmlcfg->messages->karma_message;
	$tmkarma_config['messages']['karma_your_vote']			= (string)$xmlcfg->messages->karma_your_vote;
	$tmkarma_config['messages']['karma_not_voted']			= (string)$xmlcfg->messages->karma_not_voted;
	$tmkarma_config['messages']['karma_details']			= (string)$xmlcfg->messages->karma_details;
	$tmkarma_config['messages']['karma_done']			= (string)$xmlcfg->messages->karma_done;
	$tmkarma_config['messages']['karma_change']			= (string)$xmlcfg->messages->karma_change;
	$tmkarma_config['messages']['karma_voted']			= (string)$xmlcfg->messages->karma_voted;
	$tmkarma_config['messages']['karma_remind']			= (string)$xmlcfg->messages->karma_remind;
	$tmkarma_config['messages']['karma_require_finish']		= (string)$xmlcfg->messages->karma_require_finish;
	$tmkarma_config['messages']['karma_no_public']			= (string)$xmlcfg->messages->karma_no_public;
	$tmkarma_config['messages']['karma_list_help']			= (string)$xmlcfg->messages->karma_list_help;
	$tmkarma_config['messages']['karma_help']			= (string)$xmlcfg->messages->karma_help;

	$tmkarma_config['messages']['karma_reminder_at_score']		= (string)$xmlcfg->messages->karma_reminder_at_score;
	$tmkarma_config['messages']['karma_vote_singular']		= (string)$xmlcfg->messages->karma_vote_singular;
	$tmkarma_config['messages']['karma_vote_plural']		= (string)$xmlcfg->messages->karma_vote_plural;
	$tmkarma_config['messages']['karma_you_have_voted']		= (string)$xmlcfg->messages->karma_you_have_voted;
	$tmkarma_config['messages']['karma_fantastic']			= (string)$xmlcfg->messages->karma_fantastic;
	$tmkarma_config['messages']['karma_beautiful']			= (string)$xmlcfg->messages->karma_beautiful;
	$tmkarma_config['messages']['karma_good']			= (string)$xmlcfg->messages->karma_good;
	$tmkarma_config['messages']['karma_undecided']			= (string)$xmlcfg->messages->karma_undecided;
	$tmkarma_config['messages']['karma_bad']			= (string)$xmlcfg->messages->karma_bad;
	$tmkarma_config['messages']['karma_poor']			= (string)$xmlcfg->messages->karma_poor;
	$tmkarma_config['messages']['karma_waste']			= (string)$xmlcfg->messages->karma_waste;
	$tmkarma_config['messages']['karma_show_opinion']		= (string)$xmlcfg->messages->karma_show_opinion;
	$tmkarma_config['messages']['karma_show_undecided']		= (string)$xmlcfg->messages->karma_show_undecided;

	// Lottery messages
	$tmkarma_config['messages']['lottery_mail_body']		= (string)$xmlcfg->messages->lottery_mail_body;
	$tmkarma_config['messages']['lottery_player_won']		= (string)$xmlcfg->messages->lottery_player_won;
	$tmkarma_config['messages']['lottery_low_coppers']		= (string)$xmlcfg->messages->lottery_low_coppers;
	$tmkarma_config['messages']['lottery_to_few_players']		= (string)$xmlcfg->messages->lottery_to_few_players;
	$tmkarma_config['messages']['lottery_total_player_win']		= (string)$xmlcfg->messages->lottery_total_player_win;
	$tmkarma_config['messages']['lottery_help']			= (string)$xmlcfg->messages->lottery_help;

	// Widget specific
	$tmkarma_config['widget']['race']['background_style']		= (string)$xmlcfg->widget_styles->race->background_style;
	$tmkarma_config['widget']['race']['background_substyle']	= (string)$xmlcfg->widget_styles->race->background_substyle;
	$tmkarma_config['widget']['race']['title_style']		= (string)$xmlcfg->widget_styles->race->title_style;
	$tmkarma_config['widget']['race']['title_substyle']		= (string)$xmlcfg->widget_styles->race->title_substyle;
	$tmkarma_config['widget']['score']['background_style']		= (string)$xmlcfg->widget_styles->score->background_style;
	$tmkarma_config['widget']['score']['background_substyle']	= (string)$xmlcfg->widget_styles->score->background_substyle;
	$tmkarma_config['widget']['score']['title_style']		= (string)$xmlcfg->widget_styles->score->title_style;
	$tmkarma_config['widget']['score']['title_substyle']		= (string)$xmlcfg->widget_styles->score->title_substyle;


	// Define the formats for number_format()
	$tmkarma_config['number_format'] = strtolower((string)$xmlcfg->number_format);
	$tmkarma_config['NumberFormat'] = array(
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


	// Get required data of Challenge
	$current_challenge = tmkarma_getChallengeInfo();

	// Prebuild the Widgets
	$tmkarma_config['widget']['skeleton']['race']			= tmkarma_buildKarmaWidget($current_challenge->uid, $tmkarma_config['widget']['current_state']);
	$tmkarma_config['widget']['skeleton']['score']			= tmkarma_buildKarmaWidget($current_challenge->uid, 6);		// 6 = Score


	// Add "/karma lottery" to "/karma help" if lottery is enabled
	if ($tmkarma_config['karma_lottery']['enabled'] == true) {
		$tmkarma_config['messages']['karma_help'] .= $tmkarma_config['messages']['lottery_help'];
	}

	// Split long message
	$tmkarma_config['messages']['karma_help'] = str_replace('{br}', LF, $aseco->formatColors($tmkarma_config['messages']['karma_help']));

	// Free mem.
	unset($xmlcfg);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onShutdown
function tmkarma_onShutdown ($aseco) {


	// Save all Votes into the global and local (if enabled) Database
	tmkarma_saveKarmaVotes();
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onChat
function tmkarma_onChat ($aseco, $chat) {
	global $tmkarma_config;


	// If server message, bail out immediately
	if ($chat[0] == $aseco->server->id) return;

	// Check if public vote is enabled
	if ($tmkarma_config['allow_public_vote'] == true) {

		// Get Player-Object
		$player = $aseco->server->players->getPlayer($chat[1]);

		// check for possible public karma vote
		if ($chat[2] == '+++') {
			tmkarma_doPlayerVote($player, 3);
		}
		else if ($chat[2] == '++') {
			tmkarma_doPlayerVote($player, 2);
		}
		else if ($chat[2] == '+') {
			tmkarma_doPlayerVote($player, 1);
		}
		else if ($chat[2] == '-') {
			tmkarma_doPlayerVote($player, -1);
		}
		else if ($chat[2] == '--') {
			tmkarma_doPlayerVote($player, -2);
		}
		else if ($chat[2] == '---') {
			tmkarma_doPlayerVote($player, -3);
		}
	}
	else if ( ($chat[2] == '+++') || ($chat[2] == '++') || ($chat[2] == '+') || ($chat[2] == '-') || ($chat[2] == '--') || ($chat[2] == '---') ) {

		// Get Player-Object
		$player = $aseco->server->players->getPlayer($chat[1]);

		$message = formatText($tmkarma_config['messages']['karma_no_public'], '/'. $chat[2]);
		if ( ($tmkarma_config['messages_in_window'] == true) && (function_exists('send_window_message')) ) {
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
	global $tmkarma_config, $karma;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called chat_karma() with commands '. $command['params']);
	}

	// Init
	$message = false;

	// Check optional parameter
	if ( (strtoupper($command['params']) == 'HELP') || (strtoupper($command['params']) == 'ABOUT') ) {

		$command['author']->data['KarmaWidgetStatus'] = 'default';
		tmkarma_sendKarmaDetailWindow($command['author'], false);

		tmkarma_sendHelpAboutWindow($command['author'], $tmkarma_config['messages']['karma_help'], true);
	}
	else if (strtoupper($command['params']) == 'DETAILS') {
		$message = formatText($tmkarma_config['messages']['karma_details'],
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
			$aseco->console('[plugin.tm-karma-dot-com.php] MasterAdmin '. $command['author']->login .' reloads the configuration.');
			$message = '{#admin}Reloading the configuration "tmkarma.xml" now.';
			tmkarma_onSync($aseco);
		}
	}
	else if (strtoupper($command['params']) == 'DEBUG') {
		if ($aseco->isMasterAdmin($command['author'])) {
			if ($tmkarma_config['debug'] == true) {
				$aseco->console('[plugin.tm-karma-dot-com.php] MasterAdmin '. $command['author']->login .' turns debugging off.');
				$message = '{#admin}> [plugin.tm-karma-dot-com.php] Debug is now disabled.';
				$tmkarma_config['debug'] = 'false';	// as string
			}
			else {
				$aseco->console('[plugin.tm-karma-dot-com.php] MasterAdmin '. $command['author']->login .' turns debugging on.');
				$message = '{#admin}> [plugin.tm-karma-dot-com.php] Debug is now enabled.';
				$tmkarma_config['debug'] = 'true';	// as string
			}
		}
	}
	else if (strtoupper($command['params']) == 'EXPORT') {
		if ($aseco->isMasterAdmin($command['author'])) {
			$aseco->console('[plugin.tm-karma-dot-com.php] MasterAdmin '. $command['author']->login .' start the export of all local votes.');
			tmkarma_exportVotes($command['author']);
		}
	}
	else if (strtoupper($command['params']) == 'UPTODATE') {
		if ($aseco->isMasterAdmin($command['author'])) {
			$aseco->console('[plugin.tm-karma-dot-com.php] MasterAdmin '. $command['author']->login .' start the up-to-date check.');
			tmkarma_uptodateCheck($command['author']);
		}
	}
	else if ( (strtoupper($command['params']) == 'LOTTERY') && ($tmkarma_config['karma_lottery']['enabled'] == true) ) {
		if ($command['author']->rights) {
			$message = formatText($tmkarma_config['messages']['lottery_total_player_win'],
				$command['author']->data['KarmaLotteryPayout']
			);
		}
	}
	else if (strtoupper($command['params']) == '') {
		$message = tmkarma_createKarmaMessage($command['author']->login, true);
	}

	// Show message
	if ($message != false) {
		if ( ($tmkarma_config['messages_in_window'] == true) && (function_exists('send_window_message')) && ($tmkarma_config['widget']['current_state'] != 6) ) {
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
	global $tmkarma_config;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called chat_plusplusplus()');
	}
	tmkarma_doPlayerVote($command['author'], 3);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function chat_plusplus ($aseco, $command) {
	global $tmkarma_config;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called chat_plusplus()');
	}
	tmkarma_doPlayerVote($command['author'], 2);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function chat_plus ($aseco, $command) {
	global $tmkarma_config;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called chat_plus()');
	}
	tmkarma_doPlayerVote($command['author'], 1);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function chat_dashdashdash ($aseco, $command) {
	global $tmkarma_config;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called chat_dashdashdash()');
	}
	tmkarma_doPlayerVote($command['author'], -3);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function chat_dashdash ($aseco, $command) {
	global $tmkarma_config;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called chat_dashdash()');
	}
	tmkarma_doPlayerVote($command['author'], -2);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function chat_dash ($aseco, $command) {
	global $tmkarma_config;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called chat_dash()');
	}
	tmkarma_doPlayerVote($command['author'], -1);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onKarmaChange
function tmkarma_onKarmaChange ($aseco, $unused) {
	global $tmkarma_config;


	// Update the KarmaWidget for all Players
	tmkarma_sendWidgetCombination(array('cups_values'), false);

	// Build the cached DetailedWindow
	$tmkarma_config['widget']['skeleton']['details'] = tmkarma_buildKarmaDetailWindow();
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onPlayerConnect
function tmkarma_onPlayerConnect ($aseco, $player) {
	global $tmkarma_config, $karma;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_onPlayerConnect()');
	}


	// Show welcome message to the new player?
	if ($tmkarma_config['show_welcome'] == true) {
		$message = formatText($tmkarma_config['messages']['welcome'],
				'http://'. $tmkarma_config['urls']['website'] .'/',
				$tmkarma_config['urls']['website']
		);
		$message = str_replace('{br}', LF, $message);  // split long message
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
	}


	// Do UpToDate check?
	if ($tmkarma_config['uptodate_check'] == true) {
		// Check for a master admin
		if ($aseco->isMasterAdmin($player)) {
			tmkarma_uptodateCheck($player);
		}
	}


	// Check for a master admin and if the export was not yet done
	if ( ($aseco->isMasterAdmin($player)) && ($tmkarma_config['import_done'] == false) ) {
		$message = '{#server}> {#emotic}#################################################'. LF;
		$message .= '{#server}> {#emotic}Please start the export of your current local votes with the command "/karma export". Thanks!'. LF;
		$message .= '{#server}> {#emotic}#################################################'. LF;
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
	}


	// If karma lottery is enabled, then initialize (if player has related rights)
	if ( ($tmkarma_config['karma_lottery']['enabled'] == true) && ($player->rights) ) {
		$player->data['KarmaLotteryPayout'] = 0;
	}


	// Init the 'KarmaWidgetStatus' and 'KarmaReminderWindow' to the defaults
	$player->data['KarmaWidgetStatus'] = 'default';
	$player->data['KarmaReminderWindow'] = false;

	// Set PlayerId (from Database), cache the Id here for later use
	$player->data['KarmaDatabasePlayerId'] = $aseco->getPlayerId( $player->login );


	// Preset false
	$current_challenge = false;


	// Check if finishes are required
	if ($tmkarma_config['require_finish'] > 0) {

		// Set default
		$player->data['KarmaPlayerFinishedMap'] = 0;

		// Get required data for Challenge
		$current_challenge = tmkarma_getChallengeInfo();

		// Find the amount of finish for this Player
		tmkarma_findPlayersLocalRecords($current_challenge->id, array($player));
	}


	// Do nothing at Startup!!
	if ($tmkarma_config['widget']['startup_phase'] != true) {
		// Check if Player is already in $karma,
		// for "unwished disconnects" and "reconnected" Players
		if ( ( !isset($karma['global']['players'][$player->login]) ) || ($aseco->server->challenge->uid != $karma['data']['uid']) ) {

			// Get required data for Challenge
			if ($current_challenge == false) {
				$current_challenge = tmkarma_getChallengeInfo();
			}

			// Get the Karma from remote for this Player
			$result = tmkarma_doKarmaRemoteCall($current_challenge, $player);

			// Update the global $karma, only if this is not already loaded (do not override possible new votes)
			if ( (isset($karma['new']['players'])) && (count($karma['new']['players']) == 0) ) {
				$karma['global']['votes']['fantastic']['percent']	= $result['global']['votes']['fantastic']['percent'];
				$karma['global']['votes']['fantastic']['count']		= $result['global']['votes']['fantastic']['count'];
				$karma['global']['votes']['beautiful']['percent']	= $result['global']['votes']['beautiful']['percent'];
				$karma['global']['votes']['beautiful']['count']		= $result['global']['votes']['beautiful']['count'];
				$karma['global']['votes']['good']['percent']		= $result['global']['votes']['good']['percent'];
				$karma['global']['votes']['good']['count']		= $result['global']['votes']['good']['count'];

				$karma['global']['votes']['bad']['percent']		= $result['global']['votes']['bad']['percent'];
				$karma['global']['votes']['bad']['count']		= $result['global']['votes']['bad']['count'];
				$karma['global']['votes']['poor']['percent']		= $result['global']['votes']['poor']['percent'];
				$karma['global']['votes']['poor']['count']		= $result['global']['votes']['poor']['count'];
				$karma['global']['votes']['waste']['percent']		= $result['global']['votes']['waste']['percent'];
				$karma['global']['votes']['waste']['count']		= $result['global']['votes']['waste']['count'];

				$karma['global']['votes']['karma']			= $result['global']['votes']['karma'];
				$karma['global']['votes']['total']			= $result['global']['votes']['total'];
			}

			$karma['global']['players'][$player->login]['vote']		= $result['global']['players'][$player->login]['vote'];
			$karma['global']['players'][$player->login]['previous']		= $result['global']['players'][$player->login]['previous'];


			if ( !isset($karma['local']['players'][$player->login]) ) {
				// Get the local votes for this Player
				tmkarma_getLocalVotes($current_challenge->id, $player->login);
			}

			// Check to see if it is required to sync global to local votes?
			if ($tmkarma_config['sync_global_karma_local'] == true) {
				tmkarma_syncGlobaAndLocalVotes('local');
			}

			// Build the cached DetailedWindow
			$tmkarma_config['widget']['skeleton']['details'] = tmkarma_buildKarmaDetailWindow();
		}

		// Display the complete KarmaWidget only for connected Player
		if ($tmkarma_config['widget']['current_state'] == 6) {
			tmkarma_sendWidgetCombination(array('skeleton_score', 'cups_values', 'player_marker'), $player);
		}
		else {
			tmkarma_sendWidgetCombination(array('skeleton_race', 'cups_values', 'player_marker'), $player);
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onPlayerDisconnect
function tmkarma_onPlayerDisconnect ($aseco, $player) {
	global $tmkarma_config;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_onPlayerDisconnect(), player "'. $player->login .'" has "'. (isset($player->data['KarmaLotteryPayout']) ? $player->data['KarmaLotteryPayout'] : 0) .'" coppers to payout.');
	}

	// Need to pay coppers for lottery wins to this player?
	if ( ($tmkarma_config['karma_lottery']['enabled'] == true) && ($player->rights) ) {

		if ($player->data['KarmaLotteryPayout'] > 0) {
			// Pay coppers to player
			$message = formatText($tmkarma_config['messages']['lottery_mail_body'],
				$aseco->server->name,
				(int)$player->data['KarmaLotteryPayout'],
				$tmkarma_config['account']['login']
			);
			$message = str_replace('{br}', "%0A", $message);  // split long message

			$aseco->client->resetError();
			$aseco->client->query('Pay', (string)$player->login, (int)$player->data['KarmaLotteryPayout'], (string)$aseco->formatColors($message) );
			$billid = $aseco->client->getResponse();

			// Is there an error on pay?
			if ( $aseco->client->isError() ) {
				$aseco->console('[plugin.tm-karma-dot-com.php] (tm-karm.com lottery) Pay '. $player->data['KarmaLotteryPayout'] .' coppers to player "'. $player->login .'" failed: [' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage());
			}
			else {
				$aseco->console('[plugin.tm-karma-dot-com.php] (tm-karm.com lottery) Pay '. $player->data['KarmaLotteryPayout'] .' coppers to player "'. $player->login .'" done. (BillId #'. $billid .')');
			}

			// Subtract payed amounts from total (on error too, because player leaved)
			$tmkarma_config['karma_lottery']['total_payout'] -= (int)$player->data['KarmaLotteryPayout'];
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onPlayerFinish
function tmkarma_onPlayerFinish ($aseco, $finish_item) {
	global $tmkarma_config, $karma;


	// If no actual finish, bail out immediately
	if ($finish_item->score == 0) {
		return;
	}

	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_onPlayerFinish() Player '. $finish_item->player->login .' finished with score of "'. $finish_item->score .'"');
	}

	// Check if finishes are required
	if ($tmkarma_config['require_finish'] > 0) {
		// Save that the player finished this map
		$finish_item->player->data['KarmaPlayerFinishedMap'] += 1;

		// Enable the vote possibilities for this player
		tmkarma_sendWidgetCombination(array('player_marker'), $finish_item->player);
	}

	// If no finish reminders, bail out too (does not need to check $player->data['KarmaPlayerFinishedMap'], because actually finished ;)
	if ( ($tmkarma_config['remind_to_vote'] == 'FINISHED') || ($tmkarma_config['remind_to_vote'] == 'ALWAYS') ) {

		// Check whether player already voted
		if ( ($karma['global']['players'][$finish_item->player->login]['vote'] == 0) && ( ($tmkarma_config['require_finish'] > 0) && ($finish_item->data['KarmaPlayerFinishedMap'] < $tmkarma_config['require_finish']) ) ) {
			if ( ($tmkarma_config['reminder_window'] == 'FINISHED') || ($tmkarma_config['reminder_window'] == 'ALWAYS') ) {
				// Show reminder window
				tmkarma_showReminderWindow($finish_item->player->login);
				$finish_item->data['KarmaReminderWindow'] = true;
			}
			else {
				// Show reminder message
				$message = $tmkarma_config['messages']['karma_remind'];
				if ( ($tmkarma_config['messages_in_window'] == true) && (function_exists('send_window_message')) ) {
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

// called @ onPlayerManialinkPageAnswer
function tmkarma_onPlayerManialinkPageAnswer ($aseco, $answer) {
	global $tmkarma_config;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_onPlayerManialinkPageAnswer() with answer "'. $answer[2] .'" from Player "'. $answer[1] .'"');
	}

	// If id = 0, bail out immediately
	if ($answer[2] == 0) {
		return;
	}

	// Get Player
	$command['author'] = $aseco->server->players->getPlayer($answer[1]);


	if ($answer[2] == $tmkarma_config['manialink_id'] .'02') {		// Open HelpWindow

		$command['author']->data['KarmaWidgetStatus'] = 'default';
		tmkarma_sendHelpAboutWindow($command['author'], $tmkarma_config['messages']['karma_help'], true);
	}
	else if ($answer[2] == $tmkarma_config['manialink_id'] .'03') {		// Close HelpWindow
		tmkarma_sendHelpAboutWindow($command['author'], false, false);
	}
	else if ($answer[2] == $tmkarma_config['manialink_id'] .'08') {		// Extended KarmaWidget

		// Enable the switch for the Player to hold the old behavior
		if ($command['author']->data['KarmaWidgetStatus'] == 'default') {
			$command['author']->data['KarmaWidgetStatus'] = 'extended';
			tmkarma_sendKarmaDetailWindow($command['author'], true);
		}
		else {
			$command['author']->data['KarmaWidgetStatus'] = 'default';
			tmkarma_sendKarmaDetailWindow($command['author'], false);
		}
	}
	else if ($answer[2] == $tmkarma_config['manialink_id'] .'09') {		// Default KarmaWidget
		$command['author']->data['KarmaWidgetStatus'] = 'default';
		tmkarma_sendKarmaDetailWindow($command['author'], false);
	}
	else if ($answer[2] == $tmkarma_config['manialink_id'] .'12') {		// Vote +++
		tmkarma_doPlayerVote($command['author'], 3);
	}
	else if ($answer[2] == $tmkarma_config['manialink_id'] .'11') {		// Vote ++
		tmkarma_doPlayerVote($command['author'], 2);
	}
	else if ($answer[2] == $tmkarma_config['manialink_id'] .'10') {		// Vote +
		tmkarma_doPlayerVote($command['author'], 1);
	}
	else if ($answer[2] == $tmkarma_config['manialink_id'] .'13') {		// Vote undecided
		tmkarma_showUndecidedMessage($command);
	}
	else if ($answer[2] == $tmkarma_config['manialink_id'] .'14') {		// Vote -
		tmkarma_doPlayerVote($command['author'], -1);
}
	else if ($answer[2] == $tmkarma_config['manialink_id'] .'15') {		// Vote --
		tmkarma_doPlayerVote($command['author'], -2);
	}
	else if ($answer[2] == $tmkarma_config['manialink_id'] .'16') {		// Vote ---
		tmkarma_doPlayerVote($command['author'], -3);
	}
	else if ($answer[2] == $tmkarma_config['manialink_id'] .'17') {		// Vote disabled on <require_finish> >= 1
		tmkarma_doPlayerVote($command['author'], 0);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onNewChallenge
function tmkarma_onNewChallenge ($aseco, $challenge) {
	global $tmkarma_config;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_onNewChallenge()');
	}

	// Set $gamemode for the KarmaWidget
	$tmkarma_config['widget']['current_state'] = $aseco->server->gameinfo->mode;

	// Close at all Players the reminder window
	tmkarma_closeReminderWindow(false);

	// Remove all marker at all connected Players
	tmkarma_sendWidgetCombination(array('hide_all'), false);

	// Save all Votes into the global and local (if enabled) Database
	tmkarma_saveKarmaVotes();

	if ($tmkarma_config['require_finish'] > 0) {
		// Remove the state that the player has finished this map (it is an new map now)
		// MUST placed here _BEFORE_ tmkarma_doKarmaRemoteCall() call, this sets
		// $player->data['KarmaPlayerFinishedMap'] to true if the player has voted this map
		foreach ($aseco->server->players->player_list as &$player) {
			$player->data['KarmaPlayerFinishedMap'] = 0;
		}
		unset($player);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onNewChallenge2
function tmkarma_onNewChallenge2 ($aseco, $challenge) {
	global $tmkarma_config, $karma;


	// Only now the Startup is finished (when XAseco was started)
	$tmkarma_config['widget']['startup_phase'] = false;

	// Reset and Setup data for MultiVotes
	$karma = array();
	$karma['data']['uid']		= $challenge->uid;
	$karma['data']['id']		= $challenge->id;
	$karma['data']['name']		= $challenge->name;
	$karma['data']['author']	= $challenge->author;
	$karma['data']['env']		= $challenge->environment;
	$karma['data']['tmx']		= (isset($challenge->tmx->id) ? $challenge->tmx->id : '');
	$karma['new']['players']	= array();

	// Get the local karma
	tmkarma_getLocalKarma($challenge->id);


	// If there no players, bail out
	if (count($aseco->server->players->player_list) == 0) {
		return;
	}

	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_onNewChallenge2()');
	}

	// If <require_finish> is enabled
	if ($tmkarma_config['require_finish'] > 0) {
		// Find the amount of finish for all Players
		tmkarma_findPlayersLocalRecords($challenge->id, $aseco->server->players->player_list);
	}

	// Replace $karma from last Challenge with $karma of the current Challenge
	$result = tmkarma_doKarmaRemoteCall($challenge, false);

	$karma['global']['votes']['fantastic']['percent']		= $result['global']['votes']['fantastic']['percent'];
	$karma['global']['votes']['fantastic']['count']			= $result['global']['votes']['fantastic']['count'];
	$karma['global']['votes']['beautiful']['percent']		= $result['global']['votes']['beautiful']['percent'];
	$karma['global']['votes']['beautiful']['count']			= $result['global']['votes']['beautiful']['count'];
	$karma['global']['votes']['good']['percent']			= $result['global']['votes']['good']['percent'];
	$karma['global']['votes']['good']['count']			= $result['global']['votes']['good']['count'];

	$karma['global']['votes']['bad']['percent']			= $result['global']['votes']['bad']['percent'];
	$karma['global']['votes']['bad']['count']			= $result['global']['votes']['bad']['count'];
	$karma['global']['votes']['poor']['percent']			= $result['global']['votes']['poor']['percent'];
	$karma['global']['votes']['poor']['count']			= $result['global']['votes']['poor']['count'];
	$karma['global']['votes']['waste']['percent']			= $result['global']['votes']['waste']['percent'];
	$karma['global']['votes']['waste']['count']			= $result['global']['votes']['waste']['count'];

	$karma['global']['votes']['karma']				= $result['global']['votes']['karma'];
	$karma['global']['votes']['total']				= $result['global']['votes']['total'];

	foreach ($result['global']['players'] as $login => &$votes) {
		$karma['global']['players'][$login]['vote']		= $votes['vote'];
		$karma['global']['players'][$login]['previous']		= $votes['previous'];
	}
	unset($login, $votes);


	// Get the local votes for all Players
	tmkarma_getLocalVotes($challenge->id, false);

	// Check to see if it is required to sync global to local votes?
	if ($tmkarma_config['sync_global_karma_local'] == true) {
		tmkarma_syncGlobaAndLocalVotes('local');
	}

	// Now sync local votes to global votes (e.g. on connection lost...)
	tmkarma_syncGlobaAndLocalVotes('global');

	// Rebuild the Widget, it is an new Map (and possible Gamemode)
	$tmkarma_config['widget']['skeleton']['race']		= tmkarma_buildKarmaWidget($challenge->uid, $aseco->server->gameinfo->mode);
	$tmkarma_config['widget']['skeleton']['score']		= tmkarma_buildKarmaWidget($challenge->uid, 6);		// 6 = Score
	$tmkarma_config['widget']['skeleton']['details']	= tmkarma_buildKarmaDetailWindow();

	// Update KarmaWidget for all connected Players
	tmkarma_sendWidgetCombination(array('skeleton_race', 'cups_values'), false);


	// Refresh the Player-Marker for all Players
	foreach ($aseco->server->players->player_list as &$player) {

		// Reset the Widget-Status
		$player->data['KarmaWidgetStatus'] = 'default';

		// Display the Marker
		tmkarma_sendWidgetCombination(array('player_marker'), $player);
	}
	unset($player);



	// Display the Karma value of Challenge?
	if ($tmkarma_config['show_at_start'] == true) {
		// Show players' actual votes, or global karma message?
		if ($tmkarma_config['show_votes'] == true) {
			// Send individual player messages
			foreach ($aseco->server->players->player_list as &$player) {
				tmkarma_sendTrackKarmaMessage($player->login);
			}
			unset($player);
		}
		else {
			// Send more efficient global message
			tmkarma_sendTrackKarmaMessage(false);
		}
	}



	// Before draw a lottery winner, check if players has already voted, if lottery is enabled and if players has related rights (TMU)
	if ($tmkarma_config['karma_lottery']['enabled'] == true) {

		// Init message
		$message = false;

		// Is there not enough player on, bail out
		if (count($aseco->server->players->player_list) < $tmkarma_config['karma_lottery']['minimum_players']) {
			// Show to few players message to all players
			$message = $tmkarma_config['messages']['lottery_to_few_players'];
		}
		else {
			// Can all Player be paid with the new total? Add only Coppers if Server is over minimum.
			if ((tmkarma_getServerCoppers() - $tmkarma_config['karma_lottery']['minimum_server_coppers']) > ($tmkarma_config['karma_lottery']['total_payout'] + $tmkarma_config['karma_lottery']['coppers_win']) ) {

				// Init the lottery array
				$lottery_attendant = array();

				// Check all connected Players (TMU only) if they has voted
				foreach ($aseco->server->players->player_list as &$player) {
					if ( ($karma['global']['players'][$player->login]['vote'] != 0) && ($player->rights) ) {
						array_push($lottery_attendant, $player->login);
					}
				}
				unset($player);

				// Are enough TMU Player online and has voted?
				if (count($lottery_attendant) >= $tmkarma_config['karma_lottery']['minimum_players']) {
					// Drawing of the lottery ("and the winner is")
					$winner = array_rand($lottery_attendant, 1);

					// If the Player is not already gone, go ahead
					$player = $aseco->server->players->getPlayer($lottery_attendant[$winner]);
					if ($player != false) {
						// Add to Players total
						$player->data['KarmaLotteryPayout'] += $tmkarma_config['karma_lottery']['coppers_win'];

						// Add to total payout
						$tmkarma_config['karma_lottery']['total_payout'] += $tmkarma_config['karma_lottery']['coppers_win'];

						// Show won message to all Players
						$message = formatText($tmkarma_config['messages']['lottery_player_won'],
								$player->nickname,
								$tmkarma_config['karma_lottery']['coppers_win']
						);
					}
					else {
						// Show to few Players message to all Players
						$message = $tmkarma_config['messages']['lottery_to_few_players'];
					}
				}
				else {
					// Show to few players message to all players
					$message = $tmkarma_config['messages']['lottery_to_few_players'];
				}
			}
			else {
				// Show low coppers message to all Players
				$message = $tmkarma_config['messages']['lottery_low_coppers'];
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

// called @ onRestartChallenge2
function tmkarma_onRestartChallenge2 ($aseco, $challenge) {
	global $tmkarma_config;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_onRestartChallenge2()');
	}

	// Close at all Players the reminder window
	tmkarma_closeReminderWindow(false);

	// Set $gamemode for the KarmaWidget
	$tmkarma_config['widget']['current_state'] = $aseco->server->gameinfo->mode;


	// Make sure the Widget gets updated at all Players at Race
	tmkarma_sendWidgetCombination(array('skeleton_race', 'cups_values'), false);


	// Display the Marker
	foreach ($aseco->server->players->player_list as &$player) {
		// Update KarmaWidget only for given Player
		tmkarma_sendWidgetCombination(array('player_marker'), $player);
	}
	unset($player);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// called @ onEndRace1
function tmkarma_onEndRace1 ($aseco, $data) {
	global $tmkarma_config, $karma;


	// If there no players, bail out immediately
	if (count($aseco->server->players->player_list) == 0) {
		return;
	}

	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_onEndRace1()');
	}


	// Finished run, set 'SCORE' for the KarmaWidget
	$tmkarma_config['widget']['current_state'] = 6;


	// Update KarmaWidget for all connected Players
	tmkarma_sendWidgetCombination(array('skeleton_score', 'cups_values', 'close_extended'), false);

	// Refresh the Player-Marker for all Players
	foreach ($aseco->server->players->player_list as &$player) {
		// Update KarmaWidget only for given Player
		tmkarma_sendWidgetCombination(array('player_marker'), $player);
	}
	unset($player);


	// If no end race reminders, bail out immediately
	if ( ($tmkarma_config['remind_to_vote'] == 'SCORE') || ($tmkarma_config['remind_to_vote'] == 'ALWAYS') ) {

		// Check all connected Players
		$players_reminder = array();
		foreach ($aseco->server->players->player_list as &$player) {

			// Skip if Player did not finished the map but it is required to vote
			if ( ($tmkarma_config['require_finish'] > 0) && ($player->data['KarmaPlayerFinishedMap'] < $tmkarma_config['require_finish']) ) {
				continue;
			}

			// Check whether Player already voted
			if ($karma['global']['players'][$player->login]['vote'] == 0) {
				$players_reminder[] = $player->login;
				$player->data['KarmaReminderWindow'] = true;
			}
			else if ($tmkarma_config['score_tmx_window'] == true) {
				// Show the MX-Link-Window
				tmkarma_showTmxLinkWindow($player);
			}
		}
		unset($player);

		if (count($players_reminder) > 0) {
			if ( ($tmkarma_config['reminder_window'] == 'SCORE') || ($tmkarma_config['reminder_window'] == 'ALWAYS') ) {
				// Show reminder Window
				tmkarma_showReminderWindow(implode(',', $players_reminder));
			}
			else {
				// Show reminder message (not to the TMF-Message Window)
				$message = $tmkarma_config['messages']['karma_remind'];
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), implode(',', $players_reminder));
			}
		}
		unset($players_reminder);

	}
	else if ($tmkarma_config['score_tmx_window'] == true) {
		// Check all connected Players
		foreach ($aseco->server->players->player_list as &$player) {

			// Get current Player status and ignore Spectators
			if ( $aseco->isSpectator($player) ) {
				continue;
			}

			// Check whether Player already voted
			if ($karma['global']['players'][$player->login]['vote'] != 0) {
				// Show the MX-Link-Window
				tmkarma_showTmxLinkWindow($player);
			}
		}
		unset($player);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function tmkarma_sendWidgetCombination ($widgets, $player = false) {
	global $aseco, $tmkarma_config;


	// If there no players, bail out immediately
	if (count($aseco->server->players->player_list) == 0) {
		return;
	}

	$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';

	// Possible parameters: 'skeleton_race', 'skeleton_score', 'cups_values', 'player_marker', 'close_extended' and 'hide_all'
	foreach ($widgets as $widget) {
		if ($widget == 'hide_all') {
			$xml .= '<manialink id="'. $tmkarma_config['manialink_id'] .'04"></manialink>';	// The hole Widget
			$xml .= '<manialink id="'. $tmkarma_config['manialink_id'] .'05"></manialink>';	// DetailWindow
			$xml .= '<manialink id="'. $tmkarma_config['manialink_id'] .'06"></manialink>';	// PlayerVoteMarker
			$xml .= '<manialink id="'. $tmkarma_config['manialink_id'] .'07"></manialink>';	// KarmaCupsValue
			break;
		}
		else if ($widget == 'close_extended') {
			$xml .= '<manialink id="'. $tmkarma_config['manialink_id'] .'05"></manialink>';
		}

		if ($tmkarma_config['widget']['states'][$tmkarma_config['widget']['current_state']]['enabled'] == true) {
			if ($widget == 'skeleton_race') {
				$xml .= $tmkarma_config['widget']['skeleton']['race'];
			}
			else if ($widget == 'skeleton_score') {
				$xml .= $tmkarma_config['widget']['skeleton']['score'];
			}
			else if ($widget == 'cups_values') {
				$xml .= tmkarma_buildKarmaCupsValue($tmkarma_config['widget']['current_state']);
			}
			else if ($widget == 'player_marker') {
				$xml .= tmkarma_buildPlayerVoteMarker($player, $tmkarma_config['widget']['current_state']);
			}
		}
		else {
			$xml .= '<manialink id="'. $tmkarma_config['manialink_id'] .'04"></manialink>';	// The hole Widget
			$xml .= '<manialink id="'. $tmkarma_config['manialink_id'] .'06"></manialink>';	// PlayerVoteMarker
			$xml .= '<manialink id="'. $tmkarma_config['manialink_id'] .'07"></manialink>';	// KarmaCupsValue
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

function tmkarma_buildKarmaWidget ($challenge_uid, $gamemode) {
	global $aseco, $tmkarma_config;


	// No Placeholder here!
	$xml = '<manialink id="'. $tmkarma_config['manialink_id'] .'04">';

	// MainWidget Frame
	$xml .= '<frame posn="'. $tmkarma_config['widget']['states'][$gamemode]['widget_pos_x'] .' '. $tmkarma_config['widget']['states'][$gamemode]['widget_pos_y'] .' 10">';
	if ($gamemode == 6) {
		// No action to open the full widget at 'Score'
		$xml .= '<quad posn="0 0 0" sizen="15.5 10.75" style="'. $tmkarma_config['widget']['score']['background_style'] .'" substyle="'. $tmkarma_config['widget']['score']['background_substyle'] .'"/>';
	}
	else {
		$xml .= '<quad posn="0 0 0" sizen="15.5 10.75" action="'. $tmkarma_config['manialink_id'] .'08" style="'. $tmkarma_config['widget']['race']['background_style'] .'" substyle="'. $tmkarma_config['widget']['race']['background_substyle'] .'"/>';
		if ($tmkarma_config['widget']['states'][$gamemode]['widget_pos_x'] > 0) {
			$xml .= '<quad posn="-0.3 -7.4 0.05" sizen="3.5 3.5" image="'. $tmkarma_config['images']['widget_open_left'] .'"/>';
		}
		else {
			$xml .= '<quad posn="12.2 -7.4 0.05" sizen="3.5 3.5" image="'. $tmkarma_config['images']['widget_open_right'] .'"/>';
		}
	}

	// Vote Frame, different offset on default widget
	$xml .= '<frame posn="0 0 0">';


	// Window title
	if ($gamemode == 6) {
		$xml .= '<quad posn="0.4 -0.36 3" sizen="14.7 2" url="http://'. $tmkarma_config['urls']['website'] .'/Goto?uid='. $challenge_uid .'" style="'. $tmkarma_config['widget']['score']['title_style'] .'" substyle="'. $tmkarma_config['widget']['score']['title_substyle'] .'"/>';
	}
	else {
		$xml .= '<quad posn="0.4 -0.36 3" sizen="14.7 2" url="http://'. $tmkarma_config['urls']['website'] .'/Goto?uid='. $challenge_uid .'" style="'. $tmkarma_config['widget']['race']['title_style'] .'" substyle="'. $tmkarma_config['widget']['race']['title_substyle'] .'"/>';
	}
	if ($tmkarma_config['widget']['states'][$gamemode]['widget_pos_x'] > 0) {
		$xml .= '<quad posn="0.6 0 3.1" sizen="2.6 2.6" style="Icons128x128_1" substyle="NewTrack"/>';
		$xml .= '<label posn="3.2 -0.5 3.2" sizen="10 0" halign="left" textsize="1" text="$EEEtm-karma.com"/>';
	}
	else {
		$xml .= '<quad posn="12.5 0 3.1" sizen="2.6 2.6" style="Icons128x128_1" substyle="NewTrack"/>';
		$xml .= '<label posn="12.4 -0.5 3.2" sizen="10 0" halign="right" textsize="1" text="$EEEtm-karma.com"/>';
	}


	// + Button
	$xml .= '<frame posn="1.95 -7 1">';
	$xml .= '<label posn="0.2 -0.08 0.1" sizen="3.45 1.4" action="'. $tmkarma_config['manialink_id'] .'10" focusareacolor1="EEEF" focusareacolor2="FFFF" text=" "/>';
	$xml .= '<label posn="1.9 -0.05 1" sizen="10 0" textsize="1" halign="center" text="$390+"/>';
	$xml .= '</frame>';

	// ++ Button
	$xml .= '<frame posn="5.75 -7 1">';
	$xml .= '<label posn="0.2 -0.08 0.1" sizen="3.45 1.4" action="'. $tmkarma_config['manialink_id'] .'11" focusareacolor1="EEEF" focusareacolor2="FFFF" text=" "/>';
	$xml .= '<label posn="1.9 -0.05 1" sizen="10 0" textsize="1" halign="center" text="$390++"/>';
	$xml .= '</frame>';

	// +++ Button
	$xml .= '<frame posn="9.55 -7 1">';
	$xml .= '<label posn="0.2 -0.08 0.1" sizen="3.45 1.4" action="'. $tmkarma_config['manialink_id'] .'12" focusareacolor1="EEEF" focusareacolor2="FFFF" text=" "/>';
	$xml .= '<label posn="1.9 -0.05 1" sizen="10 0" textsize="1" halign="center" text="$390+++"/>';
	$xml .= '</frame>';

	// - Button
	$xml .= '<frame posn="1.95 -8.6 1">';
	$xml .= '<label posn="0.2 -0.08 0.1" sizen="3.45 1.4" action="'. $tmkarma_config['manialink_id'] .'14" focusareacolor1="EEEF" focusareacolor2="FFFF" text=" "/>';
	$xml .= '<label posn="1.9 0.13 1" sizen="14 0" textsize="1" halign="center" text="$D02-"/>';
	$xml .= '</frame>';

	// -- Button
	$xml .= '<frame posn="5.75 -8.6 1">';
	$xml .= '<label posn="0.2 -0.08 0.1" sizen="3.45 1.4" action="'. $tmkarma_config['manialink_id'] .'15" focusareacolor1="EEEF" focusareacolor2="FFFF" text=" "/>';
	$xml .= '<label posn="1.9 0.13 1" sizen="14 0" textsize="1" halign="center" text="$D02--"/>';
	$xml .= '</frame>';

	// --- Button
	$xml .= '<frame posn="9.55 -8.6 1">';
	$xml .= '<label posn="0.2 -0.08 0.1" sizen="3.45 1.4" action="'. $tmkarma_config['manialink_id'] .'16" focusareacolor1="EEEF" focusareacolor2="FFFF" text=" "/>';
	$xml .= '<label posn="1.9 0.13 1" sizen="14 0" textsize="1" halign="center" text="$D02---"/>';
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

function tmkarma_buildKarmaCupsValue ($gamemode) {
	global $aseco, $tmkarma_config, $karma;


	$TotalCups = 10;
	if ($karma['global']['votes']['karma'] > 0) {
		$GlowCups = intval($karma['global']['votes']['karma'] / $TotalCups);
	}
	else {
		$GlowCups = 0;
	}
	$DefaultAward = '<quad posn="%x% %y% %z%" sizen="%width% %width%" valign="bottom" halign="center" style="Icons64x64_1" substyle="OfficialRace"/>';
	$GlowAward = '<quad posn="%x% %y% %z%" sizen="%width% %width%" valign="bottom" halign="center" style="Icons128x32_1" substyle="RT_Cup"/>';
	$cups_result = '';
	for ($i = 0 ; $i < $TotalCups ; $i ++) {
		$width = 1.5 + ($i / $TotalCups) * 0.8;
		if ($i < $GlowCups) {
			$award = $GlowAward;
			$layer = "0.1$i";
		}
		else {
			$award = $DefaultAward;
			$layer = "0.0$i";
		}
		$cups_result .= str_replace(array('%width%', '%x%', '%y%', '%z%'), array($width, (1*$i), 0, $layer), $award);
	}


	$xml  = '<manialink id="'. $tmkarma_config['manialink_id'] .'07">';
	$xml .= '<frame posn="'. $tmkarma_config['widget']['states'][$gamemode]['widget_pos_x'] .' '. $tmkarma_config['widget']['states'][$gamemode]['widget_pos_y'] .' 10">';

	// Cups
	$xml .= '<frame posn="2.9 -4.8 0.01">';
	$xml .= $cups_result;
	$xml .= '</frame>';

	// Global Value and Votes
	if ( ($karma['global']['votes']['karma'] >= 0) && ($karma['global']['votes']['karma'] <= 30) ) {
		$globalcolor = '$F00';
	}
	else if ( ($karma['global']['votes']['karma'] >= 31) && ($karma['global']['votes']['karma'] <= 60) ) {
		$globalcolor = '$FF0';
	}
	else if ( ($karma['global']['votes']['karma'] >= 61) && ($karma['global']['votes']['karma'] <= 100) ) {
		$globalcolor = '$0F0';
	}

	// Local Value and Votes
	if ( ($karma['local']['votes']['karma'] >= 0) && ($karma['local']['votes']['karma'] <= 30) ) {
		$localcolor = '$F00';
	}
	else if ( ($karma['local']['votes']['karma'] >= 31) && ($karma['local']['votes']['karma'] <= 60) ) {
		$localcolor = '$FF0';
	}
	else if ( ($karma['local']['votes']['karma'] >= 61) && ($karma['local']['votes']['karma'] <= 100) ) {
		$localcolor = '$0F0';
	}
	$xml .= '<frame posn="2.95 -5 0">';
	$xml .= '<label posn="1.2 -0.1 1" sizen="7.2 0" textsize="1" halign="center" text="'. $globalcolor .'G'. $karma['global']['votes']['karma'] .' $FFF/ '. $localcolor .'L'. $karma['local']['votes']['karma'] .'"/>';

	$xml .= '<label posn="8.25 -0.1 1" sizen="6.4 0" textsize="1" halign="center" text="$0F3'. number_format($karma['global']['votes']['total'], 0, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .' '. (($karma['global']['votes']['total'] == 1) ? $tmkarma_config['messages']['karma_vote_singular'] : $tmkarma_config['messages']['karma_vote_plural']) .'"/>';
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

function tmkarma_sendKarmaDetailWindow ($player, $display = true) {
	global $aseco, $tmkarma_config, $karma;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_sendKarmaDetailWindow()');
	}

	$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $tmkarma_config['manialink_id'] .'02"></manialink>';		// Close Help
	$xml .= '<manialink id="'. $tmkarma_config['manialink_id'] .'05">';

	if ($display == true) {

		$xml .= $tmkarma_config['widget']['skeleton']['details'];

	// BEGIN: Place Player marker, if Player has already voted

		if ( isset($karma['global']['players'][$player->login]) ) {
			// BEGIN: Global vote frame
			$xml .= '<frame posn="-36.9 -18.15 0.02">';
			if ($karma['global']['players'][$player->login]['vote'] == 3) {
				$xml .= '<quad posn="10 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
				$xml .= '<label posn="10 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
			}
			else if ($karma['global']['players'][$player->login]['vote'] == 2) {
				$xml .= '<quad posn="14.5 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
				$xml .= '<label posn="14.5 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
			}
			else if ($karma['global']['players'][$player->login]['vote'] == 1) {
				$xml .= '<quad posn="19 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
				$xml .= '<label posn="19 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
			}
			else if ($karma['global']['players'][$player->login]['vote'] == -1) {
				$xml .= '<quad posn="23.5 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
				$xml .= '<label posn="23.5 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
			}
			else if ($karma['global']['players'][$player->login]['vote'] == -2) {
				$xml .= '<quad posn="28 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
				$xml .= '<label posn="28 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
			}
			else if ($karma['global']['players'][$player->login]['vote'] == -3) {
				$xml .= '<quad posn="32.5 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
				$xml .= '<label posn="32.5 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
			}
			$xml .= '</frame>';
			// END: Global vote frame
		}

		if ( isset($karma['local']['players'][$player->login]) ) {
			// BEGIN: Local vote frame
			$xml .= '<frame posn="0.1 -18.15 0.02">';
			if ($karma['local']['players'][$player->login]['vote'] == 3) {
				$xml .= '<quad posn="10 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
				$xml .= '<label posn="10 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
			}
			else if ($karma['local']['players'][$player->login]['vote'] == 2) {
				$xml .= '<quad posn="14.5 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
				$xml .= '<label posn="14.5 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
			}
			else if ($karma['local']['players'][$player->login]['vote'] == 1) {
				$xml .= '<quad posn="19 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
				$xml .= '<label posn="19 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
			}
			else if ($karma['local']['players'][$player->login]['vote'] == -1) {
				$xml .= '<quad posn="23.5 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
				$xml .= '<label posn="23.5 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
			}
			else if ($karma['local']['players'][$player->login]['vote'] == -2) {
				$xml .= '<quad posn="28 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
				$xml .= '<label posn="28 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
			}
			else if ($karma['local']['players'][$player->login]['vote'] == -3) {
				$xml .= '<quad posn="32.5 0 0.05" sizen="2.8 2.8" halign="center" style="Icons64x64_1" substyle="YellowHigh"/>';
				$xml .= '<label posn="32.5 -2.5 0.03" sizen="6 0" halign="center" textsize="1" scale="0.85" textcolor="FFFF" text="Your vote"/>';
			}
			$xml .= '</frame>';
			// END: Local vote frame
		}

	// END: Place Player marker
	}

	$xml .= '</manialink>';
	$xml .= '</manialinks>';

	$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function tmkarma_buildKarmaDetailWindow () {
	global $aseco, $tmkarma_config, $karma;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_buildKarmaDetailWindow()');
	}

	if ( !isset($karma['global']['votes']) ) {
		return;
	}


	// Window
	$xml  = '<frame posn="-40.1 30.45 -3">';	// BEGIN: Window Frame
	$xml .= '<quad posn="0.8 -0.8 0.01" sizen="78.4 53.7" bgcolor="001B"/>';
	$xml .= '<quad posn="-0.2 0.2 0.09" sizen="80.4 55.7" style="Bgs1InRace" substyle="BgCard3"/>';

	// Header Line
	$xml .= '<quad posn="0.8 -1.3 0.02" sizen="78.4 3" bgcolor="09FC"/>';
	$xml .= '<quad posn="0.8 -4.3 0.03" sizen="78.4 0.1" bgcolor="FFF9"/>';
	$xml .= '<quad posn="1.8 -1.4 0.10" sizen="2.8 2.8" style="Icons128x128_1" substyle="NewTrack"/>';
	$xml .= '<label posn="5.5 -1.8 0.10" sizen="74 0" halign="left" textsize="2" scale="0.9" textcolor="FFFF" text="TM-Karma.com detailed vote statistic"/>';

	// About
	$xml .= '<quad posn="2.7 -54.1 0.12" sizen="11 1" action="'. $tmkarma_config['manialink_id'] .'02" bgcolor="0000"/>';
	$xml .= '<label posn="2.7 -54.1 0.12" sizen="30 1" halign="left" textsize="1" scale="0.7" textcolor="000F" text="TM-KARMA.COM/'. $tmkarma_config['version'] .'"/>';

	// Close Button
	$xml .= '<frame posn="77.4 1.3 0">';
	$xml .= '<quad posn="0 0 0.10" sizen="4 4" style="Icons64x64_1" substyle="ArrowDown"/>';
	$xml .= '<quad posn="1.1 -1.35 0.11" sizen="1.8 1.75" bgcolor="EEEF"/>';
	$xml .= '<quad posn="0.65 -0.7 0.12" sizen="2.6 2.6" action="'. $tmkarma_config['manialink_id'] .'09" style="Icons64x64_1" substyle="Close"/>';
	$xml .= '</frame>';


	// Build Karma Headline

	// Global Karma
	if ( ($karma['global']['votes']['karma'] >= 0) && ($karma['global']['votes']['karma'] <= 30) ) {
		$color = '$F00';
	}
	else if ( ($karma['global']['votes']['karma'] >= 31) && ($karma['global']['votes']['karma'] <= 60) ) {
		$color = '$FF0';
	}
	else if ( ($karma['global']['votes']['karma'] >= 61) && ($karma['global']['votes']['karma'] <= 100) ) {
		$color = '$0F0';
	}
	$xml .= '<label posn="10.2 -6.5 0.03" sizen="20 0" textsize="2" scale="0.9" text="Global Karma: '. $color .'~ '. $karma['global']['votes']['karma'] .' ~"/>';
	$xml .= '<label posn="38.2 -6.5 0.03" sizen="20 0" textsize="2" scale="0.9" halign="right" text="'. number_format($karma['global']['votes']['total'], 0, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .' '. (($karma['global']['votes']['total'] == 1) ? $tmkarma_config['messages']['karma_vote_singular'] : $tmkarma_config['messages']['karma_vote_plural']) .'"/>';

	// Check if connection was failed
	if ($tmkarma_config['retrytime'] > 0) {
		$xml .= '<label posn="10.2 -8.5 0.03" sizen="40 0" textsize="1" scale="0.8" text="$F00Lost connection to Global-Database..."/>';
	}

	// Local Karma
	if ( ($karma['local']['votes']['karma'] >= 0) && ($karma['local']['votes']['karma'] <= 30) ) {
		$color = '$F00';
	}
	else if ( ($karma['local']['votes']['karma'] >= 31) && ($karma['local']['votes']['karma'] <= 60) ) {
		$color = '$FF0';
	}
	else if ( ($karma['local']['votes']['karma'] >= 61) && ($karma['local']['votes']['karma'] <= 100) ) {
		$color = '$0F0';
	}
	$xml .= '<label posn="47.2 -6.5 0.03" sizen="20 0" textsize="2" scale="0.9" text="Local Karma: '. $color .'~ '. $karma['local']['votes']['karma'] .' ~"/>';
	$xml .= '<label posn="75.2 -6.5 0.03" sizen="20 0" textsize="2" scale="0.9" halign="right" text="'. number_format($karma['local']['votes']['total'], 0, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .' '. (($karma['local']['votes']['total'] == 1) ? $tmkarma_config['messages']['karma_vote_singular'] : $tmkarma_config['messages']['karma_vote_plural']) .'"/>';




	// BEGIN: Global vote frame
	$xml .= '<frame posn="3.2 -0.6 0.01">';
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

	$xml .= '<label posn="10.2 -'. (40 - $height['fantastic']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['global']['votes']['fantastic']['percent'], 2, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'%"/>';
	$xml .= '<label posn="14.7 -'. (40 - $height['beautiful']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['global']['votes']['beautiful']['percent'], 2, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'%"/>';
	$xml .= '<label posn="19.2 -'. (40 - $height['good']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['global']['votes']['good']['percent'], 2, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'%"/>';

	$xml .= '<quad posn="10 -'. (42 - $height['fantastic']) .' 0.02" sizen="4 '. $height['fantastic'] .'" halign="center" bgcolor="170F"/>';
	$xml .= '<quad posn="14.5 -'. (42 - $height['beautiful']) .' 0.02" sizen="4 '. $height['beautiful'] .'" halign="center" bgcolor="170F"/>';
	$xml .= '<quad posn="19 -'. (42 - $height['good']) .' 0.02" sizen="4 '. $height['good'] .'" halign="center" bgcolor="170F"/>';

	$xml .= '<quad posn="10 -'. (42 - $height['fantastic']) .' 0.03" sizen="4 '. $height['fantastic'] .'" halign="center" style="BgRaceScore2" substyle="CupFinisher"/>';
	$xml .= '<quad posn="14.5 -'. (42 - $height['beautiful']) .' 0.03" sizen="4 '. $height['beautiful'] .'" halign="center" style="BgRaceScore2" substyle="CupFinisher"/>';
	$xml .= '<quad posn="19 -'. (42 - $height['good']) .' 0.03" sizen="4 '. $height['good'] .'" halign="center" style="BgRaceScore2" substyle="CupFinisher"/>';

	$xml .= '<quad posn="10 -'. (42 - $height['fantastic']) .' 0.035" sizen="4.4 '. (($height['fantastic'] < 3) ? $height['fantastic'] : 3) .'" halign="center" style="BgsPlayerCard" substyle="BgRacePlayerLine"/>';
	$xml .= '<quad posn="14.5 -'. (42 - $height['beautiful']) .' 0.035" sizen="4.4 '. (($height['beautiful'] < 3) ? $height['beautiful'] : 3) .'" halign="center" style="BgsPlayerCard" substyle="BgRacePlayerLine"/>';
	$xml .= '<quad posn="19 -'. (42 - $height['good']) .' 0.035" sizen="4.4 '. (($height['good'] < 3) ? $height['good'] : 3) .'" halign="center" style="BgsPlayerCard" substyle="BgRacePlayerLine"/>';

	$xml .= '<label posn="23.7 -'. (40 - $height['bad']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['global']['votes']['bad']['percent'], 2, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'%"/>';
	$xml .= '<label posn="28.2 -'. (40 - $height['poor']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['global']['votes']['poor']['percent'], 2, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'%"/>';
	$xml .= '<label posn="32.7 -'. (40 - $height['waste']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['global']['votes']['waste']['percent'], 2, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'%"/>';

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

	$xml .= '<label posn="10 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['global']['votes']['fantastic']['count'], 0, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="14.5 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['global']['votes']['beautiful']['count'], 0, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="19 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['global']['votes']['good']['count'], 0, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="23.5 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['global']['votes']['bad']['count'], 0, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="28 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['global']['votes']['poor']['count'], 0, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="32.5 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['global']['votes']['waste']['count'], 0, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'"/>';

	$xml .= '<label posn="10 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$6C0'. ucfirst($tmkarma_config['messages']['karma_fantastic']) .'"/>';
	$xml .= '<label posn="14.5 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$6C0'. ucfirst($tmkarma_config['messages']['karma_beautiful']) .'"/>';
	$xml .= '<label posn="19 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$6C0'. ucfirst($tmkarma_config['messages']['karma_good']) .'"/>';
	$xml .= '<label posn="23.5 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$D02'. ucfirst($tmkarma_config['messages']['karma_bad']) .'"/>';
	$xml .= '<label posn="28 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$D02'. ucfirst($tmkarma_config['messages']['karma_poor']) .'"/>';
	$xml .= '<label posn="32.5 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$D02'. ucfirst($tmkarma_config['messages']['karma_waste']) .'"/>';

	$xml .= '<label posn="10 -46.05 0.03" sizen="10 0" halign="center" text="$6C0+++"/>';
	$xml .= '<label posn="14.5 -46.05 0.03" sizen="10 0" halign="center" text="$6C0++"/>';
	$xml .= '<label posn="19 -46.05 0.03" sizen="10 0" halign="center" text="$6C0+"/>';
	$xml .= '<label posn="23.5 -46.05 0.03" sizen="10 0" halign="center" text="$D02-"/>';
	$xml .= '<label posn="28 -46.05 0.03" sizen="10 0" halign="center" text="$D02--"/>';
	$xml .= '<label posn="32.5 -46.05 0.03" sizen="10 0" halign="center" text="$D02---"/>';

	$xml .= '</frame>';
	// END: Global vote frame





	// BEGIN: Local vote frame
	$xml .= '<frame posn="40.2 -0.6 0.01">';
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

	$xml .= '<label posn="10.2 -'. (40 - $height['fantastic']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['local']['votes']['fantastic']['percent'], 2, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'%"/>';
	$xml .= '<label posn="14.7 -'. (40 - $height['beautiful']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['local']['votes']['beautiful']['percent'], 2, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'%"/>';
	$xml .= '<label posn="19.2 -'. (40 - $height['good']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['local']['votes']['good']['percent'], 2, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'%"/>';

	$xml .= '<quad posn="10 -'. (42 - $height['fantastic']) .' 0.02" sizen="4 '. $height['fantastic'] .'" halign="center" bgcolor="170F"/>';
	$xml .= '<quad posn="14.5 -'. (42 - $height['beautiful']) .' 0.02" sizen="4 '. $height['beautiful'] .'" halign="center" bgcolor="170F"/>';
	$xml .= '<quad posn="19 -'. (42 - $height['good']) .' 0.02" sizen="4 '. $height['good'] .'" halign="center" bgcolor="170F"/>';

	$xml .= '<quad posn="10 -'. (42 - $height['fantastic']) .' 0.03" sizen="4 '. $height['fantastic'] .'" halign="center" style="BgRaceScore2" substyle="CupFinisher"/>';
	$xml .= '<quad posn="14.5 -'. (42 - $height['beautiful']) .' 0.03" sizen="4 '. $height['beautiful'] .'" halign="center" style="BgRaceScore2" substyle="CupFinisher"/>';
	$xml .= '<quad posn="19 -'. (42 - $height['good']) .' 0.03" sizen="4 '. $height['good'] .'" halign="center" style="BgRaceScore2" substyle="CupFinisher"/>';

	$xml .= '<quad posn="10 -'. (42 - $height['fantastic']) .' 0.035" sizen="4.4 '. (($height['fantastic'] < 3) ? $height['fantastic'] : 3) .'" halign="center" style="BgsPlayerCard" substyle="BgRacePlayerLine"/>';
	$xml .= '<quad posn="14.5 -'. (42 - $height['beautiful']) .' 0.035" sizen="4.4 '. (($height['beautiful'] < 3) ? $height['beautiful'] : 3) .'" halign="center" style="BgsPlayerCard" substyle="BgRacePlayerLine"/>';
	$xml .= '<quad posn="19 -'. (42 - $height['good']) .' 0.035" sizen="4.4 '. (($height['good'] < 3) ? $height['good'] : 3) .'" halign="center" style="BgsPlayerCard" substyle="BgRacePlayerLine"/>';

	$xml .= '<label posn="23.7 -'. (40 - $height['bad']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['local']['votes']['bad']['percent'], 2, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'%"/>';
	$xml .= '<label posn="28.2 -'. (40 - $height['poor']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['local']['votes']['poor']['percent'], 2, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'%"/>';
	$xml .= '<label posn="32.7 -'. (40 - $height['waste']) .' 0.06" sizen="3.8 0" halign="center" textcolor="FFFF" scale="0.8" text="'. number_format($karma['local']['votes']['waste']['percent'], 2, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'%"/>';

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

	$xml .= '<label posn="10 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['local']['votes']['fantastic']['count'], 0, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="14.5 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['local']['votes']['beautiful']['count'], 0, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="19 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['local']['votes']['good']['count'], 0, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="23.5 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['local']['votes']['bad']['count'], 0, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="28 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['local']['votes']['poor']['count'], 0, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'"/>';
	$xml .= '<label posn="32.5 -43 0.03" sizen="10 0" halign="center" text="'. number_format($karma['local']['votes']['waste']['count'], 0, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) .'"/>';

	$xml .= '<label posn="10 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$6C0'. ucfirst($tmkarma_config['messages']['karma_fantastic']) .'"/>';
	$xml .= '<label posn="14.5 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$6C0'. ucfirst($tmkarma_config['messages']['karma_beautiful']) .'"/>';
	$xml .= '<label posn="19 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$6C0'. ucfirst($tmkarma_config['messages']['karma_good']) .'"/>';
	$xml .= '<label posn="23.5 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$D02'. ucfirst($tmkarma_config['messages']['karma_bad']) .'"/>';
	$xml .= '<label posn="28 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$D02'. ucfirst($tmkarma_config['messages']['karma_poor']) .'"/>';
	$xml .= '<label posn="32.5 -45.05 0.03" sizen="10 0" halign="center" scale="0.8" text="$D02'. ucfirst($tmkarma_config['messages']['karma_waste']) .'"/>';

	$xml .= '<label posn="10 -46.05 0.03" sizen="10 0" halign="center" text="$6C0+++"/>';
	$xml .= '<label posn="14.5 -46.05 0.03" sizen="10 0" halign="center" text="$6C0++"/>';
	$xml .= '<label posn="19 -46.05 0.03" sizen="10 0" halign="center" text="$6C0+"/>';
	$xml .= '<label posn="23.5 -46.05 0.03" sizen="10 0" halign="center" text="$D02-"/>';
	$xml .= '<label posn="28 -46.05 0.03" sizen="10 0" halign="center" text="$D02--"/>';
	$xml .= '<label posn="32.5 -46.05 0.03" sizen="10 0" halign="center" text="$D02---"/>';

	$xml .= '</frame>';
	// END: Local vote frame


	$xml .= '</frame>'; // MainWidget Frame

	return $xml;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function tmkarma_buildPlayerVoteMarker ($player, $gamemode) {
	global $tmkarma_config, $karma;


	// Bail out if Player is already disconnected
	if (!isset($player->login)) {
		return;
	}

	// Build the colors for Player vote marker
	$preset = array();
	$preset['good']['color']	= '0000';	// Alpha = 0, = transparent
	$preset['beautiful']['color']	= '0000';	// Alpha = 0, = transparent
	$preset['fantastic']['color']	= '0000';	// Alpha = 0, = transparent
	$preset['bad']['color']		= '0000';	// Alpha = 0, = transparent
	$preset['poor']['color']	= '0000';	// Alpha = 0, = transparent
	$preset['waste']['color']	= '0000';	// Alpha = 0, = transparent

	// Good
	if ($karma['global']['players'][$player->login]['vote'] == 1) {
		$preset['good']['color'] = '9CFF';
	}
	else if ( ($karma['global']['players'][$player->login]['vote'] == 0) && (($tmkarma_config['require_finish'] > 0) && ($player->data['KarmaPlayerFinishedMap'] < $tmkarma_config['require_finish'])) ) {
		$preset['good']['color'] = 'F70F';
	}

	// Beautiful
	if ($karma['global']['players'][$player->login]['vote'] == 2) {
		$preset['beautiful']['color'] = '9CFF';
	}
	else if ( ($karma['global']['players'][$player->login]['vote'] == 0) && (($tmkarma_config['require_finish'] > 0) && ($player->data['KarmaPlayerFinishedMap'] < $tmkarma_config['require_finish'])) ) {
		$preset['beautiful']['color'] = 'F70F';
	}

	// Fantastic
	if ($karma['global']['players'][$player->login]['vote'] == 3) {
		$preset['fantastic']['color'] = '9CFF';
	}
	else if ( ($karma['global']['players'][$player->login]['vote'] == 0) && (($tmkarma_config['require_finish'] > 0) && ($player->data['KarmaPlayerFinishedMap'] < $tmkarma_config['require_finish'])) ) {
		$preset['fantastic']['color'] = 'F70F';
	}

	// Bad
	if ($karma['global']['players'][$player->login]['vote'] == -1) {
		$preset['bad']['color'] = '9CFF';
	}
	else if ( ($karma['global']['players'][$player->login]['vote'] == 0) && (($tmkarma_config['require_finish'] > 0) && ($player->data['KarmaPlayerFinishedMap'] < $tmkarma_config['require_finish'])) ) {
		$preset['bad']['color'] = 'F70F';
	}

	// Poor
	if ($karma['global']['players'][$player->login]['vote'] == -2) {
		$preset['poor']['color'] = '9CFF';
	}
	else if ( ($karma['global']['players'][$player->login]['vote'] == 0) && (($tmkarma_config['require_finish'] > 0) && ($player->data['KarmaPlayerFinishedMap'] < $tmkarma_config['require_finish'])) ) {
		$preset['poor']['color'] = 'F70F';
	}

	// Waste
	if ($karma['global']['players'][$player->login]['vote'] == -3) {
		$preset['waste']['color'] = '9CFF';
	}
	else if ( ($karma['global']['players'][$player->login]['vote'] == 0) && (($tmkarma_config['require_finish'] > 0) && ($player->data['KarmaPlayerFinishedMap'] < $tmkarma_config['require_finish'])) ) {
		$preset['waste']['color'] = 'F70F';
	}





	// Init Marker
	$marker = false;

	// + Button
	if ($preset['good']['color'] != '0000') {
		// Mark current vote or disable the vote possibility
		$marker .= '<quad posn="2.15 -7.08 1.2" sizen="3.45 1.4" action="'. $tmkarma_config['manialink_id'] .'17" bgcolor="'. $preset['good']['color'] .'"/>';
	}

	// ++ Button
	if ($preset['beautiful']['color'] != '0000') {
		// Mark current vote or disable the vote possibility
		$marker .= '<quad posn="5.95 -7.08 1.2" sizen="3.45 1.4" action="'. $tmkarma_config['manialink_id'] .'17" bgcolor="'. $preset['beautiful']['color'] .'"/>';
	}

	// +++ Button
	if ($preset['fantastic']['color'] != '0000') {
		// Mark current vote or disable the vote possibility
		$marker .= '<quad posn="9.75 -7.08 1.2" sizen="3.45 1.4" action="'. $tmkarma_config['manialink_id'] .'17" bgcolor="'. $preset['fantastic']['color'] .'"/>';
	}

	// - Button
	if ($preset['bad']['color'] != '0000') {
		// Mark current vote or disable the vote possibility
		$marker .= '<quad posn="2.15 -8.68 1.2" sizen="3.45 1.4" action="'. $tmkarma_config['manialink_id'] .'17" bgcolor="'. $preset['bad']['color'] .'"/>';
	}

	// -- Button
	if ($preset['poor']['color'] != '0000') {
		// Mark current vote or disable the vote possibility
		$marker .= '<quad posn="5.95 -8.68 1.2" sizen="3.45 1.4" action="'. $tmkarma_config['manialink_id'] .'17" bgcolor="'. $preset['poor']['color'] .'"/>';
	}

	// --- Button
	if ($preset['waste']['color'] != '0000') {
		// Mark current vote or disable the vote possibility
		$marker .= '<quad posn="9.75 -8.68 1.2" sizen="3.45 1.4" action="'. $tmkarma_config['manialink_id'] .'17" bgcolor="'. $preset['waste']['color'] .'"/>';
	}


	$xml = '<manialink id="'. $tmkarma_config['manialink_id'] .'06">';

	// Send/Build MainWidget Frame only when required, if empty then the player can vote
	if ($marker != false) {
		$xml .= '<frame posn="'. $tmkarma_config['widget']['states'][$gamemode]['widget_pos_x'] .' '. $tmkarma_config['widget']['states'][$gamemode]['widget_pos_y'] .' 10">';
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

// called @ several Events
function tmkarma_doPlayerVote ($player, $vote) {
	global $aseco, $tmkarma_config, $karma;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_doPlayerVote(), player "'. $player->login .'" voted "'. $vote .'"');
	}


	// Do nothing at Startup!!
	if ($tmkarma_config['widget']['startup_phase'] == true) {
		return;
	}


	// Close reminder-window if there is one for this Player
	tmkarma_closeReminderWindow($player);


	// $vote is "0" when the Player clicks on a red (no vote possible) or blue marked (same vote) button,
	// in both situation we bail out now.
	if ($vote == 0) {
		return;
	}


	// Before call the remote API, check if player has the same already voted
	if ($karma['global']['players'][$player->login]['vote'] == $vote) {
		// Same vote, does not need to call remote API, bail out immediately
		$message = $tmkarma_config['messages']['karma_voted'];
		if ( ($tmkarma_config['messages_in_window'] == true) && (function_exists('send_window_message')) ) {
			send_window_message($aseco, $message, $player);
		}
		else {
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		}
		return;
	}


	// Check if finishes are required
	if ( ($tmkarma_config['require_finish'] > 0) && ($tmkarma_config['require_finish'] > $player->data['KarmaPlayerFinishedMap']) ) {

		// Show chat message
		$message = formatText($tmkarma_config['messages']['karma_require_finish'],
					$tmkarma_config['require_finish'],
					($tmkarma_config['require_finish'] == 1 ? '' : 's')
		);
		if ( ($tmkarma_config['messages_in_window'] == true) && (function_exists('send_window_message')) && ($tmkarma_config['widget']['current_state'] != 6) ) {
			send_window_message($aseco, $message, $player);
		}
		else {
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		}
		return;
	}


	// Store the new vote for send them later with "MultiVote"
	$karma['new']['players'][$player->login] = $vote;


	// Check if connection was failed
	if ($tmkarma_config['retrytime'] == 0) {
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
	}

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

	// Check if connection was failed, and store the current Vote (only local or both)
	if ($tmkarma_config['retrytime'] == 0) {
		$karma['global']['players'][$player->login]['vote'] = $vote;
	}
	$karma['local']['players'][$player->login]['vote'] = $vote;


	// Check if connection was failed
	if ($tmkarma_config['retrytime'] == 0) {
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
	}
	else {
		// Add the new Vote into the counts (only local)
		switch ($vote) {
			case 3:
				$karma['local']['votes']['fantastic']['count'] += 1;
				break;
			case 2:
				$karma['local']['votes']['beautiful']['count'] += 1;
				break;
			case 1:
				$karma['local']['votes']['good']['count'] += 1;
				break;
			case -1:
				$karma['local']['votes']['bad']['count'] += 1;
				break;
			case -2:
				$karma['local']['votes']['poor']['count'] += 1;
				break;
			case -3:
				$karma['local']['votes']['waste']['count'] += 1;
				break;
			default:
				// Do nothing
				break;
		}
	}


	// Check if connection was failed
	if ($tmkarma_config['retrytime'] == 0) {
		// Update the global/local $karma
		tmkarma_calculateKarma(array('global','local'));
	}
	else {
		// Update only the local $karma
		tmkarma_calculateKarma(array('local'));
	}


	// Show the TMX-Link-Window (if enabled and we are at Score)
	if ($tmkarma_config['score_tmx_window'] == true) {
		tmkarma_showTmxLinkWindow($player);
	}


	// Tell the player the result for his/her vote
	if ($karma['global']['players'][$player->login]['previous'] == 0) {
		$message = formatText($tmkarma_config['messages']['karma_done'], stripColors($aseco->server->challenge->name) );
		if ( ($tmkarma_config['messages_in_window'] == true) && (function_exists('send_window_message')) ) {
			send_window_message($aseco, $message, $player);
		}
		else {
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		}

	}
	else if ($karma['global']['players'][$player->login]['previous'] != $vote) {
		$message = formatText($tmkarma_config['messages']['karma_change'], stripColors($aseco->server->challenge->name) );
		if ( ($tmkarma_config['messages_in_window'] == true) && (function_exists('send_window_message')) ) {
			send_window_message($aseco, $message, $player);
		}
		else {
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		}
	}


	// Show Challenge Karma (with details?)
	$message = tmkarma_createKarmaMessage($player->login, false);
	if ($message != false) {
		if ( ($tmkarma_config['messages_in_window'] == true) && (function_exists('send_window_message')) ) {
			send_window_message($aseco, $message, $player);
		}
		else {
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		}
	}


	// Update the KarmaWidget for given Player
	tmkarma_sendWidgetCombination(array('player_marker'), $player);


	// Should all other player (except the vote given player) be informed/asked?
	if ($tmkarma_config['show_player_vote_public'] == true) {
		$logins = array();
		foreach ($aseco->server->players->player_list as &$pl) {

			// Don't ask/tell the player that give the vote
			if ($pl->login == $player->login) {
				continue;
			}

			// Don't ask/tell Players they did not reached the <require_finish> limit
			if ( ($tmkarma_config['require_finish'] > 0) && ($tmkarma_config['require_finish'] > $pl->data['KarmaPlayerFinishedMap']) ) {
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

		// Build the message and send out
		if ($vote == 1) {
			$player_voted = $tmkarma_config['messages']['karma_good'];
		}
		else if ($vote == 2) {
			$player_voted = $tmkarma_config['messages']['karma_beautiful'];
		}
		else if ($vote == 3) {
			$player_voted = $tmkarma_config['messages']['karma_fantastic'];
		}
		else if ($vote == -1) {
			$player_voted = $tmkarma_config['messages']['karma_bad'];
		}
		else if ($vote == -2) {
			$player_voted = $tmkarma_config['messages']['karma_poor'];
		}
		else if ($vote == -3) {
			$player_voted = $tmkarma_config['messages']['karma_waste'];
		}
		$message = formatText($tmkarma_config['messages']['karma_show_opinion'],
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

function tmkarma_sendTrackKarmaMessage ($login) {
	global $aseco, $tmkarma_config;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_sendTrackKarmaMessage() to player "'. $login .'"');
	}

	// Create message
	$message = tmkarma_createKarmaMessage($login, false);

	// Show message
	if ($message != false) {
		if ($login) {
			if ( ($tmkarma_config['messages_in_window'] == true) && (function_exists('send_window_message')) ) {
				$player = $aseco->server->players->getPlayer($login);
				send_window_message($aseco, $message, $player);
			}
			else {
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
			}
		}
		else {
			if ( ($tmkarma_config['messages_in_window'] == true) && (function_exists('send_window_message')) ) {
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

function tmkarma_createKarmaMessage ($login, $force_display = false) {
	global $aseco, $tmkarma_config, $karma;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_createKarmaMessage() to player "'. $login .'"');
	}

	// Init
	$message = false;

	// Show default Karma message
	if ( ($tmkarma_config['show_karma'] == true) || ($force_display == true) ) {

		// Get required data for Challenge
		$current_challenge = tmkarma_getChallengeInfo();

		$message = formatText($tmkarma_config['messages']['karma_message'],
			stripColors($current_challenge->name),
			$karma['global']['votes']['karma']
		);
	}

	// Optionally show player's actual vote
	if ( ($tmkarma_config['show_votes'] == true) || ($force_display == true) ) {
		if ($karma['global']['players'][$login]['vote'] == 1) {
			$message .= formatText($tmkarma_config['messages']['karma_your_vote'], $tmkarma_config['messages']['karma_good'], '/+');
		}
		else if ($karma['global']['players'][$login]['vote'] == 2) {
			$message .= formatText($tmkarma_config['messages']['karma_your_vote'], $tmkarma_config['messages']['karma_beautiful'], '/++');
		}
		else if ($karma['global']['players'][$login]['vote'] == 3) {
			$message .= formatText($tmkarma_config['messages']['karma_your_vote'], $tmkarma_config['messages']['karma_fantastic'], '/+++');
		}
		else if ($karma['global']['players'][$login]['vote'] == -1) {
			$message .= formatText($tmkarma_config['messages']['karma_your_vote'], $tmkarma_config['messages']['karma_bad'], '/-');
		}
		else if ($karma['global']['players'][$login]['vote'] == -2) {
			$message .= formatText($tmkarma_config['messages']['karma_your_vote'], $tmkarma_config['messages']['karma_poor'], '/--');
		}
		else if ($karma['global']['players'][$login]['vote'] == -3) {
			$message .= formatText($tmkarma_config['messages']['karma_your_vote'], $tmkarma_config['messages']['karma_waste'], '/---');
		}
		else {
			$message .= $tmkarma_config['messages']['karma_not_voted'];
		}

	}

	// Optionally show vote counts & percentages
	if ( ($tmkarma_config['show_details'] == true) || ($force_display == true) ) {
		$message .= formatText(LF. $tmkarma_config['messages']['karma_details'],
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
function tmkarma_showUndecidedMessage ($command) {
	global $aseco, $tmkarma_config, $karma;


	// Should all other player (except the vote given player) be informed/asked?
	if ($tmkarma_config['show_player_vote_public'] == true) {
		foreach ($aseco->server->players->player_list as &$player) {

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

				$message = formatText($tmkarma_config['messages']['karma_show_undecided'],
						stripColors($command['author']->nickname)
				);
				$message = str_replace('{br}', LF, $message);  // split long message
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
			}
		}
		unset($player);
	}

	// Close reminder-window if there is one for this Player
	if ($command['author']->data['KarmaReminderWindow'] == true) {
		tmkarma_closeReminderWindow($command['author']);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// This shows the reminder window to the given Players (comma seperated list)
function tmkarma_showReminderWindow ($players) {
	global $aseco, $tmkarma_config;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_showReminderWindow()');
	}

	// Check if connection was failed and do not show the reminder window
	if ($tmkarma_config['retrytime'] > 0) {
		return;
	}

	$content =  '<?xml version="1.0" encoding="UTF-8"?>';
	$content .= '<manialinks>';
	$content .= '<manialink id="'. $tmkarma_config['manialink_id'] .'01">';
	$content .= '<frame posn="'. $tmkarma_config['reminder_window_pos_x'] .' '. $tmkarma_config['reminder_window_pos_y'] .' 2">';
	$content .= '<quad posn="0 0 0" sizen="73.7 4.3" style="Bgs1InRace" substyle="NavButton"/>';
	$content .= '<label posn="9.8 -0.6 1" sizen="18 1.8" textsize="2" halign="center" text="$FFF'. $tmkarma_config['messages']['karma_reminder_at_score'] .'"/>';
	$content .= '<label posn="13.8 -2.5 1" sizen="10 0.2" textsize="1" halign="center" text="$EEEpowered by tm-karma.com"/>';

	$content .= '<frame posn="19.2 -0.45 1">';
	$content .= '<quad posn="0 0 0" sizen="8 3.45" action="'. $tmkarma_config['manialink_id'] .'12" style="Bgs1InRace" substyle="BgIconBorder"/>';
	$content .= '<label posn="4 -0.5 0" textsize="1" sizen="7 0" halign="center" text="$390'. ucfirst($tmkarma_config['messages']['karma_fantastic']) .'"/>';
	$content .= '<label posn="4 -1.8 0" textsize="1" sizen="10 0" halign="center" text="$390+++"/>';
	$content .= '</frame>';

	$content .= '<frame posn="26.9 -0.45 1">';
	$content .= '<quad posn="0 0 0" sizen="8 3.45" action="'. $tmkarma_config['manialink_id'] .'11" style="Bgs1InRace" substyle="BgIconBorder"/>';
	$content .= '<label posn="4 -0.5 0" sizen="7 0" halign="center" textsize="1" text="$390'. ucfirst($tmkarma_config['messages']['karma_beautiful']) .'"/>';
	$content .= '<label posn="4 -1.8 0" sizen="10 0" halign="center" textsize="1" text="$390++"/>';
	$content .= '</frame>';

	$content .= '<frame posn="34.6 -0.45 1">';
	$content .= '<quad posn="0 0 0" sizen="8 3.45" action="'. $tmkarma_config['manialink_id'] .'10" style="Bgs1InRace" substyle="BgIconBorder"/>';
	$content .= '<label posn="4 -0.5 0" sizen="7 0" halign="center" textsize="1" text="$390'. ucfirst($tmkarma_config['messages']['karma_good']) .'"/>';
	$content .= '<label posn="4 -1.8 0" sizen="10 0" halign="center" textsize="1" text="$390+"/>';
	$content .= '</frame>';

	$content .= '<frame posn="42.3 -0.45 1">';
	$content .= '<quad posn="0 0 0" sizen="8 3.45" action="'. $tmkarma_config['manialink_id'] .'13" style="Bgs1InRace" substyle="BgIconBorder"/>';
	$content .= '<label posn="4 -0.5 0" sizen="7 0" halign="center" textsize="1" text="$888'. ucfirst($tmkarma_config['messages']['karma_undecided']) .'"/>';
	$content .= '<label posn="4 -2 0" sizen="10 0" halign="center" textsize="1" scale="0.7" text="$888???"/>';
	$content .= '</frame>';

	$content .= '<frame posn="50 -0.45 1">';
	$content .= '<quad posn="0 0 0" sizen="8 3.45" action="'. $tmkarma_config['manialink_id'] .'14" style="Bgs1InRace" substyle="BgIconBorder"/>';
	$content .= '<label posn="4 -0.5 0" sizen="7 0" halign="center" textsize="1" text="$D02'. ucfirst($tmkarma_config['messages']['karma_bad']) .'"/>';
	$content .= '<label posn="4 -1.7 0" sizen="14 0" halign="center" textsize="1" text="$D02-"/>';
	$content .= '</frame>';

	$content .= '<frame posn="57.7 -0.45 1">';
	$content .= '<quad posn="0 0 0" sizen="8 3.45" action="'. $tmkarma_config['manialink_id'] .'15" style="Bgs1InRace" substyle="BgIconBorder"/>';
	$content .= '<label posn="4 -0.5 0" sizen="7 0" halign="center" textsize="1" text="$D02'. ucfirst($tmkarma_config['messages']['karma_poor']) .'"/>';
	$content .= '<label posn="4 -1.7 0" sizen="14 0" halign="center" textsize="1" text="$D02--"/>';
	$content .= '</frame>';

	$content .= '<frame posn="65.4 -0.45 1">';
	$content .= '<quad posn="0 0 0" sizen="8 3.45" action="'. $tmkarma_config['manialink_id'] .'16" style="Bgs1InRace" substyle="BgIconBorder"/>';
	$content .= '<label posn="4 -0.5 0" sizen="7 0" halign="center" textsize="1" text="$D02'. ucfirst($tmkarma_config['messages']['karma_waste']) .'"/>';
	$content .= '<label posn="4 -1.7 0" sizen="14 0" halign="center" textsize="1" text="$D02---"/>';
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

// This shows the TMX-Link Window to the given player
function tmkarma_showTmxLinkWindow ($player) {
	global $aseco, $tmkarma_config, $karma;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_showTmxLinkWindow()');
	}

	// Bail out immediately if not at Score
	if ($tmkarma_config['widget']['current_state'] != 6) {
		return;
	}

	// Find the Player vote
	switch ($karma['global']['players'][$player->login]['vote']) {
		case 3:
			$voted = '$390'. ucfirst($tmkarma_config['messages']['karma_fantastic']);
			$cmd = '$390+++';
			break;
		case 2:
			$voted = '$390'. ucfirst($tmkarma_config['messages']['karma_beautiful']);
			$cmd = '$390++';
			break;
		case 1:
			$voted = '$390'. ucfirst($tmkarma_config['messages']['karma_good']);
			$cmd = '$390+';
			break;
		case -1:
			$voted = '$D02'. ucfirst($tmkarma_config['messages']['karma_bad']);
			$cmd = '$D02-';
			break;
		case -2:
			$voted = '$D02'. ucfirst($tmkarma_config['messages']['karma_poor']);
			$cmd = '$D02--';
			break;
		case -3:
			$voted = '$D02'. ucfirst($tmkarma_config['messages']['karma_waste']);
			$cmd = '$D02---';
			break;
	}

	$content =  '<?xml version="1.0" encoding="UTF-8"?>';
	$content .= '<manialinks>';
	$content .= '<manialink id="'. $tmkarma_config['manialink_id'] .'01">';
	$content .= '<frame posn="'. $tmkarma_config['reminder_window_pos_x'] .' '. $tmkarma_config['reminder_window_pos_y'] .' 2">';
	$content .= '<quad posn="0 0 0" sizen="73.7 4.3" style="Bgs1InRace" substyle="NavButton"/>';
	$content .= '<label posn="19 -0.6 1" sizen="18 1.8" textsize="2" halign="right" text="$FFF'. $tmkarma_config['messages']['karma_you_have_voted'] .'"/>';
	$content .= '<label posn="13.8 -2.5 1" sizen="10 0.2" textsize="1" halign="center" text="$EEEpowered by tm-karma.com"/>';

	$content .= '<frame posn="19.2 -0.45 1">';
	$content .= '<quad posn="0 0 0" sizen="8 3.45" style="Bgs1InRace" substyle="BgIconBorder"/>';
	$content .= '<label posn="4 -0.5 0" sizen="7 0" halign="center" textsize="1" text="'. $voted .'"/>';
	$content .= '<label posn="4 -1.8 0" sizen="10 0" halign="center" textsize="1" text="'. $cmd .'"/>';
	$content .= '</frame>';

	if ( isset($aseco->server->challenge->tmx->pageurl) ) {
		// Show link direct to the last map
		$content .= '<frame posn="26 -0.2 1">';
		$content .= '<label posn="39.5 -1.3 0" sizen="40 0" halign="right" textsize="1" text="$FFFVisit &#187; '. preg_replace('/\$S/i', '', $aseco->server->challenge->name) .'$Z$FFF &#171; at"/>';
		$content .= '<quad posn="40 0 0" sizen="7 4" image="'. $tmkarma_config['images']['tmx_logo_normal'] .'" imagefocus="'. $tmkarma_config['images']['tmx_logo_focus'] .'" url="'. preg_replace('/(&)/', '&amp;', $aseco->server->challenge->tmx->pageurl) .'"/>';
		$content .= '</frame>';
	}
	else {
		// Show link to www.tm-exchange.com
		$content .= '<frame posn="26 -0.2 1">';
		$content .= '<quad posn="40 0 0" sizen="7 4" image="'. $tmkarma_config['images']['tmx_logo_normal'] .'" imagefocus="'. $tmkarma_config['images']['tmx_logo_focus'] .'" url="http://www.tm-exchange.com/"/>';
		$content .= '</frame>';
	}

	$content .= '</frame>';
	$content .= '</manialink>';
	$content .= '</manialinks>';

	$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $content, 0, false);
	$player->data['KarmaReminderWindow'] = true;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// This close the reminder window from given Player or all Players
function tmkarma_closeReminderWindow ($player = false) {
	global $aseco, $tmkarma_config;


	// If there no players, bail out immediately
	if (count($aseco->server->players->player_list) == 0) {
		return;
	}

	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_closeReminderWindow()');
	}

	// Build the Manialink
	$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $tmkarma_config['manialink_id'] .'01"></manialink>';
	$xml .= '</manialinks>';

	if ($player != false) {
		if ($player->data['KarmaReminderWindow'] == true) {
			$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);
			$player->data['KarmaReminderWindow'] = false;
		}
	}
	else {
		// Reset state at all Players
		foreach ($aseco->server->players->player_list as &$player) {
			if ($player->data['KarmaReminderWindow'] == true) {
				$player->data['KarmaReminderWindow'] = false;
			}
		}
		unset($player);

		// Send manialink to all Player
		$aseco->client->query('SendDisplayManialinkPage', $xml, 0, false);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function tmkarma_sendHelpAboutWindow ($player, $message, $display = true) {
	global $aseco, $tmkarma_config;


	$xml =  '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<manialinks>';
	$xml .= '<manialink id="'. $tmkarma_config['manialink_id'] .'05"></manialink>';		// Close DetailWindow
	$xml .= '<manialink id="'. $tmkarma_config['manialink_id'] .'02">';

	if ($display == true) {
		// Window
		$xml .= '<frame posn="-40.1 30.45 -3">';	// BEGIN: Window Frame
		$xml .= '<quad posn="0.8 -0.8 0.01" sizen="78.4 53.7" bgcolor="001B"/>';
		$xml .= '<quad posn="-0.2 0.2 0.09" sizen="80.4 55.7" style="Bgs1InRace" substyle="BgCard3"/>';

		// Header Line
		$xml .= '<quad posn="0.8 -1.3 0.02" sizen="78.4 3" bgcolor="09FC"/>';
		$xml .= '<quad posn="0.8 -4.3 0.03" sizen="78.4 0.1" bgcolor="FFF9"/>';
		$xml .= '<quad posn="1.8 -1.4 0.10" sizen="2.8 2.8" style="Icons128x128_1" substyle="NewTrack"/>';
		$xml .= '<label posn="5.5 -1.8 0.10" sizen="74 0" halign="left" textsize="2" scale="0.9" textcolor="FFFF" text="$L[http://'. $tmkarma_config['urls']['website'] .'/Downloads/]TM-Karma.com$L/'. $tmkarma_config['version'] .' for XAseco"/>';

		// Close Button
		$xml .= '<frame posn="77.4 1.3 0">';
		$xml .= '<quad posn="0 0 0.10" sizen="4 4" style="Icons64x64_1" substyle="ArrowDown"/>';
		$xml .= '<quad posn="1.1 -1.35 0.11" sizen="1.8 1.75" bgcolor="EEEF"/>';
		$xml .= '<quad posn="0.65 -0.7 0.12" sizen="2.6 2.6" action="'. $tmkarma_config['manialink_id'] .'03" style="Icons64x64_1" substyle="Close"/>';
		$xml .= '</frame>';

		$xml .= '<frame posn="3 -6 0">';
		$xml .= '<quad posn="54 4 0.11" sizen="23 23" image="'. $tmkarma_config['images']['tmkarma_logo'] .'" url="http://www.tm-karma.com"/>';
		$xml .= '<label posn="0 0 0.10" sizen="57 0" autonewline="1" textsize="1" textcolor="FF0F" text="'. $message .'"/>';
		$xml .= '</frame>';

		$xml .= '</frame>';	// Window
	}

	$xml .= '</manialink>';
	$xml .= '</manialinks>';

	$aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, 0, false);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function tmkarma_saveKarmaVotes () {
	global $aseco, $tmkarma_config, $karma;


	// Send the new vote from the last Map to the central database and store them local (if enabled)
	if ( (isset($karma['new']['players'])) && (count($karma['new']['players']) > 0) ) {

		// Check if connection was failed
		if ( ($tmkarma_config['retrytime'] > 0) && (time() >= $tmkarma_config['retrytime']) ) {

			// Reconnect to the database
			tmkarma_onSync($aseco);
		}

		if ($tmkarma_config['connected'] == true) {

			// Check for all required parameters for an remote API Call
			if ( ($karma['data']['uid'] == '') && ($karma['data']['name'] == '') && ($karma['data']['author'] == '') && ($karma['data']['env'] == '') ) {
				$aseco->console('[plugin.tm-karma-dot-com.php] Could not do a remote API Call "Vote", one of the required parameter missed! uid:'. $karma['data']['uid'] .' name:'. $karma['data']['name'] .' author:'. $karma['data']['author'] .' env:'. $karma['data']['env']);
			}

			// Build the Player/Vote pairs
			$pairs = '';
			foreach ($karma['new']['players'] as $login => &$vote) {
				$pairs .= urlencode($login) .'='. $vote .'|';

			}
			unset($login, $vote);
			$pairs = substr($pairs, 0, strlen($pairs)-1);		// remove trailing |


			// Generate the url for this Votes
			$api_url = sprintf("%s?Action=Vote&login=%s&authcode=%s&uid=%s&map=%s&author=%s&env=%s&votes=%s&tmx=%s",
				$tmkarma_config['urls']['api'],
				urlencode( $tmkarma_config['account']['login'] ),
				urlencode( $tmkarma_config['account']['authcode'] ),
				urlencode( $karma['data']['uid'] ),
				base64_encode( $karma['data']['name'] ),
				urlencode( $karma['data']['author'] ),
				urlencode( $karma['data']['env'] ),
				$pairs,
				$karma['data']['tmx']
			);


			$response = tmkarma_httpConnect($api_url, 'GET', false, $tmkarma_config['user_agent']);
			if ($response['Code'] == 200) {

				// Read the response
				if ($xml = @simplexml_load_string($response['Message'], null, LIBXML_COMPACT) ) {
					if (!$xml->status == 200) {
						$aseco->console('[plugin.tm-karma-dot-com.php] Storing votes failed with returncode "'. $xml->status .'"');
					}
					unset($xml);
				}
				else {
					$aseco->console("[plugin.tm-karma-dot-com.php] Could not read/parse response from tm-karma.com '". $response['Message'] ."'!");
				}
			}
			else {
				$aseco->console('[plugin.tm-karma-dot-com.php] tmkarma_saveKarmaVotes() connection failed with '. $response['Code'] .' ('. $response['Reason'] .') for url ['. $api_url .']');

				$tmkarma_config['retrytime'] = (time() + $tmkarma_config['retrywait']);
			}
		}


		// Check if karma should saved local also
		if ($tmkarma_config['save_karma_also_local'] == true) {

			$logins = '';
			foreach ($karma['new']['players'] as $login => &$vote) {
				$logins .= "'". $login ."',";
			}
			unset($login, $vote);
			$logins = substr($logins, 0, strlen($logins)-1);		// remove trailing ,


			$query = "
			SELECT
				`p`.`Login` AS `Login`,
				`k`.`Id` AS `VoteId`
			FROM `rs_karma` AS `k`
			LEFT JOIN `players` AS `p` ON `p`.`Id`=`k`.`PlayerId`
			WHERE `p`.`Login` IN (". $logins .")
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
								$aseco->console('[plugin.tm-karma-dot-com.php] Could not UPDATE karma vote for "'. $row->Login .'" [for statement "'. $query2 .'"]!');
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
			$values = '';
			foreach ($karma['new']['players'] as $login => &$vote) {
				if ( !isset($updated[$login]) ) {
					$playerid = $aseco->getPlayerId($login);
					if ($playerid > 0) {
						// Add only Players with an PlayerId
						$values .= "('". $vote ."', '". $playerid ."', '". $karma['data']['id'] ."'),";
					}
				}
			}
			unset($login, $vote);
			$values = substr($values, 0, strlen($values)-1);		// remove trailing ,

			if ($values != '') {
				$result = mysql_query($query2.$values);
				if (!$result) {
					$aseco->console('[plugin.tm-karma-dot-com.php] Could not INSERT karma votes... [for statement "'. $query2.$values .'"]!');
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

function tmkarma_syncGlobaAndLocalVotes ($source) {
	global $aseco, $tmkarma_config, $karma;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_syncGlobaAndLocalVotes()');
	}

	// Switch source and destination if required
	$destination = 'local';
	if ($source == 'local') {
		$destination = 'global';
	}

	$found = false;
	foreach ($karma[$source]['players'] as $login => &$votes) {
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

			// Set the sync'd vote as a new vote to store them into the database at onNewChallenge
			$karma['new']['players'][$login] = $votes['vote'];

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
	unset($login, $votes);

	if ($found == true) {
		// Update the $destination $karma
		tmkarma_calculateKarma(array($destination));

		// Update the KarmaWidget for all Players
		tmkarma_sendWidgetCombination(array('cups_values'), false);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function tmkarma_doKarmaRemoteCall ($challenge, $target = false) {
	global $aseco, $tmkarma_config;


	// If there no players, bail out immediately
	if (count($aseco->server->players->player_list) == 0) {
		return;
	}

	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_doKarmaRemoteCall()');
	}

	// Check if connection was failed and try to reconnect
	if ($tmkarma_config['retrytime'] > 0) {
		if (time() >= $tmkarma_config['retrytime']) {
			tmkarma_onSync($aseco);
		}
		else {
			return tmkarma_setEmptyKarma();
		}
	}

	if ( ($tmkarma_config['urls']['api'] == '') || ($tmkarma_config['connected'] == false) ) {
		if ( ($tmkarma_config['retrytime'] > 0) && (time() >= $tmkarma_config['retrytime']) ) {
			tmkarma_onSync($aseco);
		}
		else {
			return tmkarma_setEmptyKarma();
		}
	}

	// Check for all required parameters for an remote API Call
	if ( ($challenge->uid == '') || ($challenge->name == '') || ($challenge->author == '') || ($challenge->environment == '') ) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Could not do a remote API Call "Vote", one of the required parameter missed! uid:'. $challenge->uid .' name:'. $challenge->name .' author:'. $challenge->author .' env:'. $challenge->environment);
		return tmkarma_setEmptyKarma();
	}

	$players = array();
	if ($target != false) {
		// Get Karma for ONE Player
		$player_list = array($target);
	}
	else {
		// Get Karma for ALL Players
		$player_list = $aseco->server->players->player_list;
	}
	foreach ($player_list as &$player) {
		$players[] = urlencode($player->login);
	}
	unset($player);


	// Generate the url for this Track-Karma-Request
	$api_url = sprintf("%s?Action=Get&login=%s&authcode=%s&uid=%s&map=%s&author=%s&env=%s&player=%s",
		$tmkarma_config['urls']['api'],
		urlencode( $tmkarma_config['account']['login'] ),
		urlencode( $tmkarma_config['account']['authcode'] ),
		urlencode( $challenge->uid ),
		base64_encode( $challenge->name ),
		urlencode( $challenge->author ),
		urlencode( $challenge->environment ),
		implode('|', $players)					// Already Url-Encoded
	);


	$response = tmkarma_httpConnect($api_url, 'GET', false, $tmkarma_config['user_agent']);
	if ($response['Code'] == 200) {

		// Read the response
		if (!$xml = @simplexml_load_string($response['Message'], null, LIBXML_COMPACT) ) {
			$aseco->console("[plugin.tm-karma-dot-com.php] Could not read/parse response from tm-karma.com '". $response['Message'] ."'!");
			return tmkarma_setEmptyKarma();
		}
		else {
			if ($xml->status == 200) {

				$newk = array();
				$newk['global']['votes']['fantastic']['percent']	= (float)$xml->votes->fantastic['percent'];
				$newk['global']['votes']['fantastic']['count']		= (int)$xml->votes->fantastic['count'];
				$newk['global']['votes']['beautiful']['percent']	= (float)$xml->votes->beautiful['percent'];
				$newk['global']['votes']['beautiful']['count']		= (int)$xml->votes->beautiful['count'];
				$newk['global']['votes']['good']['percent']		= (float)$xml->votes->good['percent'];
				$newk['global']['votes']['good']['count']		= (int)$xml->votes->good['count'];

				$newk['global']['votes']['bad']['percent']		= (float)$xml->votes->bad['percent'];
				$newk['global']['votes']['bad']['count']		= (int)$xml->votes->bad['count'];
				$newk['global']['votes']['poor']['percent']		= (float)$xml->votes->poor['percent'];
				$newk['global']['votes']['poor']['count']		= (int)$xml->votes->poor['count'];
				$newk['global']['votes']['waste']['percent']		= (float)$xml->votes->waste['percent'];
				$newk['global']['votes']['waste']['count']		= (int)$xml->votes->waste['count'];

				$newk['global']['votes']['karma']			= (int)$xml->votes->karma;
				$newk['global']['votes']['total']			= ($newk['global']['votes']['fantastic']['count'] + $newk['global']['votes']['beautiful']['count'] + $newk['global']['votes']['good']['count'] + $newk['global']['votes']['bad']['count'] + $newk['global']['votes']['poor']['count'] + $newk['global']['votes']['waste']['count']);

				// Insert the votes for every Player
				foreach ($player_list as &$player) {
					foreach ($xml->players->player as $pl) {
						if ($player->login == $pl['login']) {
							$newk['global']['players'][$player->login]['vote']	= (int)$pl['vote'];
							$newk['global']['players'][$player->login]['previous']	= (int)$pl['previous'];
						}
					}
				}
				unset($player);

				// If <require_finish> is enabled
				if ($tmkarma_config['require_finish'] > 0) {
					// Has the Player already vote this Map? If true, set to 9999 for max.
					foreach ($aseco->server->players->player_list as &$player) {
						foreach ($xml->players->player as $pl) {
							if ( ($player->login == $pl['login']) && ((int)$pl['vote'] != 0) ) {
								// Set the state of finishing this map, if not already has a setup of a != 0 value
								if ($player->data['KarmaPlayerFinishedMap'] == 0) {
									$player->data['KarmaPlayerFinishedMap'] = 9999;
								}
							}
						}
					}
					unset($player, $pl);
				}
				return $newk;
			}
			else {
				$aseco->console('[plugin.tm-karma-dot-com.php] Connection failed with "'. $xml->status .'" for url ['. $api_url .']');
				return tmkarma_setEmptyKarma();
			}
		}
	}
	else {
		$aseco->console('[plugin.tm-karma-dot-com.php] tmkarma_doKarmaRemoteCall() connection failed with '. $response['Code'] .' ('. $response['Reason'] .') for url ['. $api_url .']');

		$tmkarma_config['retrytime'] = (time() + $tmkarma_config['retrywait']);

		return tmkarma_setEmptyKarma();
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function tmkarma_findPlayersLocalRecords ($challenge_id, $player_list) {
	global $aseco;


	$player_ids = '';
	foreach ($player_list as &$player) {
		$player_ids .= "'". $player->data['KarmaDatabasePlayerId'] ."',";
	}
	unset($player);
	// remove the last ,
	$player_ids = substr($player_ids, 0, strlen($player_ids)-1);

	$query = "SELECT `p`.`Login` AS `login`,COUNT(`t`.`Id`) AS `count` FROM `rs_times` AS `t` LEFT JOIN `players` AS `p` ON `p`.`Id`=`t`.`playerID` WHERE `t`.`playerID` IN (". $player_ids .") AND `t`.`challengeID`='". $challenge_id ."' GROUP BY `p`.`Login`;";
	$res = mysql_query($query);

	if ($res) {
		if (mysql_num_rows($res) > 0) {
			while ($row = mysql_fetch_object($res)) {
				foreach ($aseco->server->players->player_list as &$player) {
					if ($player->login == $row->login) {
						$player->data['KarmaPlayerFinishedMap'] = (int)$row->count;
					}
				}
				unset($player);
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

function tmkarma_getChallengeInfo () {
	global $aseco, $tmkarma_config;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_getChallengeInfo()');
	}

	$challenge = new stdClass;

	// Try hard to get the UniqueId of Track.
	// Within StartUp of XAseco and on Event 'onPlayerConnect'
	// is '$aseco->server->challenge->uid' always not set,
	// the Event 'onPlayerConnect' is released too early.
	if ($aseco->server->challenge->uid == '') {
		// Name, UId, FileName, Author, Environnement, Mood, BronzeTime, SilverTime, GoldTime, AuthorTime, CopperPrice and LapRace
		$aseco->client->query('GetCurrentChallengeInfo');
		$response = $aseco->client->getResponse();
		$challenge->uid		= $response['UId'];
		$challenge->name	= $response['Name'];
		$challenge->author	= $response['Author'];
		$challenge->environment	= $response['Environnement'];		// "Environnement" is wrong written by nadeo!!!

		// Need to find out the Datebase Id of the challenge
		$query = "SELECT `Id`,`Uid`,`Name`,`Author`,`Environment` FROM `challenges` WHERE `Uid`='". mysql_real_escape_string($challenge->uid) ."';";
		$res = mysql_query($query);
		if ($res) {
			if (mysql_num_rows($res) == 1) {
				$row = mysql_fetch_object($res);
				$challenge->id		= $row->Id;
				$challenge->uid		= $row->Uid;
				$challenge->name	= $row->Name;
				$challenge->author	= $row->Author;
				$challenge->environment	= $row->Environment;
			}
			mysql_free_result($res);
		}

	}
	else {
		$challenge->id		= $aseco->server->challenge->id;
		$challenge->uid		= $aseco->server->challenge->uid;
		$challenge->name	= $aseco->server->challenge->name;
		$challenge->author	= $aseco->server->challenge->author;
		$challenge->environment	= $aseco->server->challenge->environment;
	}

	// Fallback
	if ( ( !isset($challenge->id) ) && ($challenge->uid) ) {
		$query = "SELECT `Id`,`Uid`,`Name`,`Author`,`Environment` FROM `challenges` WHERE `Uid`='". mysql_real_escape_string($challenge->uid) ."';";
		$res = mysql_query($query);
		if ($res) {
			if (mysql_num_rows($res) == 1) {
				$row = mysql_fetch_object($res);
				$challenge->id		= $row->Id;
				$challenge->uid		= $row->Uid;
				$challenge->name	= $row->Name;
				$challenge->author	= $row->Author;
				$challenge->environment	= $row->Environment;
			}
			mysql_free_result($res);
		}
	}

	return $challenge;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function tmkarma_getLocalKarma ($ChallengeId = false) {
	global $aseco, $tmkarma_config, $karma;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_getLocalKarma()');
	}

	// Bail out if $ChallengeId is not given
	if ($ChallengeId == false) {
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
		  WHERE `ChallengeId`='$ChallengeId'
		  AND `Score`='3'
		) AS `FantasticCount`,
		(
		  SELECT COUNT(`Score`)
		  FROM `rs_karma`
		  WHERE `ChallengeId`='$ChallengeId'
		  AND `Score`='2'
		) AS `BeautifulCount`,
		(
		  SELECT COUNT(`Score`)
		  FROM `rs_karma`
		  WHERE `ChallengeId`='$ChallengeId'
		  AND `Score`='1'
		) AS `GoodCount`,
		(
		  SELECT COUNT(`Score`)
		  FROM `rs_karma`
		  WHERE `ChallengeId`='$ChallengeId'
		  AND `Score`='-1'
		) AS `BadCount`,
		(
		  SELECT COUNT(`Score`)
		  FROM `rs_karma`
		  WHERE `ChallengeId`='$ChallengeId'
		  AND `Score`='-2'
		) AS `PoorCount`,
		(
		  SELECT COUNT(`Score`)
		  FROM `rs_karma`
		  WHERE `ChallengeId`='$ChallengeId'
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
	tmkarma_calculateKarma(array('local'));
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function tmkarma_getLocalVotes ($ChallengeId, $login = false) {
	global $aseco, $tmkarma_config, $karma;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_getLocalVotes()');
	}

	// Bail out if $ChallengeId is not given
	if ($ChallengeId == false) {
		return;
	}


	// Build the Player votes Array
	$logins = '';
	if ($login == false) {
		// Add all Players
		foreach ($aseco->server->players->player_list as &$player) {
			$logins .= "'". $player->login ."',";
		}
		unset($player);
		$logins = substr($logins, 0, strlen($logins)-1);		// remove trailing ,
	}
	else {
		// Add only given Player
		$logins = "'". $login ."'";
	}

	// Request the Player votes
	$votes = array();
	$query = "
	SELECT
		`p`.`Login`,
		`k`.`Score`
	FROM `rs_karma` AS `k`
	LEFT JOIN `players` AS `p` ON `p`.`Id`=`k`.`PlayerId`
	WHERE `k`.`ChallengeId`='$ChallengeId'
	AND `p`.`Login` IN ($logins);
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
		// If some Players has not voted this Map, we need to add them with Vote=0
		foreach ($aseco->server->players->player_list as &$player) {
			if ( !isset($karma['local']['players'][$player->login]) ) {
				$karma['local']['players'][$player->login]['vote'] = 0;
			}
		}
		unset($player);
	}
	else if ( !isset($karma['local']['players'][$login]) ) {
		$karma['local']['players'][$login]['vote'] = 0;
	}


	// Find out which Player already vote this Map? If true, set to 9999 for max.
	if ($tmkarma_config['require_finish'] > 0) {
		foreach ($aseco->server->players->player_list as &$player) {
			if ($karma['local']['players'][$player->login]['vote'] != 0) {
				// Set the state of finishing this map, if not already has a setup of a != 0 value
				if ($player->data['KarmaPlayerFinishedMap'] == 0) {
					$player->data['KarmaPlayerFinishedMap'] = 9999;
				}
			}
		}
		unset($player);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// Returns the current amount of server oppers
function tmkarma_getServerCoppers () {
	global $aseco, $tmkarma_config;


	$aseco->client->resetError();
	$aseco->client->query('GetServerCoppers');
	$coppers = $aseco->client->getResponse();

	if ( $aseco->client->isError() ) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Error getting the amount of server coppers: [' . $aseco->client->getErrorCode() . '] ' . $aseco->client->getErrorMessage());
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

// Returns an empty $karma
function tmkarma_setEmptyKarma () {
	global $aseco;


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

	foreach ($aseco->server->players->player_list as &$player) {
		$empty['global']['players'][$player->login]['vote']	= 0;
		$empty['global']['players'][$player->login]['previous']	= 0;
	}
	unset($player);
	return $empty;
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// Checks plugin version at MasterAdmin connect
function tmkarma_uptodateCheck ($player) {
	global $aseco, $tmkarma_config;


	// Check if connection was failed and try to reconnect
	if ($tmkarma_config['retrytime'] > 0) {
		if (time() >= $tmkarma_config['retrytime']) {
			tmkarma_onSync($aseco);
		}
		else {
			return;
		}
	}

	$url = 'http://'. $tmkarma_config['urls']['website'] .'/.downloads/plugin-releases.xml';
	$response = tmkarma_httpConnect($url, 'GET', false, $tmkarma_config['user_agent']);
	if ($response['Code'] == 200) {

		// Read the response
		if ($xml = @simplexml_load_string($response['Message'], null, LIBXML_COMPACT) ) {
			$current_release = $xml->xaseco1xx;
			if ( version_compare($current_release, $tmkarma_config['version'], '>') ) {
				$release_url = 'http://'. $tmkarma_config['urls']['website'] .'/Downloads/';
				$message = formatText($tmkarma_config['messages']['uptodate_new'],
					$current_release,
					'$L[' . $release_url . ']' . $release_url . '$L'
				);
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
			}
			else {
				if ($tmkarma_config['uptodate_info'] == 'DEFAULT') {
					$message = formatText($tmkarma_config['messages']['uptodate_ok'],
						$tmkarma_config['version']
					);
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
				}
			}
		}
		else {
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($tmkarma_config['messages']['uptodate_failed']), $player->login);
			$aseco->console('[plugin.tm-karma-dot-com.php] tmkarma_uptodateCheck() parsing xml response failed!');
		}
	}
	else {
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($tmkarma_config['messages']['uptodate_failed']), $player->login);
		$aseco->console('[plugin.tm-karma-dot-com.php] tmkarma_uptodateCheck() connection failed with '. $response['Code'] .' ('. $response['Reason'] .') for url ['. $api_url .']');
	}

}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

// Simple HTTP-Connect function with timeout, taken from XAseco basic.inc.php/web_access.inc.php
// and modified to our needs.
// $method = 'GET' or 'POST'
function tmkarma_httpConnect ($geturl, $method, $content = false, $useragent = 'tmkarma_httpConnect()') {
	global $aseco, $tmkarma_config;


	if ($tmkarma_config['debug'] == true) {
		$aseco->console('[plugin.tm-karma-dot-com.php] Called tmkarma_httpConnect() with method "'. $method .'" for Url "'. $geturl .'"');
	}

	$url = parse_url($geturl);
	$url['port'] = isset($url['port']) ? $url['port'] : 80;

	$fp = @fsockopen($url['host'], $url['port'], $errno, $errstr, $tmkarma_config['connect_timeout']);
	if ($fp == false) {
		return array(
			'Code'		=> 503,
			'Reason'	=> $errstr,
			'Message'	=> ''
		);
	}

	if (!(get_resource_type($fp) == 'stream')) {
		return array(
			'Code'		=> 503,
			'Reason'	=> $errstr,
			'Message'	=> ''
		);
	}

	if ($method == 'GET') {
		$query = isset($url['query']) ? '?'.$url['query'] : '';

		$request = '';
		$request .= 'GET '. $url['path'] . $query ." HTTP/1.0\r\n";
		$request .= 'Host: '. $url['host'] ."\r\n";
		$request .= 'User-Agent: '. $useragent ."\r\n";
		$request .= "Accept-Charset: UTF-8\r\n";
		$request .= "Accept-Encoding: identity;\r\n";
		$request .= "Connection: Close\r\n";
		$request .= "\r\n";

		fwrite($fp, $request);
	}
	else if ($method == 'POST') {
		$request = '';
		$request .= 'POST '. $url['path'] ." HTTP/1.0\r\n";
		$request .= 'Host: '. $url['host'] ."\r\n";
		$request .= 'User-Agent: '. $useragent ."\r\n";
		$request .= "Accept-Charset: UTF-8\r\n";
		$request .= "Accept-Encoding: identity;\r\n";
		$request .= "Content-Type: application/x-www-form-urlencoded; charset=UTF-8\r\n";
		$request .= "Content-Range: bytes\r\n";
		$request .= 'Content-Length: '. strlen($content) ."\r\n";
		$request .= "Connection: Close\r\n";
		$request .= "\r\n";
		$request .= $content;

		fwrite($fp, $request);
	}
	stream_set_timeout($fp, $tmkarma_config['wait_timeout']);

	$response = '';
	$meta['timed_out'] = false;
	while (!feof($fp) && !$meta['timed_out']) {
		$response .= fread($fp, 128);
		$meta = stream_get_meta_data($fp);
	}
	fclose($fp);

	if ( $meta['timed_out'] ) {
		return array(
			'Code'		=> 408,
			'Reason'	=> 'Request Timeout',
			'Message'	=> ''
		);
	}
	else {
		if (substr($response, 9, 3) != '200') {
			return array(
				'Code'		=> substr($response, 9, 3),
				'Reason'	=> substr($response, 13),
				'Message'	=> ''
			);
		}
		else {
			$content = explode("\r\n\r\n", $response, 2);
			return array(
				'Code'		=> 200,
				'Reason'	=> 'OK',
				'Message'	=> $content[1]
			);
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function tmkarma_calculateKarma ($which) {
	global $karma;


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
		$karma[$location]['votes']['karma'] = floor( ($good_votes + $bad_votes) / $totalvotes);

		$karma[$location]['votes']['total'] = intval($totalvotes);
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function tmkarma_exportVotes ($player) {
	global $aseco, $tmkarma_config;


	if ($tmkarma_config['import_done'] != false) {
		$message = "{#server}>> {#admin}Export of local votes already done, skipping...";
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		return;
	}

	$message = "{#server}>> {#admin}Collecting players with their votes on tracks...";
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
						$tmkarma_config['account']['login'],
						$tmkarma_config['account']['authcode'],
						$tmkarma_config['account']['nation'],
						$row->Login,
						$row->Score
					);
				}
				$count ++;
			}
		}
		mysql_free_result($res);
	}

	$message = "{#server}>> {#admin}Found ". number_format($count, 0, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) ." votes in database.";
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);


	// gzip the CSV
	$message = "{#server}>> {#admin}Compressing collected data...";
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
	$csv = gzencode($csv, 9, FORCE_GZIP);


	// Encode them Base64
	$message = "{#server}>> {#admin}Encoding data...";
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
	$csv = base64_encode($csv);


	$message = "{#server}>> {#admin}Sending now the export with size of ". number_format(strlen($csv), 0, $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['decimal_sep'], $tmkarma_config['NumberFormat'][$tmkarma_config['number_format']]['thousands_sep']) ." bytes...";
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);


	// Generate the HTTP-POST-Body
	$request_body = sprintf("Action=Import&login=%s&authcode=%s&nation=%s&ExportData=%s",
		urlencode( $tmkarma_config['account']['login'] ),
		urlencode( $tmkarma_config['account']['authcode'] ),
		urlencode( $tmkarma_config['account']['nation'] ),
		urlencode( $csv )
	);


	// Generate the url for the Import-Request
	$api_url = sprintf("%s", $tmkarma_config['urls']['api']);


	$response = tmkarma_httpConnect($api_url, 'POST', $request_body, $tmkarma_config['user_agent']);
	if ($response['Code'] == 200) {
		$tmkarma_config['import_done'] = true;		// Set to true, otherwise only after restart XAseco knows that
		$message = '{#server}>> {#admin}Export done. Thanks for supporting tm-karma.com!';
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
	}
	else if ($response['Code'] == 406) {
		$message = '{#server}>> {#error}Export rejected! Please check your <login> and <nation> in config file "tmkarma.xml"!';
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
	}
	else if ($response['Code'] == 409) {
		$message = '{#server}>> {#error}Export rejected! Export was already done, allowed only one time!';
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
	}
	else {
		$message = '{#server}>> {#error}Connection failed with '. $response['Code'] .' ('. $response['Reason'] .') for url ['. $api_url .']' ."\n\r";
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
	}
}

?>
