<!-- $Id$ -->
<!-- BEGIN alarm_management -->
<form action="{action_url}" method="post" name="alarmform">
<center>
  <table border="0" width="90%">
   {row}
  </table>
</center>
</form>
<!-- END alarm_management -->
<!-- BEGIN alarm_headers -->
  <tr class="th">
   <th valign="top" align="left" width="4%">&nbsp;</b></th>
   <th valign="top" align="left" width="30%">&nbsp;<b>{time_lang}</b></th>
   <th valign="top" align="left" width="54%">&nbsp;{text_lang}</th>
   <th valign="top" align="center" width="6%"><img src="{enabled_pict}" width="13" height="13" alt="enabled"></th>
   <th valign="top" align="center" width="6%"><img src="{disabled_pict}" width="13" height="13" alt="disabled"></th>
  </tr>
<!-- END alarm_headers -->
<!-- BEGIN list -->
  <tr class="{tr_color}">
   <td valign="top" align="left" width="4%">{edit_box}</td>
   <td valign="top" align="left" width="30%"><b>{field}:</b></td>
   <td valign="top" align="left" width="54%">{data}</td>
   <td valign="top" align="left" width="6%">{alarm_enabled}</td>
   <td valign="top" align="left" width="6%">{alarm_disabled}</td>
  </tr>
<!-- END list -->
<!-- BEGIN hr -->
 <tr class="th">
  <td colspan="5" align="center"><b>{hr_text}</b></td>
 </tr>
<!-- END hr -->
