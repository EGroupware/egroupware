<!-- begin login_stage_header.tpl -->
<tr bgcolor="#486591">
	<td colspan="2">
		<font color="#fefefe">&nbsp;<b>Setup/Config Admin Login</b></font>
	</td>
</tr>
<tr bgcolor="#e6e6e6">
	<td colspan="2">
		<font color="#ff0000">{ConfigLoginMSG}</font>
	</td>
</tr>
<tr bgcolor="#e6e6e6">
	<td>
		<form action="index.php" method="POST" name="config">
		<!-- BEGIN B_multi_domain -->
		<table>
		<tr>
			<td>
				Domain: 
			</td>
			<td>
				<input type="text" name="FormDomain" value="">
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
