<!-- $Id$ -->
<!-- BEGIN matrix_query -->
<center>
<h2><font color="#000000">{matrix_action}</font></h2>

<form target="viewmatrix" action="{action_url}" method="post" name="matrixform">
<table border="0" width="75%">
{rows}
</table>
<input type="submit" value="{submit_button}">
</form>
{cancel_button}
</center>
<!-- END matrix_query -->
<!-- BEGIN list -->
 <tr>
  <td valign="top" width="35%"><b>{field}:</b></td>
  <td valign="top" width="65%">{data}</td>
 </tr>
<!-- END list -->
