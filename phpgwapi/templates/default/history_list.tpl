<!-- BEGIN row_no_history -->
	<tr bgcolor="{tr_color}">
		<td align="center" colspan="5"><b>{lang_no_history}</b></td>
	</tr>
<!-- END row_no_history -->

<!-- BEGIN row -->
	<tr bgcolor="{tr_color}">
		<td>&nbsp;{row_date}</td>
		<td>&nbsp;{row_owner}</td>
		<td>&nbsp;{row_status}</td>
		<td>&nbsp;{row_old_value}</td>
		<td>&nbsp;{row_new_value}</td>
	</tr>
<!-- END row -->

<!-- BEGIN list -->
<table border="0" width="95%">
	<tr bgcolor="{th_bg}">
		<td>{sort_date}</td>
		<td>{sort_owner}</td>
		<td>{sort_status}</td>
		<td>{sort_old_value}</td>
		<td>{sort_new_value}</td>
	</tr>
{rows}
</table>
<!-- END list -->
