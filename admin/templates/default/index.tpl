<!-- BEGIN list -->
<SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript">
	var phpinfo;

	function openwindow(url)
	{
		if (phpinfo)
		{
			if (phpinfo.closed)
			{
				phpinfo.stop;
				phpinfo.close;
			}
		}
		phpinfo = window.open(url, "phpinfoWindow","width=700,height=600,location=no,menubar=no,directories=no,toolbar=no,scrollbars=yes,resizable=yes,status=no");
		if (phpinfo.opener == null)
		{
			phpinfo.opener = window;
		}
	}
</SCRIPT>
<table width="75%" border="0" cellspacing="0" cellpadding="0">
 {rows}
</table>
<!-- END list -->

<!-- BEGIN app_row -->
 <tr class="row_off">
  <td width="5%" valign="middle"><img src="{app_icon}" alt="[ {app_title} ]"> <a name="{app_name}"></a></td>
  <td width="95%" valign="middle"><b>&nbsp;&nbsp;{app_title}</b></td>
 </tr>
<!-- END app_row -->

<!-- BEGIN app_row_noicon -->
 <tr class="row_off">
  <td height="25" colspan="2" width="95%" valign="bottom"><b>&nbsp;{app_title}</b> <a name="{app_name}"></a></td>
 </tr>
<!-- END app_row_noicon -->

<!-- BEGIN link_row -->
 <tr>
  <td colspan="2">&nbsp;&#8226;&nbsp;<a href="{pref_link}">{pref_text}</a></td>
 </tr>
<!-- END link_row -->

<!-- BEGIN spacer_row -->
 <tr>
  <td colspan="2">&nbsp;</td>
 </tr>
<!-- END spacer_row -->
