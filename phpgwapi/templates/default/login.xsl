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
				<title><xsl:value-of select="phpgw_head_website_title"/></title>
				<link rel="stylesheet" type="text/css" href="{$login_theme}"/>
			</head>
			<body>
				<xsl:apply-templates select="login_standard"/>
			</body>
		</html>
	</xsl:template>

	<xsl:template match="login_standard">
	<xsl:variable name="login_layout"><xsl:value-of select="login_layout"/></xsl:variable>
	<xsl:variable name="login_url"><xsl:value-of select="login_url"/></xsl:variable>
		<p>
			<a href="http://www.phpgroupware.org" target="_blank" onMouseout="window.status='';return true;">
				<xsl:attribute name="onMouseover">
					<xsl:text>window.status='</xsl:text>
					<xsl:value-of select="lang_phpgw_statustext"/>
					<xsl:text>'; return true;</xsl:text>
				</xsl:attribute>
				<img src="phpgwapi/templates/{$login_layout}/images/logo.png" alt="phpGroupWare" border="0"/>
			</a>
		</p>
		<p>
			<xsl:value-of select="phpgw_loginscreen_message"/>
		</p>
		<table cellpadding="2" cellspacing="0" align="center" class="login">
			<xsl:choose>
				<xsl:when test="loginscreen">
				<form name="login" method="post" action="{$login_url}">
				<input type="hidden" name="passwd_type" value="text"/>
				<xsl:variable name="cookie"><xsl:value-of select="cookie"/></xsl:variable>
				<xsl:variable name="lang_login"><xsl:value-of select="lang_login"/></xsl:variable>
				<tr>
					<td colspan="3" align="center"><b><xsl:value-of select="website_title"/></b></td>
				</tr>
				<tr class="row_off">
					<td colspan="3" align="center"><xsl:value-of disable-output-escaping="yes" select="msgbox"/></td>
				</tr>
				<tr class="row_off">
					<td width="33%" align="right"><xsl:value-of select="lang_username"/></td>
					<td width="33%" align="center"><input name="login" value="{$cookie}"/></td>
						<xsl:choose>
							<xsl:when test="domain_select">
								<td><select name="logindomain"><xsl:apply-templates select="domain_select"/></select></td>
							</xsl:when>
							<xsl:otherwise>
								<td></td>
							</xsl:otherwise>
						</xsl:choose>
					</tr>
					<tr class="row_off">
						<td align="right"><xsl:value-of select="lang_password"/></td>
						<td align="center"><input name="passwd" type="password" onChange="this.form.submit()"/></td>
						<td></td>
					</tr>
					<tr class="row_off">
						<td colspan="3" align="center">
							<input type="submit" value="{$lang_login}" name="submitit"/>
						</td>
					</tr>
					<tr>
						<td colspan="3" align="center">
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
					</form>
					</xsl:when>
					<xsl:otherwise>
					<tr>
						<td align="center">Opps! You caught us in the middle of a system upgrade.<br/>Please, check back with us shortly.</td>
					</tr>
					</xsl:otherwise>
				</xsl:choose>
			</table>
	</xsl:template>

	<xsl:template match="domain_select">
	<xsl:variable name="domain"><xsl:value-of select="domain"/></xsl:variable>
		<xsl:choose>
			<xsl:when test="selected">
				<option value="{$domain}" selected="selected"><xsl:value-of select="domain"/></option>
			</xsl:when>
			<xsl:otherwise>
				<option value="{$domain}"><xsl:value-of select="domain"/></option>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
