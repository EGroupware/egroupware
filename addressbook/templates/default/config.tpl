<!-- BEGIN header -->
<form method="POST" action="{action_url}">
<table border="0" align="center">
   <tr bgcolor="{th_bg}">
	   <td colspan="2"><font color="{th_text}">&nbsp;<b>{title}</b></font></td>
   </tr>
   <tr bgcolor="{th_err}">
    <td colspan="2">&nbsp;<b>{error}</b></font></td>
   </tr>
<!-- END header -->
<!-- BEGIN body -->
   <tr bgcolor="{row_on}">
    <td colspan="2">&nbsp;</td>
   </tr>
   <tr bgcolor="{row_off}">
    <td colspan="2">&nbsp;<b>{lang_Addressbook}/{lang_Contact_Settings}</b></font></td>
   </tr>
   <tr bgcolor="{row_on}">
    <td>{lang_Contact_application}:</td>
    <td><input name="newsettings[contact_application]" value="{value_contact_application}"></td>
   </tr>
   <tr bgcolor="{row_off}">
    <td align="center" colspan="2">{lang_WARNING!!_LDAP_is_valid_only_if_you_are_NOT_using_contacts_for_accounts_storage!}</td>
   </tr>
   <tr bgcolor="{row_off}">
    <td>{lang_Select_where_you_want_to_store}/{lang_retrieve_contacts}.</td>
    <td>
     <select name="newsettings[contact_repository]">
      <option value="sql" {selected_contact_repository_sql}>SQL</option>
      <option value="ldap" {selected_contact_repository_ldap}>LDAP</option>
     </select>
    </td>
   </tr>
   <tr bgcolor="{row_on}">
    <td>{lang_LDAP_host_for_contacts}:</td>
    <td><input name="newsettings[ldap_contact_host]" value="{value_ldap_contact_host}"></td>
   </tr>
   <tr bgcolor="{row_off}">
    <td>{lang_LDAP_context_for_contacts}:</td>
    <td><input name="newsettings[ldap_contact_context]" value="{value_ldap_contact_context}" size="40"></td>
   </tr>
  <tr bgcolor="{row_on}">
   <td>{lang_LDAP_root_dn_for_contacts}:</td>
   <td><input name="newsettings[ldap_contact_dn]" value="{value_ldap_contact_dn}" size="40"></td>
  </tr>
  <tr bgcolor="{row_off}">
   <td>{lang_LDAP_root_pw_for_contacts}:</td>
   <td><input name="newsettings[ldap_contact_pw]" type="password" value=""></td>
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
