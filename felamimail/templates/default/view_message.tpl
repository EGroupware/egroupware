<!-- BEGIN message_main -->
<script language="JavaScript1.2">
var lang_sendnotify = "{lang_sendnotify}";
self.focus();
</script>
<!-- {print_navbar} -->
<div id="navbarDIV">
        {navbar}
</div>
<div id="subjectDIV" style="border: 0px solid green; margin:0px; padding:0px; left:0px;">
	<span id="subjectDATA" style="padding-left:2px; font-size: 110%;">{subject_data}</span>
</div>
<div id="headerDIV">
	{header}
</div>
<div class="bodyDIV" id="bodyDIV" style="border: 0px solid green; margin:0px; padding:0px; left:0px;">
	<iframe id="messageIFRAME" frameborder="no" scrolling="auto" src="{url_displayBody}">
	</iframe>
</div>
<script type="text/javascript">
{sentNotify}
</script>
<!-- END message_main -->

<!-- BEGIN message_main_attachment -->
<script language="JavaScript1.2">
self.focus();
</script>
<!-- {print_navbar} -->
<div id="navbarDIV">
        {navbar}
</div>
<div id="subjectDIV" style="border: 0px solid green; margin:0px; padding:0px; left:0px;">
	<span id="subjectDATA" style="padding-left:2px; font-size: 110%;">{subject_data}</span>
</div>
<div id="headerDIV">
	{header}
</div>
<div class="bodyDIV bodyDIVAttachment" id="bodyDIV" style="border: 0px solid green; margin:0px; padding:0px; left:0px; {attachment_div_height}">
	<iframe frameborder="no" scrolling="auto" style="width:100%; height:100%;" src="{url_displayBody}">
	</iframe>
</div>
<div id="attachmentDIV" style="border: 0px solid green; margin:0px; padding:0px; left:0px;">
<table border="0" width="100%" cellspacing="0">
{attachment_rows}
</table>
</div>
<!-- END message_main_attachment -->

<!-- BEGIN message_raw_header -->
<tr>
	<td bgcolor="white">
		<pre><font face="Arial" size="-1">{raw_header_data}</font></pre>
	</td>
</tr>
<!-- END message_raw_header -->

<!-- BEGIN message_navbar -->
<table border="0" cellpadding="0" cellspacing="0" width="100%">
	<tr class="navbarBackground">
		<td width="250px">
			<div class="parentDIV">
				{navbarButtonsLeft}
			</div>
		</td>
		<td>
			&nbsp;
		</td>
		<td width="60px">
			<div class="parentDIV">
				{navbarButtonsRight}
			</div>
		</td>
	</tr>
</table>
<!-- END message_navbar -->

<!-- BEGIN message_navbar_print -->
<html>
<body onload="javascript:window.print()">
<!-- END message_navbar_print -->

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

<!-- BEGIN message_cc -->
<tr>
	<td width="100" style="font-weight:bold; font-size:10px;">
		{lang_cc}:
	</td>
	<td style="font-size:10px;" colspan="3">
		{cc_data}
	</td>
</tr>
<!-- END message_cc -->

<!-- BEGIN message_bcc -->
<tr>
    <td width="100" style="font-weight:bold; font-size:10px;">
        {lang_bcc}:
    </td>
    <td style="font-size:10px;" colspan="3">
        {bcc_data}
    </td>
</tr>
<!-- END message_bcc -->

<!-- BEGIN message_onbehalfof -->
<tr>
	<td width="100" style="font-weight:bold; font-size:10px; vertical-align:top;">
		{lang_on_behalf_of}:
	</td>
	<td style="font-size:10px;" colspan="3">
		{onbehalfof_data}
	</td>
</tr>
<!-- END message_onbehalfof -->

<!-- BEGIN message_header -->
<table border="0" cellpadding="1" cellspacing="0" width="100%" style="padding-left:2px;" id="headerTable">
<tr>
	<td style="text-align:left; width:100px; font-weight:bold; font-size:10px;">
		{lang_from}:
	</td>
	<td style="font-size:10px;" colspan="2">
		{from_data}
	</td>
	<td style="font-size:10px;" align="right">
		<div id="moreDIV" onclick="toggleHeaderSize();" style="display:none; border:1px dotted black; width:10px; height:10px; line-height:10px; text-align:center; cursor: pointer;">
			<span id="toogleSPAN">+</span>
		</div>
	</td>
</tr>

{on_behalf_of_part}

<tr>
	<td style="font-weight:bold; font-size:10px;">
		{lang_date}:
	</td>
	<td style="font-size:10px;" colspan="3">
		{date_received}
	</td>
</tr>

<tr>
	<td style="font-weight:bold; font-size:10px; vertical-align:top;">
		{lang_to}:
	</td>
	<td style="font-size:10px;" colspan="3">
		{to_data}
	</td>
</tr>

{cc_data_part}
{bcc_data_part}
</table>
<!-- END message_header -->

<!-- BEGIN previous_message_block -->
<a href="{previous_url}">{lang_previous_message}</a>
<!-- END previous_message_block -->

<!-- BEGIN next_message_block -->
<a href="{next_url}">{lang_next_message}</a>
<!-- END next_message_block -->
