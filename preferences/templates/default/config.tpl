<!-- $Id: config.tpl 43784 2013-09-11 13:08:59Z ralfbecker $ -->
<!-- BEGIN header -->
<p style="text-align: center; color: red; font-weight: bold;">{error}</p>
<form method="POST" action="{action_url}">
<table align="center" width="85%">
   <tr class="th">
    <td colspan="2">&nbsp;<b>{title}</b></td>
   </tr>
<!-- END header -->
<!-- BEGIN body -->
   <tr class="row_off">
    <td>{lang_Deny_following_groups_access_to_preferences}:</td>
    <td>{call_preferences_hooks::deny_prefs}</td>
   </tr>
   <tr class="row_on">
    <td>{lang_Deny_following_groups_access_to_ACL_(grant_access)}:</td>
    <td>{call_preferences_hooks::deny_acl}</td>
   </tr>
   <tr class="row_off">
    <td>{lang_Deny_following_groups_access_to_edit_categories}:</td>
    <td>{call_preferences_hooks::deny_cats}</td>
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
		  <br>
    </td>
  </tr>
</table>
</form>
<!-- END footer -->
