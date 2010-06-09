<!-- BEGIN header -->
<form method="POST" action="{action_url}">
<table border="0" align="center">
   <tr bgcolor="{th_bg}">
    <td colspan="2"><font color="{th_text}">&nbsp;<b>{title}</b></font></td>
   </tr>
<!-- END header -->
<!-- BEGIN body -->
   <tr bgcolor="{row_on}">
    <td colspan="2">&nbsp;</td>
   </tr>

   <tr bgcolor="{row_off}">
    <td colspan="2">&nbsp;<b>{lang_Mail_settings}</b></td>
   </tr>

   <tr bgcolor="{row_on}">
    <td>{lang_IMAP_admin_user}:</td>
    <td><input name="newsettings[imapAdminUser]" value="{value_imapAdminUser}"></td>
   </tr>

   <tr bgcolor="{row_off}">
    <td>{lang_IMAP_admin_password}:</td>
    <td><input name="newsettings[imapAdminPassword]" value="{value_imapAdminPassword}"></td>
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
