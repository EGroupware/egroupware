<!-- $Id$ -->
<!-- BEGIN mini_cal -->

 <style type="text/css">
  <!--
    .minicalendar
    {
      color: #000000;
    }

    .minicalendargrey
    {
      color: #999999;
    }

  -->
 </style>

<table border="0" cellspacing="0" cellpadding="0" valign="top" bgcolor="{bgcolor}" width="46%">
 <tr valign="center" bgcolor="{bgcolor}">
  <td align="left" colspan="5"><font size="-2">&nbsp;&nbsp;&nbsp;&nbsp;<b>{month}</b></font></td>
  <td align="right" colspan="2"><a href="{prevmonth}"><img src="{img_root}/left.gif" border="0"></a>&nbsp;&nbsp;<a href="{nextmonth}"><img src="{img_root}/right.gif" border="0"></a></td>
 </tr>
 <tr>
  <td bgcolor="{bgcolor}"  align="center" colspan="7"><img src="{cal_img_root}/mini-calendar-bar.gif" width="90%" height="5"></td>
 </tr>
 <tr valign="top">
  <td bgcolor="{bgcolor}" colspan="7">
   <table border="0" width="100%" cellspacing="7" cellpadding="0" valign="top" cols="7">
    <tr>{daynames}
    </tr>{display_monthweek}
   </table>
  </td>
 </tr>
</table>
<!-- END mini_cal -->

