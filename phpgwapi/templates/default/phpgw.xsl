<!-- $Id$ -->

	<xsl:template match="phpgw">
	<xsl:variable name="phpgw_css_file"><xsl:value-of select="phpgw_css_file"/></xsl:variable>
	<xsl:variable name="theme_css_file"><xsl:value-of select="theme_css_file"/></xsl:variable>
	<xsl:variable name="charset"><xsl:value-of select="charset"/></xsl:variable>
	<xsl:variable name="onload"><xsl:value-of select="onload"/></xsl:variable>
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
			<body onLoad="{$onload}">
				<table width="100%" height="100%" cellspacing="0" cellpadding="0">
					<tr valign="top" align="right" class="navbar" width="100%">
						<td>
							<table width="100%" cellspacing="0" cellpadding="2">
								<tr width="100%">
									<td colspan="4">
										<table cellspacing="0" cellpadding="0" width="100%">
											<tr>
												<xsl:apply-templates select="applications"/>
											</tr>
										</table>
									</td>
								</tr>
								<tr width="100%">
									<td width="33%" class="info"><xsl:value-of select="user_info_name"/></td>
										<xsl:choose>
											<xsl:when test="current_users">
												<xsl:variable name="url_current_users"><xsl:value-of select="url_current_users"/></xsl:variable>
												<td width="33%" class="info"><a href="{$url_current_users}"><xsl:value-of select="current_users"/></a></td>
											</xsl:when>
											<xsl:otherwise>
												<td width="33%"></td>
											</xsl:otherwise>
										</xsl:choose>
									<td width="33%" class="info" align="right"><xsl:value-of select="user_info_date"/></td>
									<td>
										<table cellspacing="0" cellpadding="0" align="right">
											<tr>
												<td><a href="{$home_link}" onMouseOver="" onMouseOut=""><img src="{$home_img}" border="0" name="nine" alt="{$home_title}" title="{$home_title}"/></a></td>
												<td><a href="{$prefs_link}" onMouseOver="" onMouseOut=""><img src="{$prefs_img}" border="0" name="ten" alt="{$prefs_title}" title="{$prefs_title}"/></a></td>
												<td><a href="{$logout_link}" onMouseOver="" onMouseOut=""><img src="{$logout_img}" border="0" name="eleven" alt="{$logout_title}" title="{$logout_title}"/></a></td>
												<td><a href="{$about_link}" onMouseOver="" onMouseOut=""><img src="{$about_img}" border="0" name="about" alt="{$about_title}" title="{$about_title}"/></a></td>
											</tr>
										</table>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
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
								<xsl:otherwise>
									<xsl:value-of disable-output-escaping="yes" select="body_data"/>
								</xsl:otherwise>
							</xsl:choose>
						</td>
					</tr>
					<tr valign="top">
						<td align="center" valign="top" class="bottom">
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
	<xsl:variable name="icon"><xsl:value-of select="icon"/></xsl:variable>
	<xsl:variable name="title"><xsl:value-of select="title"/></xsl:variable>
		<td>
				<a href="{$url}" onMouseOver="" onMouseOut=""><img src="{$icon}" border="0" alt="{$title}" title="{$title}" name="{$name}"/></a>
		</td>
	</xsl:template>

