<!-- begin lang_main.tpl -->
<p>&nbsp;</p>

<table border="0" align="center" width="{tbl_width}">
<tr bgcolor="#486591">
	<td colspan="{td_colspan}">
		&nbsp;<font color="#fefefe">{stage_title}</font>
	</td>
</tr>
<tr bgcolor="#e6e6e6">
	<td colspan="{td_colspan}">
		{stage_desc}
	</td>
</tr>
<tr bgcolor="#e6e6e6">
	<td {td_align}>
		{select_box_desc}
		<form method="POST" action="lang.php">
		{hidden_var1}
		<select name="lang_selected[]" multiple size="10">
		{select_box_langs}
		</select>
	</td>
	<!-- BEGIN B_choose_method -->
	<td valign="top">
		{meth_desc}
		<br><br>
		<input type="radio" name="upgrademethod" value="dumpold" checked>
		&nbsp;{blurb_dumpold}
		<br>
		<input type="radio" name="upgrademethod" value="addonlynew">
		&nbsp;{blurb_addonlynew}
		<br>
		<input type="radio" name="upgrademethod" value="addmissing">
		&nbsp;{blurb_addmissing}
	</td>
	<!-- END B_choose_method -->
</tr>
</table>

<div align="center">
	<input type="submit" name="submit" value="{lang_install}">
	<input type="submit" name="cancel" value="{lang_cancel}">
</div>
<!-- end lang_main.tpl -->
