<!-- $Id$ -->

	<xsl:template name="phpgw">
	<xsl:variable name="css_path"><xsl:value-of select="css_path"/></xsl:variable>
	<xsl:variable name="phpgw_bg"><xsl:value-of select="phpgw_bg"/></xsl:variable>
	<xsl:variable name="phpgw_onload"><xsl:value-of select="phpgw_onload"/></xsl:variable>
	<xsl:variable name="phpgw_top_table_height"><xsl:value-of select="phpgw_top_table_height"/></xsl:variable>
	<xsl:variable name="phpgw_left_table_width"><xsl:value-of select="phpgw_left_table_width"/></xsl:variable>
	<xsl:variable name="phpgw_body_table_height"><xsl:value-of select="phpgw_body_table_height"/></xsl:variable>
	<xsl:variable name="phpgw_body_table_width"><xsl:value-of select="phpgw_body_table_width"/></xsl:variable>
	<xsl:variable name="phpgw_right_table_width"><xsl:value-of select="phpgw_right_table_width"/></xsl:variable>
	<xsl:variable name="phpgw_bottom_table_height"><xsl:value-of select="phpgw_bottom_table_height"/></xsl:variable>
		<html>
			<head>
				<meta http-equiv="Content-Type" content="text/html; charset={phpgw_head_charset}"/>
				<meta name="AUTHOR" content="phpGroupWare http://www.phpgroupware.org"/>
				<meta name="description" content="phpGroupWare"/>
				<meta name="keywords" content="phpGroupWare"/>
				<meta name="robots" content="noindex"/>
				<base target="{phpgw_head_target}"/>
				<link rel="icon" href="favicon.ico" type="image/x-ico"/>
				<link rel="shortcut icon" href="favicon.ico"/>
				<title><xsl:value-of select="phpgw_website_title"/></title>
				<xsl:apply-templates select="head_js"/>
				<link rel="stylesheet" type="text/css" href="{$css_path}">
			</head>
			<body background="{$phpgw_bg}" onLoad="{phpgw_onload}">
				<table border="0" width="100%" height="100%" cellspacing="0" cellpadding="0">
					<tr>
						<td width="100%" height="{$phpgw_top_table_height}" valign="top" colspan="3">
							<xsl:apply-templates select="phpgw_top"/>
						</td>
					</tr>
					<tr>
						<td width="{$phpgw_left_table_width}" height="{$phpgw_body_table_height}" valign="top">
							<xsl:apply-templates select="phpgw_left"/>
						</td>
						<td width="{$phpgw_body_table_width}" height="{$phpgw_body_table_height}" valign="top">
							<xsl:apply-templates select="phpgw_msg_box"/>
							<xsl:apply-templates select="phpgw_body"/>
						</td>
						<td width="{$phpgw_right_table_width}" height="{$phpgw_body_table_height}" align="right" valign="top">
							<xsl:apply-templates select="phpgw_right"/>
						</td>
					</tr>
					<tr>
						<td width="100%" height="{$phpgw_bottom_table_height}" valign="top" colspan="3">
							<xsl:apply-templates select="phpgw_bottom"/>
						</td>
					</tr>
				</table>
			</body>
		</html>
	</xsl:template>

	<xsl:template match="head_js">
		<script language="JavaScript" type="text/javascript">
		<xsl:text>
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
					if(!d.MM_p)
						d.MM_p=new Array();
					var i,j=d.MM_p.length,a=MM_preloadImages.arguments;
					for(i=0; i<a.length; i++)
						if (a[i].indexOf("#")!=0)
						{
							d.MM_p[j]=new Image; d.MM_p[j++].src=a[i];
						}
				}
			}
			function MM_findObj(n, d)
			{ //v4.0
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
		</xsl:text>
		</script>
	</xsl:template>
