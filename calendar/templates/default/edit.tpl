<!-- $Id$ -->
<!-- BEGIN edit_entry -->
<body bgcolor="#C0C0C0">
<center>
<h2><font color="#000000">{calendar_action}</font></h2>

<form action="{action_url}" method="post" name="addform">
{common_hidden}
<table border="0" width="75%">
 <tr>
  <td colspan="2">
   <center><h1>{errormsg}</h1></center>
   <hr>
  </td>
 </tr>
{row}
</table>
<input type="submit" value="{submit_button}">
</form>

{delete_button}
</center>
<!-- END edit_entry -->
<!-- BEGIN list -->
 <tr>
  <td valign="top" width="35%"><b>{field}:</b></td>
  <td valign="top" width="65%">{data}</td>
 </tr>
<!-- END list -->
<!-- BEGIN hr -->
 <tr>
  <td colspan="2">
   {hr_text}
  </td>
 </tr>
<!-- END hr -->
