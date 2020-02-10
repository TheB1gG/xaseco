#!/usr/bin/php -q
<?php
// vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2:

// Simple command line driver for GBXChallengeFetcher class
// Created Jan 2008 by Xymph <tm@gamers.org>

	include_once('gbxdatafetcher.inc.php');

	$filename = $argv[1];
	$gbx = new GBXChallengeFetcher($filename, true);

	print_r($gbx);
	// file_put_contents('thumb.jpg', $gbx->thumbnail);
?>
