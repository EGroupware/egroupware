<!-- $Id$ -->
<!-- BEGIN alarm_management -->
<form action="{action_url}" method="post" name="alarmform">
{hidden_vars}
  <table border="0" width="90%" align="center">
   {rows}
   <tr><td colspan="6">
	<br>&nbsp;{input_days}&nbsp;{input_hours}&nbsp;{input_minutes}&nbsp;{input_owner}&nbsp;{input_add}<br>&nbsp;
   </td></tr>
  </table>
</form>
<!-- END alarm_management -->
<!-- BEGIN alarm_headers -->
  <tr bgcolor="{tr_color}">
   <th align="left" width="25%">{lang_time}</th>
   <th align="left" width="30%">{lang_text}</th>
   <th align="left" width="25%">{lang_owner}</th>
   <th width="10%">{lang_enabled}</th>
   <th width="10%">{lang_select}</th>
  </tr>
<!-- END alarm_headers -->
<!-- BEGIN list -->
  <tr bgcolor="{tr_color}">
   <td><b>{field}:</b></td>
   <td>{data}</td>
   <td>{owner}</td>
   <td align="center">{enabled}</td>
   <td align="center">{select}</td>
  </tr>
<!-- END list -->
<!-- BEGIN hr -->
 <tr bgcolor="{th_bg}">
  <td colspan="5" align="center"><b>{hr_text}</b></td>
 </tr>
<!-- END hr -->
<!-- BEGIN buttons -->
 <tr>
  <td colspan="6" align="right">
   {enable_button}&nbsp;{disable_button}&nbsp;{delete_button}
  </td>
 </tr>
<!-- END buttons -->
