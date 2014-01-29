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
<tr class="th">
<td colspan="2">&nbsp;<b>{lang_History_logging}</b></td>
</tr>
<tr class="row_on">
<td>&nbsp;{lang_Prevent_deleting}</td>
<td>
<select name="newsettings[history]">
<option value="">{lang_No}</option>
<option value="history"{selected_history_history}>{lang_Yes,_only_admins_can_purge_deleted_items}</option>
<option value="userpurge"{selected_history_userpurge}>{lang_Yes,_users_can_purge_their_deleted_items}</option>
</select>
</td>
</tr>
<tr class="row_on">
	<td>
		&nbsp;{lang_Allow_ignore_conflicts}
	</td>
	<td>
		<select name="newsettings[ignoreconflicts]">
			<option value=""{selected_ignoreconflicts_directbooking}>{lang_Yes,_only_users_with_direct_booking_permission}</option>
			<option value="allusers"{selected_ignoreconflicts_allusers}>{lang_Yes,_all_users_can_ignore_conflicts}</option>
			<option value="no">{lang_No}</option>
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
