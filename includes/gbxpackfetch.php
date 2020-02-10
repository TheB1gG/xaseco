#!/usr/bin/php -q
<?php
// vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2:

// Simple command line driver for GBXPackFetcher class
// Created Oct 2012 by Xymph <tm@gamers.org>

	require_once('gbxdatafetcher.inc.php');

	if (!isset($argv[1]) || $argv[1] == '') {
		echo "missing filename\n";
		return;
	}
	$filename = $argv[1];
	$gbx = new GBXPackFetcher(true, true);
	try
	{
		$gbx->processFile($filename);
	}
	catch (Exception $e)
	{
		echo $e->getMessage() . "\n";
	}
	print_r($gbx);

//	$gbxdata = file_get_contents($filename);
//	$gbx = new GBXPackFetcher(true, true);
//	try
//	{
//		$gbx->processData($gbxdata);
//	}
//	catch (Exception $e)
//	{
//		echo $e->getMessage() . "\n";
//	}
//	print_r($gbx);
?>
