#!/usr/bin/php -q
<?php
	/**************************************************************************\
	* eGroupWare - Setup - db-schema-processor - unit tests                    *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	// For security reasons we exit by default if called via the webserver
	if (isset($_SERVER['HTTP_HOST']))
	{
		die ('Access denied !!!');
	}
	// the used domain has to be given as first parameter if called on the commandline or as domain= on the url
	if (!isset($_GET['domain'])) 
	{
		$_GET['domain'] = $_SERVER['argc'] > 1 ? $_SERVER['argv'][1] : 'default';
	}
	$path_to_egroupware = realpath(dirname(__FILE__).'/../..');	//  need to be adapted if this script is moved somewhere else

	$phpgw_info = array(
		'flags' => array(
			'disable_Template_class' => True,
			'login'                  => True,
			'currentapp'             => 'login',
			'noheader'               => True,
		)
	);
	include ($path_to_egroupware.'/header.inc.php');
	$GLOBALS['phpgw_info']['server']['asyncservice'] = 'off';

	// now we should have a valid db-connection
	$adodb = &$GLOBALS['phpgw']->ADOdb;
	$db = &$GLOBALS['phpgw']->db;

	if (isset($_SERVER['HTTP_HOST'])) echo "<pre>\n";
	echo "Serverinfo: Domain $_GET[domain]: $db->Type($db->Database)\n";
	print_r($adodb->ServerInfo());
	
	// creating a schema_proc instance
	$schema_proc = CreateObject('phpgwapi.schema_proc',$db->Type);
	$schema_proc->debug = isset($_GET['debug']) ? $_GET['debug'] : ($_SERVER['argc'] > 2 ? $_SERVER['argv'][2] :  0);

	// define a test-table to create
	$test_tables = array(
		'schema_proc_test' => array(
			'fd' => array(
				'test_auto' => array('type' => 'auto'),
				'test_int4' => array('type' => 'int','precision' => '4'),
				'test_varchar' => array('type' => 'varchar','precision' => '128'),
				'test_char' => array('type' => 'char','precision' => '10'),
				'test_timestamp' => array('type' => 'timestamp','default'=>'current_timestamp'),
				'test_text' => array('type' => 'text'),
				'test_blob' => array('type' => 'blob'),
			),
			'pk' => array('test_auto'),
			'fk' => array(),
			'ix' => array('test_varchar',array('test_text','options'=>array('mysql'=>'FULLTEXT','maxdb'=>false,'pgsql'=>false))),
			'uc' => array('test_char')
		),
	);
	
	echo "Creating table(s):\n";
	$meta_tables = $adodb->MetaTables();
	foreach($test_tables as $name => $definition)
	{
		// droping the tables, if they exist from a previous run
		if (in_array($name,$meta_tables) || in_array(strtoupper($name),$meta_tables))
		{
			$schema_proc->DropTable($name);
		}
		$schema_proc->CreateTable($name,$definition);
	}
	
	echo "\nReading back the tables via MetaColumns:\n";
	foreach($test_tables as $name => $definition)
	{
		$columns = $adodb->MetaColumns($name);
		if (!$columns || count($columns) <= 0)
		{
			echo "\n\n!!! Table '$name' has NOT been created !!!\n\n";
		}
		else
		{
			print_r($columns);
			print_r($adodb->MetaIndexes($name));
		}		
	}
	echo "Inserting some content:\n";
	$adodb->Execute("INSERT INTO schema_proc_test (test_int4,test_varchar,test_char) VALUES (1,'Hallo Ralf','0123456789')");
	$adodb->Execute("INSERT INTO schema_proc_test (test_int4,test_varchar,test_char) VALUES (2,'Hallo wer noch?','9876543210')");
	
	echo "Droping column test_blob:\n";
	$new_table_def = $test_tables['schema_proc_test'];
	unset($new_table_def['fd']['test_blob']);
	$schema_proc->DropColumn('schema_proc_test',$new_table_def,'test_blob');
	
	echo "Altering column test_char to varchar(32):\n";
	$schema_proc->AlterColumn('schema_proc_test','test_char',array('type' => 'varchar','precision' => 32));
	
	echo "Adding column test_bool bool:\n";
	$schema_proc->AddColumn('schema_proc_test','test_bool',array('type' => 'bool'));
	
	echo "Renaming column test_timestamp to test_time:\n";
	$schema_proc->RenameColumn('schema_proc_test','test_timestamp','test_time');
	
	echo "Renaming table schema_proc_test to schema_proc_renamed:\n";
	$schema_proc->RenameTable('schema_proc_test','schema_proc_renamed');
	
	print_r($adodb->MetaColumns('schema_proc_renamed'));
	print_r($adodb->MetaIndexes('schema_proc_renamed'));
	
	echo "Inserting some more content:\n";
	$db->query("INSERT INTO schema_proc_renamed (test_int4,test_varchar,test_char) VALUES (10,'Hallo Hallo Hallo ...','12345678901234567890123456789012')");

	echo "Reading back the content:\n";
	$all = $adodb->GetAll("SELECT * FROM schema_proc_renamed");
	print_r($all);

	$schema_proc->RenameTable('schema_proc_renamed','schema_proc_test');	// so we can drop it under its original name
	
	echo "\nDroping the test-tables again:\n";
	if (!$schema_proc->DropAllTables($test_tables)) echo "!!!Failed !!!\n";
	
	echo "\nbye ...\n";
	
