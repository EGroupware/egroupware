<table border="0" width="100%" cellpadding="0" cellspacing="0">
 <tr>
  <td width="2%">
   &nbsp;
  </td>
  <td width="2%">
   <a href="<?php echo $phpgw->link("day.php","year=".$phpgw->common->show_date(time(),"Y")."&month=".$phpgw->common->show_date(time(),"m")."&day=".$phpgw->common->show_date(time(),"d")); ?>">
    <img src="<?php echo $phpgw_info["server"]["app_images"]; ?>/today.gif" alt="<?php echo lang_calendar("Today"); ?>" border="0">
   </a>
  </td>
  <td width="2%" align="left">
   <a href="<?php echo $phpgw->link("week.php","date=".$phpgw->common->show_date(time(),"Ymd")); ?>">
    <img src="<?php echo $phpgw_info["server"]["app_images"]; ?>/week.gif" alt="<?php echo lang_calendar("This week"); ?>" border="0">
   </a>
  </td>
  <td width="2%" align="left">
   <a href="<?php echo $phpgw->link("index.php","date=".$phpgw->common->show_date(time(),"Ymd")); ?>">
    <img src="<?php echo $phpgw_info["server"]["app_images"]; ?>/month.gif" alt="<?php echo lang_calendar("This month"); ?>" border="0">
   </a>
  </td>
  <td align="right">
   <form action="<?php echo $phpgw->link("search.php"); ?>" method="POST">
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
