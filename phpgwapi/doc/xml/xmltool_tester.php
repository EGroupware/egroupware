<?php
include ('../inc/class.xmltool.inc.php');

/* This is just a funky array I created to test with */
$subarray1['count'] = Array('one','two','three');
$subarray2['count'] = Array('four','five','six');
$myarr['red'] = 'base color';
$myarr['yellow'] = 'base color';
$myarr['blue'] = 'base color';
/* I add an indexed sub-array as an example */
/* for how it will properly handle putting this in xml */
$myarr['green']['mix']=Array('yellow','blue');
$myarr['orange']['mix']=Array('yellow','red');
/* and even an indexed sub-array can have more nesting */
/*	 all can loop on forever */
$myarr['orange']['wax'][]=$subarray1;
$myarr['orange']['wax'][]=$subarray2;
$myarr['jack']['jill']['harry']['met']['sally'] = 'hello';
$myarr['jack']['jill']['harry']['met']['joe'] = '';
/* I even toss in a class object which will be put into xml */
/* Since I only have the xmltool class handy, I used an instance of that */
$myarr['somenode'] = new xmltool('node', 'PHPGW');

echo "<html><body>\n";
$nav = 'navigation: <a href="#start">initial array</a> | <a href="#export_xml">export_xml</a> | <a href="#import_xml">import_xml</a> | <a href="#export_var">export_var</a> | <a href="#export_struct">export_struct</a><br>'."\n";

echo '<a name="start">';
echo $nav;
echo "This is the result of <code>print_r(\$myarr); </code>\$myarr is the multi-dimensional array we which have defined in the file.\n";
echo "note: notice the last element of the array is an object. xmltool will handle this as well\n";
echo "<pre>\n";
print_r($myarr);
echo "</pre>\n";

/* Now to auto-convert to an xmltool object */
echo '<hr>';
echo '<hr>';
echo '<a name="export_xml">';
echo $nav;
echo "The array has been auto converted to XML. This can be done in any of the following three ways<br>\n";
echo "Long method:<br>\n<code>\n \$doc = new xmltool();<br>\n \$doc->import_var('myarr',\$myarr,True);<br>\n \$xml_result = \$doc->export_xml();<br>\n</code><br>\n";
echo "Immediate export method:<br>\n<code>\n \$doc = new xmltool();<br>\n \$xml_result = \$doc->import_var('myarr',\$myarr,True,true);<br>\n</code><br>\n";
echo "Super quick method which uses the var2xml() companion function:<br>\n<code>\n \$xml_result = \$var2xml(\$myarr);<br>\n</code><br>\n";

$doc = new xmltool();
$xml_result = $doc->import_var('myarr',$myarr,True,true);
$somexmldoc = $xml_result;
echo "The gnerated XML doc:\n";
echo "<pre>\n";
echo htmlentities($xml_result);
echo "</pre>\n";

echo '<hr>';
echo '<hr>';
echo '<a name="import_xml">';
echo $nav;
echo "Now we look at importing an XML doc. We can use the one we just created as an example.\n";
echo "<br>\n<code> \$doc = new xmltool();<br>\n \$doc->import_xml(\$xml_result);<br>\n</code><br>\n";

$doc = new xmltool();
$doc->import_xml($xml_result);

echo "This is the result of <code>print_r(\$doc);</code> which shows the object tree\n";
echo "<pre>\n";
print_r($doc);
echo "</pre>\n";

$cnode = new xmltool('node','newnode');
$cnode->import_var('blah',$myarr);
echo "<br>\nThis is the result of <code>print_r(\$cnode);</code> which shows the object tree\n";
echo "<pre>\n";
print_r($cnode);
echo "</pre>\n";

//$doc->data->data[3]->import_xml($xml_result);
//$doc->data->data[3]->import_var('blah',$myarr);
$xml_result = $doc->export_xml();

echo '<hr>';
echo '<hr>';
echo "The generated XML doc:\n";
echo "<pre>\n";
echo htmlentities($xml_result);
echo "</pre>\n";

echo '<hr>';
echo '<hr>';
echo '<a name="export_var">';
echo $nav;
echo "We can export to an array like this<br>\n";
echo "<code>\$result_array = \$doc->export_var();<br>\n</code><br>\n";

$result_array = $doc->export_var();

echo "This is the result of <code>print_r(\$result_array);</code>\n";
echo "<pre>\n";
print_r($result_array);
echo "</pre>\n";

echo '<hr>';
echo '<hr>';
echo '<a name="export_struct">';
echo $nav;
echo "We can export to a struct like this<br>\n";
echo "<code>\$result_struct = \$doc->export_struct();<br>\n</code><br>\n";

$result_struct = $doc->export_struct();

echo "This is the result of <code>print_r(\$result_struct);</code>\n";
echo "<pre>\n";
print_r($result_struct);
echo "</pre>\n";
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';
echo '<br>';

echo "</body></html>";
?>
