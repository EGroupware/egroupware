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
   <tr bgcolor="{row_on}">
    <td>{lang_Enable_eGroupWare-Popup_backend}</td>
    <td>
     <select name="newsettings[popup_enable]">
      <option value=""{selected_popup_enable_False}>{lang_No}</option>
      <option value="True"{selected_popup_enable_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr>
   <tr class="th">
    <td colspan="2">&nbsp;<b>{lang_Windows-Popup_backend}</b></td>
   </tr>
   <tr bgcolor="{row_on}">
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
   <tr bgcolor="{row_on}">
    <td>{lang_Enable_SMS_backend}</td>
    <td>
     <select name="newsettings[sms_enable]">
      <option value=""{selected_sms_enable_False}>{lang_No}</option>
      <option value="True"{selected_sms_enable_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr> 
   <tr bgcolor="{row_off}">
    <td>{lang_Maximum_SMS_messages_per_notification}</td>
    <td>
     <select name="newsettings[sms_maxmessages]">
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
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
