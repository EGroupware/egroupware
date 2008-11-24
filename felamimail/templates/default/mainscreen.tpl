<!-- BEGIN main -->
<script language="JavaScript1.2">
var sURL = unescape(window.location.pathname);

// some translations needed for javascript functions

var movingMessages		= '{lang_moving_messages_to}';
var lang_askformove			= '{lang_askformove}';
var prefAskForMove			= '{prefaskformove}';

var lang_emptyTrashFolder	= '{lang_empty_trash}';
var lang_compressingFolder	= '{lang_compress_folder}';
var lang_select_target_folder	= '{lang_select_target_folder}';
var lang_updating_message_status = '{lang_updating_message_status}';
var lang_loading 		= '{lang_loading}';
var lang_deleting_messages 	= '{lang_deleting_messages}';
var lang_skipping_forward 	= '{lang_skipping_forward}';
var lang_skipping_previous 	= '{lang_skipping_previous}';
var lang_jumping_to_end 	= '{lang_jumping_to_end}';
var lang_jumping_to_start 	= '{lang_jumping_to_start}';
var lang_updating_view 		= '{lang_updating_view}';

var activityImagePath		= '{ajax-loader}';

// how many row are selected currently
var checkedCounter=0;

// the refreshtimer objects
var aktiv;
var fm_timerFolderStatus;

// refresh time for mailboxview
var refreshTimeOut = {refreshTime};
//refreshTimeOut = 105001;
//document.title=refreshTimeOut;

fm_startTimerFolderStatusUpdate(refreshTimeOut);
fm_startTimerMessageListUpdate(refreshTimeOut);

</script>

<TABLE WIDTH="100%" CELLPADDING="0" CELLSPACING="0" bborder="1" style="border: solid #aaaaaa 1px; border-right: solid black 1px; border-bottom: solid black 1px;">
	<tr class="navbarBackground">
		<td align="right" width="180px">
			<div class="parentDIV">
				{navbarButtonsLeft}
			</div>
		</td>
		<td align="right" width="90px">
			{select_search}
		</td>
		<td align="right">
			<input class="input_text" type="text" name="quickSearch" id="quickSearch" value="{quicksearch}" onChange="javascript:quickSearch();" onFocus="this.select();" style="font-size:11px; width:100%;">
		</td>
		<td align="left" width="40px" valign="middle">
			{img_clear_left}
		</td>
		<td align="left" width="40px" valign="middle">
			{lang_status}
		</td>
		<td align="center" width="100px">
			{select_status}
		</td>
		<td width="120px" style="white-space:nowrap; align:right; text-align:right;">
			<div class="parentDIV" style="text-align:right; align:right;">
				{navbarButtonsRight}
			</div>
		</td>
	</TR>
</table>
<form method="post" name="mainView" id="mainView" action="{reloadView}">
</form>
<TABLE  width="100%" cellpadding="0" cellspacing="0" border="0" style="height:100px;">
		<input type="hidden" name="folderAction" id="folderAction" value="changeFolder">
		<INPUT TYPE=hidden NAME="oldMailbox" value="{oldMailbox}">
		<INPUT TYPE=hidden NAME="mailbox">

	<tr style="height: 20px;">
		<td>

			<span id="folderFunction" align="left" style="font-size:11px;">&nbsp;</span>	
		</td>
		<td>
			&nbsp;
		</td>
		<td align="left" style="font-size:11px; width:auto;">
			<span id="messageCounter">{message}</span>
		</td>
		<td align="center" style="font-size:11px; color:red; width:180px;">
			<span id="vacationWarning">{vacation_warning}</span>
		</td>
		<td id="quotaDisplay" align="right" style="font-size:11px; width:180px;">
			{quota_display}
		</td>
	</tr>
	<TR>
		<td valign="top" class="folderlist" width="180">
			<!-- StartAccountSelector -->
			<span id="accountSelect" align="left" style="font-size:11px;">{accountSelect}</span>	
			<!-- StartFolderTree -->
			<div id="divFolderTree" style="overflow:auto; width:180px; height:458px; margin-bottom: 0px;padding-left: 0px; padding-top:0px; z-index:100; border : 1px solid Silver;">
			</div>
			{folder_tree}
			<script language="JavaScript1.2">refreshFolderStatus();</script>
		</td>
		<td width="10" valign="middle">
			<div id="vr" align="center">
				::
			</div>
		</td>
		
		<!-- ToDo: ResizeVerticalRule -->		
		
		<TD valign="top" colspan="3">

			<!-- Start Header MessageList -->

			{messageListTableHeader}

			<!-- End Header MessageList -->			


			<!-- Start MessageList -->

			<form name="formMessageList" id="formMessageList">			
			<div id="divMessageList" style="overflow:auto; height:460px; margin-left:0px; margin-right:0px; margin-top:0px; margin-bottom: 0px; z-index:90; border : 1px solid Silver;">
				<!-- <table BORDER="0" style="width:98%; ppadding-left:2; table-layout: fixed;" cellspacing="100" cellpadding="100"> -->
					{header_rows}
				<!-- </table> -->
			</div>
			</form>

			<!-- End MessageList -->

		</TD>
	</TR>
</table>

<!-- END main -->

<!-- BEGIN message_table -->
<table BORDER="0" style="width:98%; padding-left:2; table-layout: fixed;" cellspacing="0">
	{message_rows}
</table>
<!-- END message_table -->

<!-- BEGIN status_row_tpl -->
<table WIDTH="100%" BORDER="0" CELLPADDING="1" CELLSPACING="2">
				<tr BGCOLOR="{row_off}" class="text_small">
					<td width="18%">
						{link_previous}
					</td>
					<td width="10%">&nbsp;
						
					</td>
					<TD align="center" width="36%">
						{message}
					</td>
					<td width="18%">
						{trash_link}
					</td>
					<td align="right" width="18%">
						{link_next}
					</td>
				</tr>
			</table>


<!-- END status_row_tpl -->

<!-- BEGIN header_row_felamimail -->
	<tr id="row_{message_uid}" class="{row_css_class}" onMouseOver="style.backgroundColor='#dddddd';" onMouseOut="javascript:style.backgroundColor='#FFFFFF';">
		<td class="mainscreenRow" width="20px" align="left" valign="top">
			<input  style="width:12px; height:12px; border: none; margin: 1px;" class="{row_css_class}" type="checkbox" id="msgSelectInput" name="msg[]" value="{message_uid}" 
			onclick="toggleFolderRadio(this, refreshTimeOut)" {row_selected}>
		</td>
		<td class="mainscreenRow" width="20px" align="center">
			{image_url}
		</td>
		<td class="mainscreenRow" width="20px" align="center">
			 {prio_image}{attachment_image}
		</td>
		<td class="mainscreenRow" style="overflow:hidden; white-space:nowrap;"><nobr>
			<a class="{row_css_class}" name="subject_url" href="#" onclick="fm_readMessage('{url_read_message}', '{read_message_windowName}', this); return false;" title="{full_subject}">{header_subject}</a>
		</td>
		<td class="mainscreenRow" width="95px" align="center">
			<nobr><span style="font-size:10px" title="{datetime}">{date}</span>
		</td>
		<td class="mainscreenRow" style="overflow:hidden; white-space:nowrap;" width="120px"><nobr>
			<a class="{row_css_class}" href="#" onclick="{url_compose} return false;" title="{full_address}">{sender_name}</a>
		</td>
		<td colspan=2 align="right" class="mainscreenRow" width="40px">
			<span style="font-size:10px">{size}</span>
		</td>
				
</tr>
<!-- END header_row_felamimail -->

<!-- BEGIN header_row_outlook -->
	<tr id="row_{message_uid}" class="{row_css_class}" onMouseOver="style.backgroundColor='#dddddd';" onMouseOut="javascript:style.backgroundColor='#FFFFFF';" >
		<td class="mainscreenRow" width="20px" align="left" valign="top">
			<input  style="width:12px; height:12px; border: none; margin: 1px;" class="{row_css_class}" type="checkbox" id="msgSelectInput" name="msg[]" value="{message_uid}" 
			onclick="toggleFolderRadio(this, refreshTimeOut)" {row_selected}>
		</td>
		<td class="mainscreenRow" width="20px" align="center">
			{image_url}
		</td>
		<td class="mainscreenRow" width="20px" align="center">
			 {prio_image}{attachment_image}
		</td>
		<td class="mainscreenRow" style="overflow:hidden; white-space:nowrap;" width="117px"><nobr>
			<a class="{row_css_class}" href="#" onclick="{url_compose} return false;" title="{full_address}">{sender_name}</a>
		</td>
		<td class="mainscreenRow" width="2px">
		</td>
		<td class="mainscreenRow" style="overflow:hidden; white-space:nowrap;"><nobr>
			<a class="{row_css_class}" name="subject_url" href="#" onclick="fm_readMessage('{url_read_message}', '{read_message_windowName}', this); parentNode.parentNode.parentNode.style.fontWeight='normal'; return false;" title="{full_subject}">{header_subject}</a>
		</td>
		<td class="mainscreenRow" width="95px" align="center">
			<nobr><span style="font-size:10px" title="{datetime}">{date}</span>
		</td>
		<td colspan=2 align="right" class="mainscreenRow" width="40px">
			<span style="font-size:10px">{size}</span>
		</td>
				
</tr>
<!-- END header_row_outlook -->

<!-- BEGIN error_message -->
        <table style="width:100%;">
                <tr>
                        <td bgcolor="#FFFFCC" align="center" colspan="6">
                                <font color="red"><b>{lang_connection_failed}</b></font><br>
                                <br>{connection_error_message}<br><br>
                        </td>
                </tr>
        </table>
<!-- END error_message -->

<!-- BEGIN quota_block -->
	<table cellpadding="0" cellspacing="0" style="border:1px solid silver;width:150px;">
		<tr valign="middle">
			<td bgcolor="{quotaBG}" align="center" valign="middle" style="width:{leftWidth}%;height:9px;font-size:9px;">
				&nbsp;{quotaUsage_left}
			</td>
			<td align="center" valign="middle" style="height:9px;font-size:9px;">
				&nbsp;{quotaUsage_right}
			</td>
		</tr>
	</table>
<!-- END quota_block -->

<!-- BEGIN subject_same_window -->
	<td bgcolor="#FFFFFF">
		<a class="{row_css_class}" name="subject_url" href="{url_read_message}" title="{full_subject}">{header_subject}</a>
	</td>
<!-- END subject_same_window -->

<!-- BEGIN subject_new_window -->
	<td bgcolor="#FFFFFF">
		<a class="{row_css_class}" name="subject_url" href="{url_read_message}" title="{full_subject}">{header_subject}</a>
	</td>
<!-- END subject_new_window -->

<!-- BEGIN table_header_felamimail -->
			<table WIDTH=100% BORDER="0" CELLSPACING="0" style="table-layout:fixed;">
				<tr class="th" id="tableHeader">
					<td width="20px" align="left">
						<input style="width:12px; height:12px; border:none; margin: 1px; margin-left: 3px;" type="checkbox" id="messageCheckBox" onclick="selectAll(this, refreshTimeOut)">
					</td>
					<td width="20px" bgcolor="{th_bg}" align="center" class="text_small">
						&nbsp;
					</td>
					<td width="20px" bgcolor="{th_bg}" align="center" class="text_small">
						&nbsp;
					</td>
					<td bgcolor="{th_bg}" style="text-align:left;" class="{css_class_subject}">
						<a href="#" onclick="changeSorting('subject', this); return false;">{lang_subject}</a>
					</td>
					<td width="95px" bgcolor="{th_bg}" align="center" class="{css_class_date}">
						&nbsp;&nbsp;<a href="#" onclick="changeSorting('date', this); return false;">{lang_date}</a>
					</td>
					<td width="120px" bgcolor="{th_bg}" style="text-align:left;" class="{css_class_from}">
						&nbsp;<a href="#" onclick="changeSorting('from', this); return false;"><span id='from_or_to'>{lang_from}</span></a>
					</td>
					<td width="40px" bgcolor="{th_bg}" align="right" class="{css_class_size}">
						<a href="#" onclick="changeSorting('size', this); return false;">{lang_size}</a>&nbsp;
					</td>
					<td width="15px" bgcolor="{th_bg}" align="center" class="{css_class_size}">
						&nbsp;
					</td>
				</tr>
			</table>
<!-- END table_header_felamimail -->

<!-- BEGIN table_header_outlook -->
			<table WIDTH=100% BORDER="0" CELLSPACING="0" style="table-layout:fixed;">
				<tr class="th" id="tableHeader">
					<td width="20px" align="left">
						<input style="width:12px; height:12px; border:none; margin: 1px; margin-left: 3px;" type="checkbox" id="messageCheckBox" onclick="selectAll(this, refreshTimeOut)">
					</td>
					<td width="20px" bgcolor="{th_bg}" align="center" class="text_small">
						&nbsp;
					</td>
					<td width="20px" bgcolor="{th_bg}" align="center" class="text_small">
						&nbsp;
					</td>
					<td width="120px" bgcolor="{th_bg}" style="text-align:left;" class="{css_class_from}">
						&nbsp;<a href="javascript:changeSorting('from');"><span id='from_or_to'>{lang_from}</span></a>
					</td>
					<td bgcolor="{th_bg}" style="text-align:left;" class="{css_class_subject}">
						<a href="javascript:changeSorting('subject');">{lang_subject}</a>
					</td>
					<td width="95px" bgcolor="{th_bg}" align="center" class="{css_class_date}">
						&nbsp;&nbsp;<a href="javascript:changeSorting('date');">{lang_date}</a>
					</td>
					<td width="40px" bgcolor="{th_bg}" align="right" class="{css_class_size}">
						<a href="javascript:changeSorting('size');">{lang_size}</a>&nbsp;
					</td>
					<td width="15px" bgcolor="{th_bg}" align="center" class="{css_class_size}">
						&nbsp;
					</td>
				</tr>
			</table>
<!-- END table_header_outlook -->
