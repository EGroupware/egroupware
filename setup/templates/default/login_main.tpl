<!-- begin login_main.tpl -->
<p>&nbsp;</p>
<table border="0" align="center">

{V_login_stage_header}

<tr bgcolor="#486591">
	<td colspan="2">
		<font color="#fefefe">&nbsp;<b>Header Admin Login</b></font>
	</td>
</tr>
<tr bgcolor="#e6e6e6">
	<td colspan="2">
		<font color="#ff0000">{HeaderLoginMSG}</font>
	</td>
</tr>
<tr bgcolor="#e6e6e6">
	<td>
		<form action="manageheader.php" method="POST" name="admin">
		<input type="password" name="FormPW" value="">
		<input type="hidden" name="HeaderLogin" value="Login">
		<input type="submit" name="Submit" value="Login">
		</form>
	</td>
</tr>

</table>
<!-- end login_main.tpl -->
