<!-- begin db_backup.tpl -->
<p align="center"><font color="red">{error_msg}</font></p>

<script language="JavaScript1.2">

Array.prototype.contains = function(value)
{
	for(var i = 0;i < this.length;i++)
		if(this[i] == value)
			return(true);
	return(false);
}
function Numsort(a,b)
{
	return a-b;
}

function sort_table(id)
{
	var sortedby = document.getElementById('sortedby').value;
	var table = document.getElementById('files_table');
	var rows = table.rows;
	var l = rows.length;
	if(l < 2) return;
	var sort_columns = Array();
	var rows_content = Array();
	for(var i = 1;i < l;i++)
	{
		var value = rows[i].cells[id].innerHTML.toUpperCase();
		if (id == 2) 
		{
			start = value.search(/\(/)+1;
			stop = value.search(/\)/);
			value = value.substring(start,stop);
		}
		var index = 0;
		while(sort_columns.contains(value + (index != 0 ? '_' + index : '')))
			index++;
		value = value + (index != 0 ? '_' + index : '');
		sort_columns[i - 1] = value;
		rows_content[value] = rows[i].innerHTML;
	}
	if (id == 2) 
	{
		sort_columns.sort(Numsort);
	}
	else
	{
		sort_columns.sort();
	}
	if (sortedby == id) 
	{
		sort_columns.reverse();
		document.getElementById('sortedby').value = -1;
	}
	else
	{
		document.getElementById('sortedby').value = id;
	}
	for(var i = 1;i < l;i++)
		table.deleteRow(-1);
	for(var i = 1;i < l;i++)
	{
		var new_row = table.insertRow(-1);
		new_row.align = 'center';
		new_row.innerHTML = rows_content[sort_columns[i - 1]];
	}
}

</script>

<form method="post" name="backup_form" action="{self}" enctype="multipart/form-data">
<input name="sortedby" id="sortedby" type="hidden" />
<table border="0" align="center" width="98%" cellpadding="5">
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
			<b>{lang_scheduled_backups}</b>
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
<!-- BEGIN schedule_row -->
				<tr align="center">
					<td>{year}</td>
					<td>{month}</td>
					<td>{day}</td>
					<td>{dow}</td>
					<td>{hour}</td>
					<td>{min}</td>
					<td>{next_run}</td>
					<td>{actions}</td>
				</tr>
<!-- END schedule_row -->
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
	<tr bgcolor="#e6e6ee">
		<td>
			{lang_backup_cleanup}
		</td>
		<td align="right">
		 {lang_backup_mincount} {backup_mincount}
		</td>
	</tr>
	<tr bgcolor="#e6e6e6">
		<td>
			 {lang_backup_files_info}
		</td>
		<td align="right">
			{lang_backup_files} {backup_files}
		</td>
	</tr>
	<tr bgcolor="#e6e6ee">
		<td colspan="2" align="right">
			{backup_save_settings}
		</td>
	</tr>
	<tr bgcolor="#e6e6e6">
		<td colspan="2">
			<table id="files_table" style="border: 1px solid black; border-collapse: collapse;" border="1" width="100%">
				<tr align="center">
					<td><a href="#" onClick="sort_table(0);">{lang_filename}</a></td>
					<td><a href="#" onClick="sort_table(1);">{lang_mod}</a></td>
					<td><a href="#" onClick="sort_table(2);">{lang_size}</a></td>
					<td>{lang_actions}</td>
				</tr>
<!-- BEGIN set_row -->
				<tr align="center">
					<td>{filename}</td>
					<td>{mod}</td>
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
