<!-- $Id$ -->

<!-- BEGIN form -->

<center>
{message}<br>
<table border="0" width="80%" cellspacing="2" cellpadding="2"> 
<form name="form" action="{actionurl}" method="POST">
	<tr class="row_off">
		<td colspan="2">{lang_parent}</td>
		<td><select name="values[parent]"><option value="">{lang_none}</option>{category_list}</select></td>
	</tr>
	<tr class="row_on">
		<td colspan="2">{lang_name}</td>
		<td><input name="values[name]" size="50" value="{cat_name}"></td>
	</tr>
	<tr class="row_off">
		<td colspan="2">{lang_descr}</td>
		<td><textarea name="values[descr]" rows="4" cols="50" wrap="virtual">{cat_description}</textarea></td>
	</tr>
	<tr class="row_on">
		<td colspan="2">{lang_access}</td>
		<td><input type="checkbox" name="values[access]" value="True" {access}></td>
	</tr>
	{rows}

{buttons}

</table>
</center>

<!-- END form -->

<!-- BEGIN add -->

	<tr valign="bottom" height="50">
		<td colspan="2"><input type="submit" name="save" value="{lang_save}"></form></td>
		<td align="right">
			<form method="POST" action="{cancel_url}"><input type="submit" name="cancel" value="{lang_cancel}"></form>
		</td>
	</tr>

<!-- END add -->

<!-- BEGIN edit -->

	<tr valign="bottom" height="50">
		<td>
			<input type="hidden" name="values[old_parent]" value="{old_parent}">
			<input type="submit" name="save" value="{lang_save}"></form></td>
		<td>
			<form method="POST" action="{cancel_url}">
			<input type="submit" name="cancel" value="{lang_cancel}"></form></td>
		<td align="right">{delete}</td>
	</tr>


<!-- END edit -->

<!-- BEGIN data_row -->

	<tr class="row_off">
		<td colspan="2">{lang_data}</td>
		<td>{td_data}</td>
	</tr>

<!-- END data_row -->
