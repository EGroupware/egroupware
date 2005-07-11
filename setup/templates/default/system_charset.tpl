<!-- begin system_charset.tpl -->
<p align="center"><font color="red">{error_msg}</font></p>

<form method="post" action="system_charset.php">
<table border="0" align="center" width="80%">
	<tr bgcolor="#486591">
		<td colspan="2">
			&nbsp;<font color="#fefefe">{stage_title}</font>
		</td>
	</tr>
	<tr bgcolor="#e6e6e6">
		<td colspan="2">
			{stage_desc}
		</td>
	</tr>
	<tr bgcolor="#e6e6e6">
		<td>
			{lang_current}
		</td>
		<td>
			{current_charset}
		</td>
	</tr>
	<tr bgcolor="#e6e6e6">
		<td>
			{lang_convert_to}
		</td>
		<td>
			{new_charset}
		</td>
	</tr>
	<tr bgcolor="#e6e6e6">
		<td colspan="2">
			{lang_warning}
		</td>
	</tr>
	<tr>
		<td colspan="2" align="center">
			<input type="submit" name="convert" value="{lang_convert}" /> &nbsp;
			<input type="submit" name="cancel" value="{lang_cancel}" />
		</td>
	</tr>
</table>
</form>

<!-- end system_charset.tpl -->
