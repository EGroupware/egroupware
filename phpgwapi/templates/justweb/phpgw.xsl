<!-- $Id$ -->

	<xsl:template match="phpgw">
	<xsl:variable name="phpgw_css_file"><xsl:value-of select="phpgw_css_file"/></xsl:variable>
	<xsl:variable name="theme_css_file"><xsl:value-of select="theme_css_file"/></xsl:variable>
	<xsl:variable name="webserver_url"><xsl:value-of select="webserver_url"/></xsl:variable>
	<xsl:variable name="charset"><xsl:value-of select="charset"/></xsl:variable>
	<xsl:variable name="onload"><xsl:value-of select="onload"/></xsl:variable>
	<xsl:variable name="logo_img"><xsl:value-of select="logo_img"/></xsl:variable>
	<xsl:variable name="nav_bar_left_top_bg_img"><xsl:value-of select="nav_bar_left_top_bg_img"/></xsl:variable>
	<xsl:variable name="home_link"><xsl:value-of select="home_link"/></xsl:variable>
	<xsl:variable name="prefs_link"><xsl:value-of select="prefs_link"/></xsl:variable>
	<xsl:variable name="logout_link"><xsl:value-of select="logout_link"/></xsl:variable>
	<xsl:variable name="about_link"><xsl:value-of select="about_link"/></xsl:variable>
	<xsl:variable name="help_link"><xsl:value-of select="help_link"/></xsl:variable>
	<xsl:variable name="home_img"><xsl:value-of select="home_img"/></xsl:variable>
	<xsl:variable name="prefs_img"><xsl:value-of select="prefs_img"/></xsl:variable>
	<xsl:variable name="logout_img"><xsl:value-of select="logout_img"/></xsl:variable>
	<xsl:variable name="about_img"><xsl:value-of select="about_img"/></xsl:variable>
	<xsl:variable name="help_img"><xsl:value-of select="help_img"/></xsl:variable>
	<xsl:variable name="home_title"><xsl:value-of select="home_title"/></xsl:variable>
	<xsl:variable name="prefs_title"><xsl:value-of select="prefs_title"/></xsl:variable>
	<xsl:variable name="logout_title"><xsl:value-of select="logout_title"/></xsl:variable>
	<xsl:variable name="about_title"><xsl:value-of select="about_title"/></xsl:variable>
	<xsl:variable name="help_title"><xsl:value-of select="help_title"/></xsl:variable>
	<xsl:variable name="app_tpl"><xsl:value-of select="app_tpl"/></xsl:variable>
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
				<script type="text/javascript" language="javascript" src="{$webserver_url}/phpgwapi/templates/justweb/navcond.js"></script>
				<script type="text/javascript" language="javascript" src="{$webserver_url}/phpgwapi/templates/justweb/scripts.js"></script>
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
			<body onLoad="init();">
				<table width="100%" height="100%" cellspacing="0" cellpadding="0">
					<tr>
						<td colspan="2" valign="top">
						<!-- BEGIN top_part -->
							<table width="100%" cellspacing="0" cellpadding="0">
								<tr>
									<td width="218" height="33" class="top_top"><img src="{$logo_img}" alt="phpGroupWare" title="phpGroupWare"/></td>
									<td width="100%" valign="bottom" class="top_top"> </td>
									<td valign="bottom" width="56" class="top"><a href="{$home_link}"><img src="{$home_img" width="56" height="23" border="0" alt="{$home_title}" title="{$home_title}"/></a></td>
									<xsl:if test="$prefs_link != ''">
										<td valign="bottom" width="85" class="top"><a href="{$prefs_link}"><img src="{$prefs_img}" width="85" height="23" border="0" alt="{$prefs_title}" title="{$prefs_title}"/></a></td>
									</xsl:if>
									<td valign="bottom" width="56" class="top"><a href="{$logout_link}"><img src="{$logout_img}" width="56" height="23" border="0" alt="{$logout_title}" title="{$logout_title}"/></a></td>
									<td valign="bottom" width="39" class="top"><a href="{$about_link}"><img src="{$about_img}" width="39" height="23" border="0" alt="{$about_title}" title="{$about_title}"/></a></td>
									<td valign="bottom" width="39" class="top"><a href="{$help_link}" target="_blank"><img src="{$help_img}" width="39" height="23" border="0" alt="{$help_title}" title="{$help_title}"/></a></td>
								</tr>
							</table>
						</td>
					</tr>
					<!-- END top_part -->
					<tr valign="top">
						<td rowspan="2">
						<!-- BEGIN left_part -->
							<table width="59" cellspacing="0" cellpadding="0" height="100%" valign="top">
								<tr>
									<td height="7" width="59" valign="top" class="left_top"/>
								</tr>
								<xsl:apply-templates select="applications">
									<xsl:with-param name="navbar_format" select="navbar_format"/>
								</xsl:apply-templates>
								<tr>
									<td width="59" height="100%" valign="top" class="left"/>
								</tr>
								<tr>
									<td height="7" width="59" valign="top" class="left_bottom"/>
								</tr>
							</table>
						<!-- END left_part -->
						</td>
						<!-- BEGIN app_header -->
						<td height="15">
							<xsl:choose>
								<xsl:when test="app_header">
									<xsl:attribute name="class">app_header</xsl:attribute>
									<xsl:value-of disable-output-escaping="yes" select="app_header"/>
								</xsl:when>
							</xsl:choose>
						</td>
						<!-- END app_header -->
					</tr>
					<tr valign="top">
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
					<tr>
						<td class="top" height="20" colspan="2" align="center">
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
	<xsl:variable name="url"><xsl:value-of select="url"/></xsl:variable>
	<xsl:variable name="icon"><xsl:value-of select="icon"/></xsl:variable>
	<xsl:variable name="title"><xsl:value-of select="title"/></xsl:variable>
		<tr>
			<td class="left" align="center">
				<a href="{$url}">
					<xsl:if test="$navbar_format != 'text'">
						<img src="{$icon}" border="0" alt="{$title}" title="{$title}"/>
					</xsl:if>
					<xsl:if test="$navbar_format != 'icons'">
						<br/><xsl:value-of select="title"/>
					</xsl:if>
				</a>
			</td>
		</tr>
	</xsl:template>
