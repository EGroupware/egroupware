<!-- $Id$ -->
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">
<HTML LANG="en">
<head>
<title>{title}</title>
<meta http-equiv="content-type" content="text/html"; charset="{charset}">

<script LANGUAGE="JavaScript">
	var userSelectBox = opener.document.forms["app_form"].elements['{select_name};

	function ExchangeAccountSelect(thisform)
	{
		NewEntry = new Option(thisform.elements[1].value,thisform.elements[0].value,false,true);
 		userSelectBox.options[userSelectBox.length] = NewEntry;
	}
</script>
<script LANGUAGE="JavaScript">
	function ExchangeAccountText(thisform)
	{
		opener.document.app_form.accountid.value = thisform.elements[0].value;
		opener.document.app_form.accountname.value = thisform.elements[1].value;
	}
</script>
<link rel="stylesheet" type="text/css" href="{css_file}">
</head>
<body>
<center>
<table border="0" width="100%">
	<tr>
		<td colspan="4">
			<table border="0" width="100%">
				<tr>
				{left}
					<td align="center"><font face="{font}">{lang_showing}</font></td>
				{right}
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td width="100%" colspan="4" align="right">
			<form method="POST" action="{search_action}">
			<input type="text" name="query">&nbsp;<input type="submit" name="search" value="{lang_search}">
			</form>
		</td>
	</tr>
</table>

<table border="0" width="100%" cellpadding="0" cellspacing="0">
	<tr>
		<td valign="top" width="20%">
			<table border="0" width="100%" cellpadding="2" cellspacing="2">
				<tr>
					<td bgcolor="{th_bg}" colspan="2" align="center"><font face="{font}"><b>{lang_groups}</b></font></td>
				</tr>
<!-- BEGIN bla_intro -->
				<tr>
					<td bgcolor="{th_bg}" colspan="2"><font face="{font}">{lang_perm}</font></td>
				</tr>

<!-- END bla_intro -->

<!-- BEGIN other_intro -->
				<tr>
					<td bgcolor="{th_bg}" colspan="2"><font face="{font}">{lang_perm}</font></td>
				</tr>
<!-- END other_intro -->

<!-- BEGIN group_cal -->
				<tr bgcolor="{tr_color}">
					<td><a href="{link_user_group}"><font face="{font}">{name_user_group}</font></a></td>
					<td align="center">
					<form>
						<input type="hidden" name="hidden" value="{accountid}">
						<input type="hidden" name="hidden" value="{account_display}">
						<input type="image" src="{img}" onClick="{js_function}(this.form); return false;" name="{lang_select_group}" title="{lang_select_group}">
					</form>
					</td>
				</tr>
<!-- END group_cal -->

<!-- BEGIN group_other -->

				<tr bgcolor="{tr_color}">
					<td><a href="{link_user_group}"><font face="{font}">{name_user_group}</font></a></td>
				</tr>

<!-- END group_other -->

<!-- BEGIN all_intro -->
				<tr height="5">
					<td>&nbsp;</td>
				</tr>
				<tr>
					<td bgcolor="{th_bg}" colspan="2"><font face="{font}">{lang_nonperm}</font></td>
				</tr>

<!-- END all_intro -->

<!-- BEGIN group_all -->

				<tr bgcolor="{tr_color}">
					<td colspan="2"><a href="{link_all_group}"><font face="{font}">{name_all_group}</font></a></td>
				</tr>

<!-- END group_all -->


			</table>
		</td>
		<td width="80%" valign="top">
			<table border="0" width="100%" cellpadding="2" cellspacing="2">
				<tr bgcolor="{th_bg}">
					<td width="100%" bgcolor="{th_bg}" align="center" colspan="4"><font face="{font}"><b>{lang_accounts}</b></font></td>
				</tr>
				<tr bgcolor="{th_bg}">
					<td width="30%" bgcolor="{th_bg}" align="center"><font face="{font}">{sort_lid}</font></td>
					<td width="30%" bgcolor="{th_bg}" align="center"><font face="{font}">{sort_firstname}</font></td>
					<td width="30%" bgcolor="{th_bg}" align="center"><font face="{font}">{sort_lastname}</font></td>
					<td width="10%" bgcolor="{th_bg}">&nbsp;</td>
				</tr>

<!-- BEGIN accounts_list -->

	<tr bgcolor="{tr_color}">
		<td><font face="{font}">{lid}</font></td>
		<td><font face="{font}">{firstname}</font></td>
		<td><font face="{font}">{lastname}</font></td>
		<form>
			<input type="hidden" name="hidden" value="{accountid}">
			<input type="hidden" name="hidden" value="{account_display}">
			<td align="center">
				<input type="image" src="{img}" onClick="{js_function}(this.form); return false;" name="{lang_select_user}" title="{lang_select_user}"></td>
		</form>
	</tr>

<!-- END accounts_list -->

			</table>
		</td>
	</tr>
</table>
<table cellpadding="2" cellspacing="2">
	<tr> 
		<form>  
			<input type="hidden" name="start" value="{start}">
			<input type="hidden" name="sort" value="{sort}">
			<input type="hidden" name="order" value="{order}">
			<input type="hidden" name="query" value="{query}">
			<input type="hidden" name="group_id" value="{group_id}">
			<td><font face="{font}"><input type="button" name="Done" value="{lang_done}" onClick="window.close()"></font></td>
		</form>
	</tr>
</table>
</center>
</body>
</html>
