#!/usr/bin/perl
	############################################################################
	# eGroupWare                                                               #
	# http://www.egroupware.org                                                #
	# The file written by Miles Lott <milos@groupwhere.org>                    #
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
	# Path of where you want the egroupware directory to go.  NO trailing /
	$co_dir        = '/var/www/html';
	# If you do not have developer access to cvs, change to True
	$cvs_anonymous = True;
	# Only needed if you have developers cvs access
	$cvs_login    = '';

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
		&docvscommand('cvs -d:pserver:anonymous@cvs.sourceforge.net:/cvsroot/egroupware login',True);
		&docvscommand('cvs -d:pserver:anonymous@cvs.sourceforge.net:/cvsroot/egroupware co egroupware',True);
	}
	else
	{
		&docvscommand('cvs -d' . $cvs_login . '@cvs.sourceforge.net:/cvsroot/egroupware co egroupware');
	}

	chdir($co_dir . '/egroupware');

	if ($cvs_anonymous)
	{
		&docvscommand('cvs -z3 -d:pserver:anonymous@cvs.sourceforge.net:/cvsroot/egroupware co all');
	}
	else
	{
		&docvscommand('cvs -d' . $cvs_login . '@cvs.sourceforge.net:/cvsroot/egroupware co all');
	}

	&docvscommand('cvs update -dP');
