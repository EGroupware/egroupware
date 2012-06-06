<!-- BEGIN main -->
<center>
<div style="background-color: white">
<form action="{action_url}" name="mailsettings" method="post">
<table width="670px" border="0" cellspacing="0" cellpading="0">
	<tr>
		<th width="20%" id="tab1" class="activetab" onclick="javascript:tab.display(1);"><a href="#" tabindex="1" accesskey="1" onfocus="tab.display(1);" onclick="tab.display(1); return(false);">Global</a></th>
		<th width="20%" id="tab2" class="activetab" onclick="javascript:tab.display(2);"><a href="#" tabindex="2" accesskey="2" onfocus="tab.display(2);" onclick="tab.display(2); return(false);">SMTP</a></th>
		<th width="20%" id="tab3" class="activetab" onclick="javascript:tab.display(3);"><a href="#" tabindex="3" accesskey="3" onfocus="tab.display(3);" onclick="tab.display(3); return(false);">IMAP</a></th>
		<th width="20%" id="tab4" class="activetab" onclick="javascript:tab.display(4);"><a href="#" tabindex="4" accesskey="4" onfocus="tab.display(4);" onclick="tab.display(4); return(false);">Signature</a></th>
		<th width="20%" id="tab5" class="activetab" onclick="javascript:tab.display(5);"><a href="#" tabindex="5" accesskey="5" onfocus="tab.display(5);" onclick="tab.display(5); return(false);">{lang_stationery}</a></th>
	</tr>
</table>
<br><br>


<!-- The code for Global Tab -->

<div id="tabcontent1" class="inactivetab">
	<table width="670px" border="0" cellspacing="0" cellpadding="5">
		<tr class="th">
			<td width="300px">
				<b>{lang_profile_name}</b>
			</td>
			<td align="right">
				<input style="width: 250px;" type="text" size="30" name="globalsettings[description]" value="{value_description}">
			</td>
		</tr>
	</table>
	<p>
	<fieldset style="width:650px;" class="row_on"><legend>{lang_organisation}</legend>
	<table width="100%" border="0" cellspacing="0" cellpading="1">
		<tr>
			<td width="300px">
				{lang_default_domain}:
			</td>
			<td>
				<input style='width: 350px;' type="text" size="30" name="globalsettings[defaultDomain]" value="{value_defaultDomain}">
			</td>
		</tr>
		<tr>
			<td>
				{lang_organisation_name}:
			</td>
			<td>
				<input style='width: 350px;' type="text" size="30" name="globalsettings[organisationName]" value="{value_organisationName}">
			</td>
		</tr>
	</table>
	</fieldset>
	<p>
	<fieldset style="width:650px;" class="row_off"><legend>{lang_profile_access_rights}</legend>
	<table width="100%" border="0" cellspacing="0" cellpading="1">
		<tr>
			<td width="300px">
				{lang_can_be_used_by_application}:
			</td>
			<td>
				{application_select_box}
			</td>
		</tr>
		<tr>
			<td>
				{lang_can_be_used_by_group}:
			</td>
			<td>
				{group_select_box}
			</td>
		</tr>
		<tr>
			<td>
				{lang_can_be_used_by_user}:
			</td>
			<td>
				{user_select_box}
			</td>
		</tr>
	</table>
	</fieldset>
	<p>
	<fieldset style="width:650px;" class="row_off"><legend>{lang_global_options}</legend>
	<table width="100%" border="0" cellspacing="0" cellpading="1">
		<tr>
			<td width="300px">
				{lang_profile_isactive}
			</td>
			<td>
				<input type="checkbox" name="globalsettings[ea_active]" {selected_ea_active} value="yes">
			</td>
		</tr>
        <tr>
            <td width="300px">
                {lang_user_defined_identities}:
            </td>
            <td>
                <input type="checkbox" name="globalsettings[userDefinedIdentities]" {selected_userDefinedIdentities} value="yes">
            </td>
        </tr>
		<tr>
			<td width="300px">
				{lang_user_defined_accounts}:
			</td>
			<td>
				<input type="checkbox" name="globalsettings[userDefinedAccounts]" {selected_userDefinedAccounts} value="yes">
			</td>
		</tr>
	</table>
	</fieldset>
</div>


<!-- The code for SMTP Tab -->

<div id="tabcontent2" class="inactivetab">
	<table width="670px" border="0" cellspacing="0" cellpadding="5">
		<tr class="th">
			<td width="50%" cclass="td_left">
				<b>{lang_Select_type_of_SMTP_Server}<b>
			</td>
			<td width="50%" align="right" cclass="td_right">
				{smtptype}
			</td>
		</tr>
	</table>
	<p>
	
	<!-- The code for standard SMTP Server -->
	
	<div id="smtpcontent1" class="inactivetab">
		<fieldset style="width:650px;" class="row_on"><legend>{lang_smtp_settings}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr>
				<td width="300px">{lang_SMTP_server_hostname_or_IP_address}:</td>
				<td><input name="smtpsettings[1][smtpServer]" size="40" value="{value_smtpServer}"></td>
			</tr>
			
			<tr class="row_on">
				<td>{lang_SMTP_server_port}:</td>
				<td><input name="smtpsettings[1][smtpPort]" maxlength="5" size="5" value="{value_smtpPort}"></td>
			</tr>
		</table>
		</fieldset>
		<p>
		<fieldset style="width:650px;"><legend>{lang_smtp_auth}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr class="row_off">
				<td width="300px">{lang_Use_SMTP_auth}:</td>
				<td>
					<input type="checkbox" name="smtpsettings[1][smtpAuth]" {selected_smtpAuth} value="yes">
				</td>
			</tr>
            <tr class="row_off">
                <td>{lang_sender}:</td>
                <td>
                    <input type="text" name="smtpsettings[1][smtp_senderadress]" style="width: 350px;" value="{value_smtp_senderadress}">
                </td>
            </tr>
			<tr class="row_off">
				<td>{lang_username}:</td>
				<td>
					<input type="text" name="smtpsettings[1][ea_smtp_auth_username]" style="width: 350px;" value="{value_ea_smtp_auth_username}" autocomplete="off">
				</td>
			</tr>
			<tr class="row_off">
				<td>{lang_password}:</td>
				<td>
					<input type="password" name="smtpsettings[1][ea_smtp_auth_password]" style="width: 350px;" value="{value_ea_smtp_auth_password}" autocomplete="off">
				</td>
			</tr>
		</table>
		</fieldset>
	</div>
	
	
	<!-- The code for Postfix/LDAP Server -->
	
	<div id="smtpcontent2" class="inactivetab">
		<fieldset style="width:650px;" class="row_on"><legend>{lang_smtp_settings}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr>
				<td width="300px">{lang_SMTP_server_hostname_or_IP_address}:</td>
				<td><input name="smtpsettings[2][smtpServer]" size="40" value="{value_smtpServer}"></td>
			</tr>
			
			<tr>
				<td>{lang_SMTP_server_port}:</td>
				<td><input name="smtpsettings[2][smtpPort]" maxlength="5" size="5" value="{value_smtpPort}"></td>
			</tr>
		</table>
		</fieldset>
		<p>
		<fieldset style="width:650px;" class="row_off"><legend>{lang_smtp_auth}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr class="row_off">
				<td width="300px">{lang_Use_SMTP_auth}:</td>
				<td>
					<input type="checkbox" name="smtpsettings[2][smtpAuth]" {selected_smtpAuth} value="yes">
				</td>
			</tr>
            <tr class="row_off">
                <td>{lang_sender}:</td>
                <td>
                    <input type="text" name="smtpsettings[2][smtp_senderadress]" style="width: 350px;" value="{value_smtp_senderadress}">
                </td>
            </tr>
			<tr>
				<td>{lang_username}:</td>
				<td>
					<input type="text" name="smtpsettings[2][ea_smtp_auth_username]" style="width: 350px;" value="{value_ea_smtp_auth_username}" autocomplete="off">
				</td>
			</tr>
			<tr>
				<td>{lang_password}:</td>
				<td>
					<input type="password" name="smtpsettings[2][ea_smtp_auth_password]" style="width: 350px;" value="{value_ea_smtp_auth_password}" autocomplete="off">
				</td>
			</tr>
		</table>
		</fieldset>
		<p>
		<fieldset style="width:650px;" class="row_off"><legend>{lang_smtp_options}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr>
				<td width="300px">{lang_user_can_edit_forwarding_address}:</td>
				<td>
					<input type="checkbox" name="smtpsettings[2][editforwardingaddress]" {selected_editforwardingaddress} value="yes">
				</td>
			</tr>
		</table>
		</fieldset>
<!--		<table>
			<tr>
				<td colspan="2">&nbsp;</td>
			</tr>
		</table>
		<table width="90%" border="0" cellspacing="0" cellpading="1">
			<tr class="th">
				<td width="50%" class="td_left">
					<b>{lang_LDAP_settings}<b>
				</td>
				<td class="td_right">
					&nbsp;
				</td>
			</tr>
			<tr class="row_off">
				<td class="td_left">{lang_use_LDAP_defaults}:</td>
				<td class="td_right">
					<input type="checkbox" name="smtpsettings[2][smtpLDAPUseDefault]" {selected_smtpLDAPUseDefault} value="yes">
				</td>
			</tr>
			<tr class="row_on">
				<td width="50%" class="td_left">{lang_LDAP_server_hostname_or_IP_address}:</td>
				<td width="50%" class="td_right"><input name="smtpsettings[2][smtpLDAPServer]" maxlength="80" size="40" value="{value_smtpLDAPServer}"></td>
			</tr>
			
			<tr class="row_off">
				<td class="td_left">{lang_LDAP_server_admin_dn}:</td>
				<td class="td_right"><input name="smtpsettings[2][smtpLDAPAdminDN]" maxlength="200" size="40" value="{value_smtpLDAPAdminDN}"></td>
			</tr>
			
			<tr class="row_on">
				<td class="td_left">{lang_LDAP_server_admin_pw}:</td>
				<td class="td_right"><input type="password" name="smtpsettings[2][smtpLDAPAdminPW]" maxlength="30" size="40" value="{value_smtpLDAPAdminPW}"></td>
			</tr>

			<tr class="row_off">
				<td class="td_left">{lang_LDAP_server_base_dn}:</td>
				<td class="td_right"><input name="smtpsettings[2][smtpLDAPBaseDN]" maxlength="200" size="40" value="{value_smtpLDAPBaseDN}"></td>
			</tr>
		</table> -->
	</div>

	<!-- The code for Postfix SMTP Server (inetOrgPerson Schema) -->
	
	<div id="smtpcontent3" class="inactivetab">
		<fieldset style="width:650px;" class="row_on"><legend>{lang_smtp_settings}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr>
				<td width="300px">{lang_SMTP_server_hostname_or_IP_address}:</td>
				<td><input name="smtpsettings[3][smtpServer]" size="40" value="{value_smtpServer}"></td>
			</tr>
			
			<tr class="row_on">
				<td>{lang_SMTP_server_port}:</td>
				<td><input name="smtpsettings[3][smtpPort]" maxlength="5" size="5" value="{value_smtpPort}"></td>
			</tr>
		</table>
		</fieldset>
		<p>
		<fieldset style="width:650px;"><legend>{lang_smtp_auth}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr class="row_off">
				<td width="300px">{lang_Use_SMTP_auth}:</td>
				<td>
					<input type="checkbox" name="smtpsettings[3][smtpAuth]" {selected_smtpAuth} value="yes">
				</td>
			</tr>
            <tr class="row_off">
                <td>{lang_sender}:</td>
                <td>
                    <input type="text" name="smtpsettings[3][smtp_senderadress]" style="width: 350px;" value="{value_smtp_senderadress}">
                </td>
            </tr>
			<tr class="row_off">
				<td>{lang_username}:</td>
				<td>
					<input type="text" name="smtpsettings[3][ea_smtp_auth_username]" style="width: 350px;" value="{value_ea_smtp_auth_username}" autocomplete="off">
				</td>
			</tr>
			<tr class="row_off">
				<td>{lang_password}:</td>
				<td>
					<input type="password" name="smtpsettings[3][ea_smtp_auth_password]" style="width: 350px;" value="{value_ea_smtp_auth_password}" autocomplete="off">
				</td>
			</tr>
		</table>
		</fieldset>
	</div>

	<!-- The code for Plesk SMTP Server -->
	
	<div id="smtpcontent4" class="inactivetab">
		<fieldset style="width:650px;" class="row_on"><legend>{lang_smtp_settings}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr>
				<td width="300px">{lang_SMTP_server_hostname_or_IP_address}:</td>
				<td><input name="smtpsettings[4][smtpServer]" size="40" value="{value_smtpServer}"></td>
			</tr>
			
			<tr class="row_on">
				<td>{lang_SMTP_server_port}:</td>
				<td><input name="smtpsettings[4][smtpPort]" maxlength="5" size="5" value="{value_smtpPort}"></td>
			</tr>
		</table>
		</fieldset>
		<p>
		<fieldset style="width:650px;"><legend>{lang_smtp_auth}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr class="row_off">
				<td width="300px">{lang_Use_SMTP_auth}:</td>
				<td>
					<input type="checkbox" name="smtpsettings[4][smtpAuth]" {selected_smtpAuth} value="yes">
				</td>
			</tr>
            <tr class="row_off">
                <td>{lang_sender}:</td>
                <td>
                    <input type="text" name="smtpsettings[4][smtp_senderadress]" style="width: 350px;" value="{value_smtp_senderadress}">
                </td>
            </tr>
			<tr class="row_off">
				<td>{lang_username}:</td>
				<td>
					<input type="text" name="smtpsettings[4][ea_smtp_auth_username]" style="width: 350px;" value="{value_ea_smtp_auth_username}" autocomplete="off">
				</td>
			</tr>
			<tr class="row_off">
				<td>{lang_password}:</td>
				<td>
					<input type="password" name="smtpsettings[4][ea_smtp_auth_password]" style="width: 350px;" value="{value_ea_smtp_auth_password}" autocomplete="off">
				</td>
			</tr>
		</table>
		</fieldset>
		<p>
		<fieldset style="width:650px;" class="row_off"><legend>{lang_smtp_options}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr>
				<td width="300px">{lang_user_can_edit_forwarding_address}:</td>
				<td>
					<input type="checkbox" name="smtpsettings[4][editforwardingaddress]" {selected_editforwardingaddress} value="yes">
				</td>
			</tr>
		</table>
		</fieldset>
	</div>

	<!-- The code for Postfix/LDAP Server with dbmailldapschema-->
	
	<div id="smtpcontent5" class="inactivetab">
		<fieldset style="width:650px;" class="row_on"><legend>{lang_smtp_settings}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr>
				<td width="300px">{lang_SMTP_server_hostname_or_IP_address}:</td>
				<td><input name="smtpsettings[5][smtpServer]" size="40" value="{value_smtpServer}"></td>
			</tr>
			
			<tr>
				<td>{lang_SMTP_server_port}:</td>
				<td><input name="smtpsettings[5][smtpPort]" maxlength="5" size="5" value="{value_smtpPort}"></td>
			</tr>
		</table>
		</fieldset>
		<p>
		<fieldset style="width:650px;" class="row_off"><legend>{lang_smtp_auth}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr class="row_off">
				<td width="300px">{lang_Use_SMTP_auth}:</td>
				<td>
					<input type="checkbox" name="smtpsettings[5][smtpAuth]" {selected_smtpAuth} value="yes">
				</td>
			</tr>
            <tr class="row_off">
                <td>{lang_sender}:</td>
                <td>
                    <input type="text" name="smtpsettings[5][smtp_senderadress]" style="width: 350px;" value="{value_smtp_senderadress}">
                </td>
            </tr>
			<tr>
				<td>{lang_username}:</td>
				<td>
					<input type="text" name="smtpsettings[5][ea_smtp_auth_username]" style="width: 350px;" value="{value_ea_smtp_auth_username}" autocomplete="off">
				</td>
			</tr>
			<tr>
				<td>{lang_password}:</td>
				<td>
					<input type="password" name="smtpsettings[5][ea_smtp_auth_password]" style="width: 350px;" value="{value_ea_smt_pauth_password}" autocomplete="off">
				</td>
			</tr>
		</table>
		</fieldset>
		<p>
		<fieldset style="width:650px;" class="row_off"><legend>{lang_smtp_options}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr>
				<td width="300px">{lang_user_can_edit_forwarding_address}:</td>
				<td>
					<input type="checkbox" name="smtpsettings[5][editforwardingaddress]" {selected_editforwardingaddress} value="yes">
				</td>
			</tr>
		</table>
		</fieldset>
	</div>

</div>

<!-- The code for IMAP/POP3 Tab -->

<div id="tabcontent3" class="inactivetab">
	<table width="670px" border="0" cellspacing="0" cellpadding="5">
		<tr class="th">
			<td width="50%">
				<b>{lang_select_type_of_imap/pop3_server}</b>
			</td>
			<td width="50%" align="right">
				{imaptype}
			</td>
		</tr>
	</table>
	<p>

	<!-- The code for standard POP3 Server -->
	
	<div id="imapcontent1" class="inactivetab">
		<fieldset style="width:650px;" class="row_on"><legend>{lang_server_settings}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr>
				<td width="300px">{lang_pop3_server_hostname_or_IP_address}:</td>
				<td><input name="imapsettings[1][imapServer]" maxlength="80" style="width: 350px;" value="{value_imapServer}"></td>
			</tr>
			
			<tr>
				<td>{lang_pop3_server_port}:</td>
				<td><input name="imapsettings[1][imapPort]" maxlength="5" size="5" value="{value_imapPort}"></td>
			</tr>
			
			<tr>
				<td>{lang_imap_server_logintyp}:</td>
				<td>
					<select name="imapsettings[1][imapLoginType]" style="width: 350px;" size="1" onclick="onchange_imapsettings(1,'imapLoginType');">
						<option value="standard" {selected_imapLoginType_standard}>{lang_standard}</option>
						<option value="vmailmgr" {selected_imapLoginType_vmailmgr}>{lang_vmailmgr}</option>
						<option value="admin" {selected_imapLoginType_admin}>{lang_defined_by_admin}</option>
					</select>
				</td>

			</tr>
		</table>
		</fieldset>
		<p>
        <fieldset style="width:650px;" class="row_off"><legend>{lang_imap_auth}</legend>
        <table width="100%" border="0" cellspacing="0" cellpading="1">
            <tr class="row_off">
                <td width="300px">{lang_Use_IMAP_auth}:</td>
                <td>
                </td>
            </tr>
            <tr>
                <td>{lang_username}:</td>
                <td>
                    <input type="text" name="imapsettings[1][imapAuthUsername]" style="width: 350px;" value="{value_imapAuthUsername}" autocomplete="off">
                </td>
            </tr>
            <tr>
                <td>{lang_password}:</td>
                <td>
                    <input type="password" name="imapsettings[1][imapAuthPassword]" style="width: 350px;" value="{value_imapAuthPassword}" autocomplete="off">
                </td>
            </tr>
        </table>
        </fieldset>
        <p>
		<fieldset style="width:650px;" class="row_off"><legend>{lang_encryption_settings}</legend>
                <table width="100%" border="0" cellspacing="0" cellpading="1">

			<tr>
				<td width="300px">{lang_encrypted_connection}:</td>
				<td>
					<input type="radio" name="imapsettings[1][imapTLSEncryption]" value="1" {checked_imapTLSEncryption_1}> STARTTLS
					<input type="radio" name="imapsettings[1][imapTLSEncryption]" value="2" {checked_imapTLSEncryption_2}> TLS
					<input type="radio" name="imapsettings[1][imapTLSEncryption]" value="3" {checked_imapTLSEncryption_3}> SSL
					<input type="radio" name="imapsettings[1][imapTLSEncryption]" value="0" {checked_imapTLSEncryption_0}> {lang_no_encryption}
				</td>
			</tr>

			<tr>
				<td>{lang_do_not_validate_certificate}:</td>
				<td>
					<input type="checkbox" name="imapsettings[1][imapTLSAuthentication]" {selected_imapTLSAuthentication} value="dontvalidate">
				</td>
			</tr>

		</table>
		</fieldset>
	</div>
	

	<!-- The code for standard IMAP Server -->
	
	<div id="imapcontent2" class="inactivetab">
		<fieldset style="width:650px;" class="row_on"><legend>{lang_server_settings}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr>
				<td width="300px">{lang_imap_server_hostname_or_IP_address}:</td>
				<td><input name="imapsettings[2][imapServer]" maxlength="80" style="width: 350px;" value="{value_imapServer}"></td>
			</tr>
			
			<tr>
				<td>{lang_imap_server_port}:</td>
				<td><input name="imapsettings[2][imapPort]" maxlength="5" size="5" value="{value_imapPort}"></td>
			</tr>
			
			<tr>
				<td>{lang_imap_server_logintyp}:</td>
				<td>
					<select name="imapsettings[2][imapLoginType]" style="width: 350px;" size="1" onclick="onchange_imapsettings(2,'imapLoginType');">
						<option value="standard" {selected_imapLoginType_standard}>{lang_standard}</option>
						<option value="vmailmgr" {selected_imapLoginType_vmailmgr}>{lang_vmailmgr}</option>
						<option value="admin" {selected_imapLoginType_admin}>{lang_defined_by_admin}</option>
					</select>
				</td>

			</tr>
		</table>
		</fieldset>
		<p>
        <fieldset style="width:650px;" class="row_off"><legend>{lang_imap_auth}</legend>
        <table width="100%" border="0" cellspacing="0" cellpading="1">
            <tr class="row_off">
                <td width="300px">{lang_Use_IMAP_auth}:</td>
                <td>
                </td>
            </tr>
            <tr>
                <td>{lang_username}:</td>
                <td>
                    <input type="text" name="imapsettings[2][imapAuthUsername]" style="width: 350px;" value="{value_imapAuthUsername}" autocomplete="off">
                </td>
            </tr>
            <tr>
                <td>{lang_password}:</td>
                <td>
                    <input type="password" name="imapsettings[2][imapAuthPassword]" style="width: 350px;" value="{value_imapAuthPassword}" autocomplete="off">
                </td>
            </tr>
        </table>
        </fieldset>
        <p>
		<fieldset style="width:650px;" class="row_off"><legend>{lang_encryption_settings}</legend>
                <table width="100%" border="0" cellspacing="0" cellpading="1">

			<tr>
				<td width="300px">{lang_encrypted_connection}:</td>
				<td colspan="1">
<!--					<input type="checkbox" name="imapsettings[2][imapTLSEncryption]" {selected_imapTLSEncryption} value="yes"><br> -->
					<input type="radio" name="imapsettings[2][imapTLSEncryption]" value="1" {checked_imapTLSEncryption_1}> STARTTLS
					<input type="radio" name="imapsettings[2][imapTLSEncryption]" value="2" {checked_imapTLSEncryption_2}> TLS
					<input type="radio" name="imapsettings[2][imapTLSEncryption]" value="3" {checked_imapTLSEncryption_3}> SSL
					<input type="radio" name="imapsettings[2][imapTLSEncryption]" value="0" {checked_imapTLSEncryption_0}> {lang_no_encryption}
				</td>
			</tr>

			<tr>
				<td>{lang_do_not_validate_certificate}:</td>
				<td>
					<input type="checkbox" name="imapsettings[2][imapTLSAuthentication]" {selected_imapTLSAuthentication} value="dontvalidate">
				</td>
			</tr>
		</table>
		</fieldset>
	</div>
	

	<!-- The code for the Cyrus IMAP Server -->
	
	<div id="imapcontent3" class="inactivetab">
		<fieldset style="width:650px;" class="row_on"><legend>{lang_server_settings}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr>
				<td width="300px">{lang_imap_server_hostname_or_IP_address}:</td>
				<td><input name="imapsettings[3][imapServer]" maxlength="80" style="width: 350px;" value="{value_imapServer}"></td>
			</tr>
			
			<tr>
				<td>{lang_imap_server_port}:</td>
				<td><input name="imapsettings[3][imapPort]" maxlength="5" size="5" value="{value_imapPort}"></td>
			</tr>
			
			<tr>
				<td>{lang_imap_server_logintyp}:</td>
				<td>
					<select name="imapsettings[3][imapLoginType]" style="width: 350px;" size="1" onclick="onchange_imapsettings(3,'imapLoginType');">
						<option value="standard" {selected_imapLoginType_standard}>{lang_standard}</option>
						<option value="vmailmgr" {selected_imapLoginType_vmailmgr}>{lang_vmailmgr}</option>
						<option value="admin" {selected_imapLoginType_admin}>{lang_defined_by_admin}</option>
						<option value="email" {selected_imapLoginType_email}>{lang_email}</option>
					</select>
				</td>

			</tr>
		</table>
		</fieldset>
		<p>
        <fieldset style="width:650px;" class="row_off"><legend>{lang_imap_auth}</legend>
        <table width="100%" border="0" cellspacing="0" cellpading="1">
            <tr class="row_off">
                <td width="300px">{lang_Use_IMAP_auth}:</td>
                <td>
                </td>
            </tr>
            <tr>
                <td>{lang_username}:</td>
                <td>
                    <input type="text" name="imapsettings[3][imapAuthUsername]" style="width: 350px;" value="{value_imapAuthUsername}" autocomplete="off">
                </td>
            </tr>
            <tr>
                <td>{lang_password}:</td>
                <td>
                    <input type="password" name="imapsettings[3][imapAuthPassword]" style="width: 350px;" value="{value_imapAuthPassword}" autocomplete="off">
                </td>
            </tr>
        </table>
        </fieldset>
        <p>
		<fieldset style="width:650px;" class="row_off"><legend>{lang_encryption_settings}</legend>
                <table width="100%" border="0" cellspacing="0" cellpading="1">

			<tr>
				<td width="300px">{lang_encrypted_connection}:</td>
				<td>
<!--					<input type="checkbox" name="imapsettings[3][imapTLSEncryption]" {selected_imapTLSEncryption} value="yes"> -->
					<input type="radio" name="imapsettings[3][imapTLSEncryption]" value="1" {checked_imapTLSEncryption_1}> STARTTLS
					<input type="radio" name="imapsettings[3][imapTLSEncryption]" value="2" {checked_imapTLSEncryption_2}> TLS
					<input type="radio" name="imapsettings[3][imapTLSEncryption]" value="3" {checked_imapTLSEncryption_3}> SSL
					<input type="radio" name="imapsettings[3][imapTLSEncryption]" value="0" {checked_imapTLSEncryption_0}> {lang_no_encryption}
				</td>
			</tr>

			<tr>
				<td>{lang_do_not_validate_certificate}:</td>
				<td>
					<input type="checkbox" name="imapsettings[3][imapTLSAuthentication]" {selected_imapTLSAuthentication} value="dontvalidate">
				</td>
			</tr>

		</table>
		</fieldset>
		<p>
		<fieldset style="width:650px;" class="row_off"><legend>{lang_sieve_settings}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr>
				<td width="300px">{lang_enable_sieve}:</td>
				<td>
					<input type="checkbox" name="imapsettings[3][imapEnableSieve]" {selected_imapEnableSieve} value="yes">
				</td>
			</tr>
			<tr>
				<td>{lang_sieve_server_port}:</td>
				<td><input name="imapsettings[3][imapSievePort]" maxlength="5" size="5" value="{value_imapSievePort}"></td>
			</tr>
			<tr>
				<td colspan="2">{lang_vacation_requires_admin}</td>
			</tr>
		</table>
		</fieldset>
		<p>
		<fieldset style="width:650px;" class="row_off"><legend>{lang_cyrus_imap_administration}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr>
				<td width="300px">{lang_enable_cyrus_imap_administration}:</td>
				<td>
					<input type="checkbox" name="imapsettings[3][imapEnableCyrusAdmin]" {selected_imapEnableCyrusAdmin} value="yes">
				</td>
			</tr>
			<tr>
				<td>{lang_admin_username}:</td>
				<td><input name="imapsettings[3][imapAdminUsername]" maxlength="40"  style="width: 350px;" value="{value_imapAdminUsername}" autocomplete="off"></td>
			</tr>

			<tr>
				<td>{lang_admin_password}:</td>
				<td><input type="password" name="imapsettings[3][imapAdminPW]" maxlength="40"  style="width: 350px;" value="{value_imapAdminPW}" autocomplete="off"></td>
			</tr>
		</table>
		</fieldset>
	</div>
	
	<!-- The code for the DBMail Server with qmailuserbackend -->
	
	<div id="imapcontent4" class="inactivetab">
		<fieldset style="width:650px;" class="row_on"><legend>{lang_server_settings}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr>
				<td width="300px">{lang_imap_server_hostname_or_IP_address}:</td>
				<td><input name="imapsettings[4][imapServer]" maxlength="80" style="width: 350px;" value="{value_imapServer}"></td>
			</tr>
			
			<tr>
				<td>{lang_imap_server_port}:</td>
				<td><input name="imapsettings[4][imapPort]" maxlength="5" size="5" value="{value_imapPort}"></td>
			</tr>
			
			<tr>
				<td>{lang_imap_server_logintyp}:</td>
				<td>
					<select name="imapsettings[4][imapLoginType]" style="width: 350px;" size="1" onclick="onchange_imapsettings(4,'imapLoginType');">
						<option value="standard" {selected_imapLoginType_standard}>{lang_standard}</option>
						<option value="vmailmgr" {selected_imapLoginType_vmailmgr}>{lang_vmailmgr}</option>
						<option value="admin" {selected_imapLoginType_admin}>{lang_defined_by_admin}</option>
					</select>
				</td>

			</tr>
		</table>
		</fieldset>
		<p>
        <fieldset style="width:650px;" class="row_off"><legend>{lang_imap_auth}</legend>
        <table width="100%" border="0" cellspacing="0" cellpading="1">
            <tr class="row_off">
                <td width="300px">{lang_Use_IMAP_auth}:</td>
                <td>
                </td>
            </tr>
            <tr>
                <td>{lang_username}:</td>
                <td>
                    <input type="text" name="imapsettings[4][imapAuthUsername]" style="width: 350px;" value="{value_imapAuthUsername}" autocomplete="off">
                </td>
            </tr>
            <tr>
                <td>{lang_password}:</td>
                <td>
                    <input type="password" name="imapsettings[4][imapAuthPassword]" style="width: 350px;" value="{value_imapAuthPassword}" autocomplete="off">
                </td>
            </tr>
        </table>
        </fieldset>
        <p>
		<fieldset style="width:650px;" class="row_off"><legend>{lang_encryption_settings}</legend>
                <table width="100%" border="0" cellspacing="0" cellpading="1">

			<tr>
				<td width="300px">{lang_encrypted_connection}:</td>
				<td>
<!--					<input type="checkbox" name="imapsettings[4][imapTLSEncryption]" {selected_imapTLSEncryption} value="yes"> -->
					<input type="radio" name="imapsettings[4][imapTLSEncryption]" value="1" {checked_imapTLSEncryption_1}> STARTTLS
					<input type="radio" name="imapsettings[4][imapTLSEncryption]" value="2" {checked_imapTLSEncryption_2}> TLS
					<input type="radio" name="imapsettings[4][imapTLSEncryption]" value="3" {checked_imapTLSEncryption_3}> SSL
					<input type="radio" name="imapsettings[4][imapTLSEncryption]" value="0" {checked_imapTLSEncryption_0}> {lang_no_encryption}
				</td>
			</tr>

			<tr>
				<td>{lang_do_not_validate_certificate}:</td>
				<td>
					<input type="checkbox" name="imapsettings[4][imapTLSAuthentication]" {selected_imapTLSAuthentication} value="dontvalidate">
				</td>
			</tr>
		</table>
		</fieldset>
		<p>
		<fieldset style="width:650px;" class="row_off"><legend>{lang_sieve_settings}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr>
				<td width="300px">{lang_enable_sieve}:</td>
				<td>
					<input type="checkbox" name="imapsettings[4][imapEnableSieve]" {selected_imapEnableSieve} value="yes">
				</td>
			</tr>
			<tr>
				<td>{lang_sieve_server_port}:</td>
				<td><input name="imapsettings[4][imapSievePort]" maxlength="5" size="5" value="{value_imapSievePort}"></td>
			</tr>
		</table>
		</fieldset>
	</div>
	
	<!-- The code for the Plesk IMAP Server -->
	
	<div id="imapcontent5" class="inactivetab">
		<fieldset style="width:650px;" class="row_on"><legend>{lang_server_settings}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr>
				<td width="300px">{lang_imap_server_hostname_or_IP_address}:</td>
				<td><input name="imapsettings[5][imapServer]" maxlength="80" style="width: 350px;" value="{value_imapServer}"></td>
			</tr>
			
			<tr>
				<td>{lang_imap_server_port}:</td>
				<td><input name="imapsettings[5][imapPort]" maxlength="5" size="5" value="{value_imapPort}"></td>
			</tr>
			
			<tr>
				<td>{lang_imap_server_logintyp}:</td>
				<td>
					<select name="imapsettings[5][imapLoginType]" style="width: 350px;" size="1" onclick="onchange_imapsettings(5,'imapLoginType');">
						<option value="standard" {selected_imapLoginType_standard}>{lang_standard}</option>
						<option value="vmailmgr" {selected_imapLoginType_vmailmgr}>{lang_vmailmgr}</option>
						<option value="admin" {selected_imapLoginType_admin}>{lang_defined_by_admin}</option>
					</select>
				</td>

			</tr>
		</table>
		</fieldset>
		<p>
        <fieldset style="width:650px;" class="row_off"><legend>{lang_imap_auth}</legend>
        <table width="100%" border="0" cellspacing="0" cellpading="1">
            <tr class="row_off">
                <td width="300px">{lang_Use_IMAP_auth}:</td>
                <td>
                </td>
            </tr>
            <tr>
                <td>{lang_username}:</td>
                <td>
                    <input type="text" name="imapsettings[5][imapAuthUsername]" style="width: 350px;" value="{value_imapAuthUsername}" autocomplete="off">
                </td>
            </tr>
            <tr>
                <td>{lang_password}:</td>
                <td>
                    <input type="password" name="imapsettings[5][imapAuthPassword]" style="width: 350px;" value="{value_imapAuthPassword}" autocomplete="off">
                </td>
            </tr>
        </table>
        </fieldset>
        <p>
		<fieldset style="width:650px;" class="row_off"><legend>{lang_encryption_settings}</legend>
                <table width="100%" border="0" cellspacing="0" cellpading="1">

			<tr>
				<td width="300px">{lang_encrypted_connection}:</td>
				<td>
<!--					<input type="checkbox" name="imapsettings[5][imapTLSEncryption]" {selected_imapTLSEncryption} value="yes"> -->
					<input type="radio" name="imapsettings[5][imapTLSEncryption]" value="1" {checked_imapTLSEncryption_1}> STARTTLS
					<input type="radio" name="imapsettings[5][imapTLSEncryption]" value="2" {checked_imapTLSEncryption_2}> TLS
					<input type="radio" name="imapsettings[5][imapTLSEncryption]" value="3" {checked_imapTLSEncryption_3}> SSL
					<input type="radio" name="imapsettings[5][imapTLSEncryption]" value="0" {checked_imapTLSEncryption_0}> {lang_no_encryption}
				</td>
			</tr>

			<tr>
				<td>{lang_do_not_validate_certificate}:</td>
				<td>
					<input type="checkbox" name="imapsettings[5][imapTLSAuthentication]" {selected_imapTLSAuthentication} value="dontvalidate">
				</td>
			</tr>

		</table>
		</fieldset>
	</div>	

	<!-- The code for the DBMail Server with dbmailuserbackend -->
	
	<div id="imapcontent6" class="inactivetab">
		<fieldset style="width:650px;" class="row_on"><legend>{lang_server_settings}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr>
				<td width="300px">{lang_imap_server_hostname_or_IP_address}:</td>
				<td><input name="imapsettings[6][imapServer]" maxlength="80" style="width: 350px;" value="{value_imapServer}"></td>
			</tr>
			
			<tr>
				<td>{lang_imap_server_port}:</td>
				<td><input name="imapsettings[6][imapPort]" maxlength="5" size="5" value="{value_imapPort}"></td>
			</tr>
			
			<tr>
				<td>{lang_imap_server_logintyp}:</td>
				<td>
					<select name="imapsettings[6][imapLoginType]" style="width: 350px;" size="1" onclick="onchange_imapsettings(6,'imapLoginType');">
						<option value="standard" {selected_imapLoginType_standard}>{lang_standard}</option>
						<option value="vmailmgr" {selected_imapLoginType_vmailmgr}>{lang_vmailmgr}</option>
						<option value="admin" {selected_imapLoginType_admin}>{lang_defined_by_admin}</option>
					</select>
				</td>

			</tr>
		</table>
		</fieldset>
		<p>
        <fieldset style="width:650px;" class="row_off"><legend>{lang_imap_auth}</legend>
        <table width="100%" border="0" cellspacing="0" cellpading="1">
            <tr class="row_off">
                <td width="300px">{lang_Use_IMAP_auth}:</td>
                <td>
                </td>
            </tr>
            <tr>
                <td>{lang_username}:</td>
                <td>
                    <input type="text" name="imapsettings[6][imapAuthUsername]" style="width: 350px;" value="{value_imapAuthUsername}" autocomplete="off">
                </td>
            </tr>
            <tr>
                <td>{lang_password}:</td>
                <td>
                    <input type="password" name="imapsettings[6][imapAuthPassword]" style="width: 350px;" value="{value_imapAuthPassword}" autocomplete="off">
                </td>
            </tr>
        </table>
        </fieldset>
        <p>
		<fieldset style="width:650px;" class="row_off"><legend>{lang_encryption_settings}</legend>
                <table width="100%" border="0" cellspacing="0" cellpading="1">

			<tr>
				<td width="300px">{lang_encrypted_connection}:</td>
				<td>
<!--					<input type="checkbox" name="imapsettings[6][imapTLSEncryption]" {selected_imapTLSEncryption} value="yes"> -->
					<input type="radio" name="imapsettings[6][imapTLSEncryption]" value="1" {checked_imapTLSEncryption_1}> STARTTLS
					<input type="radio" name="imapsettings[6][imapTLSEncryption]" value="2" {checked_imapTLSEncryption_2}> TLS
					<input type="radio" name="imapsettings[6][imapTLSEncryption]" value="3" {checked_imapTLSEncryption_3}> SSL
					<input type="radio" name="imapsettings[6][imapTLSEncryption]" value="0" {checked_imapTLSEncryption_0}> {lang_no_encryption}
				</td>
			</tr>

			<tr>
				<td>{lang_do_not_validate_certificate}:</td>
				<td>
					<input type="checkbox" name="imapsettings[6][imapTLSAuthentication]" {selected_imapTLSAuthentication} value="dontvalidate">
				</td>
			</tr>
		</table>
		</fieldset>
		<p>
		<fieldset style="width:650px;" class="row_off"><legend>{lang_sieve_settings}</legend>
		<table width="100%" border="0" cellspacing="0" cellpading="1">
			<tr>
				<td width="300px">{lang_enable_sieve}:</td>
				<td>
					<input type="checkbox" name="imapsettings[6][imapEnableSieve]" {selected_imapEnableSieve} value="yes">
				</td>
			</tr>
			<tr>
				<td>{lang_sieve_server_port}:</td>
				<td><input name="imapsettings[6][imapSievePort]" maxlength="5" size="5" value="{value_imapSievePort}"></td>
			</tr>
		</table>
		</fieldset>
	</div>
	
</div>

<!-- The code for Signatures Tab -->

<div id="tabcontent4" class="inactivetab">
	<fieldset style="width:650px;" class="row_off"><legend>{lang_signature_settings}</legend>
	<table width="100%" border="0" cellspacing="0" cellpading="1">
		<tr>
			<td width="300px">
				{lang_user_defined_signatures}:
			</td>
			<td>
				<input type="checkbox" name="globalsettings[ea_user_defined_signatures]" {selected_ea_user_defined_signatures} value="yes">
			</td>
		</tr>
		<tr>
			<td colspan="2">
				{signature}
			</td>
		</tr>
	</table>
	</fieldset>
</div>

<!-- The code for Stationery Tab -->

<div id="tabcontent5" class="inactivetab">
	<fieldset style="width:650px;" class="row_off"><legend>{lang_active_templates}</legend>
	<table width="100%" border="0" cellspacing="0" cellpading="1">
		<tr>
			<td width="300px">
				{lang_active_templates_description}
			</td>
			<td>
				{stored_templates}
			</td>
		</tr>
		<tr>
			<td></td>
			<td align='right'>
				{link_manage_templates}
			</td>
		</tr>
	</table>
	</fieldset>
</div>

<br><br>
<table width="670px" border="0" cellspacing="0" cellpading="1">
	<tr>
		<td width="90%" align="left"  class="td_left">
			<a href="#" onclick="window.close(); return false;">{lang_back}</a>
		</td>
		<td width="10%" align="center" class="td_right">
			<a href="javascript:document.mailsettings.submit();">{lang_save}</a>
		</td>
	</tr>
</table>
</form>
<div>
</center>
<!-- END main -->

