<!-- $Id$ -->

	<xsl:template name="search_field">
		<xsl:variable name="select_action"><xsl:value-of select="select_action"/></xsl:variable>
		<xsl:variable name="query"><xsl:value-of select="query"/></xsl:variable>
		<xsl:variable name="lang_submit"><xsl:value-of select="lang_submit"/></xsl:variable>
			<form method="post" action="{$select_action}">
				<input type="text" class="forms" name="query" value="{$query}" onMouseover="window.status='Enter the search string. To show all entries, empty this field and press the SUBMIT button again.';return true;" onMouseout="window.status='';return true;"/>
				<xsl:text> </xsl:text>
				<input type="submit" class="forms" name="submit" value="{$lang_submit}" onMouseover="window.status='Submit the search string.';return true;" onMouseout="window.status='';return true;"/> 
			</form>
	</xsl:template>
