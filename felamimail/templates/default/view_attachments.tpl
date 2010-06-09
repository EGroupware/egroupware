<!-- BEGIN message_main_attachment -->
<script language="JavaScript1.2">
self.focus();
</script>
<div style="border: 0px solid green; margin:0px; padding:0px; left:0px; background-color:#ffffff; height:20px;width:100%;font-weight:bold;text-align:left;line-height:20px;">
    <span id="subjectDATA" style="padding-left:2px; font-size: 110%;">{subject_data}</span>
</div>
<div  style="border: 0px solid green; margin:0px; padding:0px; left:0px;">
<table border="0" width="100%" cellspacing="0">
{attachment_rows}
</table>
</div>
<!-- END message_main_attachment -->

<!-- BEGIN message_attachement_row -->
<tr>
	<td valign="top" width="40%">
		<a href="#" onclick="{link_view} return false;">
		<b>{filename}</b><a>
	</td>
	<td align="left">
		{mimetype}
	</td>
	<td align="right">
		{size}
	</td>
	<td width="10%">&nbsp;</td>
	<td width="10%" align="left">
		<a href="{link_save}">{url_img_save}</a>
		{vfs_save}
	</td>
</tr>
<!-- END message_attachement_row -->

