<?php

  global $date, $year, $month, $day, $thisyear, $thismonth, $thisday, $filter, $keywords;
//  global $filter_method;
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

//  if($filter=="all") $filter_method = "All";
//  if($filter=="private") $filter_method = "Private Only";
//  if($filter=="public") $filter_method = "Global Public Only";
//  if($filter=="group") $filter_method = "Group Public Only";
//  if($filter=="private+public") $filter_method = "Private and Global Public";
//  if($filter=="private+group") $filter_method = "Private and Group Public";
//  if($filter=="public+private") $filter_method = "Global Public and Group Public";

  if (!isset($phpgw_info["flags"]["nocalendarheader"]) ||
      !$phpgw_info["flags"]["nocalendarheader"]) {

?>

<table border="0" width="100%" cols="8" cellpadding="0" cellspacing="0">
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
   <a href="<?php echo $phpgw->link("year.php","day=".$phpgw->calendar->today["day"]."&month=".$phpgw->calendar->today["month"]."&year=".$phpgw->calendar->today["year"]); ?>">
    <img src="<?php echo $phpgw_info["server"]["app_images"]; ?>/year.gif" alt="<?php echo lang("This year"); ?>" border="0">
   </a>
  </td>
  <td width="2%" align="left">
   <a href="<?php echo $phpgw->link("matrixselect.php","day=".$phpgw->calendar->today["day"]."&month=".$phpgw->calendar->today["month"]."&year=".$phpgw->calendar->today["year"]); ?>">
    <img src="<?php echo $phpgw_info["server"]["app_images"]; ?>/view.gif" alt="<?php echo lang("Daily Matrix View"); ?>" border="0">
   </a>
  </td>
  <form action="<?php echo $phpgw->link(""); ?>" name="filtermethod" method="POST">
   <td width="55%" align="center" valign="center">
    <b><?php echo lang("Filter"); ?>:</b>
    <input type="hidden" name="from" value="<?php echo $PHP_SELF; ?>">
    <?php if(isset($date) && $date) { ?>
    <input type="hidden" name="date" value="<?php echo $date; ?>">
    <?php } ?>
    <input type="hidden" name="month" value="<?php echo $thismonth; ?>">
    <input type="hidden" name="day" value="<?php echo $thisday; ?>">
    <input type="hidden" name="year" value="<?php echo $thisyear; ?>">
    <?php if(isset($keywords) && $keywords) { ?>
    <input type="hidden" name="keywords" value="<?php echo $keywords; ?>">
    <?php } ?>
    <select name="filter" onchange="document.filtermethod.submit()">
     <option value="all"<?php if((!isset($filter) || !$filter) || $filter=="all") echo " selected"; ?>>All</option>
     <option value="private"<?php if($filter=="private") echo " selected"; ?>>Private Only</option>
     <option value="public"<?php if($filter=="public") echo " selected"; ?>>Global Public Only</option>
     <option value="group"<?php if($filter=="group") echo " selected"; ?>>Group Public Only</option>
     <option value="private+public"<?php if($filter=="private+public") echo " selected"; ?>>Private and Global Public</option>
     <option value="private+group"<?php if($filter=="private+group") echo " selected"; ?>>Private and Group Public</option>
     <option value="public+group"<?php if($filter=="public+group") echo " selected"; ?>>Global Public and Group Public</option>
    </select>
   </td>
  </form>
  <form action="<?php echo $phpgw->link("search.php"); ?>" method="POST">
   <td align="right" valign="center">
    <input type="hidden" name="from" value="<?php echo $PHP_SELF; ?>">
    <?php if(isset($date) && $date) { ?>
    <input type="hidden" name="date" value="<?php echo $date; ?>">
    <?php } ?>
    <input type="hidden" name="month" value="<?php echo $thismonth; ?>">
    <input type="hidden" name="day" value="<?php echo $thisday; ?>">
    <input type="hidden" name="year" value="<?php echo $thisyear; ?>">
    <?php if(isset($filter) && $filter) { ?>
    <input type="hidden" name="filter" value="<?php echo $filter; ?>">
    <?php } ?>
    <input name="keywords"<?php if($keywords) echo " value=\"".$keywords."\""; ?>>
    <input type="submit" name="submit" value="<?php echo lang("Search"); ?>">
   </td>
  </form>
 </tr>
</table>
<?php
    flush();
    }
?>
