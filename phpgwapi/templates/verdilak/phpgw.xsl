<!-- $Id$ -->

	<xsl:template match="phpgw">
	<xsl:variable name="phpgw_css_file"><xsl:value-of select="phpgw_css_file"/></xsl:variable>
	<xsl:variable name="theme_css_file"><xsl:value-of select="theme_css_file"/></xsl:variable>
	<xsl:variable name="charset"><xsl:value-of select="charset"/></xsl:variable>
	<xsl:variable name="logo_img"><xsl:value-of select="logo_img"/></xsl:variable>
	<xsl:variable name="home_link"><xsl:value-of select="home_link"/></xsl:variable>
	<xsl:variable name="prefs_link"><xsl:value-of select="prefs_link"/></xsl:variable>
	<xsl:variable name="logout_link"><xsl:value-of select="logout_link"/></xsl:variable>
	<xsl:variable name="about_link"><xsl:value-of select="about_link"/></xsl:variable>
	<xsl:variable name="home_img"><xsl:value-of select="home_img"/></xsl:variable>
	<xsl:variable name="prefs_img"><xsl:value-of select="prefs_img"/></xsl:variable>
	<xsl:variable name="logout_img"><xsl:value-of select="logout_img"/></xsl:variable>
	<xsl:variable name="about_img"><xsl:value-of select="about_img"/></xsl:variable>
	<xsl:variable name="home_title"><xsl:value-of select="home_title"/></xsl:variable>
	<xsl:variable name="prefs_title"><xsl:value-of select="prefs_title"/></xsl:variable>
	<xsl:variable name="logout_title"><xsl:value-of select="logout_title"/></xsl:variable>
	<xsl:variable name="about_title"><xsl:value-of select="about_title"/></xsl:variable>
	<xsl:variable name="phpgw_body"><xsl:value-of select="phpgw_body"/></xsl:variable>
	<xsl:variable name="greybar"><xsl:value-of select="greybar"/></xsl:variable>
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
				<link rel="stylesheet" type="text/css" href="{$phpgw_css_file}"/>
				<link rel="stylesheet" type="text/css" href="{$theme_css_file}"/>
			</head>
			<body>
				<table width="100%" height="100%" cellspacing="0" cellpadding="0"> 
					<tr>
						<td colspan="3" valign="top">
						<!-- BEGIN top_part -->
							<table class="navbar" width="100%" cellspacing="0" cellpadding="0" border="0">
								<tr>
									<td valign="bottom">
										<a href="http://www.phpgroupware.org" target="_blank">
										<img src="{$logo_img}" border="0"/></a>
									</td>
									<td class="portal_text" width="99%" valign="bottom" align="center">
										<xsl:value-of select="user_info"/>
									</td>
									<td valign="bottom" align="right" rowspan="2" nowrap="true">
										<table cellspacing="0" cellpadding="0" border="0">
											<tr>
												<td><a href="{$home_link}"><img src="{$home_img}" border="0" alt="{$home_title}" title="{$home_title}"/></a></td>
												<td><a href="{$prefs_link}"><img src="{$prefs_img}" border="0" alt="{$prefs_title}" title="{$prefs_title}"/></a></td>
												<td><a href="{$logout_link}"><img src="{$logout_img}" border="0" alt="{$logout_title}" title="{$logout_title}"/></a></td>
												<td><a href="{$about_link}"><img src="{$about_img}" border="0" alt="{$about_title}" title="{$about_title}"/></a></td>
											</tr>
										</table>
									</td>
								</tr>
								<tr valign="bottom">
									<td colspan="2" valign="bottom">
										<img src="{$greybar}" height="6" width="100%"/>
									</td>
								</tr>
							</table>
						<!-- END top_part -->
						</td>
					</tr>
					<!-- BEGIN top_part 2 -->
					<tr height="20" valign="top">
						<td class="left">
						</td>
						<td style="padding-left: 5px">
							<xsl:choose>
								<xsl:when test="current_users">
								<xsl:variable name="url_current_users"><xsl:value-of select="url_current_users"/></xsl:variable>
									<a href="{$url_current_users}"><xsl:value-of select="current_users"/></a>
								</xsl:when>
							</xsl:choose>
						</td>
						<td align="right">
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
						</td>
					</tr>
					<!-- END top_part 2 -->
					<tr valign="top">
						<td class="left" width="32">
						<!-- BEGIN left_part -->
							<table cellspacing="0" cellpadding="0" valign="top" class="left">
								<xsl:apply-templates select="applications"/>
							</table>
						<!-- END left_part -->
						</td>
						<td width="100%" height="100%" valign="top" align="center" colspan="2" style="padding-left: 5px">
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
								<xsl:otherwise>
									<xsl:value-of disable-output-escaping="yes" select="body_data"/>
								</xsl:otherwise>
							</xsl:choose>
						</td>
					</tr>
					<tr>
						<td colspan="3" class="navbar">
						<!-- BEGIN bottom_part -->      
						&nbsp;
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
				<a href="{$url}"><img src="{$icon}" border="0" alt="{$title}" title="{$title}" name="{$name}"/></a>
			</td>
		</tr>
	</xsl:template>
