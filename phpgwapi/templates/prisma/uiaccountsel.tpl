<!-- $Id$ -->

<script LANGUAGE="JavaScript">
	window.focus();

	function addOption(id,label,value,multiple)
	{
		openerSelectBox = opener.document.getElementById(id);

		if (multiple && openerSelectBox) {
			select = '';
			for(i=0; i < openerSelectBox.length; i++) {
				with (openerSelectBox.options[i]) {
					if (selected || openerSelectBox.selectedIndex == i) {
						select += (value.slice(0,1)==',' ? '' : ',')+value;
					}
				}
			}
			select += (select ? ',' : '')+value;
			opener.addOption(id,label,value,0);
			opener.addOption(id,'{lang_multiple}',select,0);
		}
		else {
			opener.addOption(id,label,value,!multiple && openerSelectBox && openerSelectBox.selectedIndex < 0);
		}
		selectBox = document.getElementById('uiaccountsel_popup_selection');
		if (selectBox) {
			for (i=0; i < selectBox.length; i++) {
				if (selectBox.options[i].value == value) {
					selectBox.options[i].selected = true;
					break;
				}
			}
			if (i >= selectBox.length) {
				selectBox.options[selectBox.length] = new Option(label,value,false,true);
			}
		}
	}

	function removeSelectedOptions(id)
	{
		openerSelectBox = opener.document.getElementById(id);
		if (openerSelectBox == null) window.close();
		selectBox = document.getElementById('uiaccountsel_popup_selection');
		for (i=0; i < selectBox.length; i++) {
			if (selectBox.options[i].selected) {
				for (j=0; j < openerSelectBox.length; j++) {
					if (openerSelectBox[j].value == selectBox.options[i].value) {
						openerSelectBox.removeChild(openerSelectBox[j]);
					}
				}
				selectBox.options[i--] = null;
			}
		}
	}

	function copyOptions(id)
	{
		openerSelectBox = opener.document.getElementById(id);
		selectBox = document.getElementById('uiaccountsel_popup_selection');
		for (i=0; i < openerSelectBox.length; i++) {
			with (openerSelectBox.options[i]) {
				if (selected && value.slice(0,1) != ',') {
					selectBox.options[selectBox.length] =  new Option(text,value);
				}
			}
		}
	}
	
	function oneLineSubmit(id)
	{
		openerSelectBox = opener.document.getElementById(id);

		if (openerSelectBox) {
			if (openerSelectBox.selectedIndex >= 0) {
				selected = openerSelectBox.options[openerSelectBox.selectedIndex].value;
				if (selected.slice(0,1) == ',') selected = selected.slice(1);
				opener.addOption(id,'{lang_multiple}',selected,1);
			}
			else {
				for (i=0; i < openerSelectBox.length; i++) {
					with (openerSelectBox.options[i]) {
						if (selected) {
							opener.addOption(id,text,value,1);
							break;
						}
					}
				}	
			}
		}
		window.close();
	}	
</script>

<style type="text/css">
	.letter_box,.letter_box_active {
		background-color: #E8F0F0;
		width: 15px;
		border: 1px solid white;
		text-align: center;
		cursor: pointer;
		cusror: hand;
	}
	.letter_box_active {
		font-weight: bold;
		background-color: #D3DCE3;
	}
	.letter_box_active,.letter_box:hover {
		border: 1px solid black;
		background-color: #D3DCE3;
	}
</style>

<div id="divMain">
<table border="0" width="100%">
	<tr>
		<td width="20%" rowspan="3">{accountsel_icon}</td>
		<td align="right" colspan="5">
			<form method="POST" action="{search_action}">
				{query_type}
				<input type="text" name="query" value="{prev_query}">
				<input type="submit" name="search" value="{lang_search}">
			</form>
		</td>
	</tr>
	<tr>
		<td colspan="5">
			<table width="100%"><tr>
<!-- BEGIN letter_search -->
				<td class="{class}" onclick="location.href='{link}';">{letter}</td>
<!-- END letter_search -->
			</tr></table>
		</td>
	</tr>
	<tr>
		{left}
		<td align="center">{lang_showing}</td>
		{right}
	</tr>
</table>

<table border="0" width="100%" cellpadding="0" cellspacing="0">
	<tr>

<!-- BEGIN bla_intro -->

<!-- END bla_intro -->

<!-- BEGIN other_intro -->

<!-- END other_intro -->

<!-- BEGIN group_cal -->

<!-- END group_cal -->

<!-- BEGIN group_other -->

<!-- END group_other -->

<!-- BEGIN all_intro -->

<!-- END all_intro -->

<!-- BEGIN group_all -->

<!-- END group_all -->

		<td valign="top">
			<table border="0" width="100%" cellpadding="2" cellspacing="2">
				<tr class="th">
					<td width="100%" class="th" align="center" colspan="4"><b>{lang_accounts}</b></td>
				</tr>
				<tr class="th">
					<td align="center">{sort_lid}</td>
					<td align="center">{sort_firstname}</td>
					<td align="center">{sort_lastname}</td>
					<td width="10%">&nbsp;</td>
				</tr>

<!-- BEGIN accounts_list -->

	<tr class="{tr_color}">
		<td>{lid}</td>
		<td>{firstname}</td>
		<td>{lastname}</td>
		<td align="center">
			<input type="image" src="{img}" onclick="{onclick}; return false;" title="{lang_select_user}">
		</td>
	</tr>

<!-- END accounts_list -->

			</table>
		</td>
		<td valign="top">
			<table border="0" width="100%" cellpadding="2" cellspacing="2">
				<tr class="th">
					<td align="center" colspan="2"><b>{lang_selection}</b> {remove}</td>
				</tr>
				<tr class="row_off">
					<td align="center">
						{selection}
					</td>
				</tr>
				<tr>
					<td align="center" colspan="2">
						<input type="button" name="close" value="{lang_close}" onClick="{close_action}">
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</div>

