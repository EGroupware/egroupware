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

  $phpgw_info["flags"]["currentapp"] = "addressbook";

  include("../header.inc.php");

  echo "<center>" . lang_addressbook("Address book");

  if (! $start)
     $start = 0;

  $limit =$phpgw->nextmatchs->sql_limit($start);

  if ($order)
     $ordermethod = "order by $order $sort";
  else
     $ordermethod = "order by lastname,firstname,email asc";

  if (! $filter) {
     $filter = "none";
  }

  if ($filter != "private") {
     if ($filter != "none") {
        $filtermethod = " access like '%,$filter,%' ";
     } else {
        $filtermethod = " (owner='" . $phpgw_info["user"]["userid"] ."' OR access='public' "
		            . $phpgw->accounts->sql_search("access") . " ) ";
     }
  } else {
     $filtermethod = " owner='" . $phpgw_info["user"]["userid"] . "' ";
  }

  if ($query) {
     $phpgw->db->query("select count(*) from addressbook where $filtermethod AND (lastname "
			. "like '%$query%' OR firstname like '%$query%' OR email like '%$query%"
			. "' OR street like '%$query%' OR city like '%$query%' OR state like '"
			. "%$query%' OR zip like '%$query%' OR notes like '%$query%' OR company"
			. " like '%$query%')");

    $phpgw->db->next_record();

     if ($phpgw->db->f(0) == 1)
        echo "<br>" . lang_common("your search returned 1 match");
     else
        echo "<br>" . lang_common("your search returned x matchs",$phpgw->db->f(0));
  } else {
     $phpgw->db->query("select count(*) from addressbook where $filtermethod");
  }

  $phpgw->db->next_record();

  if ($phpgw->db->f(0) > $phpgw_info["user"]["preferences"]["maxmatchs"])
     echo "<br>" . lang_common("showing x - x of x",($start + 1),
			   ($start + $phpgw_info["user"]["preferences"]["maxmatchs"]),$phpgw->db->f(0));
  else
     echo "<br>" . lang_common("showing x",$phpgw->db->f(0)); 
?>

<?php
 $phpgw->nextmatchs->show("index.php",$start,$phpgw->db->f(0),
		   "&order=$order&filter=$filter&sort="
		 . "$sort&query=$query", "75%", $phpgw_info["theme"][th_bg]);
?>

  <table width=75% border=0 cellspacing=1 cellpadding=3>
    <tr bgcolor="<?php echo $phpgw_info["theme"][th_bg]; ?>"> 
      <td width=29% height="21">
       <font size="-1" face="Arial, Helvetica, sans-serif">
        <?php echo $phpgw->nextmatchs->show_sort_order($sort,"lastname",$order,"index.php",
			      lang_common("Last Name"));
        ?>
       </font>
      </td>
      <td width="63%" height="21" bgcolor="<?php echo $phpgw_info["theme"][th_bg]; ?>">
       <font face="Arial, Helvetica, sans-serif" size="-1">
        <?php echo $phpgw->nextmatchs->show_sort_order($sort,"firstname",$order,"index.php",
			      lang_common("First Name"));
        ?>
       </font>
      </td>
      <td width="3%" height="21">
       <font face="Arial, Helvetica, sans-serif" size="-1">
         <?php echo lang_common("View"); ?>
       </font>
      </td>
      <td width="5%" height="21">
       <font face="Arial, Helvetica, sans-serif" size="-1">
         <?php echo lang_common("Edit"); ?>
       </font>
      </td>
    </tr>
  </form>

<?php
  if ($query) {
     $phpgw->db->query("SELECT * FROM addressbook WHERE $filtermethod AND (lastname like '"
			. "%$query%' OR firstname like '%$query%' OR email like '%$query%' OR "
	            . "street like '%$query%' OR city like '%$query%' OR state "
	            . "like '%$query%' OR zip like '%$query%' OR notes like "
	            . "'%$query%' OR company like %$query%') $ordermethod limit $limit");
  } else {
     $phpgw->db->query("SELECT * FROM addressbook WHERE $filtermethod $ordermethod limit "
			 . $limit);
  }

  while ($phpgw->db->next_record()) {
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);

    $firstname	= $phpgw->db->f("firstname");
    $lastname 	= $phpgw->db->f("lastname");
    $con	= $phpgw->db->f("con");

    /* This for for just showing the company name stored in lastname. */
    if (($lastname) && (! $firstname))
       $t_colspan = " colspan=2";
    else {
       $t_colspan = "";
       if ($firstname == "") $firstname = "&nbsp;";
       if ($lastname  == "") $lastname  = "&nbsp;";
    }

    ?>
      <tr bgcolor=<?php echo $tr_color; ?>>
       <td valign=top width=29%<?php echo $t_colspan; ?>>
        <font face=Arial, Helvetica, sans-serif size=2>
	 <?php echo $lastname; ?> 
        </font> 
       </td>
      <?php if (! $t_colspan)
         echo "
       <td valign=top width=63%>
        <font face=Arial, Helvetica, sans-serif size=2>
	 $firstname
        </font>
       </td>";
      ?>
       <td valign=top width=3%>
	<font face=Arial, Helvetica, sans-serif size=2>
          <a href="<?php echo $phpgw->link("view.php","con=$con&start=$start&order=$order&filter="
								 . "$filter&query=$query&sort=$sort");
	  ?>"> <?php echo lang_common("View"); ?> </a>
        </font>
       </td>
       <td valign=top width=5%>
        <font face=Arial, Helvetica, sans-serif size=2>
         <?php echo $phpgw->common->check_owner($phpgw->db->f("owner"),"edit.php",lang_common("edit"),"con=" . $phpgw->db->f("con")); ?>
        </font>
       </td>
      </tr>
     <?php
  }

?>
  </table>

  <form method="POST" action="<?php echo $phpgw->link("add.php"); ?>">
   <input type="hidden" name="sort" value="<?php echo $sort; ?>">
   <input type="hidden" name="order" value="<?php echo $order; ?>">
   <input type="hidden" name="query" value="<?php echo $query; ?>">
   <input type="hidden" name="start" value="<?php echo $start; ?>">
   <input type="hidden" name="filter" value="<?php echo $filter; ?>">
  <table width="75%" border="0" cellspacing="0" cellpadding="4">
    <tr> 
      <td width="4%"> 
        <div align="right"> 
          <input type="submit" name="Add" value="<?php echo lang_common("Add"); ?>">
        </div>
      </td>
      <td width="72%">&nbsp;</td>
      <td width="24%">&nbsp;</td>
    </tr>
  </table>
  </form>
</center>

<?php
  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
?>
