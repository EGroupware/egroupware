<!-- $Id$ -->

	<xsl:template match="login">
	<xsl:variable name="phpgw_head_charset"><xsl:value-of select="phpgw_head_charset"/></xsl:variable>
	<xsl:variable name="login_theme"><xsl:value-of select="login_theme"/></xsl:variable>
		<html>
			<head>
				<meta http-equiv="content-type" content="text/html; charset={$phpgw_head_charset}"/>
				<meta name="author" content="phpGroupWare http://www.phpgroupware.org"/>
				<meta name="description" content="phpGroupWare - Login Page"/>
				<meta name="keywords" content="phpGroupWare"/>
				<meta name="robots" content="none"/>
				<base target="_self"/>
				<link rel="icon" href="favicon.ico" type="image/x-ico"/>
				<link rel="shortcut icon" href="favicon.ico"/>
				<title><xsl:value-of select="phpgw_website_title"/></title>
				<link rel="stylesheet" type="text/css" href="{$login_theme}"/>
			</head>
			<body bgcolor="#FFFFFF">
				<xsl:apply-templates select="login_standard"/>
			</body>
		</html>
	</xsl:template>

	<xsl:template match="login_standard">
	<xsl:variable name="login_layout"><xsl:value-of select="login_layout"/></xsl:variable>
		<table cellpadding="0" cellspacing="0" width="40%" align="center">
			<tr>
				<td>
					<a href="http://www.phpgroupware.org" target="_blank" onMouseout="window.status='';return true;">
						<xsl:attribute name="onMouseover">
							<xsl:text>window.status='</xsl:text>
							<xsl:value-of select="lang_phpgw_statustext"/>
							<xsl:text>'; return true;</xsl:text>
						</xsl:attribute>
						<img src="phpgwapi/templates/{$login_layout}/images/logo.png" alt="phpGroupWare" border="0"/>
					</a>
				</td>
			</tr>
		</table>
		<table cellpadding="0" cellspacing="0" width="40%" align="center" class="th">
		<xsl:choose>
			<xsl:when test="loginscreen">
			<xsl:variable name="login_url"><xsl:value-of select="login_url"/></xsl:variable>
			<xsl:variable name="cookie"><xsl:value-of select="cookie"/></xsl:variable>
			<xsl:variable name="lang_login"><xsl:value-of select="lang_login"/></xsl:variable>
				<tr>
					<td align="center"><xsl:value-of select="phpgw_loginscreen_message"/></td>
				</tr>
				<tr>
					<td>
						<table width="100%" cellpadding="2" cellspacing="1">
							<tr class="th">
								<td valign="middle"><b>phpGroupWare</b></td>
							</tr>
							<tr class="row_off">
								<td valign="bottom">
									<form name="login" method="post" action="{$login_url}">
										<input type="hidden" name="passwd_type" value="text"/>
										<table align="center" width="100%" cellpadding="2" cellspacing="2">
											<tr>
												<td colspan="3" align="center"><xsl:value-of disable-output-escaping="yes" select="msgbox"/></td>
											</tr>
											<tr>
												<td align="right"><xsl:value-of select="lang_username"/></td>
												<td><input name="login" value="{$cookie}"/></td>
												<xsl:choose>
													<xsl:when test="domain_select">
														<td><select name="logindomain"><xsl:apply-templates select="select_domain"/></select></td>
													</xsl:when>
													<xsl:otherwise>
														<td></td>
													</xsl:otherwise>
												</xsl:choose>
											</tr>
											<tr>
												<td align="right"><xsl:value-of select="lang_password"/></td>
												<td><input name="passwd" type="password" onChange="this.form.submit()"/></td>
												<td></td>
											</tr>
											<tr>
												<td colspan="3" align="center">
													<input type="submit" value="{$lang_login}" name="submitit"/>
												</td>
											</tr>
										</table>
									</form>
								</td>
							</tr>
							<tr>
								<td align="right" class="th">
									<xsl:value-of select="phpgw_version"/>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</xsl:when>
			<xsl:otherwise>
				<tr>
					<td align="center">Opps! You caught us in the middle of a system upgrade.<br/>Please, check back with us shortly.</td>
				</tr>
			</xsl:otherwise>
		</xsl:choose>
		</table>
	</xsl:template>
