<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_info["flags"]["currentapp"] = "admin";

  include("../header.inc.php");
  check_code($cd);
?>

<p>
<br><a href="<?php echo $phpgw->link("accounts.php") . "\">" . lang("User accounts"); ?></a>
<br><a href="<?php echo $phpgw->link("groups.php")  . "\">" . lang("User groups"); ?></a>
<br><a href="<?php echo $phpgw->link("applications.php")  . "\">" . lang("Applications"); ?></a>
<p><a href="<?php echo $phpgw->link("currentusers.php") . "\">" . lang("View sessions"); ?></a>
<br><a href="<?php echo $phpgw->link("accesslog.php") . "\">" . lang("View Access Log"); ?></a>
<p><a href="<?php echo $phpgw->link("headlines.php") . "\">" . lang("Headline Sites"); ?></a>
<p><a href="<?php echo $phpgw->link("nntp.php") . "\">" . lang("Network News"); ?></a>
<?php
if ( $SHOW_INFO > 0 ) {
  echo "<p><a href=\"".$phpgw->link($PHP_SELF, "SHOW_INFO=0")."\">Hide PHP Information</a>";
  echo "<hr>\n";
  phpinfo();
  echo "<hr>\n";
}
else {
  echo "<p><a href=\"".$phpgw->link($PHP_SELF, "SHOW_INFO=1")."\">PHP Information</a>";
}
?>
<?php
  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
?>
