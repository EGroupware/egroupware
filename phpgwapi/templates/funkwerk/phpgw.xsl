<!-- $Id$ -->

	<xsl:template match="phpgw">
	<xsl:variable name="phpgw_css_file"><xsl:value-of select="phpgw_css_file"/></xsl:variable>
	<xsl:variable name="theme_css_file"><xsl:value-of select="theme_css_file"/></xsl:variable>
	<xsl:variable name="charset"><xsl:value-of select="charset"/></xsl:variable>
	<xsl:variable name="webserver_url"><xsl:value-of select="webserver_url"/></xsl:variable>
	<xsl:variable name="logo_img"><xsl:value-of select="logo_img"/></xsl:variable>
	<xsl:variable name="home_link"><xsl:value-of select="home_link"/></xsl:variable>
	<xsl:variable name="prefs_link"><xsl:value-of select="prefs_link"/></xsl:variable>
	<xsl:variable name="logout_link"><xsl:value-of select="logout_link"/></xsl:variable>
	<xsl:variable name="about_link"><xsl:value-of select="about_link"/></xsl:variable>
	<xsl:variable name="help_link"><xsl:value-of select="help_link"/></xsl:variable>
	<xsl:variable name="about_img"><xsl:value-of select="about_img"/></xsl:variable>
	<xsl:variable name="help_img"><xsl:value-of select="help_img"/></xsl:variable>
	<xsl:variable name="home_statustext"><xsl:value-of select="home_statustext"/></xsl:variable>
	<xsl:variable name="prefs_statustext"><xsl:value-of select="prefs_statustext"/></xsl:variable>
	<xsl:variable name="logout_statustext"><xsl:value-of select="logout_statustext"/></xsl:variable>
	<xsl:variable name="about_statustext"><xsl:value-of select="about_statustext"/></xsl:variable>
	<xsl:variable name="help_statustext"><xsl:value-of select="help_statustext"/></xsl:variable>
	<xsl:variable name="top_css_home"><xsl:value-of select="top_css_home"/></xsl:variable>
	<xsl:variable name="top_css_prefs"><xsl:value-of select="top_css_prefs"/></xsl:variable>
	<xsl:variable name="top_css_about"><xsl:value-of select="top_css_about"/></xsl:variable>
	<xsl:variable name="top_css_help"><xsl:value-of select="top_css_help"/></xsl:variable>
	<xsl:variable name="top_css"><xsl:value-of select="top_css"/></xsl:variable>
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
				<link rel="stylesheet" type="text/css" href="{$phpgw_css_file}"/>
				<link rel="stylesheet" type="text/css" href="{$theme_css_file}"/>
			</head>
			<body>
				<!-- BEGIN top_part -->
				<table width="100%" height="100%" cellspacing="2" cellpadding="2" valign="top">
					<tr>
						<td width="23%" height="30" valign="bottom" class="top_top"><a href="" target="blank" onMouseover="window.status='phpGroupWare --> home'; return true;" onMouseout="window.status=''; return true;" class="bottom">[phpGroupWare]</a></td>
						<td height="30" width="19%" valign="bottom" class="user_info">[<xsl:value-of select="user_info_name"/>]</td>
						<xsl:choose>
							<xsl:when test="current_users">
								<xsl:variable name="url_current_users"><xsl:value-of select="url_current_users"/></xsl:variable>
								<td class="admin_info" valign="bottom"><a href="{$url_current_users}" class="admin_info">[<xsl:value-of select="current_users"/>]</a></td>
							</xsl:when>
							<xsl:otherwise>
								<td class="admin_info"/>
							</xsl:otherwise>
						</xsl:choose>
						<td height="30" width="19%" class="user_info" align="right" valign="bottom">[<xsl:value-of select="user_info_date"/>]</td>
					</tr>
					<tr align="right" height="30">
						<td colspan="4" class="top_bottom">
							<table cellpadding="2" cellspacing="2">
								<tr class="top_bottom">
									<td class="top_menu"><a href="{$home_link}" onMouseOver="window.status='{$home_statustext}'; return true;" onMouseOut="window.status='';return true;" class="{$top_css_home}">[<xsl:value-of select="home_title"/>]</a></td>
									<xsl:if test="$prefs_link != ''">
										<td class="top_menu"><a href="{$prefs_link}" onMouseOver="window.status='{$prefs_statustext}'; return true;" onMouseOut="window.status='';return true;" class="{$top_css_prefs}">[<xsl:value-of select="prefs_title"/>]</a></td>
									</xsl:if>
									<td class="top_menu"><a href="{$logout_link}" onMouseOver="window.status='{$logout_statustext}'; return true;" onMouseOut="window.status='';return true;" class="{$top_css}">[<xsl:value-of select="logout_title"/>]</a></td>
									<td class="top_menu"><a href="{$about_link}" onMouseOver="window.status='{$about_statustext}'; return true;" onMouseOut="window.status='';return true;" class="{$top_css_about}">[<xsl:value-of select="about_img"/>]</a></td>
									<td class="top_menu"><a href="{$help_link}" onMouseOver="window.status='{$help_statustext}'; return true;" onMouseOut="window.status='';return true;" class="{$top_css_help}" target="_blank">[<xsl:value-of select="help_img"/>]</a></td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
				<!-- END top_part -->
				<table width="100%" height="100%" cellspacing="0" cellpadding="0" valign="top">
					<tr valign="top" width="100%">
						<td width="131">
						<!-- BEGIN left_part -->
							<table valign="top" cellpadding="2" cellspacing="2" width="131">
								<xsl:apply-templates select="applications"/>
							</table>
						<!-- END left_part -->
						</td>
						<td height="100%" width="100%" valign="top" align="center">
							<table valign="top" cellpadding="2" cellspacing="2" width="100%">
								<xsl:choose>
									<xsl:when test="app_header">
										<tr>
											<td>
												<xsl:attribute name="class">app_header</xsl:attribute>
												<xsl:value-of disable-output-escaping="yes" select="app_header"/>
											</td>
										</tr>
									</xsl:when>
								</xsl:choose>
								<tr>
									<td align="center">
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
							</table>
						</td>
						<td width="3%"/>
					</tr>
					<tr valign="top">
						<td colspan="3" align="center" valign="top" class="bottom">
						<!-- BEGIN bottom_part -->
							[<xsl:value-of select="lang_powered_by"/>
							<a href="http://www.phpgroupware.org" target="blank" onMouseout="window.status='';return true;" class="bottom">
								<xsl:attribute name="onMouseover">
									<xsl:text>window.status='</xsl:text>
									<xsl:value-of select="lang_phpgw_statustext"/>
									<xsl:text>'; return true;</xsl:text>
								</xsl:attribute>
								<xsl:text> phpGroupWare </xsl:text>
							</a>
							<xsl:text> </xsl:text><xsl:value-of select="lang_version"/><xsl:text> </xsl:text><xsl:value-of select="phpgw_version"/>]
						<!-- END bottom_part -->
						</td>
					</tr>
				</table>
			</body>
		</html>
	</xsl:template>

	<xsl:template match="applications">
	<xsl:variable name="url"><xsl:value-of select="url"/></xsl:variable>
	<xsl:variable name="statustext"><xsl:value-of select="statustext"/></xsl:variable>
	<xsl:variable name="css"><xsl:value-of select="css"/></xsl:variable>
		<tr>
			<td height="30" width="131" valign="bottom" class="left">
				<table class="left_app" cellpadding="2" cellspacing="2">
					<tr>
						<td class="left_app">
							<a href="{$url}" onMouseOver="window.status='{$statustext}'; return true;" onMouseOut="window.status='';return true;" class="{$css}">[<xsl:value-of select="title"/>]</a>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</xsl:template>
