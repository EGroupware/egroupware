<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
  * Modified by Mark Peters <skeeter@phpgroupware.org>                       *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  if (floor($PHP_VERSION ) == 4) {
    global $date, $year, $month, $day, $thisyear, $thismonth, $thisday, $filter, $keywords;
    global $matrixtype, $participants, $owner, $phpgw, $grants, $rights;
  }
?>

<table border="0" width="100%" cols="8" cellpadding="0" cellspacing="0">
 <tr>
  <td width="2%">
   &nbsp;
  </td>
  <td width="2%">
   <a href="<?php echo $phpgw->link('day.php','day='.$phpgw->calendar->today['day'].'&month='.$phpgw->calendar->today['month'].'&year='.$phpgw->calendar->today['year'].'&owner='.$owner); ?>">
    <img src="<?php echo $phpgw_info['server']['app_images']; ?>/today.gif" alt="<?php echo lang('Today'); ?>" border="0">
   </a>
  </td>
  <td width="2%" align="left">
   <a href="<?php echo $phpgw->link('week.php','day='.$phpgw->calendar->today['day'].'&month='.$phpgw->calendar->today['month'].'&year='.$phpgw->calendar->today['year'].'&owner='.$owner); ?>">
    <img src="<?php echo $phpgw_info['server']['app_images']; ?>/week.gif" alt="<?php echo lang('This week'); ?>" border="0">
   </a>
  </td>
  <td width="2%" align="left">
   <a href="<?php echo $phpgw->link('month.php','day='.$phpgw->calendar->today['day'].'&month='.$phpgw->calendar->today['month'].'&year='.$phpgw->calendar->today['year'].'&owner='.$owner); ?>">
    <img src="<?php echo $phpgw_info['server']['app_images']; ?>/month.gif" alt="<?php echo lang('This month'); ?>" border="0">
   </a>
  </td>
  <td width="2%" align="left">
   <a href="<?php echo $phpgw->link('year.php','day='.$phpgw->calendar->today['day'].'&month='.$phpgw->calendar->today['month'].'&year='.$phpgw->calendar->today['year'].'&owner='.$owner); ?>">
    <img src="<?php echo $phpgw_info['server']['app_images']; ?>/year.gif" alt="<?php echo lang('This year'); ?>" border="0">
   </a>
  </td>
  <td width="2%" align="left">
   <a href="<?php echo $phpgw->link('matrixselect.php','day='.$phpgw->calendar->today['day'].'&month='.$phpgw->calendar->today['month'].'&year='.$phpgw->calendar->today['year'].'&owner='.$owner); ?>">
    <img src="<?php echo $phpgw_info['server']['app_images']; ?>/view.gif" alt="<?php echo lang('Daily Matrix View'); ?>" border="0">
   </a>
  </td>
  <form action="<?php echo $phpgw->link('','owner='.$owner); ?>" method="POST" name="filtermethod">
   <td width="45%" align="center" valign="center">
    <b><?php echo lang('Filter'); ?>:</b>
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
<?php if(isset($matrixtype) && $matrixtype) { ?>
    <input type="hidden" name="matrixtype" value="<?php echo $matrixtype; ?>">
<?php }
    if(isset($participants) && $participants) {
      for ($i=0;$i<count($participants);$i++) {
	echo '<input type="hidden" name="participants[]" value="'.$participants[$i].'">';
      }
    } ?>
    <select name="filter" onchange="document.filtermethod.submit()">
     <option value="all"<?php if($filter=='all') echo ' selected'; ?>><?php echo lang('All'); ?></option>
     <option value="private"<?php if((!isset($filter) || !$filter) || $filter=='private') echo ' selected'; ?>><?php echo lang('Private Only'); ?></option>
     <option value="public"<?php if($filter=='public') echo ' selected'; ?>><?php echo lang('Global Public Only'); ?></option>
     <option value="group"<?php if($filter=='group') echo ' selected'; ?>><?php echo lang('Group Public Only'); ?></option>
     <option value="private+public"<?php if($filter=='private+public') echo ' selected'; ?>><?php echo lang('Private and Global Public'); ?></option>
     <option value="private+group"<?php if($filter=='private+group') echo ' selected'; ?>><?php echo lang('Private and Group Public'); ?></option>
     <option value="public+group"<?php if($filter=='public+group') echo ' selected'; ?>><?php echo lang('Global Public and Group Public'); ?></option>
    </select>
    <NOSCRIPT><INPUT TYPE="submit" VALUE="<?php echo lang('Go!'); ?>"></NOSCRIPT></FONT>
   </td>
  </form>
<?php
    if(count($grants) > 0)
    {
?>
  <form action="<?php echo $phpgw->link(); ?>" method="POST" name="setowner">
   <td width="20%" align="center" valign="center">
    <b><?php echo lang('User'); ?>:</b>
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
    <select name="owner" onchange="document.setowner.submit()">
<?php
      while(list($grantor,$temp_rights) = each($grants))
      {
?>
      <option value="<?php echo $grantor; ?>"<?php if($grantor==$owner) echo ' selected'; ?>><?php echo $phpgw->common->grab_owner_name($grantor); ?></option>
<?php
      }
?>
    </select>
    <NOSCRIPT><INPUT TYPE="submit" VALUE="<?php echo lang('Go!'); ?>"></NOSCRIPT></FONT>
   </td>
  </form>
<?php
    }
?>
  <form action="<?php echo $phpgw->link('search.php','owner='.$owner); ?>" method="POST">
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
    <input name="keywords"<?php if($keywords) echo ' value="'.$keywords.'"'; ?>>
    <input type="submit" name="submit" value="<?php echo lang('Search'); ?>">
   </td>
  </form>
 </tr>
</table>
<?php flush(); ?>
