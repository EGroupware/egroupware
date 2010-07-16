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
    <td colspan="2">&nbsp;<b>{lang_eGroupWare-Popup_backend}</b></td>
   </tr>
   <tr class="row_on">
    <td>{lang_Enable_eGroupWare-Popup_backend}</td>
    <td>
     <select name="newsettings[popup_enable]">
      <option value=""{selected_popup_enable_False}>{lang_No}</option>
      <option value="True"{selected_popup_enable_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr>
   <tr class="row_off">
    <td>{lang_Poll_interval}</td>
    <td>
     <select name="newsettings[popup_poll_interval]">
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
    <td colspan="2">&nbsp;<b>{lang_SMS_backend}</b></td>
   </tr>
   <tr class="row_on">
    <td>{lang_Enable_SMS_backend}</td>
    <td>
     <select name="newsettings[sms_enable]">
      <option value=""{selected_sms_enable_False}>{lang_No}</option>
      <option value="True"{selected_sms_enable_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr> 
   <tr class="row_off">
    <td>{lang_Maximum_SMS_messages_per_notification}</td>
    <td>
     <select name="newsettings[sms_maxmessages]">
      <option value="1"{selected_sms_maxmessages_1}>1</option>
      <option value="2"{selected_sms_maxmessages_2}>2</option>
      <option value="3"{selected_sms_maxmessages_3}>3</option>
     </select>
    </td>
   </tr>
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
