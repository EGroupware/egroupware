<?php
  // stalesession.php - to use instead of stalesession.pl
  // may be invoked via cron with "php stalesession.php"

  // config start
  $purgedelay = "3600";  // define allowed idle time before deletion in seconds
  $purgetime  = time() - $purgedelay;
  $db_user    = $ARGV[1];
  $db_pwd     = "phpgr0upwar3";
  $db_server  = "localhost";
  $db_db      = "phpGroupWare";
  // config end - do not edit after here unless you really know what you do!
  
  // establish link:
  $link = mysql_connect("$db_server","$db_user","$db_pwd");
  mysql_query("use $db_db", $link);

  // delete old (timed out) sessions
  $query = sprintf("delete from sessions where dla <= \"$purgetime\"");
  $res = mysql_query($query, $link);
?>
