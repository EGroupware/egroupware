<table border="0" cellspacing="0" cellpadding="0" valign="top" bgcolor="{bgcolor}" cols="7">
 <tr valign="center" bgcolor="{bgcolor1}" bordercolor="{bgcolor1}">
  <td align="left" colspan="4"><font size="-2">{month}</font></td>
  <td align="right" colspan="3"><font size="-2"><a href="{prevmonth}">&#171</a>&nbsp;&nbsp;<a href="{nextmonth}">&#187</a></font></td>
 </tr>
 <tr valign="top">
  <td bgcolor="{bgcolor}" colspan="7">
   <table border="0" width="100%" cellspacing="1" cellpadding="2" valign="top" cols="7">
    <tr>
     {daynames}
    </tr>
    {display_monthweek}
   </table>
  </td>
 </tr>
</table>

<!-- BEGIN day -->
     <td bgcolor="{bgcolor2}" align="center"><font size="-2"><b>{dayname}</b></font></td>
<!-- END day -->

<!-- BEGIN month_week -->
    <tr>
     {monthweek_day}
    </tr>
<!-- END month_week -->
