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
    <td colspan="2">&nbsp;<b>{lang_Telephony_integration}</b></td>
   </tr>
   <tr class="row_on">
    <td>&nbsp;{lang_URL_to_link_telephone_numbers_to_(use_%1_for_the_number)}:</td>
    <td><input name="newsettings[call_link]" value="{value_call_link}" size="40"></td>
   </tr>
   <tr class="row_off">
    <td>&nbsp;{lang_Size_of_popup_(WxH,_eg.400x300,_if_a_popup_should_be_used)}:</td>
    <td><input name="newsettings[call_popup]" value="{value_call_popup}" size="10"></td>
   </tr>
   <tr class="th">
    <td colspan="2">&nbsp;<b>{lang_Contact_repository}</b></td>
   </tr>
   <tr class="row_on">
    <td>&nbsp;{lang_Select_where_you_want_to_store_/_retrieve_contacts}:</td>
    <td>
     <select name="newsettings[contact_repository]">
      {hook_contact_repositories}
     </select>
    </td>
   </tr>
   <tr class="row_off">
    <td colspan="2">&nbsp;{lang_You_can_only_use_LDAP_as_contact_repository_if_the_accounts_are_stored_in_LDAP_too!}</td>
   </tr>
   <tr class="row_on">
    <td>&nbsp;{lang_Account_repository}:</td>
    <td>
     <b><script>document.write('{value_account_reposiory}' == 'ldap' || '{value_account_reposiory}' == '' && '{value_auth_type}' == 'ldap' ? 'LDAP' : 'SQL');</script></b>
     ({lang_Can_be_changed_via_Setup_>>_Configuration})
    </td>
   </tr>
   <tr class="th">
    <td colspan="2">&nbsp;<b>{lang_LDAP_settings_for_contacts}</b></td>
   </tr>
   <tr class="row_on">
    <td>&nbsp;{lang_LDAP_host_for_contacts}:</td>
    <td><input name="newsettings[ldap_contact_host]" value="{value_ldap_contact_host}"></td>
   </tr>
   <tr class="row_off">
    <td>&nbsp;{lang_LDAP_context_for_contacts}:</td>
    <td><input name="newsettings[ldap_contact_context]" value="{value_ldap_contact_context}" size="40"></td>
   </tr>
   <tr class="th">
    <td colspan="2">
    	&nbsp;{lang_Additional_information_about_using_LDAP_as_contact_repository}: 
    	<a href="addressbook/doc/README" target="_blank">README</a>
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
