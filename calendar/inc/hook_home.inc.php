<?php
  $d1 = strtolower(substr($phpgw_info["server"]["app_inc"],0,3));
  if($d1 == "htt" || $d1 == "ftp" ) {
    echo "Failed attempt to break in via an old Security Hole!<br>\n";
    $phpgw->common->phpgw_exit();
  } unset($d1);

  $tmp_app_inc = $phpgw_info["server"]["app_inc"];
  $phpgw_info["server"]["app_inc"] = $phpgw_info["server"]["server_root"]."/calendar/inc";

  if ($phpgw_info["user"]["preferences"]["calendar"]["mainscreen_showevents"]) {
    include($phpgw_info["server"]["app_inc"].'/functions.inc.php');
    echo "\n".'<tr valign="top"><td><table border="0" cols="2"><tr><td align="center" width="30%" valign="top"><!-- Calendar info -->'."\n";
    echo $phpgw->calendar->mini_calendar($phpgw->calendar->today["day"],$phpgw->calendar->today["month"],$phpgw->calendar->today["year"],"day.php").'</td><td align="center" width="70%">';
    $now = $phpgw->calendar->splitdate(mktime (0, 0, 0, $phpgw->calendar->today["month"], $phpgw->calendar->today["day"], $phpgw->calendar->today["year"]) - ((60 * 60) * $phpgw_info["user"]["preferences"]["common"]["tz_offset"]));
    echo '<table border="0" width="70%" cellspacing="0" cellpadding="0"><tr><td align="center">'
	    . lang(date("F",$phpgw->calendar->today["raw"])).' '.$phpgw->calendar->today["day"].', '.$phpgw->calendar->today["year"].'</tr></td>'
        . '<tr><td bgcolor="'.$phpgw_info["theme"]["bg_text"].'" valign="top">';
    $phpgw->calendar->printer_friendly = True;
    echo $phpgw->calendar->print_day_at_a_glance($now).'</td></tr></table>'."\n";
    $phpgw->calendar->printer_friendly = False;
    echo "\n".'<!-- Calendar info --></table></td></tr>'."\n";
    unset($phpgw->calendar);
  } 


  $phpgw_info["server"]["app_inc"] = $tmp_app_inc;
?>
