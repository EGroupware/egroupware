<!-- $Id$ -->

<!-- BEGIN header -->

<form method="POST" action="{action_url}">
<table border="0" align="center" width="85%">
   <tr class="th">
    <td colspan="2">&nbsp;<b>{title}</b></td>
   </tr>

<!-- END header -->

<!-- BEGIN body -->

   <tr class="row_on">
    <td colspan="2">&nbsp;</td>
   </tr>
   <tr class="row_off">
    <td>{lang_Enter_the_title_for_your_site}.</td>
    <td><input name="newsettings[site_title]" value="{value_site_title}"></td>
   </tr>

   <tr class="row_on">
    <td>{lang_Country_Selection} ({lang_Text_Entry}/{lang_SelectBox}):</td>
    <td>
     <select name="newsettings[countrylist]">
{hook_country_set}
     </select>
    </td>
   </tr>

<!--   <tr class="row_off">
    <td>{lang_Use_pure_HTML_compliant_code_(not_fully_working_yet)}:</td>
    <td>
     <select name="newsettings[htmlcompliant]">
      <option value="">{lang_No}</option>
      <option value="True"{selected_htmlcompliant_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr> -->

   <tr class="row_off">
    <td>{lang_Use_cookies_to_pass_sessionid}:</td>
    <td>
     <select name="newsettings[usecookies]">
      <option value="">{lang_No}</option>
      <option value="True"{selected_usecookies_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr>

   <tr class="row_on">
    <td>{lang_check_ip_address_of_all_sessions}:</td>
    <td>
     <select name="newsettings[sessions_checkip]">
      <option value="">{lang_No}</option>
      <option value="True"{selected_sessions_checkip_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr>

   <tr class="row_off">
    <td>{lang_Would_you_like_phpGroupWare_to_check_for_a_new_version<br>when_admins_login_?}:</td>
    <td>
     <select name="newsettings[checkfornewversion]">
      <option value="">{lang_No}</option>
      <option value="True"{selected_checkfornewversion_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr>

   <tr class="row_on">
    <td>
      {lang_Timeout_for_sessions_(in_seconds)}.
      <br>{lang_(not_for_php4_sessions)}
    </td>
    <td><input size="8" name="newsettings[sessions_timeout]" value="{value_sessions_timeout}"></td>
   </tr>

   <tr class="row_off">
    <td>{lang_Timeout_for_application_session_data_(in_seconds)}.</td>
    <td><input size="8" name="newsettings[sessions_app_timeout]" value="{value_sessions_app_timeout}"></td>
   </tr>

<!--
   <tr class="row_off">
    <td>{lang_How_would_you_like_to_sort_applications_in_the_navbar?}:</td>
    <td>
     <select name="newsettings[app_order]">
      <option value="">{lang_Order_id}</option>
      <option value="True"{selected_app_order_True}>{lang_Alphabetically}</option>
     </select>
    </td>
   </tr>
-->

   <tr class="row_on">
    <td>{lang_Would_you_like_to_show_each_application's_upgrade_status_?}:</td><td>
     <select name="newsettings[checkappversions]">
      <option value="">{lang_No}</option>
      <option value="Admin"{selected_checkappversions_Admin}>{lang_Admins}</option>
      <option value="All"{selected_checkappversions_All}>{lang_All_Users}</option>
     </select>
    </td>
   </tr>

   <tr class="row_off">
    <td>{lang_Would_you_like_phpGroupWare_to_cache_the_phpgw_info_array_?}:</td>
    <td>
     <select name="newsettings[cache_phpgw_info]">
      <option value="">{lang_No}</option>
      <option value="True"{selected_cache_phpgw_info_True}>{lang_Yes}</option>
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

   <tr class="row_off">
    <td>{lang_Minimum_password_length}.</td>
    <td><input size="4" name="newsettings[pass_min_length]" value="{value_pass_min_length}"></td>
   </tr>

	<tr class="row_on">
    <td>{lang_Require_non-alpha_characters}:</td>
    <td>
     <select name="newsettings[pass_require_non_alpha]">
      <option value="">{lang_No}</option>
      <option value="True"{selected_pass_require_non_alpha_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr>
   <tr class="row_off">
    <td>{lang_Require_numerical_characters}:</td>
    <td>
     <select name="newsettings[pass_require_numbers]">
      <option value="">{lang_No}</option>
      <option value="True"{selected_pass_require_numbers_True}>{lang_Yes}</option>
     </select>
    </td>
   </tr>
   <tr class="row_on">
    <td>{lang_Require_special_characters}:</td>
    <td>
     <select name="newsettings[pass_require_special_char]">
      <option value="">{lang_No}</option>
      <option value="True"{selected_pass_require_special_char_True}>{lang_Yes}</option>
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
    </td>
  </tr>
</table>
</form>
<!-- END footer -->
