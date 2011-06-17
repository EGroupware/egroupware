<!-- BEGIN header -->
<form method="POST" action="{action_url}">
<table border="0" align="center">
   <tr bgcolor="{th_bg}">
    <td colspan="2"><font color="{th_text}">&nbsp;<b>{title}</b></font></td>
   </tr>
<!-- END header -->
<!-- BEGIN body -->
   <tr>
    <td colspan="2">&nbsp;</td>
   </tr>

   <tr bgcolor="{row_off}">
    <td colspan="2">&nbsp;<b>{lang_Mail_settings}</b></td>
   </tr>

   <tr bgcolor="{row_on}">
    <td>{lang_Enter_your_IMAP_mail_server_hostname_or_IP_address}:</td>
    <td><input name="newsettings[imapServer]" value="{value_imapServer}"></td>
   </tr>

   <tr bgcolor="{row_off}">
    <td>{lang_Select_your_mail_server_type}:</td>
    <td>
     <select name="newsettings[imapServerMode]">
      <option value="imap" {selected_imapServerMode_imap}>IMAP</option>
      <option value="imaps-encr-only" {selected_imapServerMode_imaps-encr-only}>IMAPS Encryption only</option>
      <option value="imaps-encr-auth" {selected_imapServerMode_imaps-encr-auth}>IMAPS Authentication</option>
     </select>
    </td>
   </tr>

   <tr bgcolor="{row_on}">
    <td>IMAP server type:</td>
    <td>
     <select name="newsettings[imapServerType]">
      <option value="Cyrus" {selected_imapServerType_Cyrus}>Cyrus</option>
      <option value="Cyrus-LDAP" {selected_imapServerType_Cyrus-LDAP}>Cyrus-LDAP</option>
      <option value="Courier" {selected_imapServerType_Courier}>Courier</option>
      <option value="UWash" {selected_imapServerType_UWash}>UWash</option>
     </select>
    </td>
   </tr>

   <tr bgcolor="{row_off}">
    <td>{lang_Enter_your_default_mail_domain_(_From:_user@domain_)}:</td>
    <td><input name="newsettings[mailSuffix]" value="{value_mailSuffix}"></td>
   </tr>

   <tr bgcolor="{row_on}">
    <td>{lang_Mail_server_login_type}:</td>
    <td>
     <select name="newsettings[mailLoginType]">
      <option value="standard" {selected_mailLoginType_standard}>standard</option>
      <option value="vmailmgr" {selected_mailLoginType_vmailmgr}>vmailmgr</option>
     </select>
    </td>
   </tr>
   
   <tr bgcolor="{row_off}">
    <td>{lang_Users_can_define_their_own_emailaccounts}:</td>
    <td>
     <select name="newsettings[userDefinedAccounts]">
      <option value="no" {selected_userDefinedAccounts_no}>{lang_no}</option>
      <option value="yes" {selected_userDefinedAccounts_yes}>{lang_yes}</option>
     </select>
    </td>
   </tr>
   
   <tr bgcolor="{row_on}">
    <td>{lang_Organization_name}:</td>
    <td><input name="newsettings[organizationName]" value="{value_organizationName}" size="30"></td>
   </tr>

   <tr>
   	<td colspan="2">&nbsp;</td>
   </tr>

   <tr bgcolor="{row_off}">
    <td colspan="2">&nbsp;<b>{lang_SMTP_settings}</b></td>
   </tr>

   <tr bgcolor="{row_on}">
    <td>{lang_Enter_your_SMTP_server_hostname_or_IP_address}:</td>
    <td><input name="newsettings[smtpServer]" value="{value_smtpServer}"></td>
   </tr>

   <tr bgcolor="{row_off}">
    <td>{lang_Enter_your_SMTP_server_port}:</td>
    <td><input name="newsettings[smtpPort]" value="{value_smtpPort}"></td>
   </tr>

   <tr bgcolor="{row_on}">
    <td>{lang_Use_SMTP_auth}:</td>
    <td>
     <select name="newsettings[smtpAuth]">
      <option value="no" {selected_smtpAuth_no}>{lang_no}</option>
      <option value="yes" {selected_smtpAuth_yes}>{lang_yes}</option>
     </select>
    </td>
   </tr>

   <tr>
   	<td colspan="2">&nbsp;</td>
   </tr>

   <tr bgcolor="{row_off}">
    <td colspan="2">&nbsp;<b>{lang_Sieve_settings}</b></td>
   </tr>

   <tr bgcolor="{row_on}">
    <td>{lang_Enter_your_SIEVE_server_hostname_or_IP_address}:</td>
    <td><input name="newsettings[sieveServer]" value="{value_sieveServer}"></td>
   </tr>

   <tr bgcolor="{row_off}">
    <td>{lang_Enter_your_SIEVE_server_port}:</td>
    <td><input name="newsettings[sievePort]" value="{value_sievePort}"></td>
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
      <input type="submit" name="submit" value="Submit">
      <input type="submit" name="cancel" value="Cancel">
    </td>
  </tr>
</table>
</form>
<!-- END footer -->
