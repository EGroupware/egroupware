<!-- BEGIN header -->
<form method="POST" action="{action_url}">
{hidden_vars}
<table border="0" align="center">
   <tr class="th">
	   <td colspan="2"><font color="{th_text}">&nbsp;<b>{title}</b></font></td>
   </tr>
   <tr>
    <td colspan="2">&nbsp;<i><font color="red">{error}</i></font></td>
   </tr>
<!-- END header -->
<!-- BEGIN body -->
   <tr class="th">
    <td colspan="2">&nbsp;<b>{lang_filemanager_configuration}</b></td>
   </tr>
   <tr class="row_on">
    <td>&nbsp;{lang_max_folderlinks}:</td>
    <td><input name="newsettings[max_folderlinks]" value="{value_max_folderlinks}" size="2"></td>
   </tr>
   <tr class="row_off">
	<td colspan="2">&nbsp;{lang_allow_a_maximum_of_the_above_configured_folderlinks_to_be_configured_in_settings}</td>
   </tr>
<!-- END body -->
<!-- BEGIN footer -->
  <tr valign="bottom" style="height: 30px;">
    <td colspan="2" align="center">
      <input type="submit" name="submit" value="{lang_submit}">
      <input type="submit" name="cancel" value="{lang_cancel}">
    </td>
  </tr>
</table>
</form>
<!-- END footer -->
