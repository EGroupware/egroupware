<!-- $Id$ -->

<!-- BEGIN form -->

<center>
<table border="0" width="80%" cellspacing="2" cellpadding="2">
	<tr>
		<td colspan="1" align="center" bgcolor="#c9c9c9"><b>{title_categories}:&nbsp;{user_name}<b/></td>
	</tr>
</table>
{message}
<table border="0" width="80%" cellspacing="2" cellpadding="2"> 
<form name="form" action="{actionurl}" method="POST">
	<tr>
		<td>{lang_parent}</td>
		<td><select name="new_parent"><option value="">{lang_none}</option>{category_list}</select></td>
	</tr>
	<tr>
		<td>{lang_name}</font></td>
		<td><input name="cat_name" size="50" value="{cat_name}"></td>
	</tr>
	<tr>
		<td>{lang_descr}</td>
		<td colspan="2"><textarea name="cat_description" rows="4" cols="50" wrap="virtual">{cat_description}</textarea></td>
	</tr>
	<tr>
		<td>{lang_data}</td>
		<td>{td_data}</td>
	</tr>
	<tr>
		<td>{lang_access}</td>
		<td colspan="2">{access}</td>
	</tr>
</table>

<!-- BEGIN add -->

<table width="50%" border="0" cellspacing="2" cellpadding="2">
	<tr valign="bottom">
		<td height="50" align="right">
			<input type="submit" name="submit" value="{lang_add}"></td>
		<td height="50" align="center">
			<input type="reset" name="reset" value="{lang_reset}"></form></td>
		<td height="50">
			<form method="POST" action="{doneurl}">
			<input type="submit" name="done" value="{lang_done}"></form></td>
	</tr>
</table>
</center>

<!-- END add -->

<!-- BEGIN edit -->

<table width="50%" border="0" cellspacing="2" cellpadding="2">
	<tr valign="bottom">
		<td height="50" align="right">
			<input type="submit" name="submit" value="{lang_edit}"></form></td>
		<td height="50" align="center">
			{delete}</td>
		<td height="50">
			<form method="POST" action="{doneurl}">
			<input type="submit" name="done" value="{lang_done}"></form></td>
	</tr>
</table>
</center>

<!-- END edit -->

<!-- END form -->
