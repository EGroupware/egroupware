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
   <!-- currently not used, might be reactived later
   <tr class="th">
    <td colspan="2">&nbsp;<b>{lang_Mail_backend}</b></td>
   </tr>
   <tr class="row_on">
    <td>{lang_choose_from_mailsettings_used_for_notification}</td>
    <td>
     <select name="newsettings[dontUseUserDefinedProfiles]">
      <option value=""{selected_dontUseUserDefinedProfiles_False}>{lang_Check_both_(first_try_(active)_User_defined_account,_if_none_use_emailadmin_profile)}</option>
      <option value="True"{selected_dontUseUserDefinedProfiles_True}>{lang_Emailadmin_Profile_only_(Do_not_use_User_defined_(active)_Mail_Profiles_for_Notification)}</option>
     </select>
    </td>
   </tr -->
   <tr class="th">
    <td colspan="2">&nbsp;<b>{lang_EGroupware-Popup_backend}</b></td>
   </tr>
   <tr class="row_on">
    <td>{lang_Poll_interval}</td>
    <td>
     <select name="newsettings[popup_poll_interval]">
      <option value="5"{selected_popup_poll_interval_60}>5secs</option>
      <option value="60"{selected_popup_poll_interval_60}>1 {lang_minute}</option>
      <option value="120"{selected_popup_poll_interval_120}>2 {lang_minutes}</option>
      <option value="300"{selected_popup_poll_interval_300}>5 {lang_minutes}</option>
     </select>
    </td>
   </tr>
   <tr class="th">
    <td colspan="2">&nbsp;<b>{lang_Windows-Popup_backend}</b></td>
   </tr>
   <tr class="row_on">
    <td>{lang_Enable_Windows-Popup_backend}</td>
    <td>
     <select name="newsettings[winpopup_enable]">
      <option value=""{selected_winpopup_enable_False}>{lang_No}</option>
      <option value="True"{selected_winpopup_enable_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr>
   <tr class="th">
     <td colspan="2">{lang_Signature}</td>
   </tr>
   <tr class = "row_off">
    <td>{lang_Signature_added_to_every_change_notification}<br />{lang_You_can_also_use} <a href="index.php?menuaction=addressbook.addressbook_merge.show_replacements" target="_blank">{lang_addressbook}</a> {lang_placeholders_with_user/_prefix}</td>
    <td><textarea rows="7" cols="50" name="newsettings[signature]">{value_signature}</textarea></td>
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
