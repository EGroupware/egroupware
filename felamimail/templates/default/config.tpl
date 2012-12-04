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
    <td colspan="2">&nbsp;<b>{lang_felamimail}</b> - {lang_acl}</td>
   </tr>
   <tr class="row_on">
    <td>&nbsp;{lang_restrict_acl_management}:</td>
    <td>
	 <select name="newsettings[restrict_acl_management]">
      <option value=""{selected_restrict_acl_management_False}>{lang_No}</option>
      <option value="True"{selected_restrict_acl_management_True}>{lang_Yes}</option>
     </select>
	</td>
   </tr>
   <tr class="row_off">
	<td colspan="2">&nbsp;{lang_effective_only_if_server_supports_ACL_at_all}</td>
   </tr>
   <tr class="th">
    <td colspan="2">&nbsp;<b>{lang_felamimail}</b> - {lang_sieve}</td>
   </tr>
   <tr class="row_on">
    <td>&nbsp;{lang_vacation_notice}:</td>
    <td><textarea name="newsettings[default_vacation_text]" cols="50" rows="8">{value_default_vacation_text}</textarea></td>
   </tr>
   <tr class="row_off">
	<td colspan="2">&nbsp;{lang_provide_a_default_vacation_text,_(used_on_new_vacation_messages_when_there_was_no_message_set_up_previously)}</td>
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
