<!-- BEGIN file_edit_header -->
<!-- END file_edit_header -->

<!-- BEGIN column -->
<!-- END column -->

<!-- BEGIN row -->

{preview_content}<br/>
<form method="post" action="{form_action}">
	<input type="hidden" name="edit" value="1" />
	<input type="hidden" name="edit_file" value="{edit_file}" />
	{filemans_hidden}
	<textarea name="edit_file_content" rows="10" cols="50">{file_content}</textarea>
<!--			</td>
		</tr>
		<tr>
			<td align=center>-->
				<!--<input type="submit" name="edit_preview" value="{lang_preview}" />-->
				<!--<input type="submit" name="edit_save" value="{lang_save}" />-->
	<table>
	<tr>
{buttonSave} {buttonPreview} {buttonDone} {buttonCancel}

</tr>
</table>

</form>
<!-- END row -->

<!-- BEGIN file_edit_footer -->
<!-- END file_edit_footer -->

