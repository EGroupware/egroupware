<!-- BEGIN list -->
<p>

<div align="center">
	<table border="0" width="95%">
		<tr>
  			{rows}
			<td>
				<div align="center">
					<table border="0" width="100%">
						<tr valign="bottom">
							<td align="left" colspan="2">
								{lang_last_x_logins}
							</td>
							<td align="center" colspan="2">
								{showing}
							</td>
							<td align="right">
								<table border="0">
									<tr>
										{nextmatchs_left}
										<td>&nbsp;</td>
										{nextmatchs_right}
									</tr>
								</table>
							</td>
						</tr>
						<tr bgcolor="{th_bg}">
							<td width="10%">{lang_loginid}</td>
							<td width="15%">{lang_ip}</td>
							<td width="20%">{lang_login}</td>
							<td width="30%">{lang_logout}</td>
							<td>{lang_total}</td>
						</tr>
						{rows_access}
						<tr bgcolor="{bg_color}">
							<td colspan="5" align="left">{footer_total}</td>
						</tr>
						<tr bgcolor="{bg_color}">
							<td colspan="5" align="left">{lang_percent}</td>
						</tr>
					</table>
				</div>
			</td>
		</tr>
	</table>
</div>
<!-- END list -->

<!-- BEGIN row -->
	<tr bgcolor="{tr_color}">
		<td>{row_loginid}</td>
		<td>{row_ip}</td>
		<td>{row_li}</td>
		<td>{row_lo}&nbsp;</td>
		<td>{row_total}&nbsp;</td>
	</tr>
<!-- END row -->

<!-- BEGIN row_empty -->
	<tr bgcolor="{tr_color}">
		<td align="center" colspan="5">{row_message}</td>
	</tr>
<!-- END row_empty -->
