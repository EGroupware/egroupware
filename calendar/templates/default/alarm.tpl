<!-- $Id$ -->
<!-- BEGIN alarm_management -->
<form action="{action_url}" method="post" name="alarmform">
<center>
 <font color="{bg_text}">
  <table border="0" width="75%">
   {row}
  </table>
 </font>
</center>
</form>
<!-- END alarm_management -->
<!-- BEGIN alarm_headers -->
  <tr>
   <th valign="top" align="left" width="4%">&nbsp;</b></th>
   <th valign="top" align="left" width="30%"><b>{time_lang}</b></th>
   <th valign="top" align="left" width="54%">{text_lang}</th>
   <th valign="top" align="left" width="6%"><img src="{enabled_pict}" width="13" height="13" alt="enabled"></th>
   <th valign="top" align="left" width="6%"><img src="{disabled_pict}" width="13" height="13" alt="disabled"></th>
  </tr>
<!-- END alarm_headers -->
<!-- BEGIN list -->
  <tr>
   <td valign="top" align="left" width="4%">{edit_box}</td>
   <td valign="top" align="left" width="30%"><b>{field}:</b></td>
   <td valign="top" align="left" width="54%">{data}</td>
   <td valign="top" align="left" width="6%">{alarm_enabled}</td>
   <td valign="top" align="left" width="6%">{alarm_disabled}</td>
  </tr>
<!-- END list -->
<!-- BEGIN hr -->
 <tr>
  <td colspan="5">
   {hr_text}
  </td>
 </tr>
<!-- END hr -->
