<!-- BEGIN form -->
<script language="JavaScript1.2">

var langAddAddress="{lang_enter_new_address}";
var langModifyAddress="{lang_update_current_address}";

</script>
 <form method="POST" action="{form_action}">
  <center>
	<table border="0" width="95%">
		<tr>
			<td valign="top">
					{rows}
			</td>
			<td>
				<table border="0" width="100%" cellspacing="0" cellpadding="2">
					<tr bgcolor="{th_bg}">
						<td colspan="2">
							<b>{lang_email_config}</b>
						</td>
						<td align="right">
							{lang_emailaccount_active}
							<input type="checkbox" name="accountStatus" {account_checked}>
						</td>
					</tr>
					<tr bgcolor="{tr_color1}">
						<td width="200">{lang_emailAddress}</td>
						<td colspan="2">
							<input name="mailLocalAddress" value="{mailLocalAddress}" style="width:350px;">
						</td>
					</tr>




					<tr bgcolor="{tr_color2}">
						<td>{lang_mailAlternateAddress}</td>
						<td align="center" style="width:350px;">
								{selectbox_mailAlternateAddress}
						</td>
						<td align="left">
							<button type="button" onclick="addRow('mailAlternateAddress', langAddAddress)"><img src="{url_image_add}" alt="{lang_add}" title="{lang_add}"></button><br>
							<button type="button" onclick="editRow('mailAlternateAddress', langModifyAddress)"><img src="{url_image_edit}" alt="{lang_edit}" title="{lang_edit}"></button><br>
							<button type="button" onclick="removeRow('mailAlternateAddress')"><img src="{url_image_delete}" alt="{lang_remove}" title="{lang_remove}"></button>
						</td>
					</tr>


					
					<tr bgcolor="{tr_color1}">
						<td>{lang_mailRoutingAddress}</td>
						<td align="center">
								{selectbox_mailRoutingAddress}
						</td>
						<td align="left">
							<button type="button" onclick="addRow('mailRoutingAddress', langAddAddress)"><img src="{url_image_add}" alt="{lang_add}" title="{lang_add}"></button><br>
							<button type="button" onclick="editRow('mailRoutingAddress', langModifyAddress)"><img src="{url_image_edit}" alt="{lang_edit}" title="{lang_edit}"></button><br>
							<button type="button" onclick="removeRow('mailRoutingAddress')"><img src="{url_image_delete}" alt="{lang_remove}" title="{lang_remove}"></button>
						</td>
					</tr>

					<tr bgcolor="{tr_color2}">
						<td>
							{lang_forward_only}
						</td>
						<td colspan="2">
							<input type="checkbox" name="forwardOnly" {forwardOnly_checked}>
						</td>
					</tr>

					<tr>
						<td colspan="3">
							&nbsp;
						</td>
					</tr>
					<tr bgcolor="{th_bg}">
						<td colspan="3">
							<b>{lang_quota_settings}</b>
						</td>
					</tr>
					<tr bgcolor="{tr_color2}">
						<td width="200">{lang_qoutainmbyte}</td>
						<td colspan="2">
							<input name="quotaLimit" value="{quotaLimit}" style="width:350px;"> ({lang_0forunlimited})
						</td>
					</tr>
					<tr>
						<td colspan="3">
							&nbsp;
						</td>
					</tr>
				</table>
				<table border=0 width=100%>
					<tr bgcolor="{tr_color1}">
						<td align="right" colspan="2">
							<input type="submit" name="save" value="{lang_button}" onclick="selectAllOptions('mailAlternateAddress'); selectAllOptions('mailRoutingAddress');">
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
  </center>
 </form>
<!-- END form -->

<!-- BEGIN link_row -->
					<tr bgcolor="{tr_color}">
						<td colspan="2">&nbsp;&nbsp;<a href="{row_link}">{row_text}</a></td>
					</tr>
<!-- END link_row -->
