<!-- BEGIN message_main -->
<STYLE type="text/css">
        .subjectBold {
        	font-size: 110%;
        	font-weight : bold;
        	font-family : Arial;
        }

        .subject {
        	font-size: 110%;
        	font-family : Arial;
        }

        .body {
        	font-size: 110%;
        }
</STYLE>
<table border="0" cellpadding="1" cellspacing="0" width="100%" style="table-layout:fixed">

<tr style="background: white;">
	<td colspnan="2" style="font-weight:bold; text-align: center; font-size: 120%;">
		<a class="{row_css_class}" name="subject_url" href="{url_read_message}" target="{read_message_windowName}" title="{full_subject_data}">{subject_data}</a>
	</td>
</tr>
</table>
<div id="tabcontent1" class="activetab" bgcolor="white">
<table border="0" width="100%" cellspacing="0" cellpading="0" bgcolor="white" style="table-layout:fixed">
<tr>
	<td>
		&nbsp;
	</td>
</tr>
<tr>
	<td>
{header}
	</td>
</tr>
<tr>
	<td bgcolor="white">
<div class="body">
{body}
</div>
	</td>
</tr>
</table>
<table border="0" width="100%" cellspacing="0" cellpading="0" bgcolor="white" style="table-layout:fixed">
{attachment_rows}
</table>
</div>

<!-- END message_main -->

<!-- BEGIN message_attachement_row -->
<tr>
	<td align="left">
		{filename}
	</td> 
	<td align="center">
		{mimetype}
	</td>
	<td align="right">
		{size}
	</td>
</tr>
<!-- END message_attachement_row -->

<!-- BEGIN message_cc -->
<tr>
	<td width="100" style="font-weight:bold;">
		{lang_cc}:
	</td> 
	<td>
		{cc_data}
	</td>
</tr>
<!-- END message_cc -->

<!-- BEGIN message_org -->
<tr>
	<td width="100" style="font-weight:bold;">
		{lang_organisation}:
	</td> 
	<td>
		{organization_data}
	</td>
</tr>
<!-- END message_org -->

<!-- BEGIN message_onbehalfof -->
<tr>
	<td width="100" style="font-weight:bold;">
		{lang_on_behalf_of}:
	</td> 
	<td>
		{onbehalfof_data}
	</td>
</tr>
<!-- END message_onbehalfof -->

<!-- BEGIN message_header -->
<table border="0" cellpadding="1" cellspacing="0" width="100%" style="table-layout:fixed">

<table border="0" cellpadding="1" cellspacing="0" width="100%">
<tr cclass="row_on">
	<td style="text-align:left; width:120px; font-weight:bold;">
		{lang_from}:
	</td>
	<td style="font-weight:bold;">
		{from_data}
	</td>
</tr>

{on_behalf_of_part}

<tr cclass="row_off">
	<td style="font-weight:bold;">
		{lang_to}:
	</td> 
	<td>
		{to_data}
	</td>
</tr>

{cc_data_part}

<tr cclass="row_on">
	<td style="font-weight:bold;">
		{lang_date}:
	</td> 
	<td>
		{date_data}
	</td>
</tr>

</table>
<br>
<!-- END message_header -->

<!-- BEGIN previous_message_block -->
<a class="head_link" href="{previous_url}">{lang_previous_message}</a>
<!-- END previous_message_block -->

<!-- BEGIN next_message_block -->
<a class="head_link" href="{next_url}">{lang_next_message}</a>
<!-- END next_message_block -->
