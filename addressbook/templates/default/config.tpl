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
    <td colspan="2">&nbsp;<b>{lang_Addressbook}/{lang_Contact_Settings}</b></td>
   </tr>
   <tr class="row_off">
    <td>{lang_Select_where_you_want_to_store_/_retrieve_contacts}.</td>
    <td>
     <select name="newsettings[contact_repository]">
      <option value="sql" {selected_contact_repository_sql}>SQL</option>
      <option value="ldap" {selected_contact_repository_ldap}>LDAP</option>
     </select>
    </td>
   </tr>
   <tr class="row_on">
    <td>{lang_LDAP_host_for_contacts}:</td>
    <td><input name="newsettings[ldap_contact_host]" value="{value_ldap_contact_host}"></td>
   </tr>
   <tr class="row_off">
    <td>{lang_LDAP_context_for_contacts}:</td>
    <td><input name="newsettings[ldap_contact_context]" value="{value_ldap_contact_context}" size="40"></td>
   </tr>
   <tr class="th">
    <td colspan="2">
    	{lang_Additional_information_about_using_LDAP_as_contact_repository}: 
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
