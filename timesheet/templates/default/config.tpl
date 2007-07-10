<!-- BEGIN header -->
<p style="text-align: center; color: {th_err};">{error}</p>
<form name=frm method="POST" action="{action_url}">
{hidden_vars}
<table border="0" align="left">
   <tr class="th">
    <td colspan="2">&nbsp;<b>{title}</b></td>
   </tr>
<!-- END header -->

<!-- BEGIN body -->
<tr class="row_on">
<td>{lang_ProjectManager_integration}:</td>
<td>
<select name="newsettings[pm_integration]">
<option value="">{lang_Both:_allow_to_use_ProjectManager_and_free_project-names}</option>
<option value="none"{selected_pm_integration_none}>{lang_None:_use_only_free_project-names}</option>
<option value="full"{selected_pm_integration_full}>{lang_Full:_use_only_ProjectManager}</option>
</select>
</td>
</tr>
<tr class="row_on">
<td>{lang_Mode}:</td>
<td>
<select name="newsettings[ts_viewtype]">
<option value="normal"{selected_ts_viewtype_normal}>{lang_Regular}</option>
<option value="short"{selected_ts_viewtype_short}>{lang_Simple}</option>
</select>
</td>
</tr>
<tr class="row_on">
<td>{lang_Show_a_quantity_sum_(eg._to_sum_up_negative_overtime)}:</td>
<td>
<select name="newsettings[quantity_sum]">
<option value="">{lang_No}</option>
<option value="true"{selected_quantity_sum_true}>{lang_Yes}</option>
</select>
</td>
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
    </td>
  </tr>
</table>
</form>
<!-- END footer -->
