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
