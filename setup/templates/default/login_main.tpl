<!-- begin login_main.tpl -->
<p>&nbsp;</p>
<table align="center" cellspacing="0" cellpadding="2" style="border: 1px solid #486591;">

{V_login_stage_header}

<tr class="th">
	<td colspan="2">&nbsp;<b>Header Admin Login</b></td>
</tr>
<tr class="row_on">
	<td colspan="2" class="msg" align="center">{HeaderLoginMSG}</td>
</tr>
<form action="manageheader.php" method="POST" name="admin">
<tr class="row_on">
	<td>
		Password:
	</td>
	<td>
		<input type="password" name="FormPW" value="">
	</td>
</tr>
<tr class="row_on">
	<td colspan="2">
		{lang_select}
		<input type="submit" name="Submit" value="Login">
		<input type="hidden" name="HeaderLogin" value="Login">
	</td>
</tr>
</form>

</table>
<!-- end login_main.tpl -->


