#!/usr/bin/perl
	############################################################################
	# phpGroupWare                                                             #
	# http:#www.phpgroupware.org                                               #
	# The file written by Miles Lott <milosch@phpgroupware.org>                #
	# --------------------------------------------                             #
	#  This program is free software; you can redistribute it and/or modify it #
	#  under the terms of the GNU General Public License as published by the   #
	#  Free Software Foundation; either version 2 of the License, or (at your  #
	#  option) any later version.                                              #
	############################################################################

	# $Id$

	#**************************************************************************#
	# Config section                                                           #
	#**************************************************************************#
	# Temp paths that can be read and written to
	$tmp_dir       = '/tmp';
	# Path of where you want the phpgroupware directory to go.  NO trailing /
	$co_dir        = '/var/www/html';
	# If you do not have developer access to cvs, change to True
	$cvs_anonymous = True;
	# Only needed if you have developers cvs access
	$cvs_login    = '';

	# Modules you want to checkout, do NOT add the phpgroupware module
	@co_modules = (
		'addressbook',
		'admin',
		'backup',
		'bookkeeping',
		'bookmarks',
		'brewer',
		'calendar',
		'cart',
		'ccs',
		'cdb',
		'chat',
		'chora',
		'comic',
		'cron',
		'developer_tools',
		'dj',
		'eldaptir',
		'email',
		'filemanager',
		'forum',
		'ftp',
		'headlines',
		'hr',
		'infolog',
		'inv',
		'manual',
		'mediadb',
		'meerkat',
		'messenger',
		'napster',
		'netsaint',
		'news_admin',
		'nntp',
		'notes',
		'phonelog',
		'phpGWShell_Win32_VB',
		'phpgwapi',
		'phpgwnetsaint',
		'phpsysinfo',
		'polls',
		'preferences',
		'projects',
		'qmailldap',
		'rbs',
		'setup',
		'skel',
		'soap',
		'squirrelmail',
		'stocks',
		'syncml-server',
		'timetrack',
		'todo',
		'transy',
		'tts',
		'wap',
		'wcm',
		'weather',
		'xmlrpc'
	);

	# -- End config section

	sub docvscommand
	{
		my $command = $_[0];
		my $anonymous_login = $_[1];

		open(FP, ">$tmp_dir/createrelease.exp");
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

		if ($cvs_anonymous and $anonymous_login)
		{
			$contents .= "expect \"CVS password:\"\n";
			$contents .= "send -- \"\\r\"\n";
		}

		$contents .= "expect eof\n";
		print FP $contents;
		close FP;
		system('/usr/bin/expect ' . $tmp_dir . '/createrelease.exp');
		unlink($tmp_dir . '/createrelease.exp');
	}

	chdir($co_dir);
	if ($cvs_anonymous)
	{
		&docvscommand('cvs -d:pserver:anonymous@subversions.gnu.org:443/cvsroot/phpgroupware login',True);
		&docvscommand('cvs -d:pserver:anonymous@subversions.gnu.org:443/cvsroot/phpgroupware co phpgroupware',True);
	}
	else
	{
		&docvscommand('cvs -d' . $cvs_login . '@subversions.gnu.org:/cvsroot/phpgroupware co phpgroupware');
	}

	chdir($co_dir . '/phpgroupware');

	if ($cvs_anonymous)
	{
		&docvscommand('cvs -z3 -d:pserver:anonymous@subversions.gnu.org:443/cvsroot/phpgroupware co ' . join(' ',@co_modules));
	}
	else
	{
		&docvscommand('cvs -d' . $cvs_login . '@subversions.gnu.org:/cvsroot/phpgroupware co ' . join(' ',@co_modules));
	}

	&docvscommand('cvs update -dP');
