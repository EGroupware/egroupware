<!-- $Id$ -->

<!-- BEGIN header -->

<form method="post" action="{action_url}">
<table align="center" cellspacing="0" border="5" width="90%" >
   <tr class="th">
    <td colspan="2">&nbsp;{title}</td>
   </tr>

<!-- END header -->

<!-- BEGIN body -->
   <tr class="th">
    <td colspan="2"><b>{lang_Path_information}, {lang_Virtual_filesystem}</b></td>
   </tr>

   <tr class="row_on">
    <td>{lang_Where_should_eGroupware_store_file_content}:</td>
    <td>
      <select name="newsettings[vfs_storage_mode]">
{hook_vfs_storage_mode_options}
      </select>
    </td>
   </tr>

   <tr class="row_off">
    <td colspan="2"><b>{lang_Don't_change,_if_you_already_stored_files!_You_will_loose_them!}</b> There's currently no migration avaliable.</td>
   </tr>

   <tr class="row_on">
    <td>{lang_Enter_the_full_path_for_users_and_group_files.<br />Examples:_/files,_E:\FILES}</td>
    <td><input name="newsettings[files_dir]" value="{value_files_dir}" size="40" /></td>
   </tr>

   <tr class="row_off">
    <td colspan="2">
    	<b>{lang_This_has_to_be_outside_the_webservers_document-root!!!}</b><br />
    	{lang_If_you_can_only_access_the_docroot_choose_<b>Database</b>_for_where_to_store_the_file_content_AND_use_same_path_as_for_temporary_files.}
    </td>
   </tr>

   <tr class="row_on">
    <td>{lang_Usernames_(comma-separated)_which_can_get_VFS_root_access_(beside_setup_user)}</td>
    <td><input name="newsettings[vfs_root_user]" value="{value_vfs_root_user}" size="40" /></td>
   </tr>

   <tr class="row_off">
    <td>{lang_Enter_the_full_path_to_the_backup_directory.<br />if_empty:_files_directory}/db_backup:</td>
    <td><input name="newsettings[backup_dir]" value="{value_backup_dir}" size="40" /></td>
   </tr>

   <tr class="row_on">
    <td colspan="2"><b>{lang_This_has_to_be_outside_the_webservers_document-root!!!}</b></td>
   </tr>

   <tr class="row_off">
    <td>{lang_Enter_the_full_path_for_temporary_files.<br />Examples:_/tmp,_C:\TEMP}:</td>
    <td><input name="newsettings[temp_dir]" value="{value_temp_dir}" size="40" /></td>
   </tr>

   <tr class="row_on">
    <td>{lang_Enter_the_location_of_eGroupWare's_URL.<br />Example:_http://www.domain.com/egroupware_&nbsp;_or_&nbsp;_/egroupware<br /><b>No_trailing_slash</b>}:</td>
    <td><input name="newsettings[webserver_url]" value="{value_webserver_url}" size="40" /></td>
   </tr>

   <tr class="row_off">
    <td>{lang_Enforce_SSL_(allows_to_specify_just_a_path_above)}:</td>
    <td>
     <select name="newsettings[enforce_ssl]">
      <option value="">None</option>
      <option value="links"{selected_enforce_ssl_links}>{lang_By_rewriting_links_to_https_(allows_eg._SiteMgr_to_run_on_http)}</option>
      <option value="redirect"{selected_enforce_ssl_redirect}>{lang_By_redirecting_to_https}</option>
     </select>
    </td>
   </tr>

   <tr class="row_on">
    <td>{lang_Image_type_selection_order}:</td>
    <td>
     <select name="newsettings[image_type]">
      <option value="">GIF-&gt;JPG-&gt;PNG</option>
      <option value="1"{selected_image_type_1}>PNG-&gt;JPG-&gt;GIF</option>
      <option value="2"{selected_image_type_2}>PNG-&gt;JPG</option>
     </select>
    </td>
   </tr>

   <tr class="row_on">
    <td>{lang_Complete_path_to_aspell_program}:</td>
    <td>
     <input name="newsettings[aspell_path]" value="{value_aspell_path}" size="40">
    </td>
   </tr>

   <tr class="row_off">
    <td colspan="2">&nbsp;</td>
   </tr>

   <tr class="th">
    <td colspan="2"><b>{lang_Host_information}</b></td>
   </tr>

   <tr class="row_on">
    <td>{lang_Enter_the_hostname_of_the_machine_on_which_this_server_is_running}:</td>
    <td><input name="newsettings[hostname]" value="{value_hostname}" /></td>
   </tr>

   <tr class="row_off">
    <td>{lang_Enter_your_default_FTP_server}:</td>
    <td><input name="newsettings[default_ftp_server]" value="{value_default_ftp_server}" /></td>
   </tr>

   <tr class="row_on">
    <td>{lang_Attempt_to_use_correct_mimetype_for_FTP_instead_of_default_'application/octet-stream'}:</td>
    <td>
     <select name="newsettings[ftp_use_mime]">
      <option value="">{lang_No}</option>
      <option value="True"{selected_ftp_use_mime_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr>
<!-- this is not working correct
   <tr class="row_off">
    <td>{lang_Datetime_port.<br />If_using_port_13,_please_set_firewall_rules_appropriately_before_submitting_this_page.<br />(Port:_13_/_Host:_129.6.15.28)}</td>
    <td>
      <select name="newsettings[daytime_port]">
       <option value="00"{selected_daytime_port_00}>{lang_00_(disable)}</option>
       <option value="13"{selected_daytime_port_13}>{lang_13_(ntp)}</option>
       <option value="80"{selected_daytime_port_80}>{lang_80_(http)}</option>
      </select>
    </td>
   </tr>
-->
   <tr class="row_off">
    <td>{lang_Enter_your_HTTP_proxy_server}:</td>
    <td><input name="newsettings[httpproxy_server]" value="{value_httpproxy_server}" /></td>
   </tr>

   <tr class="row_on">
    <td>{lang_Enter_your_HTTP_proxy_server_port}:</td>
    <td><input name="newsettings[httpproxy_port]" value="{value_httpproxy_port}" /></td>
   </tr>

   <tr class="row_off">
    <td>{lang_Enter_your_HTTP_proxy_server_username}:</td>
    <td><input name="newsettings[httpproxy_server_username]" value="{value_httpproxy_server_username}" /></td>
   </tr>

   <tr class="row_on">
    <td>{lang_Enter_your_HTTP_proxy_server_password}:</td>
    <td><input name="newsettings[httpproxy_server_password]" value="{value_httpproxy_server_password}" /></td>
   </tr>

<!-- until they are working
   <tr class="row_off">
    <td>{lang_Enter_the_site_username_for_peer_servers}.</td>
    <td><input name="newsettings[site_username]" value="{value_site_username}" /></td>
   </tr>

   <tr class="row_on">
    <td>{lang_Enter_the_site_password_for_peer_servers}.</td>
    <td><input type="password" name="newsettings[site_password]" value="{value_site_password}" /></td>
   </tr>
-->

  <tr class="row_off">
    <td colspan="2">&nbsp;</td>
   </tr>

   <tr class="th">
    <td colspan="2"><b>{lang_Standard_mailserver_settings_(used_for_Mail_authentication_too)}:</b></td>
   </tr>
   <tr class="row_on"">
    <td>{lang_POP/IMAP_mail_server_hostname_or_IP_address}:</td>
    <td><input name="newsettings[mail_server]" value="{value_mail_server}"></td>
   </tr>
   <tr class="row_off"">
    <td>{lang_Mail_server_protocol}:</td>
    <td>
     <select name="newsettings[mail_server_type]">
      <option value="imap" {selected_mail_server_type_imap}>IMAP</option>
      <option value="imaps" {selected_mail_server_type_imaps}>IMAPS</option>
     </select>
    </td>
   </tr>
   <tr class="row_on"">
    <td>{lang_Mail_server_login_type}:</td>
    <td>
     <select name="newsettings[mail_login_type]">{hook_mail_login_type}</select>
    </td>
   </tr>
   <tr class="row_off"">
    <td>{lang_Mail_domain_(for_Virtual_mail_manager)}:</td>
    <td><input name="newsettings[mail_suffix]" value="{value_mail_suffix}"></td>
   </tr>
   <tr class="row_on">
    <td>{lang_SMTP_server_hostname_or_IP_address}:</td>
    <td><input name="newsettings[smtp_server]" value="{value_smtp_server}"></td>
   </tr>
   <tr class="row_off">
    <td>{lang_SMTP_server_port}:</td>
    <td><input name="newsettings[smtp_port]" value="{value_smtp_port}"></td>
   </tr>
   <tr class="row_on">
    <td>{lang_User_for_SMTP-authentication_(leave_it_empty_if_no_auth_required)}:</td>
    <td><input name="newsettings[smtp_auth_user]" value="{value_smtp_auth_user}"></td>
   </tr>
   <tr class="row_off">
    <td>{lang_Password_for_SMTP-authentication}:</td>
    <td><input type="password" name="newsettings[smtp_auth_passwd]" value="{value_smtp_auth_passwd}"></td>
   </tr>
   <tr class="row_on">
    <td colspan="2">&nbsp;</td>
   </tr>

<!-- from admin -->

   <tr class="th">
    <td colspan="2"><b>{lang_Authentication_/_Accounts}</b></td>
   </tr>

   <tr class="row_off">
    <td>{lang_Select_which_type_of_authentication_you_are_using}:</td>
    <td>
     <select name="newsettings[auth_type]">
{hook_auth_type}
     </select>
    </td>
   </tr>

   <tr class="row_on">
    <td>{lang_Authentication_type_for_application}: <b>SyncML</b></td>
    <td>
     <select name="newsettings[auth_type_syncml]">
      <option value="">{lang_Standard,_as_defined_above}</option>
{hook_auth_type_syncml}
     </select>
    </td>
   </tr>

    <tr class="row_off">
    <td>{lang_Authentication_type_for_application}: <b>GroupDAV/CalDAV/CardDAV</b></td>
    <td>
     <select name="newsettings[auth_type_groupdav]">
      <option value="">{lang_Standard,_as_defined_above}</option>
{hook_auth_type_groupdav}
     </select>
    </td>
   </tr>

  <tr class="row_on">
    <td>{lang_HTTP_auth_types_(comma-separated)_to_use_without_login-page, eg. "NTLM"}:</td>
    <td>
      <input name="newsettings[http_auth_types]" value="{value_http_auth_types}" size="20" />
    </td>
   </tr>

   <tr class="row_off">
    <td>{lang_Select_where_you_want_to_store/retrieve_user_accounts}:</td>
    <td>
     <select name="newsettings[account_repository]">
      <option value="sql"{selected_account_repository_sql}>SQL</option>
      <option value="ldap"{selected_account_repository_ldap}>LDAP</option>
      <!--<option value="contacts"{selected_account_repository_contacts}>Contacts - EXPERIMENTAL</option>-->
     </select>
    </td>
   </tr>

   <tr class="row_on">
    <td>{lang_sql_encryption_type}:</td>
    <td>
     <select name="newsettings[sql_encryption_type]">{hook_sql_passwdhashes}</select>
    </td>
   </tr>

   <tr class="row_off">
     <td>{lang_Activate_safe_password_check}:</td>
     <td>
      <select name="newsettings[check_save_passwd]">
       <option value="">{lang_No}</option>
       <option value="True" {selected_check_save_passwd_True}>{lang_Yes}</option>
      </select>
     </td>
    </tr>

   <tr class="row_off">
    <td>{lang_Allow_authentication_via_cookie}:</td>
    <td>
      <select name="newsettings[allow_cookie_auth]">
         <option value="">{lang_No}</option>
         <option value="True" {selected_allow_cookie_auth_True}>{lang_Yes}</option>
       </select>
    </td>
   </tr>

   <tr class="row_on">
    <td>{lang_Auto_login_anonymous_user}:</td>
    <td>
      <select name="newsettings[auto_anon_login]">
         <option value="">{lang_No}</option>
         <option value="True"{selected_auto_anon_login_True}>{lang_Yes}</option>
       </select>
    </td>
   </tr>

   <tr class="row_off">
    <td>{lang_Allow_password_migration}:</td>
    <td>
      <select name="newsettings[pwd_migration_allowed]">
         <option value="">{lang_No}</option>
         <option value="True" {selected_pwd_migration_allowed_True}>{lang_Yes}</option>
       </select>
    </td>
   </tr>

   <tr class="row_on">
    <td>{lang_Allowed_migration_types_(comma-separated)}:</td>
    <td>
      <input name="newsettings[pwd_migration_types]" value="{value_pwd_migration_types}" size="20" />
    </td>
   </tr>

   <tr class="row_off">
    <td>{lang_Minimum_account_id_(e.g._500_or_100,_etc.)}:</td>
    <td><input name="newsettings[account_min_id]" value="{value_account_min_id}" /></td>
   </tr>
   <tr class="row_on">
    <td>{lang_Maximum_account_id_(e.g._65535_or_1000000)}:</td>
    <td><input name="newsettings[account_max_id]" value="{value_account_max_id}" /></td>
   </tr>
   <tr class="row_off">
    <td>{lang_User_account_prefix}:</td>
    <td><input name="newsettings[account_prefix]" value="{value_account_prefix}" /></td>
   </tr>

   <tr class="row_on">
    <td>{lang_Usernames_are_casesensitive}:</td>
    <td>
      <select name="newsettings[case_sensitive_username]">
       <option value="">{lang_No}</option>
       <option value="True"{selected_case_sensitive_username_True}>{lang_Yes}</option>
      </select>
    </td>
   </tr>

   <tr class="row_off">
    <td>{lang_Auto_create_account_records_for_authenticated_users}:</td>
    <td>
      <select name="newsettings[auto_create_acct]">
       <option value="">{lang_No}</option>
       <option value="True"{selected_auto_create_acct_True}>{lang_Yes}</option>
       <option value="lowercase"{selected_auto_create_acct_lowercase}>{lang_Yes,_with lowercase_usernames}</option>
      </select>
    </td>
   </tr>

   <tr class="row_on">
    <td>{lang_Auto-created_user_accounts_expire}:</td>
    <td>
     <select name="newsettings[auto_create_expire]">
      <option value="604800"{selected_auto_create_expire_604800}>{lang_one_week}</option>
      <option value="1209600"{selected_auto_create_expire_1209600}>{lang_two_weeks}</option>
      <option value="2592000"{selected_auto_create_expire_2592000}>{lang_one_month}</option>
      <option value="never"{selected_auto_create_expire_never}>{lang_Never}</option>
     </select>
    </td>
   </tr>

   <tr class="row_off">
    <td>{lang_Add_auto-created_users_to_this_group_('Default'_will_be_attempted_if_this_is_empty.)}:</td>
    <td><input name="newsettings[default_group_lid]" value="{value_default_group_lid}" /></td>
   </tr>

   <tr class="row_on">
    <td>{lang_If_no_ACL_records_for_user_or_any_group_the_user_is_a_member_of}:</td>
    <td>
     <select name="newsettings[acl_default]">
      <option value="deny"{selected_acl_default_deny}>{lang_Deny_Access}</option>
      <option value="grant"{selected_acl_default_grant}>{lang_Grant_Access}</option>
     </select>
    </td>
   </tr>

   <tr class="row_off">
    <td colspan="2">&nbsp;</td>
   </tr>

   <tr class="th">
    <td colspan="2"><b>{lang_If_using_LDAP}:</b></td>
   </tr>

   <tr class="row_on">
    <td>{lang_LDAP_host}:</td>
    <td><input name="newsettings[ldap_host]" value="{value_ldap_host}" /></td>
   </tr>

   <tr class="row_off">
    <td>{lang_LDAP_accounts_context}:</td>
    <td><input name="newsettings[ldap_context]" value="{value_ldap_context}" size="40" /></td>
   </tr>

   <tr class="row_on">
    <td>{lang_LDAP_search_filter_for_accounts,_default:_"(uid=%user)",_%domain=eGW-domain}:</td>
    <td><input name="newsettings[ldap_search_filter]" value="{value_ldap_search_filter}" size="40" /></td>
   </tr>

   <tr class="row_off">
    <td>{lang_LDAP_groups_context}:</td>
    <td><input name="newsettings[ldap_group_context]" value="{value_ldap_group_context}" size="40" /></td>
   </tr>

   <tr class="row_on">
    <td>{lang_LDAP_rootdn} {lang_(searching_accounts_and_changing_passwords)}:</td>
    <td><input name="newsettings[ldap_root_dn]" value="{value_ldap_root_dn}" size="40" /></td>
   </tr>

   <tr class="row_off">
    <td>{lang_LDAP_root_password}:</td>
    <td><input name="newsettings[ldap_root_pw]" type="password" value="{value_ldap_root_pw}" /></td>
   </tr>

   <tr class="row_on">
    <td>{lang_LDAP_encryption_type}:</td>
    <td>
     <select name="newsettings[ldap_encryption_type]">
{hook_passwdhashes}
     </select>
    </td>
   </tr>

   <tr class="row_off">
     <td>{lang_Do_you_want_to_manage_homedirectory_and_loginshell_attributes?}:</td>
     <td>
      <select name="newsettings[ldap_extra_attributes]">
       <option value="">{lang_No}</option>
       <option value="True"{selected_ldap_extra_attributes_True}>{lang_Yes}</option>
      </select>
     </td>
    </tr>

   <tr class="row_on">
    <td>{lang_LDAP_Default_homedirectory_prefix_(e.g._/home_for_/home/username)}:</td>
    <td><input name="newsettings[ldap_account_home]" value="{value_ldap_account_home}" /></td>
   </tr>

   <tr class="row_off">
    <td>{lang_LDAP_Default_shell_(e.g._/bin/bash)}:</td>
    <td><input name="newsettings[ldap_account_shell]" value="{value_ldap_account_shell}" /></td>
   </tr>

   <tr class="row_on">
     <td>{lang_Allow_usernames_identical_to_system_users?}:</td>
     <td>
      <select name="newsettings[ldap_allow_systemusernames]">
       <option value="">{lang_No}</option>
       <option value="True"{selected_ldap_allow_systemusernames_True}>{lang_Yes}</option>
      </select>
     </td>
    </tr>

   <tr class="row_off" valign="top">
    <td>
     <a href="account_migration.php"><b>{lang_Migration_between_eGroupWare_account_repositories}:</b></a>
    </td>
    <td>
      <li>{lang_Account_repository_need_to_be_set_to_the_one_you_migrate_to!}</li>
      <li>{lang_You_need_to_save_the_settings_you_made_here_first!}</li>
    </td>
   </tr>

   <tr class="th">
    <td colspan="2"><b>{lang_If_using_CAS_(Central_Authentication_Service):}</b></td>
   </tr>

   <tr class="row_off">
    <td>{lang_CAS_server_host_name:<br />Example:_sso-cas.univ-rennes1.fr}</td>
    <td><input name="newsettings[cas_server_host_name]" value="{value_cas_server_host_name}" size="40" /></td>
   </tr>

   <tr class="row_off">
    <td>{lang_CAS_server_port:<br />Example:_443}</td>
    <td><input name="newsettings[cas_server_port]" value="{value_cas_server_port}" size="40" /></td>
   </tr>

   <tr class="row_off">
    <td>{lang_CAS_server_uri:}</td>
    <td><input name="newsettings[cas_server_uri]" value="{value_cas_server_uri}" size="40" /></td>
   </tr>

   <tr class="row_off">
    <td>{lang_Authentification_mode:}</td>
    <td>
     <select name="newsettings[cas_authentication_mode]">
      <option value="Client"{selected_cas_authentication_mode_Client}>{lang_php_Client}</option>
      <option value="Proxy"{selected_cas_authentication_mode_Proxy}>{lang_php_Proxy}</option>
     </select>
    </td>
   </tr>

   <tr class="row_off">
    <td>{lang_SSL_validation:}</td>
    <td>
     <select name="newsettings[cas_ssl_validation]">
      <option value="No"{selected_cas_ssl_validation_No}>{lang_No}</option>
      <option value="PEMCertificate"{selected_cas_ssl_validation_PEMCertificate}>{lang_PEM_Certificate}</option>
      <option value="CACertificate"{selected_cas_ssl_validation_CACertificate}>{lang_CA_Certificate}</option>
     </select>
    </td>
   </tr>

   <tr class="row_off">
    <td>{lang_Certificate_(PEM_or_CA):}</td>
    <td><input name="newsettings[cas_cert]" value="{value_cas_cert}" size="40" /></td>
   </tr>

   <tr class="row_on">
    <td colspan="2">&nbsp;</td>
   </tr>

   <tr class="th">
    <td colspan="2"><b>{lang_If_using_ADS_(Active_Directory)_authentication}:</b></td>
   </tr>
   <tr class="row_off">
     <td>{lang_Host/IP_Domain_controler}:</td>
     <td><input name="newsettings[ads_host]" value="{value_ads_host}" size="40" /></td>
   </tr>
   <tr class="row_on">
     <td>{lang_Domain_name}:</td>
     <td><input name="newsettings[ads_domain]" value="{value_ads_domain}" size="40" /></td>
   </tr>

   <tr class="row_off">
    <td colspan="2">&nbsp;</td>
   </tr>
<!--
   <tr class="th">
    <td colspan="2"><b>{lang_Mcrypt_settings_(requires_mcrypt_PHP_extension)}</b></td>
   </tr>

   <tr class="row_on">
    <td>{lang_Enter_some_random_text_for_app_session_encryption}:</td>
    <td><input name="newsettings[encryptkey]" value="{value_encryptkey}" size="40" /></td>
   </tr>

   <tr class="row_off">
    <td>{lang_Mcrypt_algorithm_(default_TRIPLEDES)}:</td>
    <td>
     <select name="newsettings[mcrypt_algo]">
{hook_encryptalgo}
     </select>
    </td>
   </tr>

   <tr class="row_on">
    <td>{lang_Mcrypt_mode_(default_CBC)}:</td>
    <td>
     <select name="newsettings[mcrypt_mode]">
{hook_encryptmode}
     </select>
    </td>
   </tr>

   <tr class="row_off">
    <td colspan="2">&nbsp;</td>
   </tr>
-->
<!-- end from admin -->

<!-- END body -->

<!-- BEGIN footer -->
  <tr class="th">
    <td colspan="2">
	&nbsp;
    </td>
  </tr>
  <tr>
    <td colspan="2" align="center">
      <input type="submit" name="submit" value="Submit" />
      <input type="submit" name="cancel" value="Cancel" />
    </td>
  </tr>
</table>
</form>
<!-- END footer -->


