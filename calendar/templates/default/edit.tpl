<!-- $Id$ -->
<!-- BEGIN edit_entry -->
<body bgcolor="#C0C0C0">
<center>
<font color="#000000" face="{font}">
<h2>{calendar_action}</h2>

<form action="{action_url}" method="post" name="addform">
{common_hidden}
<table border="0" width="75%">
 <tr>
  <td colspan="2">
   <center><font size="+1"><b>{errormsg}</b></font></center>
   <hr>
  </td>
 </tr>
{row}
</table>
<input type="submit" value="{submit_button}">
</form>
</br>
</br>
{delete_button}
</font>
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
