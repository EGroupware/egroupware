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
  
  if (! $con) {
     Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"]. "/addressbook/",
	       "cd=16&order=$order&sort=$sort&filter=$filter&start=$start&query=$query"));
     exit;
  }

  if (! $submit) {
     $phpgw->db->query("SELECT * FROM addressbook WHERE owner='"
		           . $phpgw_info["user"]["userid"] . "' AND con='$con'");
     $phpgw->db->next_record();

     $fields = array(
                'con'           => $phpgw->db->f("con"),
                'owner'         => $phpgw->db->f("owner"),
                'access'        => $phpgw->db->f("access"),
                'firstname'     => $phpgw->db->f("firstname"),
                'lastname'      => $phpgw->db->f("lastname"),
                'email'         => $phpgw->db->f("email"),
                'hphone'        => $phpgw->db->f("hphone"),
                'wphone'        => $phpgw->db->f("wphone"),
                'fax'           => $phpgw->db->f("fax"),
                'pager'         => $phpgw->db->f("pager"),
                'mphone'        => $phpgw->db->f("mphone"),
                'ophone'        => $phpgw->db->f("ophone"),
                'street'        => $phpgw->db->f("street"),
                'city'          => $phpgw->db->f("city"),
                'state'         => $phpgw->db->f("state"),
                'zip'           => $phpgw->db->f("zip"),
                'bday'          => $phpgw->db->f("bday"),
                'notes'         => $phpgw->db->f("notes"),
		'company'		=> $phpgw->db->f("company")
                    );

     form("","edit.php","Edit",$fields);

  } else {
    $bday = $bday_month . "/" . $bday_day . "/" . $bday_year;
    $access = $phpgw->accounts->array_to_string($access,$n_groups);

    $sql = "UPDATE addressbook set email='" . addslashes($email)
	 . "', firstname='"	. addslashes($firstname)
	 . "', lastname='" 	. addslashes($lastname)
	 . "', hphone='" 	. addslashes($hphone)
	 . "', wphone='" 	. addslashes($wphone)
	 . "', fax='" 		. addslashes($fax)
	 . "', pager='" 	. addslashes($pager)
	 . "', mphone='" 	. addslashes($mphone)
	 . "', ophone='" 	. addslashes($ophone)
	 . "', street='" 	. addslashes($street)
	 . "', city='" 		. addslashes($city)
	 . "', state='" 	. addslashes($state)
	 . "', zip='" 		. addslashes($zip)
	 . "', bday='" 		. addslashes($bday)
	 . "', notes='" 	. addslashes($notes)
	 . "', company='" 	. addslashes($company)
	 . "', access='" 	. addslashes($access)
	 . "'  WHERE owner='" . $phpgw_info["user"]["userid"] . "' AND con='$con'";

     $phpgw->db->query($sql);

     Header("Location: " . $phpgw->link("view.php","&con=$con&order=$order&sort=$sort&filter="
	  . "$filter&start=$start"));
     exit;
  }

?>
   <input type="hidden" name="con" value="<? echo $con; ?>">
   <input type="hidden" name="sort" value="<? echo $sort; ?>">
   <input type="hidden" name="order" value="<? echo $order; ?>">
   <input type="hidden" name="filter" value="<? echo $filter; ?>">
   <input type="hidden" name="start" value="<? echo $start; ?>">

          <TABLE border=0 cellPadding=1 cellSpacing=1 width="95%">
            <TBODY>
             <tr>
              <TD align=left width=7%>
               <input type="submit" name="submit" value="<?php echo lang("Submit"); ?>">
              </TD>
              <TD align=left width=7%>
                <a href="<?php echo $phpgw->link("view.php","con=$con") . "\">" . lang("Cancel"); ?></a>
              </TD>
              <TD align=right> 
               <a href="<?php echo $phpgw->link("delete.php","con=$con") . "\">" . lang("Delete"); ?></a>
              </TD>
            </TR>
            </TBODY> 
          </TABLE>

</DIV>
</BODY>
</HTML>

<?php
  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
