
	function MM_swapImgRestore()
	{ //v3.0
		var i,x,a=document.MM_sr;
		for(i=0;a&&i<a.length&&(x=a[i])&&x.oSrc;i++)
		x.src=x.oSrc;
	}

	function MM_preloadImages()
	{ //v3.0
		var d=document; if(d.images)
		{
			if(!d.MM_p) d.MM_p=new Array();
			var i,j=d.MM_p.length,a=MM_preloadImages.arguments;
			for(i=0; i<a.length; i++)
				if (a[i].indexOf("#")!=0)
				{
					d.MM_p[j]=new Image; d.MM_p[j++].src=a[i];
				}
		}
	}

	function MM_findObj(n,d)
	{ //v4.0 
		var p,i,x;
		if(!d) d=document;

		if((p=n.indexOf("?"))>0&&parent.frames.length)
		{
			d=parent.frames[n.substring(p+1)].document;
			n=n.substring(0,p);
		}

		if(!(x=d[n])&&d.all) x=d.all[n];

		for (i=0;!x&&i<d.forms.length;i++) x=d.forms[i][n];
		for(i=0;!x&&d.layers&&i<d.layers.length;i++) x=MM_findObj(n,d.layers[i].document);
		if(!x && document.getElementById) x=document.getElementById(n); return x;
	}

	function MM_swapImage()
	{ //v3.0
		var i,j=0,x,a=MM_swapImage.arguments;
		document.MM_sr=new Array;

		for(i=0;i<(a.length-2);i+=3)
			if ((x=MM_findObj(a[i]))!=null)
			{
				document.MM_sr[j++]=x;
				if(!x.oSrc) x.oSrc=x.src;
				x.src=a[i+2];
			}
	}

	function multiLoad(top_doc,left_doc,body_doc,right_doc,bottom_doc)
	{
		if(top_doc != null){ parent.top.location.href=top_doc; }
		if(left_doc != null){ parent.left.location.href=left_doc; }
		if(body_doc != null){ parent.body.location.href=body_doc; }
		if(right_doc != null){ parent.right.location.href=right_doc; }
		if(bottom_doc != null){ parent.bottom.location.href=bottom_doc; }
	}

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
