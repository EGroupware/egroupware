<!-- BEGIN form -->
 <form method="POST" action="{form_action}">
  <center>
	<table border="0" width="95%">
		<tr>
			<td valign="top">
					{rows}
			</td>
			<td>
				<table border=0 width=100% cellspacing="1" cellpadding="2">
					<tr bgcolor="{th_bg}">
						<td colspan="2" style="padding-left:10px; text-align: left">
							<b>{lang_email_config}<img src="{info_icon}" border="0" onMouseOver="this.T_TITLE='Info:'; this.T_WIDTH=250; return escape('<p>{lang_info_UsageHints}</p>')" /></b>
						</td>
						<td align="right">
							<input type="checkbox" name="accountStatus" {account_checked}>
							{lang_emailaccount_active}<img src="{info_icon}" border="0" onMouseOver="this.T_TITLE='Info:'; this.T_WIDTH=250; return escape('<p>{lang_info_AccountActive}</p>')" />
						</td>
					</tr>
					<tr bgcolor="{tr_color1}">
						<td style="padding-left:10px; text-align: left">{lang_masterEmailAddress}<img src="{info_icon}" border="0" onMouseOver="this.T_TITLE='Info:'; this.T_WIDTH=250; return escape('<p>{lang_info_masterEmailAddress}</p>')" /></td>
						<td colspan="2" style="text-align: left"><input name="mail" value="{mail}" size=35></td>
					</tr>
					<tr bgcolor="{tr_color2}">
						<td rowspan="4" style="padding-left:10px; vertical-align: top; text-align: left;">{lang_mailAliases}<img src="{info_icon}" border="0" onMouseOver="this.T_TITLE='Info:'; this.T_WIDTH=250; return escape('<p>{lang_info_mailAliases}</p>')" /></td>
						<td rowspan="4" style="text-align: left">{options_mailAlternateAddress}</td>
						<td align="center">
							<input type="submit" value="{lang_remove} -->" name="remove_mailAlternateAddress">
						</td>
					</tr>
					<tr bgcolor="{tr_color1}">
						<td>
							&nbsp;
						</td>
					</tr>
					<tr bgcolor="{tr_color2}">
						<td align="center">
							<input name="mailAlternateAddressInput" value="" size=35>
						</td>
					</tr>
					<tr bgcolor="{tr_color2}">
						<td align="center">
							<input type="submit" value="<-- {lang_add}" name="add_mailAlternateAddress">
						</td>
					</tr>

					<tr bgcolor="{tr_color1}">
						<td style="padding-left:10px; text-align: left">
							{lang_RouteMailsTo}<img src="{info_icon}" border="0" onMouseOver="this.T_TITLE='Info:'; this.T_WIDTH=250; return escape('<p>{lang_info_RouteMailsTo}</p>')" />
						</td>
						<td colspan="2" style="text-align: left">
							<input name="mailForwardingAddress" value="{mailForwardingAddress}" size=35>
						</td>
					</tr>
					<tr>
						<td colspan="3">
							&nbsp;
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
							<input type="submit" name="save" value="{lang_button}">
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
