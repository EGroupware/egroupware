<!-- begin db_backup.tpl -->
<p align="center"><font color="red">{error_msg}</font></p>

<form method="POST" action="{self}" enctype="multipart/form-data">
<table border="0" align="center" width="90%" cellpadding="5">
<!-- BEGIN setup_header -->
	<tr bgcolor="#486591">
		<td colspan="2">
			&nbsp;<font color="#fefefe"><b>{stage_title}</b></font>
		</td>
	</tr>
	<tr bgcolor="#e6e6e6">
		<td colspan="2">
			{stage_desc}
		</td>
	</tr>
<!-- END setup_header -->
	<tr bgcolor="#e6e6e6">
		<td>
			<b>{lang_sheduled_backups}</b>
		</td>
		<td align="right">
			{backup_now_button}
		</td>
	</tr>
	<tr bgcolor="#e6e6e6">
		<td colspan="2">
			<table style="border: 1px solid black; border-collapse: collapse;" border="1" width="100%">
				<tr align="center">
					<td>{lang_year}</td>
					<td>{lang_month}</td>
					<td>{lang_day}</td>
					<td>{lang_dow}</td>
					<td>{lang_hour}</td>
					<td>{lang_minute}</td>
					<td>{lang_next_run}</td>
					<td>{lang_actions}</td>
				</tr>
<!-- BEGIN shedule_row -->
				<tr align="center">
					<td>{year}</td>
					<td>{month}</td>
					<td>{day}</td>
					<td>{dow}</td>
					<td>{hour}</td>
					<td>{minute}</td>
					<td>{next_run}</td>
					<td>{actions}</td>
				</tr>
<!-- END shedule_row -->
			</table>
		</td>
	</tr>
	<tr bgcolor="#e6e6e6">
		<td>
			<b>{lang_backup_sets}</b> {backup_dir}
		</td>
		<td align="right">
		 {upload}
		</td>
	</tr>
	<tr bgcolor="#e6e6e6">
		<td colspan="2">
			<table style="border: 1px solid black; border-collapse: collapse;" border="1" width="100%">
				<tr align="center">
					<td>{lang_filename}</td>
					<td>{lang_date}</td>
					<td>{lang_size}</td>
					<td>{lang_actions}</td>
				</tr>
<!-- BEGIN set_row -->
				<tr align="center">
					<td>{filename}</td>
					<td>{date}</td>
					<td>{size}</td>
					<td>{actions}</td>
				</tr>
<!-- END set_row -->
			</table>
		</td>
	</tr>
</table>
</form>

<!-- end db_backup.tpl -->
