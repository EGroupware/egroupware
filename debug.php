<?php
  /**************************************************************************\
  * phpGroupWare module (File Manager)                                       *
  * http://www.phpgroupware.org                                              *
  * Written by Dan Kuykendall <dan@kuykendall.org>                           *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  $phpgw_flags["currentapp"] = "home";
  include("header.inc.php");

  //if ($showme) {
  if ($save == "OK") {
    echo "sessionid is: ".$sessionid."<br>\n"
      ."kp3 is: ".$kp3."<br>\n"
      ."the hidden field is: ".$hfield1."<br>\n"
      ."you entered: ".$field1."<br>\n"
      ."If everything is displayed, its working fine";
    exit;
  }
?>
          <form name=showme method=post action="<?php echo $phpgw->link($PHP_SELF);?>">
            <input type=hidden name=hfield1 value="just fine.">
            <input type=text size="56" name="field1"><br>
            <input type=submit name="save" value="OK">
          </form>
<?php    include($phpgw_info["server"]["api_dir"] . "/footer.inc.php"); ?>
