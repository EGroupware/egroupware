<table border="0" width="100%" cellpadding="0" cellspacing="0">
 <tr>
  <td width="2%">
   &nbsp;
  </td>
  <td width="2%">
   <a href="<?php echo $phpgw->link("day.php","year=".$phpgw->preferences->show_date_other("Y",time())."&month=".$phpgw->preferences->show_date_other("m", time())."&day=".$phpgw->preferences->show_date_other("d",time())); ?>">
    <img src="<?php echo $phpgw_info["server"]["app_images"]; ?>/today.gif" alt="<?php echo lang_calendar("Today"); ?>" border="0">
   </a>
  </td>
  <td width="2%" align="left">
   <a href="<?php echo $phpgw->link("week.php","date=".$phpgw->preferences->show_date_other("Ymd",time())); ?>">
    <img src="<?php echo $phpgw_info["server"]["app_images"]; ?>/week.gif" alt="<?php echo lang_calendar("This week"); ?>" border="0">
   </a>
  </td>
  <td width="2%" align="left">
   <a href="<?php echo $phpgw->link("index.php","date=".$phpgw->preferences->show_date_other("Ymd",time())); ?>">
    <img src="<?php echo $phpgw_info["server"]["app_images"]; ?>/month.gif" alt="<?php echo lang_calendar("This month"); ?>" border="0">
   </a>
  </td>
  <td align="right">
   <form action="<?php echo $phpgw_info["server"]["app_root"]; ?>/search.php">
    <?php echo $phpgw->session->hidden_var(); ?>
    <input type="hidden" name="from" value="<?php echo $PHP_SELF; ?>">
    <input type="hidden" name="date" value="<?php echo $date; ?>">
    <input type="hidden" name="month" value="<?php echo $thismonth; ?>">
    <input type="hidden" name="day" value="<?php echo $thisday; ?>">
    <input type="hidden" name="year" value="<?php echo $thisyear; ?>">
    <input name="keywords">
    <input type="submit" name="submit" value="<?php echo lang_common("Search"); ?>">
   </form>
  </td>
 </tr>
</table>
