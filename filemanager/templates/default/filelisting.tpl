<!-- BEGIN filemanager_header -->
<form name="formfm" method="post" action="{form_action}">


{toolbar0}
<div id="fmMenu">
{toolbar1}
</div>
<div id="fmFileWindow">
{messages}			<table cellspacing="0" cellpadding="2">
				<tbody>
<!-- END filemanager_header -->

<!-- BEGIN column -->
  <td valign="top" style="padding-left:2px;padding-right:2px;">{col_data}&nbsp;</td>
<!-- END column -->

<!-- BEGIN row -->
	<tr bgcolor="{row_tr_color}">
		<td style="padding-left:2px;padding-right:2px;">{actions}</td>
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
