<!-- $Id$ -->
<!-- BEGIN matrix_query -->
<center>
<form action="{action_url}" method="post" name="matrixform">
<table border="0" width="90%">
 <tr bgcolor="{th_bg}">
  <td colspan="2" align="center"><b>{title}</b></td>
 </tr>
 {rows}
 <tr>
  <td>
   <table cellspacing="5"><tr>
    <td><input type="submit" value="{submit_button}"></form></td>
    <td>{cancel_button}</td>
   </tr></table>
  </td>
 </tr>
</table>
</center>
<!-- END matrix_query -->
<!-- BEGIN list -->
 <tr bgcolor="{tr_color}">
  <td valign="top" width="35%"><b>&nbsp;{field}:</b></td>
  <td valign="top" width="65%">{data}</td>
 </tr>
<!-- END list -->
