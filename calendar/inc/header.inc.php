<?php

  global $date, $year, $month, $day, $thisyear, $thismonth, $thisday;
  if(!isset($phpgw_info["user"]["preferences"]["calendar"]["weekdaystarts"]))
     $phpgw_info["user"]["preferences"]["calendar"]["weekdaystarts"] = "Sunday";

  if (isset($date) && strlen($date) > 0) {
     $thisyear  = substr($date, 0, 4);
     $thismonth = substr($date, 4, 2);
     $thisday   = substr($date, 6, 2);
  } else {
     if (!isset($day) || !$day)
        $thisday = $phpgw->calendar->today["day"];
     else
        $thisday = $day;
     if (!isset($month) || !$month)
        $thismonth = $phpgw->calendar->today["month"];
     else
        $thismonth = $month;
     if (!isset($year) || !$year)
        $thisyear = $phpgw->calendar->today["year"];
     else
        $thisyear = $year;
  }

  if (!isset($phpgw_info["flags"]["nocalendarheader"]) ||
      !$phpgw_info["flags"]["nocalendarheader"]) {

?>

<table border="0" width="100%" cellpadding="0" cellspacing="0">
 <tr>
  <td width="2%">
   &nbsp;
  </td>
  <td width="2%">
   <a href="<?php echo $phpgw->link("day.php","day=".$phpgw->calendar->today["day"]."&month=".$phpgw->calendar->today["month"]."&year=".$phpgw->calendar->today["year"]); ?>">
    <img src="<?php echo $phpgw_info["server"]["app_images"]; ?>/today.gif" alt="<?php echo lang("Today"); ?>" border="0">
   </a>
  </td>
  <td width="2%" align="left">
   <a href="<?php echo $phpgw->link("week.php","day=".$phpgw->calendar->today["day"]."&month=".$phpgw->calendar->today["month"]."&year=".$phpgw->calendar->today["year"]); ?>">
    <img src="<?php echo $phpgw_info["server"]["app_images"]; ?>/week.gif" alt="<?php echo lang("This week"); ?>" border="0">
   </a>
  </td>
  <td width="2%" align="left">
   <a href="<?php echo $phpgw->link("index.php","day=".$phpgw->calendar->today["day"]."&month=".$phpgw->calendar->today["month"]."&year=".$phpgw->calendar->today["year"]); ?>">
    <img src="<?php echo $phpgw_info["server"]["app_images"]; ?>/month.gif" alt="<?php echo lang("This month"); ?>" border="0">
   </a>
  </td>
  <td width="2%" align="left">
   <a href="<?php echo $phpgw->link("matrixselect.php","day=".$phpgw->calendar->today["day"]."&month=".$phpgw->calendar->today["month"]."&year=".$phpgw->calendar->today["year"]); ?>">
    <img src="<?php echo $phpgw_info["server"]["app_images"]; ?>/view.gif" alt="<?php echo lang("Daily Matrix View"); ?>" border="0">
   </a>
  </td>
  <td align="right">
   <form action="<?php echo $phpgw->link("search.php"); ?>" method="POST">
    <input type="hidden" name="from" value="<?php echo $PHP_SELF; ?>">
    <?php if(isset($date) && $date) { ?>
    <input type="hidden" name="date" value="<?php echo $date; ?>">
    <?php } ?>
    <input type="hidden" name="month" value="<?php echo $thismonth; ?>">
    <input type="hidden" name="day" value="<?php echo $thisday; ?>">
    <input type="hidden" name="year" value="<?php echo $thisyear; ?>">
    <input name="keywords">
    <input type="submit" name="submit" value="<?php echo lang("Search"); ?>">
   </form>
  </td>
 </tr>
</table>
<?php
    }
?>
