<!-- $Id$ -->

<!-- BEGIN form -->

<center>
<table border="0" width="80%" cellspacing="2" cellpadding="2">
	<tr>
		<td align="center" bgcolor="#c9c9c9"><b>{title_categories}:&nbsp;{user_name}<b/></td>
	</tr>
</table>
{message}
<table border="0" width="80%" cellspacing="2" cellpadding="2"> 
<form name="form" action="{actionurl}" method="POST">
	<tr>
		<td>{lang_parent}</td>
		<td><select name="values[parent]"><option value="">{lang_none}</option>{category_list}</select></td>
	</tr>
	<tr>
		<td>{lang_name}</td>
		<td><input name="values[name]" size="50" value="{cat_name}"></td>
	</tr>
	<tr>
		<td>{lang_descr}</td>
		<td><textarea name="values[descr]" rows="4" cols="50" wrap="virtual">{cat_description}</textarea></td>
	</tr>

	{rows}

	<tr>
		<td>{lang_access}</td>
		<td><input type="checkbox" name="values[access]" value="True" {access}></td>
	</tr>
</table>

{buttons}

</center>

<!-- END form -->

<!-- BEGIN add -->

<table width="80%" border="0" cellspacing="2" cellpadding="2">
	<tr valign="bottom">
		<td height="50">
			<input type="submit" name="submit" value="{lang_save}"></td>
		<td height="50" align="center">
			<input type="reset" name="reset" value="{lang_reset}"></form></td>
		<td height="50" align="right">
			<form method="POST" action="{doneurl}">
			<input type="submit" name="done" value="{lang_done}"></form></td>
	</tr>
</table>

<!-- END add -->

<!-- BEGIN edit -->

<table width="80%" border="0" cellspacing="2" cellpadding="2">
	<tr valign="bottom">
		<td height="50">
			<input type="hidden" name="values[old_parent]" value="{old_parent}">
			<input type="submit" name="submit" value="{lang_save}"></form></td>
		<td height="50" align="center">
			{delete}</td>
		<td height="50" align="right">
			<form method="POST" action="{doneurl}">
			<input type="submit" name="done" value="{lang_done}"></form></td>
	</tr>
</table>

<!-- END edit -->

<!-- BEGIN data_row -->

	<tr>
		<td>{lang_data}</td>
		<td>{td_data}</td>
	</tr>

<!-- END data_row -->
