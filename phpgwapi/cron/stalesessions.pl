#!/usr/bin/perl
# /**************************************************************************\
# * phpGroupWare                                                             *
# * http://www.phpgroupware.org                                              *
# * Written by Joseph Engo <jengo@phpgroupware.org>                          *
# * --------------------------------------------                             *
# *  This program is free software; you can redistribute it and/or modify it *
# *  under the terms of the GNU General Public License as published by the   *
# *  Free Software Foundation; either version 2 of the License, or (at your  *
# *  option) any later version.                                              *
# \**************************************************************************/
# $Id$

use DBI;

# Config section

$db_host = 'localhost';
$db_name = 'phpGroupWare';
$db_user = 'phpgroupware';
$db_pass = 'my_password';

# Email users when they don't log out ?
# If you are selecting this option.  You might want to customize the message
# below.

# Note: This takes much longer to clean out the database each time. Since it
#       must go select each user and then email them.

$email_user = 'Y';
$sendmail_location = '/usr/sbin/sendmail';

# Where should the message be comming from ?
$message_from = "webmaster\@mydomain.com";

# This is how long a user can be at idle before being deleted. Look at the
# SECURITY file for more information.
# The default is set to 2 hours.

$secs_to_stale = '3600';


# uncomment the line for your database.
# mysql
$dbase = DBI->connect("DBI:mysql:$db_name;$db_host",$db_user,$db_pass);
# postgresql
#$dbase = DBI->connect("DBI:Pg:dbname=$db_name;host=$db_host",$db_user,$db_pass);

# End of config section

$staletime = time() - $secs_to_stale;

if ($email_user eq 'Y') {
   $command = $dbase->prepare("select session_lid,session_logintime,session_ip,session_id from "
                            . "phpgw_sessions where session_dla <= '$staletime'");
   $command->execute();

   while (@session_data = $command->fetchrow_array()) {
     send_message($session_data[0],$session_data[1],$session_data[2]);
     $command2 = $dbase->do("delete from phpgw_sessions where session_id='$session_data[3]'");
   }
} else {
   $command = $dbase->do("delete from phpgw_sessions where session_dla <= '$staletime'");
}

#$command->finish();
$dbase->disconnect();

sub send_message($$$)
{
  my($loginid, $logintime,$IP)=@_;
  open ( SENDMAIL, "|$sendmail_location -t" ) || warn "Can't open sendmail";
   print SENDMAIL "To: $loginid\@localhost\n";
   print SENDMAIL "Subject: Important account information\n";
   print SENDMAIL "From: $message_from\n\n";
   print SENDMAIL "According to our records, you have not logged out during ";
   print SENDMAIL "your recent session.  It is important to that you click ";
   print SENDMAIL "on the logout icons when done with your session. ";
   print SENDMAIL "\nFor more information, contact your system admin.\n\n";
   print SENDMAIL "Session information:\n";
   print SENDMAIL "logintime: " . localtime( $logintime ) . "\n";
   print SENDMAIL "IP Address: " . $IP . "\n.\n";
  close ( SENDMAIL );
}
