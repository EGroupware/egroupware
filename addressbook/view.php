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
    Header("Location: " . $phpgw->link("index.php"));
  }

  if ($filter != "private")
     $filtermethod = " or ab_access='public' " . $phpgw->accounts->sql_search("ab_access");

  $phpgw->db->query("SELECT * FROM addressbook WHERE ab_id=$ab_id AND (ab_owner='"
	             . $phpgw_info["user"]["userid"] . "' $filtermethod)");
  $phpgw->db->next_record();

  $fields = array('ab_id'   => $phpgw->db->f("ab_id"),
 		        'owner'   => $phpgw->db->f("ab_owner"),
			   'access'  => $phpgw->db->f("ab_access"),
			   'firstname' => $phpgw->db->f("ab_firstname"),
			   'lastname' => $phpgw->db->f("ab_lastname"),
			   'email'   => $phpgw->db->f("ab_email"),
			   'hphone'  => $phpgw->db->f("ab_hphone"),
 		        'wphone'  => $phpgw->db->f("ab_wphone"),
			   'fax'	   => $phpgw->db->f("ab_fax"),
			   'pager'   => $phpgw->db->f("ab_pager"),
			   'mphone'  => $phpgw->db->f("ab_mphone"),
			   'ophone'  => $phpgw->db->f("ab_ophone"),
			   'street'  => $phpgw->db->f("ab_street"),
			   'city'	   => $phpgw->db->f("ab_city"),
			   'state'   => $phpgw->db->f("ab_state"),
			   'zip'	   => $phpgw->db->f("ab_zip"),
			   'bday'	   => $phpgw->db->f("ab_bday"),
			   'company' => $phpgw->db->f("ab_company"),
			   'notes'   => $phpgw->db->f("ab_notes")
		        );

  $owner = $phpgw->db->f("ab_owner");
  $ab_id = $phpgw->db->f("ab_id");

  form("view","","View",$fields);
?>
    <TABLE border=0 cellPadding=0 cellSpacing=0 width="95%">
      <TBODY> 
      <TR> 
        <TD> 
          <TABLE border=0 cellPadding=1 cellSpacing=1>
            <TBODY> 
            <TR> 
              <TD align=left> 
               <?php
                 echo $phpgw->common->check_owner($ab_id,$owner,"Edit");
               ?>
              </TD>
              <TD align=left>
                <a href="<?php echo $phpgw->link("index.php","order=$order&start=$start&filter=$filter&query=$query&sort=$sort"); ?>">Done</a>
              </TD>
            </TR>
            </TBODY> 
          </TABLE>
        </TD>
      </TR>
      </TBODY>
    </TABLE>
</DIV>
<?php include($phpgw_info["server"]["api_dir"] . "/footer.inc.php"); ?>
