<!-- $Id$ -->
<!-- BEGIN edit_entry -->
<center>
<font color="#000000" face="{font}">

<form action="{action_url}" method="post" name="app_form">
{common_hidden}
<table border="0" width="98%">
 <tr>
  <td colspan="2">
   <center><font size="+1"><b>{errormsg}</b></font></center>
  </td>
 </tr>
{row}
 <tr>
  <td>
  <table><tr valign="top">
  <td>
  <div style="padding-right: 2px">
  	<input style="font-size:10px" type="submit" value="{submit_button}"></div></form>
  </td>
  <td>{cancel_button}</td>
  </tr></table>
  </td>
  <td valign="top" align="right">{delete_button}</td>
 </tr>
</table>
</font>
</center>
<!-- END edit_entry -->
<!-- BEGIN list -->
 <tr bgcolor="{tr_color}">
  <td valign="top" width="35%" align="right">&nbsp;<b>{field}&nbsp;:&nbsp;</b></td>
  <td valign="top" width="65%" align="left">{data}</td>
 </tr>
<!-- END list -->
<!-- BEGIN hr -->
 <tr bgcolor="{tr_color}">
  <td colspan="2">
   {hr_text}
  </td>
 </tr>
<!-- END hr -->
