<!-- $Id$ -->
<!-- BEGIN matrix_query -->
<center>
<form target="viewmatrix" action="{action_url}" method="post" name="matrixform">
<table border="0" width="90%">
 <tr class="th">
  <td colspan="2" align="center"><b>{title}</b></td>
 </tr>
 {rows}
 <tr>
  <td>
   <table cellspacing="5"><tr valign="top">
    <td><input type="submit" value="{submit_button}"></form></td>
    <td>{cancel_button}</td>
   </tr></table>
  </td>
 </tr>
</table>
</center>
<!-- END matrix_query -->
<!-- BEGIN list -->
 <tr class="{tr_color}">
  <td valign="top" width="35%"><b>&nbsp;{field}:</b></td>
  <td valign="top" width="65%">{data}</td>
 </tr>
<!-- END list -->
