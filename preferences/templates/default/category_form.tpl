<!-- $Id$ -->

<!-- BEGIN form -->
<br>
<center>
{message}<br>
<table border="0" width="80%" cellspacing="2" cellpadding="2"> 
<form name="form" action="{actionurl}" method="POST">
	<tr bgcolor="{th_bg}">
		<td colspan="2">{lang_parent}</td>
		<td><select name="new_parent"><option value="">{lang_none}</option>{category_list}</select></td>
	</tr>
	<tr bgcolor="{row_on}">
		<td colspan="2">{lang_name}</font></td>
		<td><input name="cat_name" size="50" value="{cat_name}"></td>
	</tr>
	<tr bgcolor="{row_off}">
		<td colspan="2">{lang_descr}</td>
		<td colspan="2"><textarea name="cat_description" rows="4" cols="50" wrap="virtual">{cat_description}</textarea></td>
	</tr>
	<tr bgcolor="{row_on}">
		<td colspan="2">{lang_access}</td>
		<td colspan="2">{access}</td>
	</tr>
<!-- BEGINN data_row -->
	<tr bgcolor="{tr_color}">
		<td colspan="2">{lang_data}</td>
		<td>{td_data}</td>
	</tr>
<!-- END data_row -->

<!-- BEGIN add -->

	<tr valign="bottom" height="50">
		<td colspan="2"><input type="submit" name="save" value="{lang_save}"></form></td>
		<td align="right"><form method="POST" action="{cancel_url}">
			<input type="submit" name="cancel" value="{lang_cancel}"></form></td>
	</tr>
</table>
</center>

<!-- END add -->

<!-- BEGIN edit -->

	<tr valign="bottom" height="50">
		<td>
			{hidden_vars}
			<input type="submit" name="save" value="{lang_save}"></form></td>
		<td>
			<form method="POST" action="{cancel_url}">
			<input type="submit" name="cancel" value="{lang_cancel}"></form></td>
		<td align="right">{delete}</td>
	</tr>
</table>
</center>

<!-- END edit -->

<!-- END form -->
