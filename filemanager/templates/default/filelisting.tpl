<!-- BEGIN filemanager_header -->
<form method="post" action="{form_action}">
<br/>
{messages}
<br/>

{toolbar0}
<div id="fmMenu">
{toolbar1}
</div>
<div id="fmFileWindow">
			<table>
				<tbody>
<!-- END filemanager_header -->

<!-- BEGIN column -->
  <td valign="top">{col_data}&nbsp;</td>
<!-- END column -->

<!-- BEGIN row -->
	<tr bgcolor="{row_tr_color}">
		<td>{actions}</td>
		{columns}
	</tr>
<!-- END row -->

<!-- BEGIN filemanager_footer -->
{lang_no_files}
</tbody></table>
						</div>

<div id="fmStatusBar"><b>{lang_files_in_this_dir}:</b> {files_in_this_dir} <b>{lang_used_space}: </b> {used_space}</div>
</form>
<!-- END filemanager_footer -->
