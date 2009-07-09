
<br><center>

  <form {enctype} action="{action_url}" method="post">
   <table>

<!-- BEGIN filename -->
   <tr>
    <td>{lang_csvfile}</td>
    <td><input NAME="csvfile" SIZE=30 TYPE="file" VALUE="{csvfile}" /></td>
   </tr>
   <tr>
    <td>{lang_fieldsep}</td>
    <td><input name="fieldsep" size=1 value="{fieldsep}" /></td>
   </tr>
   <tr>
    <td>{lang_charset}</td>
    <td>
{select_charset}
    </td>
   </tr>
   <tr><td>&nbsp;</td>
    <td><input NAME="convert" TYPE="submit" VALUE="{submit}" /></TD>
   </tr>
<!-- END filename -->

<!-- BEGIN fheader -->
   <tr>
    <td><b>{lang_csv_fieldname}</b></td>
    <td><b>{lang_addr_fieldname}</b></td>
    <td><b>{lang_translation}</b></td>
   </tr>
<!-- END fheader -->

<!-- BEGIN fields -->
   <tr>
    <td>{csv_field}</td>
    <td><select name="addr_fields[{csv_idx}]">{addr_fields}</select></td>
    <td><input name="trans[{csv_idx}]" size=60 value="{trans}" /></td>
   </tr>
<!-- END fields -->

<!-- BEGIN ffooter -->
   <tr>
    <td>{lang_unique_id}</td>
    <td colspan="2">{unique_id}</td>
   </tr>
   <tr>
    <td rowspan="2" valign="middle"><br>{submit}</TD>
    <td colspan="2"><br>
     {lang_start} <input name="start" type="text" size="5" value="{start}" /> &nbsp; &nbsp;
     {lang_max} <input name="max" type="text" size="3" value="{max}" /><td>
   </tr>
   <tr>
    <td colspan="3"><input name="debug" type="checkbox" value="1" {debug}> {lang_debug}</td>
   </tr>
   <tr><td colspan="3">&nbsp;<p>
    {help_on_trans}
   </td></tr>
<!-- END ffooter -->

<!-- BEGIN imported -->
   <tr>
    <td colspan="2">
     {log}<p>
     {anz_imported}
    </td>
   </tr>
<!-- END imported -->

</table>
{hiddenvars}
</form>

</center>
