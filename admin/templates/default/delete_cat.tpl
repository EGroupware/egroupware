<!-- $Id$ -->

<!-- BEGIN form -->

<center>
{error_msg}
<table border="0" width="65%" cellpadding="2" cellspacing="2">
<form method="POST" action="{action_url}">
	<tr>
		<td align="center" colspan=2>{messages}</td>
	</tr>
	<tr>
		<td align="center">{sub_select}</td>
	</tr>
	<tr>
		<td align="center">
				<input type="submit" name="confirm" value="{lang_yes}"></td>
			</form>
		</td>
		<td align="center">
			<form method="POST" action="{nolink}">
				<input type="submit" name="cancel" value="{lang_no}"></td>
			</form>
		</td>
	</tr>
</table>
</center>

<!-- END form -->
