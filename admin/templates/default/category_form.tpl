<!-- $Id$ -->

<!-- BEGIN form -->

<div align="center">
<table border="0" width="80%" cellspacing="2" cellpadding="2">
	<tr>
		<td colspan="1" align="center" bgcolor="#c9c9c9"><b>{title_categories}<b/></td>
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
		<td>{lang_name}:</td>
		<td><input name="values[name]" size="50" value="{cat_name}"></td>
	</tr>
	<tr>
		<td>{lang_descr}:</td>
		<td colspan="2"><textarea name="values[descr]" rows="4" cols="50" wrap="virtual">{cat_description}</textarea></td>
	</tr>
</table>

{buttons}

</div>

<!-- END form -->

<!-- BEGIN add -->

<table width="50%" border="0" cellspacing="2" cellpadding="2">
	<tr valign="bottom">
		<td height="50" align="center">
			<input type="submit" name="submit" value="{lang_save}"></td>
		<td height="50" align="center">
			<input type="reset" name="reset" value="{lang_reset}"></form></td>
		<td height="50" align="center">
			<form method="POST" action="{doneurl}">
			<input type="submit" name="done" value="{lang_done}"></form></td>
	</tr>
</table>

<!-- END add -->

<!-- BEGIN edit -->

<table width="50%" border="0" cellspacing="2" cellpadding="2">
	<tr valign="bottom">
		<td height="50" align="center">
			<input type="hidden" name="values[old_parent]" value="{cat_parent}">
			<input type="submit" name="submit" value="{lang_save}"></form></td>
		<td height="50" align="center">
			<form method="POST" action="{deleteurl}">
			<input type="submit" name="delete" value="{lang_delete}"></form></td>
		<td height="50" align="center">
			<form method="POST" action="{doneurl}">
			<input type="submit" name="done" value="{lang_done}"></form></td>
	</tr>
</table>

<!-- END edit -->
