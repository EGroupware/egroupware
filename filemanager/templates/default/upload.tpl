<!-- BEGIN upload_header -->
<form method="post" action="{form_action}" enctype="multipart/form-data">
<div>
<table>
<tbody>
	<tr bgcolor="{row_tr_color}">
		<td><strong>{lang_file}</strong></td>
		<td><strong>{lang_comment}</strong></td>
	</tr>
<!-- END upload_header -->

<!-- BEGIN row -->
	<tr bgcolor="{row_tr_color}">
	<td><input maxlength="255" name="upload_file[]" type="file"></td>
	<td><input name="upload_comment[]" type="text"></td>
	</tr>
<!-- END row -->

<!-- BEGIN upload_footer -->

</tbody></table>
<input value="true" name="uploadprocess" type="hidden">
<input value="{path}" name="path" type="hidden">
<input value="{num_upload_boxes}" name="show_upload_boxes" type="hidden">
<input value="{lang_upload}" name="upload_files" type="submit">
<br/>
{change_upload_boxes}
</div>
</form>
<!-- END upload_footer -->
