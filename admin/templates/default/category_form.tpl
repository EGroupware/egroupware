<!-- $Id$ -->

<!-- BEGIN form -->

<div align="center">
{message}<br>
<form name="form" action="{action_url}" method="POST">
<table border="0" width="80%" cellspacing="2" cellpadding="2"> 
	<tr bgcolor="{row_on}">
		<td>{lang_parent}</td>
		<td><select name="new_parent"><option value="">{lang_none}</option>{category_list}</select></td>
	</tr>
	<tr bgcolor="{row_off}">
		<td>{lang_name}:</td>
		<td><input name="cat_name" size="50" value="{cat_name}"></td>
	</tr>
	<tr bgcolor="{row_on}">
		<td valign="top">{lang_descr}:</td>
		<td colspan="2"><textarea name="cat_description" rows="4" cols="50" wrap="virtual">{cat_description}</textarea></td>
	</tr>
	<tr height="50" valign="bottom">
		<td><input type="submit" name="save" value="{lang_save}"></td>
		<td align="right"><input type="submit" name="cancel" value="{lang_cancel}"></td>
	</tr>
</table>
</form>

<!-- END form -->
