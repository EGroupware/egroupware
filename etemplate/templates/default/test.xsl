<!-- $Id$ -->

<xsl:template match="PHPGW">
	<xsl:value-of select="label"/>
	<input>
		<xsl:attribute name="name"><xsl:value-of select="name"/></xsl:attribute>
		<xsl:attribute name="value"><xsl:value-of select="value"/></xsl:attribute>
		<xsl:choose>
			<xsl:when test="readonly!=0">
				<xsl:attribute name="READONLY"/>
			</xsl:when>
		</xsl:choose>
		<xsl:choose>
			<xsl:when test="statustext!=''">
				<xsl:attribute name="onFocus">self.status='<xsl:value-of select="statustext"/>'; return true;</xsl:attribute>
				<xsl:attribute name="onBlur">self.status=''; return true;</xsl:attribute>
			</xsl:when>
		</xsl:choose>
		<xsl:choose>
			<xsl:when test="attr/class!=''">
				<xsl:attribute name="class"><xsl:value-of select="attr/class"/></xsl:attribute>
			</xsl:when>
		</xsl:choose>
	</input>
</xsl:template>
