<!-- begin login_stage_header.tpl -->
<tr class="th">
	<td colspan="2">
		&nbsp;<b>Setup/Config Admin Login</b>
	</td>
</tr>
<tr class="row_on">
	<td colspan="2" class="msg" align="center">{ConfigLoginMSG}</td>
</tr>
<tr class="row_on">
	<td colspan="2">
		<form action="index.php" method="POST" name="config">
		<!-- BEGIN B_multi_domain -->
		<table>
		<tr>
			<td>
				Domain: 
			</td>
			<td>
				<select name="FormDomain">{domains}</select>
			</td>
		</tr>
		<tr>
			<td>
				Password:
			</td>
			<td>
				<input type="password" name="FormPW" value="">
			</td>
		</tr>
		</table>
		<!-- END B_multi_domain -->

		<!-- &nbsp; stupid seperator -->

		<!-- BEGIN B_single_domain -->
		<input type="password" name="FormPW" value="">
		<input type="hidden" name="FormDomain" value="{default_domain_zero}">
		<!-- END B_single_domain -->
{lang_select}
		<input type="hidden" name="ConfigLogin" value="Login">
		<input type="submit" name="submit" value="Login">
		</form>
	</td>
</tr>
<!-- end login_stage_header.tpl -->

