<?php
  /**************************************************************************\
  * phpGroupWare - addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  if ($submit) {
     $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True);
  }

  $phpgw_info["flags"]["currentapp"] = "addressbook";
  include("../header.inc.php");
  
  if ($add_email) {
     list($fields["lastname"],$fields["firstname"]) = explode(" ", $name);
     $fields["email"] = $add_email;
     form("","add.php","Add",$fields);
  } else if (! $submit && ! $add_email) {
     form("","add.php","Add","","","");
  } else {
     if ($bday_month == "" && $bday_day == "" && $bday_year == "")
        $bday = "";
     else
        $bday = "$bday_month/$bday_day/$bday_year";

     $access = $phpgw->accounts->array_to_string($access,$n_groups);

     $sql = "insert into addressbook (ab_owner,ab_access,ab_firstname,ab_lastname,ab_email,"
       	. "ab_hphone,ab_wphone,ab_fax,ab_pager,ab_mphone,ab_ophone,ab_street,ab_city,ab_state,ab_zip,ab_bday,"
          . "ab_notes,ab_company) values ('" . $phpgw_info["user"]["userid"] . "','$access','"
          . addslashes($firstname). "','"
          . addslashes($lastname) . "','"
          . addslashes($email) 	. "','" 
          . addslashes($hphone) . "','"
          . addslashes($wphone) . "','"
          . addslashes($fax) 	. "','"
          . addslashes($pager) 	. "','"
          . addslashes($mphone)	. "','"
          . addslashes($ophone)	. "','"
          . addslashes($street)	. "','"
          . addslashes($city) 	. "','"
          . addslashes($state) 	. "','"
          . addslashes($zip) 	. "','"
          . addslashes($bday) 	. "','"
          . addslashes($notes) 	. "','"
          . addslashes($company). "')";
     $phpgw->db->query($sql);
 
     Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/addressbook/",
            "cd=14"));
  }

?>
    <TABLE border=0 cellPadding=0 cellSpacing=0 width="95%">
      <TBODY> 
      <TR> 
        <TD> 
          <TABLE border=0 cellPadding=1 cellSpacing=1>
            <TBODY> 
            <TR> 
              <TD align=left> 
                <INPUT type=submit name=submit value="OK">
              </TD>
              <TD align=left> 
                <INPUT name=reset type=reset value="Clear">
              </TD>
              <TD align=left> 
                <a href="<?php echo $phpgw->link($phpgw_info["server"]["webserver_url"] . "/addressbook/") . "\">" . lang("Cancel"); ?></a>
              </TD>
            </TR>
            </TBODY> 
          </TABLE>
        </TD>
      </TR>
      </TBODY> 
    </TABLE>
  </FORM>
</DIV>
</BODY>
</HTML>

<?php
  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");

