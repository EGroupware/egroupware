<!-- begin login_stage_header.tpl -->
<tbody>
<tr class="th">
		<td colspan="2" bgcolor="#cccccc">
			&nbsp;<strong>Setup/Config Admin Login</strong>
		</td>
	</tr>
	<tr class="row_on">
		<td colspan="2" class="msg" align="center">{ConfigLoginMSG}</td>
	</tr>
	<tr class="row_on">
		<td colspan="2">
			<form action="index.php" method="post" name="config">
			<!-- BEGIN B_multi_domain -->
				<table>
				<tr>
					<td>Domain:</td>
					<td colspan="2">
						<select name="FormDomain">{domains}</select>
					</td>
				</tr>
				<tr>
					<td>Config Password:</td>
					<td>
						<input type="password" name="FormPW" value="">
					</td>
					<td>
						{lang_select}
					</td>
				</tr>
				</table>
			<!-- END B_multi_domain -->
			<!-- &nbsp; stupid seperator -->
		<!-- BEGIN B_single_domain -->
				<table>
				<tr>
					<td>Config Password:</td>
					<td>
						<input type="password" name="FormPW" value="">
					</td>
					<td>
						{lang_select}
					</td>
				</table>
				<input type="hidden" name="FormDomain" value="{default_domain_zero}">
		<!-- END B_single_domain -->

				<input type="hidden" name="ConfigLogin" value="Login">
				<input type="submit" name="submit" value="Login">
			</form>
		</td>
	</tr>
	<!-- end login_stage_header.tpl -->
