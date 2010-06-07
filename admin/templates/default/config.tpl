<!-- BEGIN header -->
<form method="POST" action="{action_url}">
<table align="center" width="85%" callspacing="0" style="{ border: 1px solid #000000; }">
   <tr class="th">
    <td colspan="2">&nbsp;<b>{title}</b></td>
   </tr>
<!-- END header -->
<!-- BEGIN body -->
   <tr class="row_on">
    <td>{lang_Would_you_like_eGroupWare_to_check_for_a_new_version<br>when_admins_login_?}:</td>
    <td>
     <select name="newsettings[checkfornewversion]">
      <option value="">{lang_No}</option>
      <option value="True"{selected_checkfornewversion_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr>

   <tr class="row_off">
    <td>{lang_Timeout_for_sessions_in_seconds_(default_14400_=_4_hours)}:</td>
    <td><input size="8" name="newsettings[sessions_timeout]" value="{value_sessions_timeout}"></td>
   </tr>

   <tr class="row_on">
    <td>{lang_Timeout_for_application_session_data_in_seconds_(default_86400_=_1_day)}:</td>
    <td><input size="8" name="newsettings[sessions_app_timeout]" value="{value_sessions_app_timeout}"></td>
   </tr>

   <tr class="row_off">
    <td>{lang_Would_you_like_to_show_each_application's_upgrade_status_?}:</td><td>
     <select name="newsettings[checkappversions]">
      <option value="">{lang_No}</option>
      <option value="Admin"{selected_checkappversions_Admin}>{lang_Admins}</option>
      <option value="All"{selected_checkappversions_All}>{lang_All_Users}</option>
     </select>
    </td>
   </tr>

   <tr class="row_on">
    <td>{lang_Would_you_like_to_automaticaly_load_new_langfiles_(at_login-time)_?}:</td>
    <td>
     <select name="newsettings[disable_autoload_langfiles]">
      <option value="">{lang_Yes}</option>
      <option value="True"{selected_disable_autoload_langfiles_True}>{lang_No}</option>
     </select>
    </td>
   </tr>

    <tr class="row_off">
    <td>{lang_Should_the_login_page_include_a_language_selectbox_(useful_for_demo-sites)_?}:</td>
    <td>
     <select name="newsettings[login_show_language_selection]">
      <option value="">{lang_No}</option>
      <option value="True"{selected_login_show_language_selection_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr>

    <tr class="row_on">
    <td>{lang_How_should_EMail_addresses_for_new_users_be_constructed?}:</td>
    <td>
     <select name="newsettings[email_address_format]">
      <option value="first-dot-last"{selected_email_address_format_first-dot-last}>{lang_Firstname}.{lang_Lastname}@domain.com</option>
      <option value="first-last"{selected_email_address_format_first-last}>{lang_Firstname}{lang_Lastname}@domain.com</option>
      <option value="first-underscore-last"{selected_email_address_format_first-underscore-last}>{lang_Firstname}_{lang_Lastname}@domain.com</option>
      <option value="initial-last"{selected_email_address_format_initial-last}>{lang_Initial}{lang_Lastname}@domain.com</option>
      <option value="initial-dot-last"{selected_email_address_format_initial-dot-last}>{lang_Initial}.{lang_Lastname}@domain.com</option>
      <option value="last-dot-first"{selected_email_address_format_last-dot-first}>{lang_Lastname}.{lang_Firstname}@domain.com</option>
      <option value="last-first"{selected_email_address_format_last-first}>{lang_Lastname}{lang_Firstname}@domain.com</option>
      <option value="last-underscore-first"{selected_email_address_format_last-underscore-first}>{lang_Lastname}_{lang_Firstname}@domain.com</option>
      <option value="last"{selected_email_address_format_last}>{lang_Lastname}@domain.com</option>
      <option value="first"{selected_email_address_format_first}>{lang_Firstname}@domain.com</option>
      <option value="account"{selected_email_address_format_account}>{lang_Username}@domain.com</option>
     </select>
    </td>
   </tr>

   <tr class="row_off">
    <td>{lang_Enter_the_VFS-Path_where_additional_images,_icons_or_logos_can be_placed_(and_found_by_EGroupwares_applications)._The_path_MUST_start_with_/,and_be_readable_by_all_users}:</td>
    <td><input name="newsettings[vfs_image_dir]" value="{value_vfs_image_dir}"></td>
   </tr>

  <tr class="th">
    <td colspan="2">&nbsp;<b>{lang_appearance}</b></td>
   </tr>

   <tr class="row_on">
    <td>{lang_Enter_the_title_for_your_site}:</td>
    <td><input name="newsettings[site_title]" value="{value_site_title}"></td>
   </tr>

   <tr class="row_off">
    <td>{lang_Enter_the_URL_or_filename_(in_phpgwapi/templates/default/images)_of_your_logo}:</td>
    <td><input name="newsettings[login_logo_file]" value="{value_login_logo_file}"></td>
   </tr>

   <tr class="row_on">
    <td>{lang_Enter_the_url_where_your_logo_should_link_to}:</td>
    <td><input name="newsettings[login_logo_url]" value="{value_login_logo_url}"></td>
   </tr>

   <tr class="row_off">
    <td>{lang_Enter_the_title_of_your_logo}:</td>
    <td><input name="newsettings[login_logo_title]" value="{value_login_logo_title}"></td>
   </tr>

   <tr class="row_on">
    <td>{lang_Enter_the_URL_or_filename_(in_your_templates_image_directory)_of_your_favicon_(the_little_icon_that_appears_in_the_browsers_tabs)}:</td>
    <td><input name="newsettings[favicon_file]" value="{value_favicon_file}"></td>
   </tr>

   <tr class="row_off">
    <td>{lang_Show_an_asterisk_(*)_to_mark_untranslated_strings}:</td>
    <td>
     <select name="newsettings[markuntranslated]">
      <option value="">{lang_No}</option>
      <option value="yes" {selected_markuntranslated_yes}>{lang_Yes}</option>
     </select>
    </td>
   </tr>

   <tr class="row_on">
    <td>{lang_How_big_should_thumbnails_for_linked_images_be_(maximum_in_pixels)_?}:</td>
    <td>
     <input name="newsettings[link_list_thumbnail]" value="{value_link_list_thumbnail}" size="5">
    </td>
   </tr>

   <tr class="row_off">
    <td>{lang_Enable_the_spellcheck_in_the_ritch_text_editor_?}:</td>
    <td>
     <select name="newsettings[enabled_spellcheck]">
      <option value="">{lang_No}</option>
      <option value="True"{selected_enabled_spellcheck_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr>

   <tr class="th">
    <td colspan="2">&nbsp;<b>{lang_security}</b></td>
   </tr>

   <tr class="row_on">
    <td>{lang_Use_cookies_to_pass_sessionid}:</td>
    <td>
     <select name="newsettings[usecookies]">
      <option value="True"{selected_usecookies_True}>{lang_Yes} - {lang_more_secure}</option>
      <option value=""{selected_usecookies_}>{lang_No}</option>
     </select>
    </td>
   </tr>

   <tr class="row_off">
    <td>{lang_Cookie_path_(allows_multiple_eGW_sessions_with_different_directories,_has_problemes_with_SiteMgr!)}:</td>
    <td>
     <select name="newsettings[cookiepath]">
      <option value="">{lang_Document_root_(default)}</option>
      <option value="egroupware"{selected_cookiepath_egroupware}>{lang_eGroupWare_directory}</option>
     </select>
    </td>
   </tr>

   <tr class="row_on">
    <td>{lang_Cookie_domain_(default_empty_means_use_full_domain_name,_for_SiteMgr_eg._".domain.com"_allows_to_use_the_same_cookie_for_egw.domain.com_and_www.domain.com)}:</td>
    <td>
     <input name="newsettings[cookiedomain]" value="{value_cookiedomain}" />
    </td>
   </tr>

   <tr class="row_off">
    <td>{lang_check_ip_address_of_all_sessions} ({lang_switch_it_off,_if_users_are_randomly_thrown_out}: "{lang_Your_session_could_not_be_verified.}")</td>
    <td>
     <select name="newsettings[sessions_checkip]">
      <option value="True"{selected_sessions_checkip_True}>{lang_Yes} - {lang_more_secure}</option>
      <option value=""{selected_sessions_checkip_}>{lang_No}</option>
     </select>
    </td>
   </tr>

   <tr class="row_on">
    <td>{lang_Deny_all_users_access_to_grant_other_users_access_to_their_entries_?}:</td>
    <td>
     <select name="newsettings[deny_user_grants_access]">
      <option value="">{lang_No}</option>
      <option value="True"{selected_deny_user_grants_access_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr>


<!--
   <tr class="row_off">
     <td>{lang_Default_file_system_space_per_user}/{lang_group_?}:</td>
     <td>
      <input type="text" name="newsettings[vfs_default_account_size_number]" size="7" value="{value_vfs_default_account_size_number}">&nbsp;&nbsp;
      <select name="newsettings[vfs_default_account_size_type]">
       <option value="gb"{selected_vfs_default_account_size_type_gb}>GB</option>
       <option value="mb"{selected_vfs_default_account_size_type_mb}>MB</option>
       <option value="kb"{selected_vfs_default_account_size_type_kb}>KB</option>
       <option value="b"{selected_vfs_default_account_size_type_b}>B</option>
      </select>
     </td>
    </tr>
-->

   <tr class="row_off">
    <td>{lang_How_many_days_should_entries_stay_in_the_access_log,_before_they_get_deleted_(default_90)_?}:</td>
    <td>
     <input name="newsettings[max_access_log_age]" value="{value_max_access_log_age}" size="5">
    </td>
   </tr>

   <tr class="row_on">
    <td>{lang_After_how_many_unsuccessful_attempts_to_login,_an_account_should_be_blocked_(default_3)_?}:</td>
    <td>
     <input name="newsettings[num_unsuccessful_id]" value="{value_num_unsuccessful_id}" size="5">
    </td>
   </tr>

   <tr class="row_off">
    <td>{lang_After_how_many_unsuccessful_attempts_to_login,_an_IP_should_be_blocked_(default_3)_?}:</td>
    <td>
     <input name="newsettings[num_unsuccessful_ip]" value="{value_num_unsuccessful_ip}" size="5">
    </td>
   </tr>

   <tr class="row_on">
    <td>{lang_How_many_minutes_should_an_account_or_IP_be_blocked_(default_30)_?}:</td>
    <td>
     <input name="newsettings[block_time]" value="{value_block_time}" size="5">
    </td>
   </tr>

   <tr class="row_off">
    <td>{lang_Admin_email_addresses_(comma-separated)_to_be_notified_about_the_blocking_(empty_for_no_notify)}:</td>
    <td>
     <input name="newsettings[admin_mails]" value="{value_admin_mails}" size="40">
    </td>
   </tr>
<!-- not used at the moment RalfBecker 2007/05/17
   <tr class="row_on">
    <td>{lang_Disable_"auto_completion"_of_the_login_form_}:</td>
    <td>
      <select name="newsettings[autocomplete_login]">
         <option value="">{lang_No}</option>
         <option value="True"{selected_autocomplete_login_True}>{lang_Yes}</option>
       </select>
    </td>
   </tr>
-->
   <tr class="row_on">
    <td>{lang_Enable_the_xmlrpc_service} {lang_(default_No,_leave_it_off_if_you_dont_use_it)}:</td>
    <td>
      <select name="newsettings[xmlrpc_enabled]">
         <option value="">{lang_No}</option>
         <option value="True"{selected_xmlrpc_enabled_True}>{lang_Yes}</option>
       </select>
    </td>
   </tr>

   <tr class="row_off">
    <td>{lang_Enable_the_soap_service} {lang_(default_No,_leave_it_off_if_you_dont_use_it)}:</td>
    <td>
      <select name="newsettings[soap_enabled]">
         <option value="">{lang_No}</option>
         <option value="True"{selected_soap_enabled_True}>{lang_Yes}</option>
       </select>
    </td>
   </tr>
   <tr class="row_on">
    <td>{lang_How_many_entries_should_non-admins_be_able_to_export_(empty_=_no_limit,_no_=_no_export)}:</td>
    <td><input name="newsettings[export_limit]" value="{value_export_limit}" size="5"></td>
   </tr>
   </tr>
   <tr class="row_off">
    <td>{lang_Allow_remote_administration_from_following_install_ID's_(comma_separated)}:<br />{lang_Own_install_ID:_}{value_install_id}</td>
    <td><input name="newsettings[allow_remote_admin]" value="{value_allow_remote_admin}" size="40"></td>
   </tr>
   <tr class="row_on">
    <td>{lang_Should_exceptions_contain_a_trace_(including_function_arguments)}:</td>
    <td>
      <select name="newsettings[exception_show_trace]">
         <option value="">{lang_No} - {lang_more_secure}</option>
         <option value="True"{selected_exception_show_trace_True}>{lang_Yes}</option>
       </select>
    </td>
   </tr>
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
