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
    <td colspan="2"><b>{lang_General}</b></td>
   </tr>
   <tr class="row_on">
    <td>
	 <b>{lang_display_of_identities}</b><br/>
	 {lang_how_should_the_available_information_on_identities_be_displayed}
	</td>
    <td>
	 <select name="newsettings[how2displayIdentities]">
      <option value=""{selected_how2displayIdentities_full}>{lang_all_available_info}</option>
      <option value="email"{selected_how2displayIdentities_email}>{lang_emailaddress}</option>
      <option value="nameNemail"{selected_how2displayIdentities_nameNemail}>{lang_name} &amp; {lang_emailaddress}</option>
      <option value="orgNemail"{selected_how2displayIdentities_orgNemail}>{lang_organisation} &amp; {lang_emailaddress}</option>
     </select>
	</td>
   </tr>
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
