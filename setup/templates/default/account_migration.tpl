<!-- BEGIN header -->
 <form action="{action_url}" method="post">
  {hidden_vars}
  <table border="0" align="center" width="70%">
   <tr bgcolor="#486591">
     <td colspan="2" align="center"><b><font color="#fefefe">{description}</font></b></td>
   </tr>
   <tr bgcolor="#e6e6e6">
<!-- END header -->

<!-- BEGIN user_list -->
    <td align="center" valign="top">
     &nbsp;{select_users}<br />
     <select name="users[]" multiple size="20">
{users}
     </select>
    </td>
<!-- END user_list -->

<!-- BEGIN group_list -->
    <td align="center" valign="top">
     &nbsp;{select_groups}<br />
     <select name="groups[]" multiple size="20">
{groups}
     </select>
    </td>
<!-- END group_list -->

<!-- BEGIN ldap_admin -->
   <tr bgcolor="#e6e6e6">
    <td colspan="2" align="center">{ldap_admin_message}:</td>
   </tr>
   <tr bgcolor="#e6e6e6">
    <td align="right">{ldap_admin_label}: </td><td><input name="ldap_admin" value="" /></td>
   </tr>
   <tr bgcolor="#e6e6e6">
    <td align="right">{ldap_admin_pw_label}: </td><td><input type="password" name="ldap_admin_pw" value="" /></td>
   </tr>
<!-- END ldap_admin -->

<!-- BEGIN truncate_egw_accounts -->
   <tr bgcolor="#e6e6e6">
    <td colspan="2" align="center">
      <label><input type="checkbox" name="truncate_egw_accounts">{truncate_egw_accounts_message}</label>
    </td>
   </tr>
<!-- END truncate_egw_accounts -->

<!-- BEGIN submit -->
   </tr>
   <tr bgcolor="#e6e6e6">
    <td colspan="2" align="center">{memberships}</td>
   </tr>
   <tr bgcolor="#e6e6e6">
    <td colspan="2" align="center">
     <input type="submit" name="migrate" value="{migrate}" />
	 {extra_button}
     <input type="submit" name="cancel" value="{cancel}" />
    </td>
   </tr>
<!-- END submit -->

<!-- BEGIN cancel_only -->
   <tr bgcolor="#e6e6e6">
    <td colspan="2" align="center">
      <input type="submit" name="cancel" value="{cancel}" />
    </td>
   </tr>
<!-- END cancel_only -->

<!-- BEGIN footer -->
  </table>
 </form>
<!-- END footer -->
