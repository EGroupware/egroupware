<!-- $Id$ -->

	<xsl:template match="phpgw">
	<xsl:variable name="phpgw_css_file"><xsl:value-of select="phpgw_css_file"/></xsl:variable>
	<xsl:variable name="theme_css_file"><xsl:value-of select="theme_css_file"/></xsl:variable>
	<xsl:variable name="charset"><xsl:value-of select="charset"/></xsl:variable>
	<xsl:variable name="webserver_url"><xsl:value-of select="webserver_url"/></xsl:variable>
	<xsl:variable name="onload"><xsl:value-of select="onload"/></xsl:variable>
	<xsl:variable name="logo_img"><xsl:value-of select="logo_img"/></xsl:variable>
	<xsl:variable name="nav_bar_left_top_bg_img"><xsl:value-of select="nav_bar_left_top_bg_img"/></xsl:variable>
	<xsl:variable name="home_link"><xsl:value-of select="home_link"/></xsl:variable>
	<xsl:variable name="prefs_link"><xsl:value-of select="prefs_link"/></xsl:variable>
	<xsl:variable name="logout_link"><xsl:value-of select="logout_link"/></xsl:variable>
	<xsl:variable name="about_link"><xsl:value-of select="about_link"/></xsl:variable>
	<xsl:variable name="help_link"><xsl:value-of select="help_link"/></xsl:variable>
	<xsl:variable name="home_img_hover"><xsl:value-of select="home_img_hover"/></xsl:variable>
	<xsl:variable name="prefs_img_hover"><xsl:value-of select="prefs_img_hover"/></xsl:variable>
	<xsl:variable name="logout_img_hover"><xsl:value-of select="logout_img_hover"/></xsl:variable>
	<xsl:variable name="about_img_hover"><xsl:value-of select="about_img_hover"/></xsl:variable>
	<xsl:variable name="help_img_hover"><xsl:value-of select="help_img_hover"/></xsl:variable>
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
				<script type="text/javascript" language="javascript" src="{$webserver_url}/phpgwapi/templates/idsociety/scripts.js"></script>
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
					<tr>
						<td colspan="2" valign="top">
						<!-- BEGIN top_part -->
							<table width="100%" height="73" cellspacing="0" cellpadding="0">
								<tr class="top_top" height="58">
								<!-- top row back images are 58px high, but the row may be smaller than that -->
								<!-- row 2 images are 15 px high, so this table with these 2 rows is 58 plus 15 equals 73px high  -->
									<td width="154" valign="top">
										<img src="{$logo_img}" border="0" alt="phpGroupWare" title="phpGroupWare"/>
									</td>
									<td valign="bottom">
										<table width="100%" cellpadding="0" cellspacing="0">
											<tr>
												<td width="33%" class="info"><xsl:value-of select="user_info_name"/></td>
												<xsl:choose>
													<xsl:when test="current_users">
													<xsl:variable name="url_current_users"><xsl:value-of select="url_current_users"/></xsl:variable>
														<td width="33%" class="info"><a href="{$url_current_users}" class="info"><xsl:value-of select="current_users"/></a></td>
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
												<xsl:if test="$prefs_link != ''">
													<td><a href="{$prefs_link}" onMouseOver="ten.src='{$prefs_img_hover}'" onMouseOut="ten.src='{$prefs_img}'"><img src="{$prefs_img}" border="0" name="ten" alt="{$prefs_title}" title="{$prefs_title}"/></a></td>
												</xsl:if>
												<td><a href="{$logout_link}" onMouseOver="eleven.src='{$logout_img_hover}'" onMouseOut="eleven.src='{$logout_img}'"><img src="{$logout_img}" border="0" name="eleven" alt="{$logout_title}" title="{$logout_title}"/></a></td>
												<td><a href="{$about_link}" onMouseOver="about.src='{$about_img_hover}'" onMouseOut="about.src='{$about_img}'"><img src="{$about_img}" border="0" name="about" alt="{$about_title}" title="{$about_title}"/></a></td>
												<td><a href="{$help_link}" onMouseOver="help.src='{$help_img_hover}'" onMouseOut="help.src='{$help_img}'" target="_blank"><img src="{$help_img}" border="0" name="help" alt="{$help_title}" title="{$help_title}"/></a></td>
											</tr>
										</table>
									</td>
								</tr>
							</table>
						<!-- END top_part -->
						</td>
					</tr>
					<tr valign="top">
						<td rowspan="2">
						<!-- BEGIN left_part -->
							<table cellspacing="0" cellpadding="0" valign="top" class="left" height="100%">
								<xsl:apply-templates select="applications"/>
								<tr>
									<td class="left" valign="top" height="100%"><img src="{$nav_bar_left_top_bg_img}"/></td>
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
