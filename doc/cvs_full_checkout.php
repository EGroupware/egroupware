#!/usr/bin/php
<?php
	/**************************************************************************\
	* eGroupWare                                                               *
	* http://www.egroupware.org                                                *
	* The file written by Dan Kuykendall <seek3r@phpgroupware.org>             *
	*                     Joseph Engo    <jengo@phpgroupware.org>              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	/****************************************************************************\
	* Config section                                                             *
	\****************************************************************************/
	// Temp paths that can be read and written to
	$tmp_dir       = '/tmp';
	// Path of where you want the egroupware directory to go.  NO trailing /
	$co_dir        = '/var/www/html';
	// If you do not have developer access to cvs, change to True
	$cvs_anonymous = True;
	// Only needed if you have developers cvs access
	$cvs_login     = '';


	// Modules you want to checkout, do NOT add the egroupware module
	$co_modules[] = 'addressbook';
	$co_modules[] = 'admin';
	$co_modules[] = 'backup';
	$co_modules[] = 'bookmarks';
	$co_modules[] = 'calendar';
	$co_modules[] = 'comic';
	$co_modules[] = 'developer_tools';
	$co_modules[] = 'email';
	$co_modules[] = 'filemanager';
	$co_modules[] = 'forum';
	$co_modules[] = 'ftp';
	$co_modules[] = 'headlines';
	$co_modules[] = 'infolog';
	$co_modules[] = 'manual';
	$co_modules[] = 'messenger';
	$co_modules[] = 'news_admin';
	$co_modules[] = 'phpgwapi';
	$co_modules[] = 'phpsysinfo';
	$co_modules[] = 'polls';
	$co_modules[] = 'preferences';
	$co_modules[] = 'projects';
	$co_modules[] = 'setup';
	$co_modules[] = 'skel';
	$co_modules[] = 'soap';
	$co_modules[] = 'stocks';
	$co_modules[] = 'tts';
	$co_modules[] = 'xmlrpc';

   // -- End config section

	function docvscommand($command, $anonymous_login = False)
	{
		global $tmp_dir, $cvs_anonymous;

		$fp = fopen($tmp_dir . '/createrelease.exp','w');
		$contents = "#!/usr/bin/expect -f\n";
		$contents .= "send -- \"export CVS_RSH=ssh\"\n";
		$contents .= "set force_conservative 0\n";
		$contents .= "if {\$force_conservative} {\n";
		$contents .= "      set send_slow {1 .1}\n";
		$contents .= "      proc send {ignore arg} {\n";
		$contents .= "              sleep .1\n";
		$contents .= "              exp_send -s -- \$arg\n";
		$contents .= "      }\n";
		$contents .= "}\n";
		$contents .= "set timeout -1\n";
		$contents .= "spawn $command\n";
		$contents .= "match_max 100000\n";

		if ($cvs_anonymous && $anonymous_login)
		{
			$contents .= "expect \"CVS password:\"\n";
			$contents .= "send -- \"\\r\"\n";
		}

		$contents .= "expect eof\n";
		fputs($fp, $contents, strlen($contents));
		fclose($fp);
		system('/usr/bin/expect ' . $tmp_dir . '/createrelease.exp');
		unlink($tmp_dir . '/createrelease.exp');
	}

	chdir($co_dir);
	if ($cvs_anonymous)
	{
		docvscommand('cvs -d:pserver:anonymous@subversions.gnu.org:443/cvsroot/phpgroupware login',True);
		docvscommand('cvs -d:pserver:anonymous@subversions.gnu.org:443/cvsroot/phpgroupware co phpgroupware',True);
	}
	else
	{
		docvscommand('cvs -d' . $cvs_login . '@subversions.gnu.org:/cvsroot/phpgroupware co phpgroupware');
	}

	chdir($co_dir . '/phpgroupware');

	if ($cvs_anonymous)
	{
		docvscommand('cvs -z3 -d:pserver:anonymous@subversions.gnu.org:443/cvsroot/phpgroupware co ' . implode(' ',$co_modules));
	}
	else
	{
		docvscommand('cvs -d' . $cvs_login . '@subversions.gnu.org:/cvsroot/phpgroupware co ' . implode(' ',$co_modules));
	}

	docvscommand('cvs update -dP');
