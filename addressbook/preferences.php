<?php
  /**************************************************************************\
  * phpGroupWare - Address Book                                              *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "addressbook");
  include("../header.inc.php");

  if ($submit) {
     $totalerrors = 0;
     if (! count($ab_selected)) {
        $errors[$totalerrors++] = lang("You must select at least 1 column to display");
     }
     
     if (! $totalerrors) {
        $phpgw->common->preferences_delete("byapp",$phpgw_info["user"]["userid"],"addressbook");
        while ($pref = each($ab_selected)) {
          $phpgw->common->preferences_add($phpgw_info["user"]["userid"],$pref[0],"addressbook","addressbook_" . $pref[1]);
        }
        if ($mainscreen_showbirthdays) {
           $phpgw->common->preferences_add($phpgw_info["user"]["userid"],"mainscreen_showbirthdays","addressbook");
        }
        Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/preferences/index.php"));
     }
  }

  $phpgw->common->phpgw_header();
  $phpgw->common->navbar();

  if ($totalerrors) {  
     echo "<p><center>" . $phpgw->common->error_list($errors) . "</center>";
  }

  echo "<p><b>" . lang("select addressbook columns to display") . ":" . "</b><hr><p>";
?>
  <form method="POST" action="<?php echo $phpgw->link(); ?>">
   <table border="0" align="center" cellspacing="1" cellpadding="1">
    <?php
      // I need to create a common function to handle displaying multiable columns
    
      echo '<tr bgcolor="' . $phpgw_info["theme"]["th_bg"] . '"><td colspan="3">&nbsp;</td></tr>';
      $abc = get_abc();		# AddressBook Columns
      $i = 0; $j = 0;
      $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
      echo '<tr bgcolor="' . $tr_color . '">';
      while (list($col, $descr) = each($abc)) {
         $i++; $j++;
         echo '<td><input type="checkbox" name="ab_selected[' . $col . ']" value="True"'
            . ($phpgw_info["user"]["preferences"]["addressbook"][$col]?" checked":"") . '>' . $descr
            . '</option></td>';

         if ($i ==3) {
            echo "</tr>";
            $i = 0;
         }
         if ($i == 0) {
            $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
            echo '<tr bgcolor="' . $tr_color . '">';
         }
         if ($j == count($abc)) {
            echo "</tr>";
         }
      }
      $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
    ?>
    <tr bgcolor="<?php echo $tr_color; ?>">
     <td colspan="2"><?php echo lang("show current users on navigation bar"); ?></td>
     <td><input type="checkbox" name="mainscreen_showbirthdays" value="True"<?php if ($phpgw_info["user"]["preferences"]["addressbook"]["mainscreen_showbirthdays"]) echo " checked"; ?>></td>
    </tr>
    <tr>
     <td colspan="3" align="center">
      <input type="submit" name="submit" value="<?php echo lang("submit"); ?>">
     </td>
    </tr>
   </table>
  </form>
<?php
  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
?>