<!-- $Id$ -->

	<xsl:template match="phpgw">
	<xsl:variable name="phpgw_css_file" select="phpgw_css_file"/>
	<xsl:variable name="theme_css_file" select="theme_css_file"/>
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
	<xsl:variable name="webserver_url"><xsl:value-of select="webserver_url"/></xsl:variable>
		<html>
			<head>
				<meta name="author" content="phpGroupWare http://www.phpgroupware.org"/>
				<meta name="description" content="phpGroupWare"/>
				<meta name="keywords" content="phpGroupWare"/>
				<meta name="robots" content="noindex"/>
				<link rel="icon" href="favicon.ico" type="image/x-ico"/>
				<link rel="shortcut icon" href="favicon.ico"/>
				<title><xsl:value-of select="website_title"/></title>
				<script type="text/javascript" language="javascript" src="{$webserver_url}/phpgwapi/templates/default/scripts.js"></script>
				<xsl:choose>
					<xsl:when test="app_java_script != ''">
						<xsl:value-of disable-output-escaping="yes" select="app_java_script"/>
					</xsl:when>
				</xsl:choose>
				<xsl:choose>
					<xsl:when test="app_java_script_url != ''">
						<xsl:variable name="app_java_script_url" select="app_java_script_url"/>
						<script type="text/javascript" language="javascript" src="{$webserver_url}/{$current_app}/templates/{$app_java_script_url}"></script>
					</xsl:when>
				</xsl:choose>
				<link rel="stylesheet" type="text/css" href="{$phpgw_css_file}"/>
				<link rel="stylesheet" type="text/css" href="{$theme_css_file}"/>
				<xsl:choose>
					<xsl:when test="app_css != ''">
						<style type="text/css">
							<xsl:text>&lt;!--</xsl:text>
								<xsl:value-of disable-output-escaping="yes" select="app_css"/>
							<xsl:text>--&gt;</xsl:text>
						</style>
					</xsl:when>
				</xsl:choose>
				<xsl:choose>
					<xsl:when test="app_css_url != ''">
						<xsl:variable name="app_css_url" select="app_css_url"/>
						<link rel="stylesheet" type="text/css" href="{$webserver_url}/{$current_app}/templates/{$app_css_url}"/>
					</xsl:when>
				</xsl:choose>
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
					<!-- BEGIN app_header -->
					<tr valign="top">
						<td height="15" class="app_body">
							<xsl:choose>
								<xsl:when test="app_header">
									<xsl:attribute name="class">app_header</xsl:attribute>
									<xsl:value-of disable-output-escaping="yes" select="app_header"/>
									<hr/>
								</xsl:when>
							</xsl:choose>
						</td>
					</tr>
					<!-- END app_header -->
					<tr>
						<td width="100%" height="100%" valign="top" align="center" class="app_body">
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
							<table width="100%" cellspacing="0" cellpadding="0" border="0">
								<tr>
									<td align="left" width="30%" class="info">
										<xsl:value-of select="user_info"/>
									</td>
									<td align="center" width="30%" class="info">
										<xsl:choose>
											<xsl:when test="current_users">
											<xsl:variable name="url_current_users"><xsl:value-of select="url_current_users"/></xsl:variable>
												<a href="{$url_current_users}" class="info"><xsl:value-of select="current_users"/></a>
											</xsl:when>
										</xsl:choose>
									</td>
									<td align="right" width="30%" class="info">
										<xsl:value-of select="lang_powered_by"/>
										<a href="http://www.phpgroupware.org" class="info" target="blank" onMouseout="window.status='';return true;">
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
							</table>
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

