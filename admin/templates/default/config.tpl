<!-- BEGIN header -->
<form method="POST" action="{action_url}">
<table border="0" align="center" width="85%">
   <tr bgcolor="{th_bg}">
    <td colspan="2"><font color="{th_text}">&nbsp;<b>{title}</b></font></td>
   </tr>
<!-- END header -->
<!-- BEGIN body -->
   <tr bgcolor="{row_on}">
    <td colspan="2">&nbsp;</td>
   </tr>

   <tr bgcolor="{th_bg}">
    <td colspan="2"><font color="{th_text}"><b>{lang_Authentication_/_Accounts}</b></font></td>
   </tr>

   <tr bgcolor="{row_off}">
    <td>{lang_Select_which_type_of_authentication_you_are_using}:</td>
    <td>
     <select name="newsettings[auth_type]">
      <option value="sql"{selected_auth_type_sql}>SQL</option>
	  <option value="sqlssl"{selected_auth_type_sqlssl}>SQL / SSL</option>
	  <option value="ldap"{selected_auth_type_ldap}>LDAP</option>
	  <option value="mail"{selected_auth_type_mail}>Mail</option>
	  <option value="http"{selected_auth_type_http}>HTTP</option>
	  <option value="nis"{selected_auth_type_nis}>NIS</option>
	  <option value="pam"{selected_auth_type_pam}>PAM (Not Ready)</option>
     </select>
    </td>
   </tr>

   <tr bgcolor="{row_off}">
    <td>{lang_Select_where_you_want_to_store/retrieve_user_accounts}:</td>
    <td>
     <select name="newsettings[account_repository]">
      <option value="sql"{selected_account_repository_sql}>SQL</option>
      <option value="ldap"{selected_account_repository_ldap}>LDAP</option>
      <option value="contacts"{selected_account_repository_contacts}>Contacts - EXPERIMENTAL</option>
     </select>
    </td>
   </tr>

   <tr bgcolor="{row_off}">
    <td>{lang_Select_where_you_want_to_store/retrieve_filesystem_information}:</td>
    <td>
     <select name="newsettings[file_repository]">
      <option value="sql"{selected_file_repository_sql}>SQL</option>
     </select>
    </td>
   </tr>

   <tr bgcolor="{row_off}">
    <td>{lang_Minimum_account_id_(e.g._500_or_100,_etc.)}:</td>
    <td><input name="newsettings[account_min_id]" value="{value_account_min_id}"></td>
   </tr>
   <tr bgcolor="{row_off}">
    <td>{lang_Maximum_account_id_(e.g._65535_or_1000000)}:</td>
	<td><input name="newsettings[account_max_id]" value="{value_account_max_id}"></td>
   </tr>

   <tr bgcolor="{row_off}">
     <td>{lang_If_using_LDAP,_do_you_want_to_manage_homedirectory_and_loginshell_attributes?}:</td>
     <td>
      <select name="newsettings[ldap_extra_attributes]">
       <option value="">No</option>
	   <option value="True"{selected_ldap_extra_attributes_True}>Yes</option>
      </select>
     </td>
    </tr>

   <tr bgcolor="{row_off}">
    <td>&nbsp;&nbsp;&nbsp;{lang_LDAP_Default_homedirectory_prefix_(e.g._/home_for_/home/username)}:</td>
    <td><input name="newsettings[ldap_account_home]" value="{value_ldap_account_home}"></td>
   </tr>

   <tr bgcolor="{row_off}">
    <td>&nbsp;&nbsp;&nbsp;{lang_LDAP_Default_shell_(e.g._/bin/bash)}:</td>
    <td><input name="newsettings[ldap_account_shell]" value="{value_ldap_account_shell}"></td>
   </tr>

   <tr bgcolor="{row_off}">
    <td>{lang_Auto_create_account_records_for_authenticated_users}:</td>
    <td>
      <select name="newsettings[auto_create_acct]">
       <option value="">No</option>
	   <option value="True"{selected_auto_create_acct_True}>Yes</option>
      </select>
    </td>
   </tr>

   <tr bgcolor="{row_off}">
    <td>{lang_Add_auto-created_users_to_this_group_('Default'_will_be_attempted_if_this_is_empty.)}:</td>
    <td><input name="newsettings[default_group_lid]" value="{value_default_group_lid}"></td>
   </tr>

   <tr bgcolor="{row_off}">
	   <td>{lang_If_no_ACL_records_for_user_or_any_group_the_user_is_a_member_of}:</td>
    <td>
     <select name="newsettings[acl_default]">
      <option value="deny"{selected_acl_default_deny}>Deny Access</option>
      <option value="grant"{selected_acl_default_grant}>Grant Access</option>
     </select>
    </td>
   </tr>

   <tr bgcolor="{row_off}">
    <td>{lang_LDAP_host}:</td>
	<td><input name="newsettings[ldap_host]" value="{value_ldap_host}"></td>
   </tr>

   <tr bgcolor="{row_off}">
    <td>{lang_LDAP_accounts_context}:</td>
	<td><input name="newsettings[ldap_context]" value="{value_ldap_context}" size="40"></td>
   </tr>

   <tr bgcolor="{row_off}">
    <td>{lang_LDAP_groups_context}:</td>
	<td><input name="newsettings[ldap_group_context]" value="{value_ldap_group_context}" size="40"></td>
   </tr>

   <tr bgcolor="{row_off}">
    <td>{lang_LDAP_rootdn}:</td>
	<td><input name="newsettings[ldap_root_dn]" value="{value_ldap_root_dn}" size="40"></td>
   </tr>

   <tr bgcolor="{row_off}">
    <td>{lang_LDAP_root_password}:</td>
	<td><input name="newsettings[ldap_root_pw]" type="password" value="{value_ldap_root_pw}"></td>
   </tr>

   <tr bgcolor="{row_off}">
	   <td>{lang_LDAP_encryption_type}:</td>
    <td>
     <select name="newsettings[ldap_encryption_type]">
      <option value="DES"{selected_ldap_encryption_type_DES}>DES</option>
      <option value="MD5"{selected_ldap_encryption_type_MD5}>MD5</option>
     </select>
    </td>
   </tr>

   <tr bgcolor="{row_off}">
    <td>{lang_Enter_some_random_text_for_app_session_<br>encryption_(requires_mcrypt)}:</td>
    <td><input name="newsettings[encryptkey]" value="{value_encryptkey}" size="40"></td>
   </tr>
<!-- END body -->

<!-- BEGIN footer -->
  <tr bgcolor="{th_bg}">
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
