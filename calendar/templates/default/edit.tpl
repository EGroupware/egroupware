<!-- $Id$ -->
<!-- BEGIN edit_entry -->
<script language="JavaScript">
	self.name="first_Window";
	function accounts_popup()
	{
		Window1=window.open('{accounts_link}',"Search","width=800,height=600,toolbar=no,scrollbars=yes,resizable=yes");
	}
</script>
<center>
<font color="#000000" face="{font}">

<form action="{action_url}" method="post" name="app_form">
{common_hidden}
<table border="0" width="90%">
 <tr>
  <td colspan="2">
   <center><font size="+1"><b>{errormsg}</b></font></center>
  </td>
 </tr>
{row}
 <tr>
  <td>
   <table><tr>
    <td><input type="submit" value="{submit_button}">&nbsp;</form></td>
    <td>{cancel_button}</td>
   </tr></table>
  </td>
  <td align="right">{delete_button}</td>
 </tr>
</table>
</font>
</center>
<!-- END edit_entry -->
<!-- BEGIN list -->
 <tr bgcolor="{tr_color}">
  <td valign="top" width="35%">&nbsp;<b>{field}:</b></td>
  <td valign="top" width="65%">{data}</td>
 </tr>
<!-- END list -->
<!-- BEGIN hr -->
 <tr bgcolor="{tr_color}">
  <td colspan="2">
   {hr_text}
  </td>
 </tr>
<!-- END hr -->
