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
     $phpgw_flags = array("noheader" => True, "nonavbar" => True);
  }

  $phpgw_flags["currentapp"] = "addressbook";
  include("../header.inc.php");
  if (! $con)
    Header("Location: " . $phpgw_info["server"]["webserver_url"] . 
	    "/addressbook/?sessionid=" . $phpgw->session->id);

  if ($filter != "private")
     $filtermethod = " or access='public' " . $phpgw->groups->sql_search();

  $phpgw->db->query("SELECT * FROM addressbook WHERE con='$con' AND (owner='"
	      . $phpgw->session->loginid . "' $filtermethod)");
  $phpgw->db->next_record();

  $fields = array(
		'con'		=> $phpgw->db->f("con"),
		'owner'		=> $phpgw->db->f("owner"),
		'access'	=> $phpgw->db->f("access"),
		'firstname'	=> $phpgw->db->f("firstname"),
		'lastname'	=> $phpgw->db->f("lastname"),
		'email'		=> $phpgw->db->f("email"),
		'hphone'	=> $phpgw->db->f("hphone"),
		'wphone'	=> $phpgw->db->f("wphone"),
		'fax'		=> $phpgw->db->f("fax"),
		'pager'		=> $phpgw->db->f("pager"),
		'mphone'	=> $phpgw->db->f("mphone"),
		'ophone'	=> $phpgw->db->f("ophone"),
		'street'	=> $phpgw->db->f("street"),
		'city'		=> $phpgw->db->f("city"),
		'state'		=> $phpgw->db->f("state"),
		'zip'		=> $phpgw->db->f("zip"),
		'bday'		=> $phpgw->db->f("bday"),
		'company' 	=> $phpgw->db->f("company"),
		'notes'		=> $phpgw->db->f("notes")
		 );

  $owner = $phpgw->db->f("owner");
  $con   = $phpgw->db->f("con");

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
                 echo check_owner($owner,$con);
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
<?php
  //include($directorys["include_root"] . "/footer.inc.php");
