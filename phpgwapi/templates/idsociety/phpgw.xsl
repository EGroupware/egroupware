<!-- $Id$ -->

	<xsl:template match="phpgw">
	<xsl:variable name="phpgw_css_file"><xsl:value-of select="phpgw_css_file"/></xsl:variable>
	<xsl:variable name="theme_css_file"><xsl:value-of select="theme_css_file"/></xsl:variable>
	<xsl:variable name="charset"><xsl:value-of select="charset"/></xsl:variable>
	<xsl:variable name="onload"><xsl:value-of select="onload"/></xsl:variable>
	<xsl:variable name="logo_img"><xsl:value-of select="logo_img"/></xsl:variable>
	<xsl:variable name="nav_bar_left_top_bg_img"><xsl:value-of select="nav_bar_left_top_bg_img"/></xsl:variable>
	<xsl:variable name="home_link"><xsl:value-of select="home_link"/></xsl:variable>
	<xsl:variable name="prefs_link"><xsl:value-of select="prefs_link"/></xsl:variable>
	<xsl:variable name="logout_link"><xsl:value-of select="logout_link"/></xsl:variable>
	<xsl:variable name="about_link"><xsl:value-of select="about_link"/></xsl:variable>
	<xsl:variable name="home_img_hover"><xsl:value-of select="home_img_hover"/></xsl:variable>
	<xsl:variable name="prefs_img_hover"><xsl:value-of select="prefs_img_hover"/></xsl:variable>
	<xsl:variable name="logout_img_hover"><xsl:value-of select="logout_img_hover"/></xsl:variable>
	<xsl:variable name="about_img_hover"><xsl:value-of select="about_img_hover"/></xsl:variable>
	<xsl:variable name="home_img"><xsl:value-of select="home_img"/></xsl:variable>
	<xsl:variable name="prefs_img"><xsl:value-of select="prefs_img"/></xsl:variable>
	<xsl:variable name="logout_img"><xsl:value-of select="logout_img"/></xsl:variable>
	<xsl:variable name="about_img"><xsl:value-of select="about_img"/></xsl:variable>
	<xsl:variable name="home_title"><xsl:value-of select="home_title"/></xsl:variable>
	<xsl:variable name="prefs_title"><xsl:value-of select="prefs_title"/></xsl:variable>
	<xsl:variable name="logout_title"><xsl:value-of select="logout_title"/></xsl:variable>
	<xsl:variable name="about_title"><xsl:value-of select="about_title"/></xsl:variable>
	<xsl:variable name="app_tpl"><xsl:value-of select="app_tpl"/></xsl:variable>
		<html>
			<head>
				<meta http-equiv="Content-Type" content="text/html; charset={$charset}"/>
				<meta name="author" content="phpGroupWare http://www.phpgroupware.org"/>
				<meta name="description" content="phpGroupWare"/>
				<meta name="keywords" content="phpGroupWare"/>
				<meta name="robots" content="noindex"/>
				<link rel="icon" href="favicon.ico" type="image/x-ico"/>
				<link rel="shortcut icon" href="favicon.ico"/>
				<title><xsl:value-of select="website_title"/></title>

				<script language="JavaScript" type="text/javascript">
				<xsl:text>
				<!--	function MM_swapImgRestore()
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
					function MM_swapImage()
					{ //v3.0
						var i,j=0,x,a=MM_swapImage.arguments; document.MM_sr=new Array; for(i=0;i<(a.length-2);i+=3)
						if ((x=MM_findObj(a[i]))!=null){document.MM_sr[j++]=x; if(!x.oSrc) x.oSrc=x.src; x.src=a[i+2];}
					}

					function multiLoad(top_doc,left_doc,body_doc,right_doc,bottom_doc)
					{
						if(top_doc != null){ parent.top.location.href=top_doc; }
						if(left_doc != null){ parent.left.location.href=left_doc; }
						if(body_doc != null){ parent.body.location.href=body_doc; }
						if(right_doc != null){ parent.right.location.href=right_doc; }
						if(bottom_doc != null){ parent.bottom.location.href=bottom_doc; }
					} -->
				</xsl:text>
				</script>
				<link rel="stylesheet" type="text/css" href="{$phpgw_css_file}"/>
				<link rel="stylesheet" type="text/css" href="{$theme_css_file}"/>
			</head>
			<body onLoad="{$onload}">
				<table width="100%" height="100%" cellspacing="0" cellpadding="0">
					<tr>
						<td colspan="2" valign="top">
						<!-- BEGIN top_part -->
							<table width="100%" height="73" cellspacing="0" cellpadding="0">
								<tr class="top_top" height="58">
								<!-- top row back images are 58px high, but the row may be smaller than that -->
								<!-- row 2 images are 15 px high, so this table with these 2 rows is 58 plus 15 equals 73px high  -->
									<td width="154" valign="top">
										<img src="{$logo_img}" border="0"/>
									</td>
									<td valign="bottom">
										<table width="100%" cellpadding="0" cellspacing="0">
											<tr>
												<td width="33%" class="info"><xsl:value-of select="user_info_name"/></td>
												<xsl:choose>
													<xsl:when test="current_users">
													<xsl:variable name="url_current_users"><xsl:value-of select="url_current_users"/></xsl:variable>
														<td width="33%" class="info"><a href="{$url_current_users}"><xsl:value-of select="current_users"/></a></td>
													</xsl:when>
													<xsl:otherwise>
														<td></td>
													</xsl:otherwise>
												</xsl:choose>
												<td width="33%" class="info" align="right"><xsl:value-of select="user_info_date"/></td>
											</tr>
										</table>
									</td>
								</tr>
								<tr align="right" class="top_bottom" height="15">
									<td colspan="2">
									<!-- row 2 right nav buttons -->
										<table cellpadding="0" cellspacing="0">
											<tr class="top_bottom">
												<td><a href="{$home_link}" onMouseOver="nine.src='{$home_img_hover}'" onMouseOut="nine.src='{$home_img}'"><img src="{$home_img}" border="0" name="nine" alt="{$home_title}" title="{$home_title}"/></a></td>
												<td><a href="{$prefs_link}" onMouseOver="ten.src='{$prefs_img_hover}'" onMouseOut="ten.src='{$prefs_img}'"><img src="{$prefs_img}" border="0" name="ten" alt="{$prefs_title}" title="{$prefs_title}"/></a></td>
												<td><a href="{$logout_link}" onMouseOver="eleven.src='{$logout_img_hover}'" onMouseOut="eleven.src='{$logout_img}'"><img src="{$logout_img}" border="0" name="eleven" alt="{$logout_title}" title="{$logout_title}"/></a></td>
												<td><a href="{$about_link}" onMouseOver="help.src='{$about_img_hover}'" onMouseOut="help.src='{$about_img}'"><img src="{$about_img}" border="0" name="help" alt="{$about_title}" title="{$about_title}"/></a></td>
											</tr>
										</table>
									</td>
								</tr>
							</table>
						<!-- END top_part -->
						</td>
					</tr>
					<tr valign="top">
						<td>
						<!-- BEGIN left_part -->
							<table cellspacing="0" cellpadding="0" valign="top" class="left">
								<xsl:apply-templates select="applications"/>
								<tr>
									<td><img src="{$nav_bar_left_top_bg_img}"/></td>
								</tr>
							</table>
						<!-- END left_part -->
						</td>
						<td width="100%" height="100%" valign="top" align="center">
							<xsl:choose>
								<xsl:when test="msgbox_data">
									<xsl:call-template name="msgbox"/>
								</xsl:when>
							</xsl:choose>
							<xsl:choose>
								<xsl:when test="home">
									<xsl:call-template name="portal"/>
								</xsl:when>
								<xsl:when test="about">
									<xsl:call-template name="about"/>
								</xsl:when>
							</xsl:choose>
							<xsl:choose>
								<xsl:when test="$app_tpl != ''">
									<xsl:choose>
										<xsl:when test="$app_tpl = 'delete'">
											<xsl:call-template name="app_delete"/>
										</xsl:when>
										<xsl:otherwise>
											<xsl:call-template name="app_data"/>
										</xsl:otherwise>
									</xsl:choose>
								</xsl:when>
								<xsl:otherwise>
									<xsl:value-of disable-output-escaping="yes" select="body_data"/>
								</xsl:otherwise>
							</xsl:choose>
						</td>
					</tr>
					<tr valign="top">
						<td colspan="2" align="center" valign="top" class="bottom">
						<!-- BEGIN bottom_part -->
							<xsl:value-of select="lang_powered_by"/>
							<a href="http://www.phpgroupware.org" target="blank" onMouseout="window.status='';return true;">
								<xsl:attribute name="onMouseover">
									<xsl:text>window.status='</xsl:text>
									<xsl:value-of select="lang_phpgw_statustext"/>
									<xsl:text>'; return true;</xsl:text>
								</xsl:attribute>
								<xsl:text> phpGroupWare </xsl:text>
							</a>
							<xsl:text> </xsl:text><xsl:value-of select="lang_version"/><xsl:text> </xsl:text><xsl:value-of select="phpgw_version"/>
						<!-- END bottom_part -->
						</td>
					</tr>
				</table>
			</body>
		</html>
	</xsl:template>

	<xsl:template match="applications">
	<xsl:variable name="url"><xsl:value-of select="url"/></xsl:variable>
	<xsl:variable name="name"><xsl:value-of select="name"/></xsl:variable>
	<xsl:variable name="img_src_over"><xsl:value-of select="img_src_over"/></xsl:variable>
	<xsl:variable name="icon"><xsl:value-of select="icon"/></xsl:variable>
	<xsl:variable name="title"><xsl:value-of select="title"/></xsl:variable>
		<tr>
			<td class="left">
				<a href="{$url}" onMouseOver="{$name}.src='{$img_src_over}'" onMouseOut="{$name}.src='{$icon}'"><img src="{$icon}" border="0" alt="{$title}" title="{$title}" name="{$name}"/></a>
			</td>
		</tr>
	</xsl:template>
