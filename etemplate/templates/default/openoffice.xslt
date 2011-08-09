<?xml version="1.0" encoding="ISO-8859-1"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" 
	xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
	xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
	xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
	xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
	xmlns:xlink="http://www.w3.org/1999/xlink"
	xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0"
	xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"
	xmlns:str="http://exslt.org/strings"
	extension-element-prefixes="str"
>
	<xsl:output method="xml" omit-xml-declaration="yes"/>

	<xsl:variable name="custom-styles">
		<style:style style:name="Custom" style:family="text" />
	</xsl:variable>
	<xsl:template match="node()|@*">
		<xsl:copy>
			<xsl:apply-templates select="node()|@*"/>
		</xsl:copy>
	</xsl:template>
	
	<!-- Fonts -->
	<xsl:template match="office:font-face-decls">
		<xsl:copy>
			<xsl:apply-templates/>
			<xsl:call-template name="extract-fonts"/>
		</xsl:copy>
	</xsl:template>
	
	<!-- Add in some known styles -->
	<xsl:template match="office:automatic-styles">
		<xsl:copy>
			<xsl:apply-templates/>
			<style:style style:name="Tbold" style:family="text">
				<style:text-properties fo:font-weight="bold" style:font-weight-asian="bold"  style:font-weight-complex="bold"/>
			</style:style>
			<style:style style:name="Titalics" style:family="text">
				<style:text-properties fo:font-style="italic"  style:font-style-asian="italic" style:font-style-complex="italic"/>
			</style:style>
			<style:style style:name="Tunderline" style:family="text">
				<style:text-properties style:text-underline-style="solid" style:text-underline-width="auto" style:text-underline-color="font-color"/>
			</style:style>
			<xsl:copy-of select="$custom-styles" />
			<xsl:call-template name="extract-styles" />
		</xsl:copy>
<!-- Pre-made styles from http://fisheye.liip.ch/browse/PUB/fluxcms/branches/matrix/inc/bx/editors/ooo/html2odt.xsl?r=9331 -->
		<office:styles>
	<style:style style:name="Pol" style:family="paragraph" style:parent-style-name="Standard" style:list-style-name="LO">
           <style:text-properties style:text-position="0% 100%"/>
       </style:style>
	<style:style style:name="Pul" style:family="paragraph" style:parent-style-name="Standard" style:list-style-name="LU">
           <style:text-properties style:text-position="0% 100%"/>
       </style:style>
       <style:style style:name="TableX" style:family="table">
           <style:table-properties style:width="16.999cm" table:align="margins"/>
       </style:style>
       <style:style style:name="TableX.A" style:family="table-column">
           <style:table-column-properties style:column-width="5.666cm" style:rel-column-width="21845*"/>
       </style:style>
       <style:style style:name="TableX.A1" style:family="table-cell">
           <style:table-cell-properties fo:padding="0.097cm" fo:border="0.002cm solid #000000" />
       </style:style>
	<style:style style:name="Numbering_20_Symbols" style:display-name="Numbering Symbols" style:family="text"/>
	<style:style style:name="Bullet_20_Symbols" style:display-name="Bullet Symbols" style:family="text">
		<style:text-properties style:font-name="StarSymbol" fo:font-size="9pt" style:font-name-asian="StarSymbol" style:font-size-asian="9pt" style:font-name-complex="StarSymbol" style:font-size-complex="9pt"/>
	</style:style>
	<text:list-style style:name="LO">
           <text:list-level-style-number text:level="1" text:style-name="Numbering_20_Symbols" style:num-suffix="." style:num-format="1">
               <style:list-level-properties text:space-before="0.635cm" text:min-label-width="0.635cm"/>
           </text:list-level-style-number>
           <text:list-level-style-number text:level="2" text:style-name="Numbering_20_Symbols" style:num-suffix="." style:num-format="1">
               <style:list-level-properties text:space-before="1.27cm" text:min-label-width="0.635cm"/>
           </text:list-level-style-number>
           <text:list-level-style-number text:level="3" text:style-name="Numbering_20_Symbols" style:num-suffix="." style:num-format="1">
               <style:list-level-properties text:space-before="1.905cm" text:min-label-width="0.635cm"/>
           </text:list-level-style-number>
           <text:list-level-style-number text:level="4" text:style-name="Numbering_20_Symbols" style:num-suffix="." style:num-format="1">
               <style:list-level-properties text:space-before="2.54cm" text:min-label-width="0.635cm"/>
           </text:list-level-style-number>
           <text:list-level-style-number text:level="5" text:style-name="Numbering_20_Symbols" style:num-suffix="." style:num-format="1">
               <style:list-level-properties text:space-before="3.175cm" text:min-label-width="0.635cm"/>
           </text:list-level-style-number>
           <text:list-level-style-number text:level="6" text:style-name="Numbering_20_Symbols" style:num-suffix="." style:num-format="1">
               <style:list-level-properties text:space-before="3.81cm" text:min-label-width="0.635cm"/>
           </text:list-level-style-number>
           <text:list-level-style-number text:level="7" text:style-name="Numbering_20_Symbols" style:num-suffix="." style:num-format="1">
               <style:list-level-properties text:space-before="4.445cm" text:min-label-width="0.635cm"/>
           </text:list-level-style-number>
           <text:list-level-style-number text:level="8" text:style-name="Numbering_20_Symbols" style:num-suffix="." style:num-format="1">
               <style:list-level-properties text:space-before="5.08cm" text:min-label-width="0.635cm"/>
           </text:list-level-style-number>
           <text:list-level-style-number text:level="9" text:style-name="Numbering_20_Symbols" style:num-suffix="." style:num-format="1">
               <style:list-level-properties text:space-before="5.715cm" text:min-label-width="0.635cm"/>
           </text:list-level-style-number>
           <text:list-level-style-number text:level="10" text:style-name="Numbering_20_Symbols" style:num-suffix="." style:num-format="1">
               <style:list-level-properties text:space-before="6.35cm" text:min-label-width="0.635cm"/>
           </text:list-level-style-number>
       </text:list-style>
       <text:list-style style:name="LU">
           <text:list-level-style-bullet text:level="1" text:style-name="Bullet_20_Symbols" style:num-suffix="." text:bullet-char="&#8226;">
               <style:list-level-properties text:space-before="0.635cm" text:min-label-width="0.635cm"/>
               <style:text-properties style:font-name="StarSymbol"/>
           </text:list-level-style-bullet>
           <text:list-level-style-bullet text:level="2" text:style-name="Bullet_20_Symbols" style:num-suffix="." text:bullet-char="&#8226;">
               <style:list-level-properties text:space-before="1.27cm" text:min-label-width="0.635cm"/>
               <style:text-properties style:font-name="StarSymbol"/>
           </text:list-level-style-bullet>
           <text:list-level-style-bullet text:level="3" text:style-name="Bullet_20_Symbols" style:num-suffix="." text:bullet-char="&#8226;">
               <style:list-level-properties text:space-before="1.905cm" text:min-label-width="0.635cm"/>
               <style:text-properties style:font-name="StarSymbol"/>
           </text:list-level-style-bullet>
           <text:list-level-style-bullet text:level="4" text:style-name="Bullet_20_Symbols" style:num-suffix="." text:bullet-char="&#8226;">
               <style:list-level-properties text:space-before="2.54cm" text:min-label-width="0.635cm"/>
               <style:text-properties style:font-name="StarSymbol"/>
           </text:list-level-style-bullet>
           <text:list-level-style-bullet text:level="5" text:style-name="Bullet_20_Symbols" style:num-suffix="." text:bullet-char="&#8226;">
               <style:list-level-properties text:space-before="3.175cm" text:min-label-width="0.635cm"/>
               <style:text-properties style:font-name="StarSymbol"/>
           </text:list-level-style-bullet>
           <text:list-level-style-bullet text:level="6" text:style-name="Bullet_20_Symbols" style:num-suffix="." text:bullet-char="&#8226;">
               <style:list-level-properties text:space-before="3.81cm" text:min-label-width="0.635cm"/>
               <style:text-properties style:font-name="StarSymbol"/>
           </text:list-level-style-bullet>
           <text:list-level-style-bullet text:level="7" text:style-name="Bullet_20_Symbols" style:num-suffix="." text:bullet-char="&#8226;">
               <style:list-level-properties text:space-before="4.445cm" text:min-label-width="0.635cm"/>
               <style:text-properties style:font-name="StarSymbol"/>
           </text:list-level-style-bullet>
           <text:list-level-style-bullet text:level="8" text:style-name="Bullet_20_Symbols" style:num-suffix="." text:bullet-char="&#8226;">
               <style:list-level-properties text:space-before="5.08cm" text:min-label-width="0.635cm"/>
               <style:text-properties style:font-name="StarSymbol"/>
           </text:list-level-style-bullet>
           <text:list-level-style-bullet text:level="9" text:style-name="Bullet_20_Symbols" style:num-suffix="." text:bullet-char="&#8226;">
               <style:list-level-properties text:space-before="5.715cm" text:min-label-width="0.635cm"/>
               <style:text-properties style:font-name="StarSymbol"/>
           </text:list-level-style-bullet>
           <text:list-level-style-bullet text:level="10" text:style-name="Bullet_20_Symbols" style:num-suffix="." text:bullet-char="&#8226;">
               <style:list-level-properties text:space-before="6.35cm" text:min-label-width="0.635cm"/>
               <style:text-properties style:font-name="StarSymbol"/>
           </text:list-level-style-bullet>
       </text:list-style>
		</office:styles>
	</xsl:template>

	<!-- Generate custom styles based on the span styles -->
	<xsl:template name="extract-fonts">
		<xsl:for-each select="//span[@style]">
			<xsl:variable name="style" select="str:tokenize(@style,';')" />
				<xsl:for-each select="$style">
					<xsl:choose>
						<xsl:when test="starts-with(.,'font-family:')">
							<xsl:variable name="font-name" select="translate(substring-before(substring-after(.,'font-family:'),','),&quot;&#39;&quot;,'')" />
							<xsl:variable name="generic" select="translate(substring-before(substring-after(.,','),','),&quot;&#39; &quot; ,'')" />
			<style:font-face style:name="{$font-name}" svg:font-family="{$font-name}" style:font-family-generic="{$generic}" />
						</xsl:when>
					</xsl:choose>
				</xsl:for-each>
		</xsl:for-each>
	</xsl:template>

	<xsl:template name="extract-styles">
		<xsl:for-each select="//span[@style]">
			<xsl:variable name="style" select="str:tokenize(@style,';')" />
			<style:style style:name="TSpan{generate-id(.)}" style:family="text">
				<xsl:for-each select="$style">
					<xsl:choose>
						<xsl:when test="starts-with(.,'color:')">
							<xsl:variable name="hex">
								<xsl:choose>
								<xsl:when test="contains(., 'rgb(')">
									<xsl:call-template name="rbga-to-hex">
										<xsl:with-param name="rgba-val" select="substring-after(.,':')"/>
									</xsl:call-template>
								</xsl:when>
								<xsl:otherwise>
									<xsl:value-of select="substring-after(.,'#')"/>
								</xsl:otherwise>
								</xsl:choose>
							</xsl:variable>
							<style:text-properties fo:color="#{$hex}"/>
						</xsl:when>
						<xsl:when test="starts-with(.,'background-color:')">
							<xsl:variable name="hex">
								<xsl:call-template name="rbga-to-hex">
									<xsl:with-param name="rgba-val" select="substring-after(.,':')"/>
								</xsl:call-template>
							</xsl:variable>
							<style:text-properties fo:background-color="#{$hex}"/>
						</xsl:when>
						<xsl:when test="starts-with(.,'font-size:')">
							<xsl:variable name="font-size" select="substring-after(text(),'font-size:')" />
							<!-- Approximate conversion that seems to work -->
							<xsl:variable name="size" select="ceiling(number(translate($font-size,translate($font-size,'0123456789',''),'')))"/>
							<style:text-properties fo:font-size="{$size}pt" fo:font-size-asian="{$size}pt"/>
						</xsl:when>
						<xsl:when test="starts-with(.,'font-family:')">

							<xsl:variable name="font-name" select="translate(substring-before(substring-after(.,'font-family:'),','),&quot;&#39;&quot;,'')" />
							<style:text-properties style:font-name="{$font-name}"/>
						</xsl:when>
					</xsl:choose>
				</xsl:for-each>
			</style:style>
		</xsl:for-each>
	</xsl:template>

	<!-- Simple, use known styles -->
	<xsl:template match="strong">
		<text:span text:style-name="Tbold"><xsl:apply-templates/></text:span>
	</xsl:template>
	<xsl:template match="em|i">
		<text:span text:style-name="Titalics"><xsl:apply-templates/></text:span>
	</xsl:template>
	<xsl:template match="u">
		<text:span text:style-name="Tunderline"><xsl:apply-templates/></text:span>
	</xsl:template>

	<xsl:template match="ul">
		<text:list text:style-name="LU">
			<xsl:apply-templates/>
		</text:list>
	</xsl:template>

	<xsl:template match="ol">
		<text:list text:style-name="LO">
			<xsl:apply-templates/>
		</text:list>
	</xsl:template>

	<xsl:template match="li">
		<xsl:variable name="list_style">
			<xsl:choose>
				<xsl:when test="name(..) = 'ul'">Pul</xsl:when>
				<xsl:otherwise>Pol</xsl:otherwise>
			</xsl:choose>
		</xsl:variable>
		<text:list-item><text:p text:style-name="{$list_style}">
			<xsl:apply-templates/>
		</text:p></text:list-item>
	</xsl:template>

	<xsl:template match="table">
		<table:table table:name="Table{generate-id(.)}" table:style-name="TableX">
			<table:table-column table:style-name="TableX.A" table:number-columns-repeated="{count(tr[position() = 1]/td | tr[position() = 1]/th)}"/>
			<xsl:apply-templates/>
		</table:table>
	</xsl:template>

	<xsl:template match="tr[th]">
		<table:table-header-rows><table:table-row>
			<xsl:apply-templates/>
		</table:table-row></table:table-header-rows>
	</xsl:template>

	<xsl:template match="th">
		<table:table-cell table:style-name="TableX.A1">
			<xsl:apply-templates/>
		</table:table-cell>
	</xsl:template>

	<xsl:template match="td">
		<table:table-cell table:style-name="TableX.A1">
			<text:p><xsl:apply-templates/></text:p>
		</table:table-cell>
	</xsl:template>

	<xsl:template match="tr">
		<table:table-row>
			<xsl:apply-templates/>
		</table:table-row>
	</xsl:template>

	<xsl:template match="a">
		<text:a xlink:href="{@href}">
			<xsl:apply-templates/>
		</text:a>
	</xsl:template>

	<!-- Need to add styles -->
	<xsl:template match="span">
		<text:span text:style-name="TSpan{generate-id(.)}"><xsl:apply-templates/></text:span>
	</xsl:template>
	
	<!-- Convert rgb(r,g,b) to hex RGB values -->
	<xsl:template name="rbga-to-hex">
		<xsl:param name="rgba-val"/>
		<xsl:param name="count" select="1"/>
		<xsl:variable name="val" select="substring-before($rgba-val,',')"/>
		<xsl:variable name="tail" select="substring-after($rgba-val,concat($val,','))"/>
		<xsl:choose>
			<xsl:when test="$count &lt; 3">
				<xsl:call-template name="to-hex">
					<xsl:with-param name="val" select="$val"/>
				</xsl:call-template>
				<xsl:call-template name="rbga-to-hex">
					<xsl:with-param name="count" select="$count + 1"/>
					<xsl:with-param name="rgba-val" select="$tail"/>
				</xsl:call-template>
			</xsl:when>
			<xsl:otherwise>
				<xsl:call-template name="to-hex">
					<xsl:with-param name="val" select="$rgba-val"/>
				</xsl:call-template>				
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

	<xsl:template name="to-hex">
		<xsl:param name="val"/>
		<xsl:param name="max" select="255"/>
		<xsl:param name="min" select="0"/>
		<xsl:param name="hex-key" select="'0123456789abcdef'"/>

		<!-- REMOVE NON-NUMERIC CHARACTERS -->
		<xsl:variable name="val"
			select="translate($val,'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ,.-_=+!@#$%^*():; ','')"/>

		<!-- insure that the rgb value is within 0-255 -->
		<xsl:variable name="num">
			<xsl:choose>

				<xsl:when test="$val &gt; $max">
					<xsl:value-of select="$max"/>
				</xsl:when>

				<xsl:when test="$val &lt; $min">
					<xsl:value-of select="$min"/>
				</xsl:when>

				<!-- insure that we have whole numbers -->
				<xsl:otherwise>
					<xsl:value-of select="round($val)"/>
				</xsl:otherwise>
			</xsl:choose>
		</xsl:variable>

		<!-- Return Hex Val -->
		<!-- substring(string, position, length) -->
		<xsl:value-of select="concat( substring($hex-key,(ceiling(($num - ceiling($num mod 16)) div 16)+1),1),
		    substring($hex-key,($num mod 16)+1,1)
		)"/>
	</xsl:template>
</xsl:stylesheet>
