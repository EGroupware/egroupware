<!-- $Id$ -->

	<xsl:template name="filter_select">
		<xsl:apply-templates select="filter_list"/>
	</xsl:template>

	<xsl:template match="filter_list">
	<xsl:variable name="key"><xsl:value-of select="key"/></xsl:variable>
		<xsl:choose>
			<xsl:when test="selected">
				<option value="{$key}" selected="selected"><xsl:value-of select="lang"/></option>
			</xsl:when>
			<xsl:otherwise>
				<option value="{$key}"><xsl:value-of select="lang"/></option>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
