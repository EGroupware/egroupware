<!-- BEGIN header -->
 <form action="{action_url}" method="POST">
  <table border="0" align="center" width="70%">
   <tr bgcolor="#486591">
     <td colspan="2">&nbsp;<font color="#fefefe">{description}<br>&nbsp;</font></td>
   </tr>
<!-- END header -->

<!-- BEGIN jump -->
  <table border="0" align="center" width="70%">
   <tr bgcolor="#e6e6e6">
    <td colspan="2"><a href="{ldapmodify}">{lang_ldapmodify}</a></td>
   </tr>
   <tr bgcolor="#e6e6e6">
    <td colspan="2"><a href="{ldapimport}">{lang_ldapimport}</a></td>
   </tr>
   <tr bgcolor="#e6e6e6">
    <td colspan="2"><a href="{ldapexport}">{lang_ldapexport}</a></td>
   </tr>
   <tr bgcolor="#e6e6e6">
    <td colspan="2"><a href="{ldapdummy}">{lang_ldapdummy}</a></td>
   </tr>
<!-- END jump -->

<!-- BEGIN user_list -->
   <tr bgcolor="#e6e6e6">
    <td align="left" valign="top">
     &nbsp;{select_users}
    </td>
    <td align="center">
     <select name="users[]" multiple size="8">
{users}
     </select>
    </td>
   </tr>
<!-- END user_list -->

<!-- BEGIN admin_list -->
   <tr bgcolor="#e6e6e6">
    <td align="left" valign="top">
     &nbsp;{select_admins}
    </td>
    <td align="center">
     <select name="admins[]" multiple size="8">
{admins}
     </select>
    </td>
   </tr>
<!-- END admin_list -->

<!-- BEGIN group_list -->
   <tr bgcolor="#e6e6e6">
    <td align="left" valign="top">
     &nbsp;{select_groups}
    </td>
    <td align="center">
     <select name="ldapgroups[]" multiple size="5">
{ldapgroups}
     </select>
    </td>
   </tr>
<!-- END group_list -->

<!-- BEGIN app_list -->
   <tr bgcolor="#e6e6e6">
    <td align="left" valign="top">
     &nbsp;{select_apps}
     <br>&nbsp;{note}
    </td>
    <td>
     <select name="s_apps[]" multiple size="10">
{s_apps}
     </select>
    </td>
   </tr>
<!-- END app_list -->

<!-- BEGIN submit -->
   <tr bgcolor="#e6e6e6">
    <td colspan="2" align="center">
     <input type="submit" name="submit" value="{form_submit}">
      <input type="submit" name="cancel" value="{cancel}">
    </td>
   </tr>
<!-- END submit -->

<!-- BEGIN cancel_only -->
   <tr bgcolor="#e6e6e6">
    <td colspan="2" align="center">
      <input type="submit" name="cancel" value="{cancel}">
    </td>
   </tr>
<!-- END cancel_only -->

<!-- BEGIN footer -->
  </table>
 </form>
<!-- END footer -->
