<!-- BEGIN phpgw_main_tables_start -->
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
<HEAD>
	<META http-equiv="Content-Type" content="text/html; charset={phpgw_head_charset}">
	<META name="AUTHOR" content="phpGroupWare http://www.phpgroupware.org">
	<META NAME="description" CONTENT="{phpgw_head_description}">
	<META NAME="keywords" CONTENT="{phpgw_head_keywords}">
	<META name="robots" content="none">
	<BASE target="{phpgw_head_target}">
	<LINK REL="ICON" href="{phpgw_head_browser_ico}" type="image/x-ico">
	<LINK REL="SHORTCUT ICON" href="{phpgw_head_browser_ico}">
	<TITLE>{phpgw_head_website_title}</TITLE>
	{phpgw_head_javascript}
	{phpgw_css}
	{phpgw_head_tags}
</HEAD>
<BODY {phpgw_body_tags}>
	<TABLE border="0" width="100%" height="100%" cellspacing="0" cellpadding="0">
		<TR>
			<TD width="100%" height="{phpgw_top_table_height}" align="left" valign="top" colspan="3">{phpgw_top}</TD>
		</TR>
		<TR>
			<TD width="{phpgw_left_table_width}" height="100%" align="left" valign="top">{phpgw_left}</TD>
			<TD width="100%" height="100%" align="left" valign="top">
				{phpgw_msgbox}
<!-- END phpgw_main_tables_start -->

<!-- BEGIN phpgw_main_tables_end -->
				{phpgw_body}
			</TD>
			<TD width="{phpgw_right_table_width}" height="100%" align="right" valign="top">{phpgw_right}</TD>
		</TR>
		<TR>
			<TD width="100%" height="{phpgw_bottom_table_height}" align="left" valign="top" colspan="3">{phpgw_bottom}</TD>
		</TR>
	</TABLE>
</BODY>
</HTML>
<!-- END phpgw_main_tables_end -->

<!-- BEGIN phpgw_main_basic_start -->
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
<HEAD>
	<META http-equiv="Content-Type" content="text/html; charset={phpgw_head_charset}">
	<META name="AUTHOR" content="phpGroupWare http://www.phpgroupware.org">
	<META NAME="description" CONTENT="{phpgw_head_description}">
	<META NAME="keywords" CONTENT="{phpgw_head_keywords}">
	<META name="robots" content="none">
	<BASE target="{phpgw_head_target}">
	<LINK REL="ICON" href="{phpgw_head_browser_ico}" type="image/x-ico">
	<LINK REL="SHORTCUT ICON" href="{phpgw_head_browser_ico}">
	<TITLE>{phpgw_head_website_title}</TITLE>
	{phpgw_head_javascript}
	{phpgw_css}
	{phpgw_head_tags}
</HEAD>
<BODY {phpgw_body_tags}>
	{phpgw_msgbox}
<!-- END phpgw_main_basic_start -->

<!-- BEGIN phpgw_main_basic_end -->
	{phpgw_body}
</BODY>
</HTML>
<!-- END phpgw_main_basic_end -->

<!-- BEGIN phpgw_main_frames_start -->
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
<HEAD>
	<META http-equiv="Content-Type" content="text/html; charset={phpgw_head_charset}">
	<META name="AUTHOR" content="phpGroupWare http://www.phpgroupware.org">
	<META NAME="description" CONTENT="{phpgw_head_description}">
	<META NAME="keywords" CONTENT="{phpgw_head_keywords}">
	<META name="robots" content="none">
	<BASE target="{phpgw_head_target}">
	<LINK REL="ICON" href="{phpgw_head_browser_ico}" type="image/x-ico">
	<LINK REL="SHORTCUT ICON" href="{phpgw_head_browser_ico}">
	<TITLE>{phpgw_head_website_title}</TITLE>
	<FRAMESET frameborder="0" border="0" framespacing="0" ROWS="{phpgw_top_frame_height},*,{phpgw_bottom_frame_height}">
		<FRAME MARGINWIDTH=0 MARGINHEIGHT=0 SRC="{phpgw_top_link}" NAME="top" SCROLLING="{phpgw_top_scrolling}">
		<FRAMESET frameborder="0" border="0" framespacing="0" COLS="{phpgw_left_frame_width},*,{phpgw_right_frame_width}">
			<FRAME MARGINWIDTH=0 MARGINHEIGHT=0 SRC="{phpgw_left_link}" NAME="left" SCROLLING="{phpgw_left_scrolling}">
			<FRAME MARGINWIDTH=0 MARGINHEIGHT=0 SRC="{phpgw_body_link}" NAME="body">
			<FRAME MARGINWIDTH=0 MARGINHEIGHT=0 SRC="{phpgw_right_link}" NAME="right" SCROLLING="{phpgw_right_scrolling}">
		</FRAMESET>
		<FRAME MARGINWIDTH=0 MARGINHEIGHT=0 SRC="{phpgw_bottom_link}" NAME="bottom" SCROLLING="{phpgw_bottom_scrolling}">
		<NOFRAMES>
			<P>phpGroupWare is configured to use frames, but your browser does not support them.<BR>
			<A href="{phpgw_unupported_link}">click here to force non-frames mode</A>
		</NOFRAMES>
	</FRAMESET>
</HEAD>
<!-- END phpgw_main_frames_start -->

<!-- BEGIN phpgw_main_frames_end -->
</HTML>
<!-- END phpgw_main_frames_end -->

<!-- BEGIN phpgw_head_javascript -->
	<SCRIPT language="JavaScript" type="text/javascript">
		<!--
		function MM_swapImgRestore() { //v3.0
			var i,x,a=document.MM_sr; for(i=0;a&&i<a.length&&(x=a[i])&&x.oSrc;i++) x.src=x.oSrc;
		}
		function MM_preloadImages() { //v3.0
			var d=document; if(d.images){ if(!d.MM_p) d.MM_p=new Array();
			var i,j=d.MM_p.length,a=MM_preloadImages.arguments; for(i=0; i<a.length; i++)
			if (a[i].indexOf("#")!=0){ d.MM_p[j]=new Image; d.MM_p[j++].src=a[i];}}
		}
		function MM_findObj(n, d) { //v4.0
			var p,i,x;  if(!d) d=document; if((p=n.indexOf("?"))>0&&parent.frames.length) {
			d=parent.frames[n.substring(p+1)].document; n=n.substring(0,p);}
			if(!(x=d[n])&&d.all) x=d.all[n]; for (i=0;!x&&i<d.forms.length;i++) x=d.forms[i][n];
			for(i=0;!x&&d.layers&&i<d.layers.length;i++) x=MM_findObj(n,d.layers[i].document);
			if(!x && document.getElementById) x=document.getElementById(n); return x;
		}
		function MM_swapImage() { //v3.0
			var i,j=0,x,a=MM_swapImage.arguments; document.MM_sr=new Array; for(i=0;i<(a.length-2);i+=3)
			if ((x=MM_findObj(a[i]))!=null){document.MM_sr[j++]=x; if(!x.oSrc) x.oSrc=x.src; x.src=a[i+2];}
		}

		function multiLoad(top_doc,left_doc,body_doc,right_doc,bottom_doc) {
			if(top_doc != null){ parent.top.location.href=top_doc; }
			if(left_doc != null){ parent.left.location.href=left_doc; }
			if(body_doc != null){ parent.body.location.href=body_doc; }
			if(right_doc != null){ parent.right.location.href=right_doc; }
			if(bottom_doc != null){ parent.bottom.location.href=bottom_doc; }
		}
		//-->
	</SCRIPT>
<!-- END phpgw_head_javascript -->
