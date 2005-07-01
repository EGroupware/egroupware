<!-- BEGIN list -->
<table width="75%" border="0" cellspacing="0" cellpadding="0">
 {rows}
</table>
<form method="POST" action="{cancel_action}">
	<input type="submit" name="cancel" value="{lang_cancel}">
</form>
<!-- END list -->

<!-- BEGIN app_row -->
 <tr bgcolor="{icon_backcolor}">
  <td class="narrow_column" valign="middle"><img src="{app_icon}" alt="[ {app_name} ]"> <a name="{a_name}"></a></td>
  <td width="95%" valign="middle"><strong>&nbsp;&nbsp;{app_name}</strong></td>
 </tr>
<!-- END app_row -->

<!-- BEGIN app_row_noicon -->
 <tr bgcolor="{icon_backcolor}">
  <td colspan="2" width="95%" valign="middle"><strong>&nbsp;&nbsp;{app_name}</strong> <a name="{a_name}"></a></td>
 </tr>
<!-- END app_row_noicon -->

<!-- BEGIN link_row -->
 <tr>
  <td colspan="2">&nbsp;&#8226;&nbsp;<a href="{link_location}">{lang_location}</a></td>
 </tr>
<!-- END link_row -->

<!-- BEGIN spacer_row -->
 <tr>
  <td colspan="2">&nbsp;</td>
 </tr>
<!-- END spacer_row -->
