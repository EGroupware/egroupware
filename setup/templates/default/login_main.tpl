<!-- begin login_main.tpl -->
<!--<p>To enter the setup please login with the setup-password.</p>-->
<!--<table align="center" cellspacing="0" cellpadding="5" style="border: 1px solid rgb(72, 101, 145);" width="430">-->
<table align="center" cellspacing="0" cellpadding="5" style="border: 0px solid rgb(72, 101, 145);" width="450">
{V_login_stage_header}
	<tr class="row_on" >
	<td colspan="2">&nbsp;</strong></td>
</tr>
<tr class="th">
		<td bgcolor="#cccccc" colspan="2">&nbsp;<strong>{lang_header_login}</strong></td>
	</tr>
	<tr class="row_on">
		<td colspan="2" class="msg" align="center">{HeaderLoginMSG}</td>
	</tr>
	<tr class="row_on">
		<td>
	<form action="manageheader.php" method="post" name="admin">
			{lang_header_username}:
			<input type="text" name="FormUser" value="">
			{lang_select}
		</td>
	</tr>
	<tr class="row_on">
		<td>
			{lang_header_password}:
			<input type="password" name="FormPW" value="">
			<input type="submit" name="Submit" value="Login">
			<input type="hidden" name="HeaderLogin" value="Login">
	</form>
		</td>
	</tr>

</tbody>
</table>
<!-- end login_main.tpl -->
