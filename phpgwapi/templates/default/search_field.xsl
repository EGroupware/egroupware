<!-- $Id$ -->

	<xsl:template name="search_field">
		<xsl:variable name="select_url"><xsl:value-of select="select_url"/></xsl:variable>
		<xsl:variable name="query"><xsl:value-of select="query"/></xsl:variable>
		<xsl:variable name="lang_search"><xsl:value-of select="lang_search"/></xsl:variable>
			<form method="post" action="{$select_url}">
				<input type="text" name="query" value="{$query}" onMouseout="window.status='';return true;">
					<xsl:attribute name="onMouseover">
						<xsl:text>window.status='</xsl:text>
							<xsl:value-of select="lang_searchfield_statustext"/>
						<xsl:text>'; return true;</xsl:text>
					</xsl:attribute>
				</input>
				<xsl:text> </xsl:text>
				<input type="submit" name="submit" value="{$lang_search}" onMouseout="window.status='';return true;"> 
					<xsl:attribute name="onMouseover">
						<xsl:text>window.status='</xsl:text>
							<xsl:value-of select="lang_searchbutton_statustext"/>
						<xsl:text>'; return true;</xsl:text>
					</xsl:attribute>
				</input>
			</form>
	</xsl:template>
