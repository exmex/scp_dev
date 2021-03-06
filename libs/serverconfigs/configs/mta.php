<?php
$forbidden['mta_xml'] = array(
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
	"resource"=>"jquery_append",
	"module"=>"jquery_append",
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

//mods/deathmatch/mtaserver.conf

/*------------------- mtaserver.conf ------------------ */
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
				$dat[$key] = array();
				foreach($value as $k => $v)
				{
					$dat[$key][] = array("src"=>$v['attributes']['src'], "startup"=>$v['attributes']['startup'], "protected"=>$v['attributes']['protected']);
				}
				continue;
			}elseif(strcmp($key, "module") == 0){
				$dat[$key] = array();
				foreach($value as $k => $v)
				{
					$dat[$key][] = array("src" => $v['attributes']['src']);
				}
				continue;
			}

			$dat[$key] = $value[0]['value'];
		}
	}else{
		$dat = $data;	
	}
	
	if(!isset($dat['module']))
		$dat['module'] = array(array("src"=>""));
	if(!isset($dat['resource']))
		$dat['resource'] = array(array("src"=>"", "startup"=>"", "protected"=>""));
	
	$temp = $dat['resource'];
	$temp2 = $dat['module'];
	unset($dat['resource']);
	unset($dat['module']);
	ksort($dat);
	$dat['resource'] = $temp;
	$dat['module'] = $temp2;
	return $dat;
}

function mta_xml_write($data)
{
	global $forbidden, $maxvals;
	$content = "<!-- Generated by BGP_Config -->\n\n";
	
	$content .= "<config>\n";
	$content .= "<!-- User generated -->\n";
	
	foreach($data as $key => $value)
	{
		if(in_array($key, $forbidden['mta_xml']) == TRUE) return -1;
		if(isset($maxvals['mta_xml'][$key]))
		{
			if(is_array($maxvals['mta_xml'][$key]) && is_array($maxvals['mta_xml'][$key]) && !in_array($value, $maxvals['mta_xml'][$key]))
				return array(false, ($key." is ".$value." but only allows: ".implode(", ", $maxvals['mta_xml'][$key])));
		
			if(is_string($maxvals['mta_xml'][$key]) && is_string($value) && strlen($value) > strlen($maxvals['mta_xml'][$key]))
				return array(false, $key." is too long. (Max: ".strlen($maxvals['mta_xml'][$key]).")");
		
			if(is_int($maxvals['mta_xml'][$key]) && ($value > $maxvals['mta_xml'][$key] || $value < 0))
				return array(false, $key." is too big. (Max: ".$maxvals['mta_xml'][$key].")");
		}
		
		if(strcmp($key, "resource") == 0)
		{
			foreach($value as $k => $v)
			{
				$content .= "\t<resource src=\"".$v[0]."\" startup=\"".$v[1]."\" protected=\"".$v[2]."\"/>\n";
			}
		}elseif(strcmp($key, "module") == 0){
			foreach($value as $k => $v)
			{
				$content .= "\t<module src=\"".$v[0]."\"/>\n";
			}
		}else{
			$content .= "\t<".$key.">".$value."</".$key.">\n";
		}
	}
	
	return $content;
}
function mta_xml_append($server, $box)
{	
	$content = "\t<!-- Automatic generated -->\n";
	
	$content .= "\t<serverip>".$box['ip']."</serverip>\n";
	$content .= "\t<serverport>".$server['port']."</serverport>\n";
	$content .= "\t<maxplayers>".$server['slots']."</maxplayers>\n";
	$content .= "\t<httpserver>1</httpserver>\n";
	$content .= "\t<httpport>". ($server['port'] + 2) ."</httpport>\n";
	$content .= "\t<idfile>server-id.keys</idfile>\n";
	$content .= "\t<logfile>logs/server.log</logfile>\n";
	$content .= "\t<authfile>logs/server_auth.log</authfile>\n";
	$content .= "\t<dbfile>logs/db.log</dbfile>\n";
	$content .= "\t<acl>acl.xml</acl>\n";
	$content .= "\t<scriptdebuglogfile>logs/scripts.log</scriptdebuglogfile>\n";
	$content .= "\t<backup_copies>1</backup_copies>\n";
	$content .= "\t<httpconnectionsperclient>5</httpconnectionsperclient>\n";
	$content .= "\t<backup_path>backups</backup_path>\n";
	$content .= "\t<backup_interval>3</backup_interval>\n";
	
	$content .= "</config>";
	
	return $content;
}

function mta_xml_reset($server, $box)
{
	$content = "<!-- Generated by BGP_Config -->\n";

	$content .= "<config>\n";
	$content .= "\t<ase>1</ase>\n";
	$content .= "\t<autologin>0</autologin>\n";
	$content .= "\t<bandwidth_reduction>medium</bandwidth_reduction>\n";
	$content .= "\t<bullet_sync>1</bullet_sync>\n";
	$content .= "\t<camera_sync_interval>500</camera_sync_interval>\n";
	$content .= "\t<compact_internal_databases>1</compact_internal_databases>\n";
	$content .= "\t<disableac></disableac>\n";
	$content .= "\t<donotbroadcastlan>0</donotbroadcastlan>\n";
	$content .= "\t<enablesd></enablesd>\n";
	$content .= "\t<fpslimit>36</fpslimit>\n";
	$content .= "\t<htmldebuglevel>0</htmldebuglevel>\n";
	$content .= "\t<http_dos_exclude></http_dos_exclude>\n";
	$content .= "\t<httpdosthreshold>20</httpdosthreshold>\n";
	$content .= "\t<httpdownloadurl></httpdownloadurl>\n";
	$content .= "\t<httpmaxconnectionsperclient>5</httpmaxconnectionsperclient>\n";
	$content .= "\t<keysync_analog_sync_interval>100</keysync_analog_sync_interval>\n";
	$content .= "\t<keysync_mouse_sync_interval>100</keysync_mouse_sync_interval>\n";
	$content .= "\t<latency_reduction>0</latency_reduction>\n";
	$content .= "\t<lightweight_sync_interval>1500</lightweight_sync_interval>\n";
	$content .= "\t<minclientversion>1.3.4</minclientversion>\n";
	$content .= "\t<minclientversion_auto_update>1</minclientversion_auto_update>\n";
	$content .= "\t<networkencryption>1</networkencryption>\n";
	$content .= "\t<password></password>\n";
	$content .= "\t<ped_sync_interval>500</ped_sync_interval>\n";
	$content .= "\t<player_sync_interval>100</player_sync_interval>\n";
	$content .= "\t<recommendedclientversion></recommendedclientversion>\n";
	$content .= "\t<scriptdebugloglevel>0</scriptdebugloglevel>\n";
	$content .= "\t<unoccupied_vehicle_sync_interval>1000</unoccupied_vehicle_sync_interval>\n";
	$content .= "\t<vehext_percent>0</vehext_percent>\n";
	$content .= "\t<vehext_ping_limit>150</vehext_ping_limit>\n";
	$content .= "\t<voice>0</voice>\n";
	$content .= "\t<voice_quality>4</voice_quality>\n";
	$content .= "\t<voice_samplerate>1</voice_samplerate>\n";
	
	$content .= "\t<resource src=\"admin\" startup=\"1\" protected=\"0\"/>\n";
	$content .= "\t<resource src=\"defaultstats\" startup=\"1\" protected=\"0\"/>\n";
	$content .= "\t<resource src=\"helpmanager\" startup=\"1\" protected=\"0\"/>\n";
	$content .= "\t<resource src=\"joinquit\" startup=\"1\" protected=\"0\"/>\n";
	$content .= "\t<resource src=\"mapcycler\" startup=\"1\" protected=\"0\"/>\n";
	$content .= "\t<resource src=\"mapmanager\" startup=\"1\" protected=\"0\"/>\n";
	$content .= "\t<resource src=\"parachute\" startup=\"1\" protected=\"0\"/>\n";
	$content .= "\t<resource src=\"performancebrowser\" startup=\"1\" protected=\"0\"/>\n";
	$content .= "\t<resource src=\"resourcebrowser\" startup=\"1\" protected=\"1\" default=\"true\"/>\n";
	$content .= "\t<resource src=\"resourcemanager\" startup=\"1\" protected=\"1\"/>\n";
	$content .= "\t<resource src=\"scoreboard\" startup=\"1\" protected=\"0\"/>\n";
	$content .= "\t<resource src=\"spawnmanager\" startup=\"1\" protected=\"0\"/>\n";
	$content .= "\t<resource src=\"voice\" startup=\"1\" protected=\"0\" />\n";
	$content .= "\t<resource src=\"votemanager\" startup=\"1\" protected=\"0\"/>\n";
	$content .= "\t<resource src=\"webadmin\" startup=\"1\" protected=\"0\"/>\n";
	$content .= "\t<resource src=\"play\" startup=\"1\" protected=\"0\"/>\n";
	
	$content .= "\t<serverip>".$box['ip']."</serverip>\n";
	$content .= "\t<serverport>".$server['port']."</serverport>\n";
	$content .= "\t<maxplayers>1</maxplayers>\n";
	$content .= "\t<httpserver>1</httpserver>\n";
	$content .= "\t<httpport>". ($server['port']+ 2) ."</httpport>\n";
	$content .= "\t<idfile>server-id.keys</idfile>\n";
	$content .= "\t<logfile>logs/server.log</logfile>\n";
	$content .= "\t<authfile>logs/server_auth.log</authfile>\n";
	$content .= "\t<dbfile>logs/db.log</dbfile>\n";
	$content .= "\t<acl>acl.xml</acl>\n";
	$content .= "\t<scriptdebuglogfile>logs/scripts.log</scriptdebuglogfile>\n";
	$content .= "\t<backup_copies>1</backup_copies>\n";
	$content .= "\t<httpconnectionsperclient>5</httpconnectionsperclient>\n";
	$content .= "\t<backup_path>backups</backup_path>\n";
	$content .= "\t<backup_interval>3</backup_interval>\n";
	$content .= "</config>";
	return $content;
}

/*------------------- acl.xml ------------------ */

function mta_acl_xml($data)
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
				$dat[$key] = array();
				foreach($value as $k => $v)
				{
					$dat[$key][] = array("src"=>$v['attributes']['src'], "startup"=>$v['attributes']['startup'], "protected"=>$v['attributes']['protected']);
				}
				continue;
			}elseif(strcmp($key, "module") == 0){
				$dat[$key] = array();
				foreach($value as $k => $v)
				{
					$dat[$key][] = array("src" => $v['attributes']['src']);
				}
				continue;
			}

			$dat[$key] = $value[0]['value'];
		}
	}else{
		$dat = $data;	
	}
	return $dat;
}

function mta_acl_xml_write($data)
{
	return $data;
}

function mta_acl_xml_append($server, $box)
{
	return "";
}

function mta_acl_xml_reset($server, $box)
{
	$content = "<!-- Generated by BGP_Config -->\n";
	$content .= "<acl>\n";
	$content .= "\t<group name=\"Everyone\">\n";
	$content .= "\t\t<acl name=\"Default\"/>\n";
	$content .= "\t\t<object name=\"user.*\"/>\n";
	$content .= "\t\t<object name=\"resource.*\"/>\n";
	$content .= "\t</group>\n";
	$content .= "\t<group name=\"Moderator\">\n";
	$content .= "\t\t<acl name=\"Moderator\"/>\n";
	$content .= "\t\t<object name=\"resource.mapcycler\"/>\n";
	$content .= "\t\t<object name=\"resource.mapmanager\"/>\n";
	$content .= "\t\t<object name=\"resource.resourcemanager\"/>\n";
	$content .= "\t\t<object name=\"resource.votemanager\"/>\n";
	$content .= "\t</group>\n";
	$content .= "\t<group name=\"SuperModerator\">\n";
	$content .= "\t\t<acl name=\"Moderator\"/>\n";
	$content .= "\t\t<acl name=\"SuperModerator\"/>\n";
	$content .= "\t</group>\n";
	$content .= "\t<group name=\"Admin\">\n";
	$content .= "\t\t<acl name=\"Moderator\"/>\n";
	$content .= "\t\t<acl name=\"SuperModerator\"/>\n";
	$content .= "\t\t<acl name=\"Admin\"/>\n";
	$content .= "\t\t<acl name=\"RPC\"/>\n";
	$content .= "\t\t<object name=\"resource.admin\"/>\n";
	$content .= "\t\t<object name=\"resource.webadmin\"/>\n";
	$content .= "\t</group>\n";
	$content .= "\t<group name=\"Console\">\n";
	$content .= "\t\t<acl name=\"Moderator\"/>\n";
	$content .= "\t\t<acl name=\"SuperModerator\"/>\n";
	$content .= "\t\t<acl name=\"Admin\"/>\n";
	$content .= "\t\t<acl name=\"RPC\"/>\n";
	$content .= "\t\t<object name=\"user.Console\"/>\n";
	$content .= "\t</group>\n";
	$content .= "\t<group name=\"RPC\">\n";
	$content .= "\t\t<acl name=\"RPC\"/>\n";
	$content .= "\t</group>\n";
	$content .= "\t<group name=\"MapEditor\">\n";
	$content .= "\t\t<acl name=\"Default\"/>\n";
	$content .= "\t\t<acl name=\"MapEditor\"/>\n";
	$content .= "\t\t<object name=\"resource.editor_main\"/>\n";
	$content .= "\t\t<object name=\"resource.edf\"/>\n";
	$content .= "\t</group>\n";
	$content .= "\t<group name=\"raceACLGroup\">\n";
	$content .= "\t\t<acl name=\"Default\"/>\n";
	$content .= "\t\t<acl name=\"raceACL\"/>\n";
	$content .= "\t\t<object name=\"resource.race\"/>\n";
	$content .= "\t</group>\n";
	$content .= "\t<group name=\"DevGroup\">\n";
	$content .= "\t\t<acl name=\"DevACL\"/>\n";
	$content .= "\t</group>\n";
	$content .= "\t<acl name=\"Default\">\n";
	$content .= "\t\t<right name=\"general.ModifyOtherObjects\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"general.http\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.start\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.stop\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.stopall\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.gamemode\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.changemode\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.changemap\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.stopmode\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.stopmap\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.skipmap\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.restart\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.refresh\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.refreshall\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.addaccount\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.delaccount\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.debugscript\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.chgpass\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.loadmodule\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.upgrade\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.mute\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.crun\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.srun\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.run\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.unmute\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.kick\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.ban\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.banip\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.unbanip\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.reloadbans\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.shutdown\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.install\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.aexec\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.whois\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.whowas\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.executeCommandHandler\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.setPlayerMuted\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.addAccount\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.addBan\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.removeBan\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.removeAccount\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.setAccountPassword\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.kickPlayer\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.banIP\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.banPlayer\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.banSerial\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.getBansXML\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.unbanIP\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.unbanSerial\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.reloadBans\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.setServerPassword\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.getServerPassword\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.callRemote\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.fetchRemote\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.startResource\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.stopResource\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.restartResource\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.createResource\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.copyResource\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.addResourceMap\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.addResourceConfig\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.removeResourceFile\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.setResourceDefaultSetting\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.removeResourceDefaultSetting\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.redirectPlayer\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.aclReload\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.aclSave\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.aclCreate\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.aclDestroy\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.aclSetRight\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.aclRemoveRight\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.aclCreateGroup\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.aclDestroyGroup\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.aclGroupAddACL\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.aclGroupRemoveACL\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.aclGroupAddObject\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.aclGroupRemoveObject\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.refreshResources\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"function.setServerConfigSetting\" access=\"false\" />\n";
	$content .= "\t\t<right name=\"function.updateResourceACLRequest\" access=\"false\" />\n";
	$content .= "\t\t<right name=\"command.aclrequest\" access=\"false\" />\n";
	$content .= "\t</acl>\n";
	$content .= "\t<acl name=\"Moderator\">\n";
	$content .= "\t\t<right name=\"general.ModifyOtherObjects\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.gamemode\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.changemode\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.changemap\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.stopmode\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.stopmap\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.skipmap\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.mute\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.unmute\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.whois\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.whowas\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.setPlayerMuted\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.kickPlayer\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.banIP\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.banPlayer\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.banSerial\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.getBansXML\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.unbanIP\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.unbanSerial\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.startResource\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.stopResource\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.restartResource\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.redirectPlayer\" access=\"true\"/>\n";
	$content .= "\t</acl>\n";
	$content .= "\t<acl name=\"SuperModerator\">\n";
	$content .= "\t\t<right name=\"general.ModifyOtherObjects\" access=\"false\"/>\n";
	$content .= "\t\t<right name=\"command.start\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.stop\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.restart\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.kick\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.ban\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.banip\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.unbanip\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.reloadbans\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.refresh\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.refreshall\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.loadmodule\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.addaccount\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.delaccount\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.chgpass\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.addAccount\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.removeAccount\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.setAccountPassword\" access=\"true\"/>\n";
	$content .= "\t</acl>\n";
	$content .= "\t<acl name=\"Admin\">\n";
	$content .= "\t\t<right name=\"general.ModifyOtherObjects\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"general.http\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.shutdown\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.install\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.aexec\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.debugscript\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.upgrade\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.crun\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.srun\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"command.run\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.addBan\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.removeBan\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.reloadBans\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.executeCommandHandler\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.setServerPassword\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.getServerPassword\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.createResource\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.copyResource\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.addResourceMap\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.addResourceConfig\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.removeResourceFile\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.setResourceDefaultSetting\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.removeResourceDefaultSetting\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.aclReload\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.aclSave\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.aclCreate\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.aclDestroy\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.aclSetRight\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.aclRemoveRight\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.aclCreateGroup\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.aclDestroyGroup\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.aclGroupAddACL\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.aclGroupRemoveACL\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.aclGroupAddObject\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.aclGroupRemoveObject\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.refreshResources\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.setServerConfigSetting\" access=\"true\" />\n";
	$content .= "\t\t<right name=\"function.updateResourceACLRequest\" access=\"true\" />\n";
	$content .= "\t\t<right name=\"command.aclrequest\" access=\"true\" />\n";
	$content .= "\t</acl>\n";
	$content .= "\t<acl name=\"RPC\">\n";
	$content .= "\t\t<right name=\"function.callRemote\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.fetchRemote\" access=\"true\"/>\n";
	$content .= "\t</acl>\n";
	$content .= "\t<acl name=\"MapEditor\">\n";
	$content .= "\t\t<right name=\"general.ModifyOtherObjects\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.startResource\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.stopResource\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.restartResource\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.createResource\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.copyResource\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.renameResource\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.deleteResource\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.addResourceMap\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.addResourceConfig\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.removeResourceFile\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.setResourceDefaultSetting\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.removeResourceDefaultSetting\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.xmlLoadFile\" access=\"true\"/>\n";
	$content .= "\t</acl>\n";
	$content .= "\t<acl name=\"raceACL\">\n";
	$content .= "\t\t<right name=\"general.ModifyOtherObjects\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.xmlLoadFile\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.startResource\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.stopResource\" access=\"true\"/>\n";
	$content .= "\t\t<right name=\"function.restartResource\" access=\"true\"/>\n";
	$content .= "\t</acl>\n";
	$content .= "\t<acl name=\"DevACL\">\n";
	$content .= "\t\t<right name=\"resource.performancebrowser.http\" access=\"true\"></right>\n";
	$content .= "\t\t<right name=\"resource.ajax.http\" access=\"true\"></right>\n";
	$content .= "\t</acl>\n";
	$content .= "</acl>";
	return $content;
}
?>