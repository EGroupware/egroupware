<!-- BEGIN preferences.tpl -->
<p><b>{lang_abprefs}:</b><hr><p>
   <form method="POST" action="{action_url}">
   <table border="0" align="center" cellspacing="1" cellpadding="1">
  <tr>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td colspan="2"><font color="#000000" face="">{lang_fields}:</font></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td><font color="#000000" face="">{lang_personal}:</font></td>
    <td></td>
    <td></td>
  </tr>
<tr bgcolor="DDDDDD">
    <td><input type="checkbox" name="ab_selected[fn]"{fn_checked}>{fn}</option></td>
    <td><input type="checkbox" name="ab_selected[n_given]"{n_given_checked}>{n_given}</option></td>
    <td><input type="checkbox" name="ab_selected[n_family]"{n_family_checked}>{n_family}</option></td>
    <td><input type="checkbox" name="ab_selected[n_middle]"{n_middle_checked}>{n_middle}</option></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
</tr>
<tr bgcolor="EEEEEE">
    <td><input type="checkbox" name="ab_selected[n_prefix]"{n_prefix_checked}>{n_prefix}</option></td>
    <td><input type="checkbox" name="ab_selected[n_suffix]"{n_suffix_checked}>{n_suffix}</option></td>
    <td><input type="checkbox" name="ab_selected[bday]"{bday_checked}>{bday}</option></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
</tr>
  <tr>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td><font color="#000000" face="">{lang_business}:</font></td>
    <td></td>
    <td></td>
  </tr>
<tr bgcolor="DDDDDD">
    <td><input type="checkbox" name="ab_selected[org_name]"{org_name_checked}>{org_name}</option></td>
    <td><input type="checkbox" name="ab_selected[org_unit]"{org_unit_checked}>{org_unit}</option></td>
    <td><input type="checkbox" name="ab_selected[title]"{title_checked}>{title}</option></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
</tr>
<tr bgcolor="EEEEEE">
    <td><input type="checkbox" name="ab_selected[adr_one_street]"{adr_one_street_checked}>{adr_one_street}</option></td>
    <td><input type="checkbox" name="ab_selected[address2]"{address2_checked}>{address2}</option></td>
    <td><input type="checkbox" name="ab_selected[address3]"{address3_checked}>{address3}</option></td>
    <td><input type="checkbox" name="ab_selected[adr_one_locality]"{adr_one_locality_checked}>{adr_one_locality}</option></td>
    <td><input type="checkbox" name="ab_selected[adr_one_region]"{adr_one_region_checked}>{adr_one_region}</option></td>
    <td><input type="checkbox" name="ab_selected[adr_one_postalcode]"{adr_one_postalcode_checked}>{adr_one_postalcode}</option></td>
</tr>
<tr bgcolor="DDDDDD">
    <td><input type="checkbox" name="ab_selected[adr_one_countryname]"{adr_one_countryname_checked}>{adr_one_countryname}</option></td>
    <td><input type="checkbox" name="ab_selected[adr_two_type]"{adr_one_type_checked}>{adr_one_type}</option></td>
    <td><input type="checkbox" name="ab_selected[tel_work]"{tel_work_checked}>{tel_work}</option></td>
    <td><input type="checkbox" name="ab_selected[email]"{email_checked}>{email}</option></td>
    <td colspan="2"><input type="checkbox" name="ab_selected[email_type]"{email_type_checked}>{email_type}</option></td>
</tr>
  <tr>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td><font color="#000000" face="">{lang_home}:</font></td>
    <td></td>
    <td></td>
  </tr>
<tr bgcolor="EEEEEE">
    <td><input type="checkbox" name="ab_selected[adr_two_street]"{adr_two_street_checked}>{adr_two_street}</option></td>
    <td><input type="checkbox" name="ab_selected[adr_two_locality]"{adr_two_locality_checked}>{adr_two_locality}</option></td>
    <td><input type="checkbox" name="ab_selected[adr_two_region]"{adr_two_region_checked}>{adr_two_region}</option></td>
    <td><input type="checkbox" name="ab_selected[adr_two_postalcode]"{adr_two_postalcode_checked}>{adr_two_postalcode}</option></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
</tr>
<tr bgcolor="DDDDDD">
    <td><input type="checkbox" name="ab_selected[adr_two_countryname]"{adr_two_countryname_checked}>{adr_two_countryname}</option></td>
    <td><input type="checkbox" name="ab_selected[adr_two_type]"{adr_two_type_checked}>{adr_two_type}</option></td>
    <td><input type="checkbox" name="ab_selected[tel_home]"{tel_home_checked}>{tel_home}</option></td>
    <td><input type="checkbox" name="ab_selected[email_home]"{email_home_checked}>{email_home}</option></td>
    <td colspan="2"><input type="checkbox" name="ab_selected[email_home_type]"{email_home_type_checked}>{email_home_type}</option></td>
</tr>
  <tr>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td><font color="#000000" face="">{lang_phones}:</font></td>
    <td></td>
    <td></td>
  </tr>
<tr bgcolor="EEEEEE">
    <td><input type="checkbox" name="ab_selected[tel_voice]"{tel_voice_checked}>{tel_voice}</option></td>
    <td><input type="checkbox" name="ab_selected[tel_fax]"{tel_fax_checked}>{tel_fax}</option></td>
    <td><input type="checkbox" name="ab_selected[tel_msg]"{tel_msg_checked}>{tel_msg}</option></td>
    <td><input type="checkbox" name="ab_selected[tel_cell]"{tel_cell_checked}>{tel_cell}</option></td>
    <td><input type="checkbox" name="ab_selected[tel_pager]"{tel_pager_checked}>{tel_pager}</option></td>
    <td><input type="checkbox" name="ab_selected[tel_bbs]"{tel_bbs_checked}>{tel_bbs}</option></td>
</tr>
<tr bgcolor="DDDDDD">
    <td><input type="checkbox" name="ab_selected[tel_modem]"{tel_modem_checked}>{tel_modem}</option></td>
    <td><input type="checkbox" name="ab_selected[tel_car]"{tel_car_checked}>{tel_car}</option></td>
    <td><input type="checkbox" name="ab_selected[tel_isdn]"{tel_isdn_checked}>{tel_isdn}</option></td>
    <td><input type="checkbox" name="ab_selected[tel_video]"{tel_video_checked}>{tel_video}</option></td>
    <td><input type="checkbox" name="ab_selected[ophone]"{ophone_checked}>{ophone}</option></td>
    <td>&nbsp;</td>
</tr>
  <tr>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td><font color="#000000" face="">{lang_other}:</font></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
<tr bgcolor="EEEEEE">
    <td><input type="checkbox" name="ab_selected[geo]"{geo_checked}>{geo}</option></td>
    <td><input type="checkbox" name="ab_selected[url]"{url_checked}>{url}</option></td>
    <td><input type="checkbox" name="ab_selected[tz]"{tz_checked}>{tz}</option></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
</tr>
  <tr>
    <td>&nbsp;</td>
  </tr>
{custom_fields}
  <tr>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td colspan="2"><font color="#000000" face="">{lang_otherprefs}:</font></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
    <tr bgcolor="EEEEEE">
     <td colspan="3">{lang_showbirthday}
     <input type="checkbox" name="mainscreen_showbirthdays"{show_birthday}></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    </tr>
    <tr bgcolor="EEEEEE">
     <td colspan="3">
     {lang_defaultcat}
     {cat_select}
     </td>
     <td colspan="3">
     {lang_autosave}
     <input type="checkbox" name="autosave_category"{autosave}></td>
    </tr>
    <tr>
     <td colspan="5" align="center">
      <input type="submit" name="submit" value="Submit">
     </td>
    </tr>
   </table>
   </form>
