<!-- $Id$ -->

	<xsl:template match="phpgw">
	<xsl:variable name="phpgw_css_path"><xsl:value-of select="phpgw_css_path"/></xsl:variable>
	<xsl:variable name="theme_css_path"><xsl:value-of select="theme_css_path"/></xsl:variable>
	<xsl:variable name="phpgw_charset"><xsl:value-of select="phpgw_charset"/></xsl:variable>
	<xsl:variable name="phpgw_onload"><xsl:value-of select="phpgw_onload"/></xsl:variable>
		<html>
			<head>
				<meta http-equiv="Content-Type" content="text/html; charset={$phpgw_charset}"/>
				<meta name="author" content="phpGroupWare http://www.phpgroupware.org"/>
				<meta name="description" content="phpGroupWare"/>
				<meta name="keywords" content="phpGroupWare"/>
				<meta name="robots" content="noindex"/>
				<link rel="icon" href="favicon.ico" type="image/x-ico"/>
				<link rel="shortcut icon" href="favicon.ico"/>
				<title><xsl:value-of select="phpgw_website_title"/></title>
				<xsl:apply-templates select="head_js"/>
				<link rel="stylesheet" type="text/css" href="{$phpgw_css_path}">
				<link rel="stylesheet" type="text/css" href="{$theme_css_path}">
			</head>
			<body onLoad="{$phpgw_onload}">
				<table width="100%" height="100%" cellspacing="0" cellpadding="0">
					<tr>
						<td colspan="2">
						<!-- BEGIN top_part -->
							<table width="100%" height="73" cellspacing="0" cellpadding="0">
								<tr class="top_top">
								<!-- top row back images are 58px high, but the row may be smaller than that -->
								<!-- row 2 images are 15 px high, so this table with these 2 rows is 58 plus 15 equals 73px high  -->
									<td width="154" valign="top">
										<img src="{logo_img}" border="0">
									</td>
									<td valign="bottom">
										<table width="100%" cellpadding="0" cellspacing="0">
											<tr>
												<td width="33%" class="info">{user_info_name}</td>
												<td width="33%" class="info">{current_users}</td>
												<td width="33%" class="info" align="right">{user_info_date}</td>
											</tr>
										</table>
									</td>
								</tr>
								<tr align="right" class="top_bottom">
									<td colspan="2">
									<!-- row 2 right nav buttons -->
										<table cellpadding="0" cellspacing="0">
											<tr>
												<td><a href="{home_link}" onMouseOver="nine.src='{welcome_img_hover}'" onMouseOut="nine.src='{welcome_img}'"><img src="{welcome_img}" border="0" name="nine"></a></td>
												<td><a href="{preferences_link}" onMouseOver="ten.src='{preferences_img_hover}'" onMouseOut="ten.src='{preferences_img}'"><img src="{preferences_img}" border="0" name="ten"></a></td>
												<td><a href="{logout_link}" onMouseOver="eleven.src='{logout_img_hover}'" onMouseOut="eleven.src='{logout_img}'"><img src="{logout_img}" border="0" name="eleven"></a></td>
												<td><a href="{help_link}" onMouseOver="help.src='{about_img_hover}'" onMouseOut="help.src='{about_img}'"><img src="{about_img}" border="0" name="help"></a></td>
											</tr>
										</table>
									</td>
								</tr>
							</table>
						<!-- END top_part -->
						</td>
					</tr>
					<tr>
						<td>
						<!-- BEGIN left_part -->
							<table cellspacing="0" cellpadding="0" valign="top" class="left">
								<xsl:apply-templates select="applications"/>
								<tr>
									<td><img src="{nav_bar_left_top_bg_img}"></td>
								</tr>
							</table>
						<!-- END left_part -->
						</td>
						<td width="85%" height="85%" valign="top">
							<xsl:call-template name="msgbox"/>
							<xsl:call-template name="phpgw_body"/>
						</td>
					</tr>
					<tr>
						<td colspan="2" align="center" valign="middle" class="info">
						<!-- BEGIN bottom_part -->
									{powered}
						<!-- END bottom_part -->
						</td>
					</tr>
				</table>
			</body>
		</html>
	</xsl:template>

	<xsl:template match="applications">
		<tr>
			<td class="info">

			</td>
		</tr>
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
