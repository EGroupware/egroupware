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
	   <td colspan="2"><b>{lang_Deny_certain_groups_access_to_following_features}</b></td>
   </tr>
   <tr class="row_on">
	<td>
	 <b>{lang_Create_new_account}</b>
	</td>
    <td>{call_mail_hooks::deny_createaccount}</td>
   </tr>
   <tr class="row_off">
	<td>
	 <b>{lang_Prevent_managing_folders}</b><br/>
	 {lang_Do_you_want_to_prevent_the_managing_of_folders_(creation,_accessrights_AND_subscribtion)?}
	</td>
    <td>{call_mail_hooks::deny_managefolders}</td>
   </tr>
   <tr class="row_on">
	<td>
	 <b>{lang_Prevent_managing_notifications}</b><br/>
	 {lang_Do_you_want_to_prevent_the_editing/setup_of_notification_by_mail_to_other_emailadresses_if_emails_arrive_(,_even_if_SIEVE_is_enabled)?}
	</td>
    <td>{call_mail_hooks::deny_notificationformailviaemail}</td>
   </tr>
   <tr class="row_off">
	<td>
	 <b>{lang_Prevent_managing_filters}</b><br/>
	 {lang_Do_you_want_to_prevent_the_editing/setup_of_filter_rules_(,_even_if_SIEVE_is_enabled)?}
	</td>
    <td>{call_mail_hooks::deny_editfilterrules}</td>
   </tr>
   <tr class="row_on">
	<td>
	 <b>{lang_Prevent_managing_vacation_notice}</b><br/>
	 {lang_Do_you_want_to_prevent_the_editing/setup_of_the_absent/vacation_notice_(,_even_if_SIEVE_is_enabled)?}
	</td>
    <td>{call_mail_hooks::deny_absentnotice}</td>
   </tr>
   <tr class="row_off">
	<td>
     <b>{lang_restrict_acl_management}</b><br/>
	 {lang_effective_only_if_server_supports_ACL_at_all}
	</td>
    <td>{call_mail_hooks::deny_aclmanagement}</td>
   </tr>
   <tr class="th">
    <td colspan="2"><b>{lang_mail}</b> - {lang_sieve}</td>
   </tr>
   <tr class="row_on">
	<td>
	 <b>{lang_vacation_notice}</b><br/>
	 {lang_provide_a_default_vacation_text,_(used_on_new_vacation_messages_when_there_was_no_message_set_up_previously)}
	</td>
    <td><textarea name="newsettings[default_vacation_text]" cols="50" rows="8">{value_default_vacation_text}</textarea></td>
   </tr>
   <tr class="th">
	   <td colspan="2"><b>{lang_Miscellaneous}</b></td>
   </tr>
   <tr class="row_off">
    <td> <b>{lang_examine_namespace_to_retrieve_folders_in_others_and_shared}</b><br/>
	{lang_only_needed_for_some_servers,_that_do_not_return_all_folders_on_root_level_queries_to_retrieve_all_folders_for_that_level}
	</td>
    <td>
     <select name="newsettings[examineNamespace]">
      <option value=""{selected_examineNamespace_False}>{lang_No}</option>
      <option value="True"{selected_examineNamespace_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr>
   <tr class="row_on">
	<td>
     <b>{lang_Disable_use_of_flowed_lines_in_plain_text_mails_(RFC3676)}</b><br>
     {lang_Some_clients_fail_to_detect_correct_charset,_if_flowed_lines_are_enabled.}
	</td>
    <td>
     <select name="newsettings[disable_rfc3676_flowed]">
      <option value=""{selected_disable_rfc3676_flowed}>{lang_No}</option>
      <option value="True"{selected_disable_rfc3676_flowed_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr>

<!-- END body -->
<!-- BEGIN footer -->
  <tr valign="bottom" style="height: 30px;">
    <td colspan="2">
      {submit}{cancel}
    </td>
  </tr>
</table>
</form>
<!-- END footer -->
