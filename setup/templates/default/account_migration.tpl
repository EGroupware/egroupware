<!-- BEGIN header -->
 <form action="{action_url}" method="post">
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

<!-- BEGIN submit -->
   </tr>
   <tr bgcolor="#e6e6e6">
    <td colspan="2" align="center">{memberships}</td>
   </tr>
   <tr bgcolor="#e6e6e6">
    <td colspan="2" align="center">
     <input type="submit" name="migrate" value="{migrate}" />
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
