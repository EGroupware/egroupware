



<!-- BEGIN notify_window -->
<SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript">
	var NotifyWindow;

	function opennotifywindow()
	{
		if (NotifyWindow)
		{
			if (NotifyWindow.closed)
			{
				NotifyWindow.stop;
				NotifyWindow.close;
			}
		}
		NotifyWindow = window.open("{link}", "NotifyWindow", "width=300,height=35,location=no,menubar=no,directories=no,toolbar=no,scrollbars=yes,resizable=yes,status=yes");
		if (NotifyWindow.opener == null)
		{
			NotifyWindow.opener = window;
		}
	}
</SCRIPT> 

<a href="javascript:opennotifywindow()">{notifywindow}</a>

<!-- END notify_window -->


<!-- BEGIN begin_table -->

<table border="0" cellpadding="5" cellspacing="0" width="100%">

<!-- END begin_table -->

<!-- BEGIN begin_row -->

<tr>

<!-- END begin_row -->

<!-- BEGIN cell -->

<td valign="top" colspan="{colspan}" width="{tdwidth}%">{content}</td>

<!-- END cell -->

<!-- BEGIN end_row -->

</tr>

<!-- END end_row -->


<!-- BEGIN end_table -->

</table>

<!-- END end_table -->