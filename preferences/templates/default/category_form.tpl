<!-- $Id$ -->

<!-- BEGIN form -->
<br>
<center>
{message}<br>
<form name="edit_cat" action="{actionurl}" method="POST">
<table border="0" width="98%" cellspacing="2" cellpadding="2"> 

	<tr class="th">
		<td colspan="2" align="right">{lang_parent}&nbsp;:&nbsp;</td>
		<td align="left"><select name="new_parent"><option value="">{lang_none}</option>{category_list}</select></td>
	</tr>
	<tr class="row_on">
		<td colspan="2" align="right">{lang_name}&nbsp;:&nbsp;</font></td>
		<td align="left"><input name="cat_name" size="50" value="{cat_name}"></td>
	</tr>
	<tr class="row_off">
		<td colspan="2" align="right">{lang_descr}&nbsp;:&nbsp;</td>
		<td colspan="2" align="left"><textarea name="cat_description" rows="4" cols="50" wrap="virtual">{cat_description}</textarea></td>
	</tr>
	<tr class="row_on">
		<td colspan="2" align="right">{lang_access}&nbsp;:&nbsp;</td>
		<td colspan="2" align="left">{access}</td>
	</tr>
	<tr class="row_off">
		<td colspan="2" align="right">{lang_color}&nbsp;:&nbsp;</td>
		<td colspan="2" align="left">{color}</td>
	</tr>
	<tr class="row_on">
		<td colspan="2" align="right">{lang_icon}&nbsp;:&nbsp;</td>
		<td colspan="2" align="left">{select_icon} {icon}</td>
	</tr>
<!-- BEGIN data_row -->
	<tr class="{class}">
		<td colspan="2" align="right">{lang_data}&nbsp;:&nbsp;</td>
		<td align="left">{td_data}</td>
	</tr>
<!-- END data_row -->

<!-- BEGIN add -->

	<tr valign="bottom" height="50">
		<td><input type="submit" name="save" value="{lang_save}"></td>
		<td></td>

	</tr>
	
</table>
</form>
</center>

<!-- END add -->

<!-- BEGIN edit -->

	<tr valign="bottom" height="50">
		<td>
			{hidden_vars}
			<input type="submit" name="save" value="{lang_save}"></td>
		<td>
			<input type="button" name="cancel" onClick="javascript:history.back()" value="{lang_cancel}"></td>
		<td>&nbsp;</td>
	</tr>
</table>
</form>
</center>

<!-- END edit -->

<!-- END form -->
