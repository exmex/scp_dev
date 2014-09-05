<?php    
$forbidden = array(
	"servername",
	"serverip",
	"serverport",
	"maxplayers",
	"httpserver",
	"httpport",
	"idfile",
	"logfile",
	"authfile",
	"dbfile",
	"acl",
	"scriptdebuglogfile",
	"backup_copies",
	"httpconnectionsperclient",
	"backup_path",
	"backup_interval",
);
    /* create a dom document with encoding utf8 */
    $domtree = new DOMDocument();
	$xmlRoot = $domtree->createElement("config");
    $xmlRoot = $domtree->appendChild($xmlRoot);

foreach($forbidden as $key)
{
    $keyNode = $domtree->createElement($key);
    $keyNode = $xmlRoot->appendChild($keyNode);
	
	$keyText = $domtree->createTextNode("hello");
	$keyNode->appendChild($keyText);
}

	//module creation

	$keyNode = $domtree->createElement("module");
	$keyNode = $xmlRoot->appendChild($keyNode);
	
	$keyAttr = $domtree->createAttribute("src");
	$keyAttr->value = "ss.so";
	$keyAttr = $keyNode->appendChild($keyAttr);

	// resource
	$keyNode = $domtree->createElement("resource");
	$keyNode = $xmlRoot->appendChild($keyNode);
	
	$keyAttr = $domtree->createAttribute("src");
	$keyAttr->value = "mymom";
	$keyAttr = $keyNode->appendChild($keyAttr);

	$keyAttr = $domtree->createAttribute("startup");
	$keyAttr->value = "0";
	$keyAttr = $keyNode->appendChild($keyAttr);
	
	$keyAttr = $domtree->createAttribute("protected");
	$keyAttr->value = "0";
	$keyAttr = $keyNode->appendChild($keyAttr);

	header('Content-type: text/xml');
	echo $domtree->saveHTML();
?>