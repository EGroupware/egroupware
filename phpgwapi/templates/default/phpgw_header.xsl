<!-- $Id$ -->

	<xsl:template match="phpgw">
	<xsl:variable name="phpgw_css_file" select="phpgw_css_file"/>
	<xsl:variable name="theme_css_file" select="theme_css_file"/>
	<xsl:variable name="charset" select="charset"/>
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
				<table width="100%" height="100%" cellspacing="0" cellpadding="0">
					<tr valign="top" width="100%">
						<td>
							<xsl:choose>
								<xsl:when test="$current_app = 'help'">
									<xsl:call-template name="help"/>
								</xsl:when>
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
