<!-- BEGIN preferences.tpl -->
<br>
 <form method="POST" action="{action_url}">
  <table border="0" align="center" cellspacing="1" cellpadding="1" width="98%">
  <tr bgcolor="{th_bg}">
    <td colspan="6" align="center"><font color="#000000" face="">{lang_fields}:</font></td>
  </tr>
  <tr bgcolor="{row_on}">
    <td colspan="6"><font color="#000000" face="">{lang_personal}:</font></td>
  </tr>
  <tr bgcolor="{row_off}">
    <td><input type="checkbox" name="prefs[fn]"{fn_checked}>{fn}</option></td>
    <td><input type="checkbox" name="prefs[n_given]"{n_given_checked}>{n_given}</option></td>
    <td><input type="checkbox" name="prefs[n_family]"{n_family_checked}>{n_family}</option></td>
    <td><input type="checkbox" name="prefs[n_middle]"{n_middle_checked}>{n_middle}</option></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
  <tr bgcolor="{row_off}">
    <td><input type="checkbox" name="prefs[n_prefix]"{n_prefix_checked}>{n_prefix}</option></td>
    <td><input type="checkbox" name="prefs[n_suffix]"{n_suffix_checked}>{n_suffix}</option></td>
    <td><input type="checkbox" name="prefs[bday]"{bday_checked}>{bday}</option></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td>&nbsp;</td>
  </tr>
  <tr bgcolor="{row_on}">
    <td colspan="6"><font color="#000000" face="">{lang_business}:</font></td>
  </tr>
  <tr bgcolor="{row_off}">
    <td><input type="checkbox" name="prefs[org_name]"{org_name_checked}>{org_name}</option></td>
    <td><input type="checkbox" name="prefs[org_unit]"{org_unit_checked}>{org_unit}</option></td>
    <td><input type="checkbox" name="prefs[title]"{title_checked}>{title}</option></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
  <tr bgcolor="{row_off}">
    <td><input type="checkbox" name="prefs[adr_one_street]"{adr_one_street_checked}>{adr_one_street}</option></td>
    <td><input type="checkbox" name="prefs[address2]"{address2_checked}>{address2}</option></td>
    <td><input type="checkbox" name="prefs[address3]"{address3_checked}>{address3}</option></td>
    <td><input type="checkbox" name="prefs[adr_one_locality]"{adr_one_locality_checked}>{adr_one_locality}</option></td>
    <td><input type="checkbox" name="prefs[adr_one_region]"{adr_one_region_checked}>{adr_one_region}</option></td>
    <td><input type="checkbox" name="prefs[adr_one_postalcode]"{adr_one_postalcode_checked}>{adr_one_postalcode}</option></td>
  </tr>
  <tr bgcolor="{row_off}">
    <td><input type="checkbox" name="prefs[adr_one_countryname]"{adr_one_countryname_checked}>{adr_one_countryname}</option></td>
    <td><input type="checkbox" name="prefs[adr_two_type]"{adr_one_type_checked}>{adr_one_type}</option></td>
    <td><input type="checkbox" name="prefs[tel_work]"{tel_work_checked}>{tel_work}</option></td>
    <td><input type="checkbox" name="prefs[email]"{email_checked}>{email}</option></td>
    <td colspan="2"><input type="checkbox" name="prefs[email_type]"{email_type_checked}>{email_type}</option></td>
  </tr>
  <tr>
    <td>&nbsp;</td>
  </tr>
  <tr bgcolor="{row_on}">
    <td colspan="6"><font color="#000000" face="">{lang_home}:</font></td>
  </tr>
  <tr bgcolor="{row_off}">
    <td><input type="checkbox" name="prefs[adr_two_street]"{adr_two_street_checked}>{adr_two_street}</option></td>
    <td><input type="checkbox" name="prefs[adr_two_locality]"{adr_two_locality_checked}>{adr_two_locality}</option></td>
    <td><input type="checkbox" name="prefs[adr_two_region]"{adr_two_region_checked}>{adr_two_region}</option></td>
    <td><input type="checkbox" name="prefs[adr_two_postalcode]"{adr_two_postalcode_checked}>{adr_two_postalcode}</option></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
  <tr bgcolor="{row_off}">
    <td><input type="checkbox" name="prefs[adr_two_countryname]"{adr_two_countryname_checked}>{adr_two_countryname}</option></td>
    <td><input type="checkbox" name="prefs[adr_two_type]"{adr_two_type_checked}>{adr_two_type}</option></td>
    <td><input type="checkbox" name="prefs[tel_home]"{tel_home_checked}>{tel_home}</option></td>
    <td><input type="checkbox" name="prefs[email_home]"{email_home_checked}>{email_home}</option></td>
    <td colspan="2"><input type="checkbox" name="prefs[email_home_type]"{email_home_type_checked}>{email_home_type}</option></td>
  </tr>
  <tr>
    <td>&nbsp;</td>
  </tr>
  <tr bgcolor="{row_on}">
    <td colspan="6"><font color="#000000" face="">{lang_phones}:</font></td>
  </tr>
  <tr bgcolor="{row_off}">
    <td><input type="checkbox" name="prefs[tel_voice]"{tel_voice_checked}>{tel_voice}</option></td>
    <td><input type="checkbox" name="prefs[tel_fax]"{tel_fax_checked}>{tel_fax}</option></td>
    <td><input type="checkbox" name="prefs[tel_msg]"{tel_msg_checked}>{tel_msg}</option></td>
    <td><input type="checkbox" name="prefs[tel_cell]"{tel_cell_checked}>{tel_cell}</option></td>
    <td><input type="checkbox" name="prefs[tel_pager]"{tel_pager_checked}>{tel_pager}</option></td>
    <td><input type="checkbox" name="prefs[tel_bbs]"{tel_bbs_checked}>{tel_bbs}</option></td>
  </tr>
  <tr bgcolor="{row_off}">
    <td><input type="checkbox" name="prefs[tel_modem]"{tel_modem_checked}>{tel_modem}</option></td>
    <td><input type="checkbox" name="prefs[tel_car]"{tel_car_checked}>{tel_car}</option></td>
    <td><input type="checkbox" name="prefs[tel_isdn]"{tel_isdn_checked}>{tel_isdn}</option></td>
    <td><input type="checkbox" name="prefs[tel_video]"{tel_video_checked}>{tel_video}</option></td>
    <td><input type="checkbox" name="prefs[ophone]"{ophone_checked}>{ophone}</option></td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td>&nbsp;</td>
  </tr>
  <tr bgcolor="{row_on}">
    <td colspan="6"><font color="#000000" face="">{lang_other}:</font></td>
  </tr>
  <tr bgcolor="{row_off}">
    <td><input type="checkbox" name="prefs[geo]"{geo_checked}>{geo}</option></td>
    <td><input type="checkbox" name="prefs[url]"{url_checked}>{url}</option></td>
    <td><input type="checkbox" name="prefs[tz]"{tz_checked}>{tz}</option></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
{custom_fields}
  <tr>
    <td>&nbsp;</td>
  </tr>
  <tr bgcolor="{th_bg}">
    <td colspan="6" align="center"><font color="#000000" face="">{lang_otherprefs}:</font></td>
  </tr>
  <tr bgcolor="{row_off}">
    <td>{lang_default_filter}</td>
    <td>{filter_select}</td>
    <td colspan="3">
     <input type="checkbox" name="other[mainscreen_showbirthdays]"{show_birthday}>
     {lang_showbirthday}
    </td>
  </tr>
  <tr bgcolor="{row_off}">
    <td>{lang_defaultcat}</td>
    <td>{cat_select}</td>
    <td colspan="3">
     <input type="checkbox" name="other[autosave_category]"{autosave}>
     {lang_autosave}
    </td>
  </tr>
  <tr height="40">
    <td colspan="6">
      <input type="submit" name="save" value="{lang_save}"> &nbsp;
      <input type="submit" name="cancel" value="{lang_cancel}">
    </td>
  </tr>
  </table>
  </form>
