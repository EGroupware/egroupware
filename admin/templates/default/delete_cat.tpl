<!-- $Id$ -->

<!-- BEGIN form -->

<center>
<table border="0" width="65%" cellpadding="2" cellspacing="2">
	<tr>
		<td align="center" colspan=2>{messages}</td>
	</tr>
	<tr>
		<td align="center">{lang_modify_subs}</td>
		<td align="center">{modify_subs}</td>
	</tr>
	<tr>
		<td align="center">{lang_drop_subs}</td>
		<td align="center">{drop_subs}</td>
	</tr>
	<tr>
		<td align="center">
			<form method="POST" action="{action_url}">
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
