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

  if ($submit || ! $ab_id) {
     $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True);
  }

  $phpgw_info["flags"]["currentapp"] = "addressbook";
  include("../header.inc.php");
  
  if (! $ab_id) {
     Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"]. "/addressbook/",
	       "cd=16&order=$order&sort=$sort&filter=$filter&start=$start&query=$query"));
     exit;
  }

  if (! $submit) {
     $phpgw->db->query("SELECT * FROM addressbook WHERE ab_owner='"
		           . $phpgw_info["user"]["userid"] . "' AND ab_id=$ab_id");
     $phpgw->db->next_record();

     $fields = array('ab_id'	=> $phpgw->db->f("ab_id"),
 		           'owner'	=> $phpgw->db->f("ab_owner"),
			      'access'	=> $phpgw->db->f("ab_access"),
			      'firstname' => $phpgw->db->f("ab_firstname"),
			      'lastname' => $phpgw->db->f("ab_lastname"),
       			      'title'   => $phpgw->db->f("ab_title"),
			      'email'	=> $phpgw->db->f("ab_email"),
			      'hphone'	=> $phpgw->db->f("ab_hphone"),
 			      'wphone'	=> $phpgw->db->f("ab_wphone"),
			      'fax'	=> $phpgw->db->f("ab_fax"),
			      'pager'	=> $phpgw->db->f("ab_pager"),
			      'mphone'	=> $phpgw->db->f("ab_mphone"),
			      'ophone'	=> $phpgw->db->f("ab_ophone"),
			      'street'	=> $phpgw->db->f("ab_street"),
                	      'address2' => $phpgw->db->f("ab_address2"),
			      'city'	=> $phpgw->db->f("ab_city"),
			      'state'	=> $phpgw->db->f("ab_state"),
			      'zip'	=> $phpgw->db->f("ab_zip"),
			      'bday'	=> $phpgw->db->f("ab_bday"),
			      'company' => $phpgw->db->f("ab_company"),
			      'company_id' => $phpgw->db->f("ab_company_id"),
			      'notes'	=> $phpgw->db->f("ab_notes")
		          );

     form("","edit.php","Edit",$fields);

  } else {
    $bday = $bday_month . "/" . $bday_day . "/" . $bday_year;
    if ($access != "private" && $access != "public") {
     $access = $phpgw->accounts->array_to_string($access,$n_groups);
    }

    if($phpgw_info["apps"]["timetrack"]["enabled"]) {
      $sql = "UPDATE addressbook set ab_email='" . addslashes($email)
            . "', ab_firstname='". addslashes($firstname)
	    . "', ab_lastname='" . addslashes($lastname)
            . "', ab_title='"   . addslashes($title)
	    . "', ab_hphone='" 	. addslashes($hphone)
	    . "', ab_wphone='" 	. addslashes($wphone)
	    . "', ab_fax='" 	. addslashes($fax)
	    . "', ab_pager='" 	. addslashes($pager)
	    . "', ab_mphone='" 	. addslashes($mphone)
	    . "', ab_ophone='" 	. addslashes($ophone)
	    . "', ab_street='" 	. addslashes($street)
            . "', ab_address2='" . addslashes($address2)
	    . "', ab_city='" 	. addslashes($city)
	    . "', ab_state='" 	. addslashes($state)
	    . "', ab_zip='" 	. addslashes($zip)
	    . "', ab_bday='" 	. addslashes($bday)
	    . "', ab_notes='" 	. addslashes($notes)
	    . "', ab_company_id='" . addslashes($company)
	    . "', ab_access='" 	. addslashes($access)
	    . "'  WHERE ab_owner='" . $phpgw_info["user"]["userid"] . "' AND ab_id=$ab_id";
     } else {
      $sql = "UPDATE addressbook set ab_email='" . addslashes($email)
            . "', ab_firstname='". addslashes($firstname)
            . "', ab_lastname='" . addslashes($lastname)
            . "', ab_title='"   . addslashes($title)
            . "', ab_hphone='"  . addslashes($hphone)
            . "', ab_wphone='"  . addslashes($wphone)
            . "', ab_fax='"     . addslashes($fax)
            . "', ab_pager='"   . addslashes($pager)
            . "', ab_mphone='"  . addslashes($mphone)
            . "', ab_ophone='"  . addslashes($ophone)
            . "', ab_street='"  . addslashes($street)
            . "', ab_address2='" . addslashes($address2)
            . "', ab_city='"    . addslashes($city)
            . "', ab_state='"   . addslashes($state)
            . "', ab_zip='"     . addslashes($zip)
            . "', ab_bday='"    . addslashes($bday)
            . "', ab_notes='"   . addslashes($notes)
            . "', ab_company='" . addslashes($company)
            . "', ab_access='"  . addslashes($access)
            . "'  WHERE ab_owner='" . $phpgw_info["user"]["userid"] . "' AND ab_id=$ab_id";
     }

     $phpgw->db->query($sql);

     Header("Location: " . $phpgw->link("view.php","&ab_id=$ab_id&order=$order&sort=$sort&filter="
 	     . "$filter&start=$start"));
     exit;
  }

?>
   <input type="hidden" name="ab_id" value="<? echo $ab_id; ?>">
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
                <a href="<?php echo $phpgw->link("view.php","ab_id=$ab_id") . "\">" . lang("Cancel"); ?></a>
              </TD>
              <TD align=right> 
               <a href="<?php echo $phpgw->link("delete.php","ab_id=$ab_id") . "\">" . lang("Delete"); ?></a>
              </TD>
            </TR>
            </TBODY> 
          </TABLE>

</DIV>
</BODY>
</HTML>

<?php
  $phpgw->common->phpgw_footer();
?>
