<!-- $Id$ -->

	<xsl:template name="help_data">
		<xsl:apply-templates select="xhelp"/>
	</xsl:template>

	<xsl:template match="xhelp">
		<xsl:choose>
			<xsl:when test="overview">
				<xsl:apply-templates select="overview"/>
			</xsl:when>
			<xsl:otherwise>
				<xsl:apply-templates select="add"/>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>


	<xsl:template match="overview">
		<table>
			<tr>
				<td>
					<xsl:value-of disable-output-escaping="yes" select="intro"/>
				</td>
			</tr>
		</table>
	</xsl:template>

	<xsl:template match="add">
		<table>
			<tr>
				<td><xsl:value-of disable-output-escaping="yes" select="intro"/><br/>
					<table width="80%" bgcolor="#ccddeb">
						<tr>
							<td><xsl:value-of select="lang_lastname"/>:</td>
							<td><xsl:value-of select="lang_firstname"/>:</td>
						</tr>
						<tr>
							<td><xsl:value-of select="lang_email"/>:</td>
							<td><xsl:value-of select="lang_company"/>:</td>
						</tr>
						<tr>
							<td><xsl:value-of select="lang_homephone"/>:</td>
							<td><xsl:value-of select="lang_fax"/>:</td>
						</tr>
						<tr>
							<td><xsl:value-of select="lang_workphone"/>:</td>
							<td><xsl:value-of select="lang_pager"/>:</td>
						</tr>
						<tr>
							<td><xsl:value-of select="lang_mobile"/>:</td>
							<td><xsl:value-of select="lang_othernumber"/>:</td>
						</tr>
						<tr>
							<td><xsl:value-of select="lang_street"/>:</td>
							<td><xsl:value-of select="lang_city"/>:</td>
						</tr>
						<tr>
							<td><xsl:value-of select="lang_state"/>:</td>
							<td><xsl:value-of select="lang_zip"/>:</td>
						</tr>
						<tr>
							<td><xsl:value-of select="lang_access"/>:</td>
							<td><xsl:value-of select="lang_groupsettings"/>:</td>
						</tr>
						<tr>
							<td><xsl:value-of select="lang_notes"/>:</td>
							<td><xsl:value-of select="lang_birthday"/>:</td>
						</tr>
					</table>
					<xsl:value-of disable-output-escaping="yes" select="end"/><br/>
					<xsl:value-of disable-output-escaping="yes" select="access_descr"/><br/>
				</td>
			</tr>
		</table>
	</xsl:template>
