<!-- $Id$ -->

<!-- BEGIN form -->

<center>
{error_msg}
<table border="0" width="65%" cellpadding="2" cellspacing="2">
<form method="POST" action="{action_url}">
	<tr>
		<td align="center" colspan="2">{delete_msg}</td>
	</tr>
	<tr>
		<td align="center" colspan="2">{sub_select}</td>
	</tr>
	<tr>
		<td>
			<input type="submit" name="confirm" value="{lang_yes}">
			</form>
		</td>
		<td align="right">
			<form method="POST" action="{nolink}">
				<input type="submit" name="cancel" value="{lang_no}">
			</form>
		</td>
	</tr>
</table>
</center>

<!-- END form -->
