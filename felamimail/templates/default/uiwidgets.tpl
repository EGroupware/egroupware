<!-- BEGIN multiSelectBox -->
<script LANGUAGE="JavaScript" TYPE="text/javascript">

	function addCustomValue()
	{
		idCustomValue = document.getElementById("htmlclass_custom_value");
		idSelectedValues = document.getElementById("htmlclass_selected_values");
				
		NewOption = new Option(idCustomValue.value,
					idCustomValue.value,false,true);
		idSelectedValues.options[idSelectedValues.length] = NewOption;
		
		idCustomValue.value = '';
		
		for(i=0;i<idSelectedValues.length;++i)
		{
			idSelectedValues.options[i].selected = true;
		}
		
		sortSelect(idSelectedValues);
	}

	function addPredefinedValue()
	{
		idSelectedValues = document.getElementById("htmlclass_selected_values");
		idPredefinedValues = document.getElementById("htmlclass_predefined_values");
		
		if(idPredefinedValues.selectedIndex != -1)
		{
			NewOption = new Option(idPredefinedValues.options[idPredefinedValues.selectedIndex].text,
						idPredefinedValues.options[idPredefinedValues.selectedIndex].value,false,true);
			idSelectedValues.options[idSelectedValues.length] = NewOption;
			
			idPredefinedValues.options[idPredefinedValues.selectedIndex] = null;
			
			if(idPredefinedValues.length > 0)
			{
				idPredefinedValues.selectedIndex = 0;
			}
			
			sortSelect(idSelectedValues);
		}
	}
	
	function removeValue()
	{
		idSelectedValues = document.getElementById("htmlclass_selected_values");
		idPredefinedValues = document.getElementById("htmlclass_predefined_values");
		while(idSelectedValues.selectedIndex != -1)
		{
			// if we want to allow removing only one by one, we need this and section idselectioncontrol
			// AND need to disable the selectAllOptions at the end of the function
			//for(i=0;i<idSelectedValues.length;++i)
			//{
			//	if (idSelectedValues[i].selected) break;
			//}
			NewOption = new Option(idSelectedValues.options[idSelectedValues.selectedIndex].text,
						idSelectedValues.options[idSelectedValues.selectedIndex].value,false,true);
			idPredefinedValues.options[idPredefinedValues.length] = NewOption;
			
			idSelectedValues.options[idSelectedValues.selectedIndex] = null;
			// section idselectioncontrol
			//if (i>idSelectedValues.length-1) i=idSelectedValues.length-1;
			//if (idSelectedValues.length!=0)idSelectedValues.options[i].selected = true;
			
		}
		sortSelect(idPredefinedValues);
		selectAllOptions();

	}

	function selectAllOptions()
	{
		idSelectedValues = document.getElementById("htmlclass_selected_values");
		
		for(i=0;i<idSelectedValues.length;++i)
		{
			idSelectedValues.options[i].selected = true;
		}
	}
	
	// Author: Matt Kruse <matt@mattkruse.com>
	// WWW: http://www.mattkruse.com/
	//
	// NOTICE: You may use this code for any purpose, commercial or
	// private, without any further permission from the author. You may
	// remove this notice from your final code if you wish, however it is
	// appreciated by the author if at least my web site address is kept.
	function sortSelect(obj) 
	{
		var o = new Array();
		if (obj.options==null) { return; }
		for (var i=0; i<obj.options.length; i++) 
		{
			o[o.length] = new Option( obj.options[i].text, obj.options[i].value, obj.options[i].defaultSelected, obj.options[i].selected) ;
		}
		if (o.length==0) { return; }
		o = o.sort(
			function(a,b) 
			{
				if ((a.text+"") < (b.text+"")) { return -1; }
				if ((a.text+"") > (b.text+"")) { return 1; }
				return 0;
			}
		);
		
		for (var i=0; i<o.length; i++) 
		{
			obj.options[i] = new Option(o[i].text, o[i].value, o[i].defaultSelected, o[i].selected);
		}
	}

</SCRIPT>
<table border="0" cellspacing="0" cellpadding="0" width="{multiSelectBox_boxWidth}">
	<tr>
		<td width="50%" align="right" rowspan="2" valign="top">
			<select name="{multiSelectBox_valueName}[]" id="htmlclass_selected_values" size=8 
				style="width : 250px;" multiple="multiple" ondblclick="removeValue()">
				<!--  onblur="selectAllOptions()" on blur was removed here and substituded with the call of selectAllOptions on submit -->
				{multiSelectBox_selected_options}
			</select>
		</td>
		<td align="left" rowspan="2" style="padding-right : 10px; padding-left : 2px;">
			<a href="javascript:removeValue()">>></a>
		</td>
		<td align="right" style="padding-left : 10px; padding-right : 2px;">
			<a href="javascript:addPredefinedValue()"><<</a>
		</td>
		<td width="50%" valign="top">
			<select name="{multiSelectBox_valueName}_predefined_values[]" id="htmlclass_predefined_values" size=6 style="width : 250px;" ondblclick="addPredefinedValue()">
				{multiSelectBox_predefinded_options}
			</select>
		</td>
	</tr>

	<tr>
		<td align="right" style="padding-left : 10px; padding-right : 2px;">
			<a href="javascript:addCustomValue()"><<</a>
		</td>
		<td>
			<input type="text" name="custom_value" id="htmlclass_custom_value" style="width : 250px;">
		</td>
	</tr>
</table>
<!-- END multiSelectBox -->

<!-- BEGIN tableView -->
<table width="{tableView_width}">

<tr>
	{tableView_Head}
</tr>

{tableView_Rows}

</table>
<!-- END tableView -->

<!-- BEGIN tableViewHead -->
<td>{tableHeadContent}</td>
<!-- END tableViewHead -->

<!-- BEGIN folderSelectTree -->
<div id="divFolderSelectTree" style="overflow:auto; width:380px; height:474px; margin-bottom: 0px;padding-left: 0px; padding-top:0px; z-index:100; border : 1px solid Silver;">
	<table width=100% BORDER="0" style="table-layout:fixed;padding-left:2;">
		<tr>
			<td width="100%" valign="top" nowrap style="font-size:10px">
				{folderTree}
			</td>
		</tr>
		<tr>
			<td width="100%" valign="bottom" nowrap style="font-size:10px">
				<br>
				<p align="center">
				<small><a href="javascript: d.openAll();">{lang_open_all}</a> | <a href="javascript: d.closeAll();">{lang_close_all}</a></small>
				</p>
			</td>
		</tr>
	</table>
</div>
<!-- END folderSelectTree -->
