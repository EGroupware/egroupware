#!/usr/bin/php
<?php
	/**************************************************************************\
	* phpGroupWare                                                             *
	* http://www.phpgroupware.org                                              *
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
	// Path of where you want the phpgroupware directory to go.  NO trailing /
	$co_dir        = '/var/www/html';
	// If you do not have developer access to cvs, change to True
	$cvs_anonymous = True;
	// Only needed if you have developers cvs access
	$cvs_login     = '';
	$cvs_password  = '';


	// Modules you want to checkout, do NOT add the phpgroupware module
	$co_modules[] = 'addressbook';
	$co_modules[] = 'admin';
	$co_modules[] = 'backup';
	$co_modules[] = 'bookkeeping';
	$co_modules[] = 'bookmarks';
	$co_modules[] = 'brewer';
	$co_modules[] = 'calendar';
	$co_modules[] = 'cart';
	$co_modules[] = 'ccs';
	$co_modules[] = 'cdb';
	$co_modules[] = 'chat';
	$co_modules[] = 'chora';
	$co_modules[] = 'comic';
	$co_modules[] = 'cron';
	$co_modules[] = 'developer_tools';
	$co_modules[] = 'dj';
	$co_modules[] = 'eldaptir';
	$co_modules[] = 'email';
	$co_modules[] = 'filemanager';
	$co_modules[] = 'forum';
	$co_modules[] = 'ftp';
	$co_modules[] = 'headlines';
	$co_modules[] = 'hr';
	$co_modules[] = 'infolog';
	$co_modules[] = 'inv';
	$co_modules[] = 'manual';
	$co_modules[] = 'mediadb';
	$co_modules[] = 'meerkat';
	$co_modules[] = 'messenger';
	$co_modules[] = 'napster';
	$co_modules[] = 'netsaint';
	$co_modules[] = 'news_admin';
	$co_modules[] = 'nntp';
	$co_modules[] = 'notes';
	$co_modules[] = 'phonelog';
	$co_modules[] = 'phpGWShell_Win32_VB';
	$co_modules[] = 'phpgwapi';
	$co_modules[] = 'phpgwnetsaint';
	$co_modules[] = 'phpsysinfo';
	$co_modules[] = 'phpwebhosting';
	$co_modules[] = 'polls';
	$co_modules[] = 'preferences';
	$co_modules[] = 'projects';
	$co_modules[] = 'qmailldap';
	$co_modules[] = 'rbs';
	$co_modules[] = 'setup';
	$co_modules[] = 'skel';
	$co_modules[] = 'soap';
	$co_modules[] = 'squirrelmail';
	$co_modules[] = 'stocks';
	$co_modules[] = 'syncml-server';
	$co_modules[] = 'timetrack';
	$co_modules[] = 'todo';
	$co_modules[] = 'transy';
	$co_modules[] = 'tts';
	$co_modules[] = 'wap';
	$co_modules[] = 'wcm';
	$co_modules[] = 'weather';
	$co_modules[] = 'xmlrpc';

   // -- End config section

	function docvscommand($command, $anonymous_login = False)
	{
		global $tmp_dir, $cvs_password, $cvs_anonymous;

		$fp = fopen($tmp_dir . '/createrelease.exp','w');
		$contents = "#!/usr/bin/expect -f\n";
		$contents = "send -- \"export CVS_RSH=ssh\"\n";
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

		if (! $cvs_anonymous)
		{
			$contents .= "expect \":\"\n";
			$contents .= "send -- \"" . $cvs_password . "\\r\"\n";
		}
		else if ($cvs_anonymous && $anonymous_login)
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
		docvscommand('cvs -d' . $cvs_login . '@subversions.gnu.org:443/cvsroot/phpgroupware co phpgroupware');
	}

	chdir($co_dir . '/phpgroupware');

	if ($cvs_anonymous)
	{
		docvscommand('cvs -z3 -d:pserver:anonymous@subversions.gnu.org:443/cvsroot/phpgroupware co ' . implode(' ',$co_modules));
	}
	else
	{
		docvscommand('cvs -d' . $cvs_login . '@subversions.gnu.org:443/cvsroot/phpgroupware co ' . implode(' ',$co_modules));
	}

	docvscommand('cvs update -dP');
