<!-- BEGIN filename -->
	  		<tr>
	    		<td>{lang_csvfile}</td>
				<td><input name="csvfile" SIZE=30 type="file" value="{csvfile}"></td>
			</tr>
			<tr>
				<td>{lang_fieldsep}</td>
				<td><input name="fieldsep" size=1 value="{fieldsep}"></td>
			</tr>
			<tr>
				<td>{lang_charset}</td>
				<td>
			{select_charset}
				</td>
			</tr>
			<tr><td>&nbsp;</td>
				<td><input name="convert" type="submit" value="{submit}"></td>
			</tr>
			<tr>
				<td colspan="2">{lang_help}</td>
			</tr>
<!-- END filename -->

<!-- BEGIN fheader -->
			<tr>
				<td><b>{lang_csv_fieldname}</b></td>
				<td><b>{lang_info_fieldname}</b></td>
				<td><b>{lang_translation}</b></td>
			</tr>
<!-- END fheader -->

<!-- BEGIN fields -->
			<tr>
				<td>{csv_field}</td>
				<td><select name="cal_fields[{csv_idx}]">{cal_fields}</select></td>
				<td><input name="trans[{csv_idx}]" size=60 value="{trans}"></td>
			</tr>
<!-- END fields -->

<!-- BEGIN ffooter -->
			<tr>
				<td rowspan="2" valign="middle" nowrap><br>{submit}</td>
				<td colspan="2"><br>
					{lang_start} <input name="start" type="text" size="5" value="{start}"> &nbsp; &nbsp;
					{lang_max} <input name="max" type="text" size="3" value="{max}"><td>
			</tr>
			<tr>
				<td colspan="2"><input name="debug" type="checkbox" value="1"{debug}> {lang_debug}</td>
			</tr>
			<tr><td colspan="3">&nbsp;<p>
				{help_on_trans}
			</td></tr>
<!-- END ffooter -->

<!-- BEGIN imported -->
			<tr>
				<td colspan=2 align=center>
					{log}<p>
					{anz_imported}
				</td>
			</tr>
<!-- END imported -->

<!-- BEGIN import -->
<br />
<form {enctype} action="{action_url}" method="post">
{hiddenvars}
	<table align="center">
{rows}
	</table>
</form>
<!-- END import -->
