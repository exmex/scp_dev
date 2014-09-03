<?php
$forbidden['mta_xml'] = array(
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
$maxvals['mta_xml'] = array(
	"httpdownloadurl"=>"",
	"httpmaxconnectionsperclient"=>8,
	"httpdosthreshold"=>100,
	"http_dos_exclude"=>"",
	"disableac"=>"",
	"enablesd"=>"",
	"networkencryption"=>array("0", "1"),
	"minclientversion"=>"1.1.0-9.03100.0",
	"minclientversion_auto_update"=>array("0", "1", "2"),
	"recommendedclientversion"=>"1.1.0-9.03100.0",
	"ase"=>array("0", "1"),
	"donotbroadcastlan"=>array("0", "1"),
	"password"=>"",
	"bandwidth_reduction"=>array("none", "medium", "maximum"),
	"player_sync_interval"=>1000,
	"lightweight_sync_interval"=>10000,
	"camera_sync_interval"=>1000,
	"ped_sync_interval"=>1000,
	"unoccupied_vehicle_sync_interval"=>10000,
	"keysync_mouse_sync_interval"=>1000,
	"keysync_analog_sync_interval"=>1000,
	"bullet_sync"=>array("0", "1"),
	"vehext_percent"=>100,
	"vehext_ping_limit"=>500,
	"latency_reduction"=>array("0", "1"),
	"scriptdebugloglevel"=>array("0","1","2","3"),
	"htmldebuglevel"=>array("0","1","2","3"),
	"fpslimit"=>100,
	"autologin"=>array("0","1"),
	"voice"=>array("0", "1"),
	"voice_samplerate"=>array("0","1", "2"),
	"voice_quality"=>10,
	"compact_internal_databases"=>array("0", "1", "2"),
	"resource"=>"textarea",
	"module"=>"textarea",
);

function xml2assoc($xml) {
	$assoc = null;
	while($xml->read()){
		switch ($xml->nodeType) {
			case XMLReader::END_ELEMENT: return $assoc;
			case XMLReader::ELEMENT:
				$assoc[$xml->name][] = array('value' => $xml->isEmptyElement ? '' : xml2assoc($xml));
				if($xml->hasAttributes){
					$el =& $assoc[$xml->name][count($assoc[$xml->name]) - 1];
					while($xml->moveToNextAttribute()) $el['attributes'][$xml->name] = $xml->value;
				}
			break;
			case XMLReader::TEXT:
			case XMLReader::CDATA: $assoc .= $xml->value;
		}
	}
	return $assoc;
}

//mods/deathmatch
function mta_xml($data)
{
	global $forbidden;

	$xml = new XMLReader();
	$xml->xml($data);
	$return = xml2assoc($xml);

	$dat = array();
	if(isset($return['config'][0]['value']) && !empty($return['config'][0]['value']))
	{
		foreach($return['config'][0]['value'] as $key => $value)
		{
			if(in_array($key, $forbidden['mta_xml']) == TRUE) continue;
			
			if(strcmp($key, "resource") == 0)
			{
				$vl = "";
				foreach($value as $k => $v)
				{
					$vl .= "<resource src=\"".$v['attributes']['src']."\" startup=\"".$v['attributes']['startup']."\" protected=\"".$v['attributes']['protected']."\"/>\n";
				}
				$dat[$key] = htmlentities($vl);
			}else
				$dat[$key] = $value[0]['value'];
		}
		
		if(!in_array("module", $dat))
		{
			$dat["module"] = htmlentities("<!-- <module src=\"sample_linux.so\"/> -->");
		}
	}else{
		$dat = $data;	
	}
	
	return $dat;
}
?>