<!-- $Id$ -->

	<xsl:template match="phpgw">
	<xsl:variable name="phpgw_css_file" select="phpgw_css_file"/>
	<xsl:variable name="theme_css_file" select="theme_css_file"/>
	<xsl:variable name="charset" select="charset"/>
	<xsl:variable name="onload" select="onload"/>
	<xsl:variable name="home_link" select="home_link"/>
	<xsl:variable name="prefs_link" select="prefs_link"/>
	<xsl:variable name="logout_link" select="logout_link"/>
	<xsl:variable name="about_link" select="about_link"/>
	<xsl:variable name="help_link" select="help_link"/>
	<xsl:variable name="home_img" select="home_img"/>
	<xsl:variable name="prefs_img" select="prefs_img"/>
	<xsl:variable name="logout_img" select="logout_img"/>
	<xsl:variable name="about_img" select="about_img"/>
	<xsl:variable name="help_img" select="help_img"/>
	<xsl:variable name="home_title" select="home_title"/>
	<xsl:variable name="prefs_title" select="prefs_title"/>
	<xsl:variable name="logout_title" select="logout_title"/>
	<xsl:variable name="about_title" select="about_title"/>
	<xsl:variable name="help_title" select="help_title"/>
	<xsl:variable name="navbar_format" select="navbar_format"/>
	<xsl:variable name="app_tpl" select="app_tpl"/>
	<xsl:variable name="current_app" select="current_app"/>
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
										<xsl:choose>
											<xsl:when test="navbar_format = 'text'">
												<xsl:value-of disable-output-escaping="yes" select="app_tabs"/>
											</xsl:when>
											<xsl:otherwise>
												<table cellspacing="0" cellpadding="0" width="100%">
													<tr>
														<xsl:apply-templates select="applications">
															<xsl:with-param name="navbar_format" select="navbar_format"/>
														</xsl:apply-templates>
													</tr>
												</table> 
											</xsl:otherwise>
										</xsl:choose>
									</td>
								</tr>
								<tr width="100%">
									<td width="25%" class="info"><xsl:value-of select="user_info_name"/></td>
										<xsl:choose>
											<xsl:when test="current_users">
												<xsl:variable name="url_current_users"><xsl:value-of select="url_current_users"/></xsl:variable>
												<td width="25%" nowrap="nowrap" class="info"><a href="{$url_current_users}"><xsl:value-of select="current_users"/></a></td>
											</xsl:when>
											<xsl:otherwise>
												<td width="25%"> </td>
											</xsl:otherwise>
										</xsl:choose>
									<td width="25%" class="info" align="right"><xsl:value-of select="user_info_date"/></td>
									<xsl:choose>
										<xsl:when test="navbar_format = 'text'">
											<td align="right">
												<xsl:value-of disable-output-escaping="yes" select="base_tabs"/>
											</td>
										</xsl:when>
										<xsl:otherwise>
											<td>
												<table cellspacing="0" cellpadding="0" align="right">
													<tr>
														<td><a href="{$home_link}" onMouseOver="" onMouseOut=""><img src="{$home_img}" border="0" name="nine" alt="{$home_title}" title="{$home_title}"/></a></td>
														<xsl:if test="$prefs_link != ''">
															<td><a href="{$prefs_link}" onMouseOver="" onMouseOut=""><img src="{$prefs_img}" border="0" name="ten" alt="{$prefs_title}" title="{$prefs_title}"/></a></td>
														</xsl:if>
														<td><a href="{$logout_link}" onMouseOver="" onMouseOut=""><img src="{$logout_img}" border="0" name="eleven" alt="{$logout_title}" title="{$logout_title}"/></a></td>
														<td><a href="{$about_link}" onMouseOver="" onMouseOut=""><img src="{$about_img}" border="0" name="about" alt="{$about_title}" title="{$about_title}"/></a></td>
														<td><a href="{$help_link}" onMouseOver="" onMouseOut="" target="_blank"><img src="{$help_img}" border="0" name="help" alt="{$help_title}" title="{$help_title}"/></a></td>
													</tr>
												</table>
											</td>
										</xsl:otherwise>
									</xsl:choose>
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
								<xsl:when test="$current_app = 'home'">
									<xsl:call-template name="portal"/>
								</xsl:when>
								<xsl:when test="$current_app = 'about'">
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
	<xsl:param name="navbar_format"/>
	<xsl:variable name="url" select="url"/>
	<xsl:variable name="icon" select="icon"/>
	<xsl:variable name="title" select="title"/>
		<td align="center">
				<a href="{$url}">
					<xsl:if test="$navbar_format != 'text'">
						<img src="{$icon}" border="0" alt="{$title}" title="{$title}"/>
					</xsl:if>
					<xsl:if test="$navbar_format != 'icons'">
						<br/><xsl:value-of select="title"/>
					</xsl:if>
				</a>
		</td>
	</xsl:template>

