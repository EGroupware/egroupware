
	var popupw;

	function openwindow(url,width,height)
	{
		if (popupw)
		{
			if (popupw.closed)
			{
				popupw.stop;
				popupw.close;
			}
		}
		popupw = window.open(url, "popupWindow","width=" + width + ",height=" + height + ",location=no,menubar=no,directories=no,toolbar=no,scrollbars=yes,resizable=yes,status=no");
		if (popupw.opener == null)
		{
			popupw.opener = window;
		}
	}

	function done()
	{
		popupw.close()
	}
