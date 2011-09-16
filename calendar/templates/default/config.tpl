<!-- BEGIN header -->
<form method="POST" action="{action_url}">
<table border="0" align="center">
   <tr class="th">
    <td colspan="2"><font color="{th_text}">&nbsp;<b>{title}</b></font></td>
   </tr>
<!-- END header -->

<!-- BEGIN body -->
   <tr class="row_on">
    <td colspan="2">&nbsp;</td>
   </tr>

   <tr class="row_off">
    <td colspan="2">&nbsp;<b>{lang_Calendar} {lang_site_configuration}</b></td>
   </tr>
   <tr class="row_on">
    <td>&nbsp;{lang_Do_you_wish_to_autoload_calendar_holidays_files_dynamically?}</td>
    <td>
     <select name="newsettings[auto_load_holidays]">
      <option value=""{selected_auto_load_holidays_False}>{lang_No}</option>
      <option value="True"{selected_auto_load_holidays_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr>
   <tr class="row_off">
    <td>&nbsp;{lang_Location_to_autoload_from}:</td>
    <td>
     <select name="newsettings[holidays_url_path]">
      <option value="localhost"{selected_holidays_url_path_localhost}>localhost</option>
      <option value="http://www.egroupware.org/cal"{selected_holidays_url_path_http://www.egroupware.org/cal}>www.egroupware.org</option>
     </select>
    </td>
   </tr>
   <!-- lock setting -->
   <tr class="row_on">
    <td>&nbsp;{lang_setting_lock_time_calender}:</td>
    <td><input name="newsettings[Lock_Time_Calender]" value="{value_Lock_Time_Calender}" size="40"></td>
   </tr>
   <tr class="row_off">
    <td>&nbsp;{lang_Deny_Ressources_reservation_for_private_events}:</td>
    <td>
     <select name="newsettings[no_ressources_private]">
      <option value="">{lang_No}</option>
      <option value="yes"{selected_no_ressources_private_yes}>{lang_Yes}</option>
     </select>
    </td>
   </tr>
   <tr class="row_on">
    <td>&nbsp;{lang_Require_an_ACL_grant_to_invite_other_users_and_groups}:</td>
    <td>
     <select name="newsettings[require_acl_invite]">
      <option value="">{lang_No}: {lang_Every_user_can_invite_other_users_and_groups}</option>
      <option value="groups"{selected_require_acl_invite_groups}>{lang_Groups:_other_users_can_allways_be_invited,_only_groups_require_an_invite_grant}</option>
      <option value="all"{selected_require_acl_invite_all}>{lang_Users_+_groups:_inviting_both_allways_requires_an_invite_grant}</option>
     </select>
    </td>
   </tr>
   <tr class="row_off">
    <td>&nbsp;{lang_While_selecting_up_to_X_users_day-_and_weekview_is_not_consolidated_(5_is_used_when_not_set)}:</td>
    <td><input name="newsettings[calview_no_consolidate]" value="{value_calview_no_consolidate}" size="10"></td>
   </tr>
   <tr class="row_on">
    <td>&nbsp;{lang_Allow_users_to_prevent_change_notifications_('Do_not_notify')}:</td>
    <td>
     <select name="newsettings[calendar_allow_no_notification]">
      <option value=""{selected_calendar_allow_no_notification_False}>{lang_No}</option>
      <option value="True"{selected_calendar_allow_no_notification_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr>
   <tr class="row_off">
    <td>&nbsp;<b>{lang_Security}</b>: {lang_How_many_appointments_should_non-admins_be_able_to_export}
    {lang_(empty_=_use_global_limit,_no_=_no_export_at_all)}:</td>
    <td><input name="newsettings[calendar_export_limit]" value="{value_calendar_export_limit}" size="5"></td>
   </tr>
   <tr class="th">
    <td colspan="2">&nbsp;<b>{lang_History_logging}</b></td>
   </tr>
   <tr class="row_on">
    <td>&nbsp;{lang_Prevent_deleting_of_entries}</td>
    <td>
     <select name="newsettings[calendar_delete_history]">
      <option value="">{lang_No}</option>
      <option value="history"{selected_calendar_delete_history_history}>{lang_Yes,_only_admins_can_purge_deleted_items}</option>
      <option value="user_purge"{selected_calendar_delete_history_user_purge}>{lang_Yes,_users_can_purge_their_deleted_items}</option>
     </select>
    </td>
   </tr>
   <tr class="row_off">
    <td>&nbsp;{lang_Automatically_purge_old_events_after}</td>
    <td>
     <select name="newsettings[calendar_purge_old]">
      <option value="0ff">{lang_No_automatic_purging}</option>
      <option value=".5"{selected_calendar_purge_old_.5}>0.5 {lang_years}</option>
      <option value="1"{selected_calendar_purge_old_1}>1 {lang_year}</option>
      <option value="2"{selected_calendar_purge_old_2}>2 {lang_years}</option>
      <option value="3"{selected_calendar_purge_old_3}>3 {lang_years}</option>
      <option value="4"{selected_calendar_purge_old_4}>4 {lang_years}</option>
      <option value="5"{selected_calendar_purge_old_5}>5 {lang_years}</option>
      <option value="10"{selected_calendar_purge_old_10}>10 {lang_years}</option>
     </select>
    </td>
   </tr>
   <tr class="th">
    <td colspan="2">&nbsp;<b>{lang_Birthdays}</b></td>
   </tr>
   <tr class="row_off">
    <td>&nbsp;{lang_Show_birthdays_from_addressbook}:</td>
    <td>
     <select name="newsettings[hide_birthdays]">
      <option value="">{lang_Yes}</option>
      <option value="dateonly"{selected_hide_birthdays_dateonly}>{lang_Show_only_the_date,_not_the_year}</option>
      <option value="yes"{selected_hide_birthdays_yes}>{lang_No}</option>
     </select>
    </td>
   </tr>
   <tr class="row_on">
    <td>&nbsp;{lang_Calendar_recurrence_horizont_in_days_(default_1000)}:</td>
    <td><input size="5" name="newsettings[calendar_horizont]" value="{value_calendar_horizont}"></td>
   </tr>

<!-- END body -->

<!-- BEGIN footer -->
  <tr class="th">
    <td colspan="2">
&nbsp;
    </td>
  </tr>
  <tr>
    <td colspan="2" align="center">
      <input type="submit" name="submit" value="{lang_submit}">
      <input type="submit" name="cancel" value="{lang_cancel}">
    </td>
  </tr>
</table>
</form>
<!-- END footer -->
