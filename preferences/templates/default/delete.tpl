<!-- $Id$ -->

<!-- BEGIN form -->

	<center>
		<table border="0" width="65%" cellpadding="2" cellspacing="2">
		<form method="POST" action="{action_url}">
			<tr>
				<td align="center"><font face="{font}">{deleteheader}</font></td>
			</tr>
			<tr>
				<td align="center">{lang_subs}</td>
				<td align="center">{subs}</td>
			</tr>
			<tr>
				<td align="center"><font face="{font}">
					{hidden_vars}
					<input type="submit" name="confirm" value="{lang_yes}"></font></td>
					</form>
				<td align="center"><font face="{font}"><a href="{nolink}">{lang_no}</a></font></td>
			</tr>
		</table>
	</center>

<!-- END form -->
