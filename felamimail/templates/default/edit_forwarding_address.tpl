<!-- BEGIN main -->
<form action="{form_action}" name="editEMailAddress" method="post">
<table width="100%" border="0" cellpadding="0" cellspacing="1">
	<tr>
		<th colspan="2" class="th" style="width: 300px;">
			{lang_edit_forwarding_address}
		</th>
	</tr>
	<tr>
		<td style="width: 300px;">
			{lang_forwarding_address}
		</td>
		<td>
			<input type="text" style="width: 100%;" name="forwardingAddress" value="{forwarding_address}">
		</td>
	</tr>
	<tr>
		<td>
			{lang_keep_local_copy}
		</td>
		<td>
			<input type="checkbox" name="keepLocalCopy" value="yes" {checked_keep_local_copy}>
		</td>
	</tr>
	<tr>
		<td colspan="2">
			&nbsp;
		</td>
	</tr>
	<tr>
		<td colspan="2">
			<input type="submit" name="save" value="{lang_save}">
			<input type="submit" name="cancel" value="{lang_cancel}">
		</td>
	</tr>
</table>
<form>
<!-- END main -->
