<?php
$page = 'serverconfig';
$tab = 2;
$isSummary = TRUE;

if ( !isset($_GET['id']) || !is_numeric($_GET['id']) )
	exit('Error: ServerID error.');

$serverid = $_GET['id'];
$return = 'serverconfig.php?id='.urlencode($serverid);


require("../configuration.php");
require("./include.php");
require_once("../includes/func.ssh2.inc.php");
require_once("../libs/phpseclib/SFTP.php");
require_once("../libs/phpseclib/Crypt/AES.php");
require_once("../libs/gameinstaller/gameinstaller.php");


$title = T_('Server Config');

$serverid = mysql_real_escape_string($_GET['id']);


if (query_numrows( "SELECT `name` FROM `".DBPREFIX."server` WHERE `serverid` = '".$serverid."'" ) == 0)
{
	exit('Error: ServerID is invalid.');
}

$rows = query_fetch_assoc( "SELECT * FROM `".DBPREFIX."server` WHERE `serverid` = '".$serverid."' LIMIT 1" );
$box = query_fetch_assoc( "SELECT * FROM `".DBPREFIX."box` WHERE `boxid` = '".$rows['boxid']."' LIMIT 1" );
$serverIp = query_fetch_assoc( "SELECT `ip` FROM `".DBPREFIX."boxIp` WHERE `ipid` = '".$rows['ipid']."' LIMIT 1" );
$type = query_fetch_assoc( "SELECT `querytype` FROM `".DBPREFIX."game` WHERE `gameid` = '".$rows['gameid']."' LIMIT 1");
$game = query_fetch_assoc( "SELECT * FROM `".DBPREFIX."game` WHERE `gameid` = '".$rows['gameid']."' LIMIT 1" );
$group = query_fetch_assoc( "SELECT `name` FROM `".DBPREFIX."group` WHERE `groupid` = '".$rows['groupid']."' LIMIT 1" );
$logs = mysql_query( "SELECT * FROM `".DBPREFIX."log` WHERE `serverid` = '".$serverid."' ORDER BY `logid` DESC LIMIT 5" );

$aes = new Crypt_AES();
$aes->setKeyLength(256);
$aes->setKey(CRYPT_KEY);

// Get SSH2 Object OR ERROR String
$ssh = newNetSSH2($box['ip'], $box['sshport'], $box['login'], $aes->decrypt($box['password']));
if (!is_object($ssh))
{
	$_SESSION['msg1'] = T_('Connection Error!');
	$_SESSION['msg2'] = $ssh;
	$_SESSION['msg-type'] = 'error';
}

$sftp = new Net_SFTP($box['ip'], $box['sshport']);
if (!$sftp->login($box['login'], $aes->decrypt($box['password'])))
{
	$_SESSION['msg1'] = T_('Connection Error!');
	$_SESSION['msg2'] = '';
	$_SESSION['msg-type'] = 'error';
	header( "Location: index.php");
	die();
}

$gameInstaller = new GameInstaller( $ssh, $sftp );

$gameInstaller->setGame($game['game']);
$gameInstaller->setGameServerPath(dirname($rows['path']));
$actions = $gameInstaller->actions;

include("./bootstrap/header.php");
include("./bootstrap/notifications.php");

/* ----------------------------------------- Config parsers ------------------------------------------ */
function java_properties($txtProperties) {
	$forbidden = array(
		"online-mode",
		"server-ip",
		"level-name",
		"query.port",
		"debug",
		"max-players",
		"rcon.port",
		"enable-rcon",
		"rcon.password",
		"enable-query",
	);
	$result = array();
	
	$lines = explode("\n", $txtProperties);
	$key = "";
	
	$isWaitingOtherLine = false;
	foreach($lines as $i=>$line) {
		if(empty($line) || (!$isWaitingOtherLine && strpos($line,"#") === 0)) continue;
	
		if(!$isWaitingOtherLine) {
			$key = substr($line,0,strpos($line,'='));
			$value = substr($line,strpos($line,'=') + 1, strlen($line));
		}else{
			$value .= $line;
		}

		if(strrpos($value,"\\") === strlen($value)-strlen("\\")) {
			$value = substr($value, 0, strlen($value)-1)."\n";
			$isWaitingOtherLine = true;
		}else{
			$isWaitingOtherLine = false;
		}
	
		if(in_array($key, $forbidden) == TRUE) continue;
	
		$result[$key] = $value;
		unset($lines[$i]);
	}
	ksort($result);
	return $result;
}
/* ----------------------------------------- Config parsers ------------------------------------------ */
?>
			<ul class="nav nav-tabs">
				<li><a href="serversummary.php?id=<?php echo $serverid; ?>"><?php echo T_('Summary'); ?></a></li>
				<li><a href="serverprofile.php?id=<?php echo $serverid; ?>"><?php echo T_('Profile'); ?></a></li>
				<li><a href="servermanage.php?id=<?php echo $serverid; ?>"><?php echo T_('Manage'); ?></a></li>
<?php
if ($type['querytype'] != 'none')
	echo "\t\t\t\t<li><a href=\"serverlgsl.php?id=".$serverid."\">LGSL</a></li>";
if ($rows['panelstatus'] == 'Started')
	echo "\t\t\t\t<li><a href=\"utilitiesrcontool.php?serverid=".$serverid."\">".T_('RCON Tool')."</a></li>";
?>
				<li><a href="#" onclick="ajxp()"><?php echo T_('WebFTP'); ?></a></li>
				<li><a href="serverlog.php?id=<?php echo $serverid; ?>"><?php echo T_('Activity Logs'); ?></a></li>
                <li class="active"><a href="serverconfig.php?id=<?php echo $serverid; ?>"><?php echo T_('Server config'); ?></a></li>
			</ul>
			<div class="row-fluid">
<?php
$task = (isset($_GET['task'])) ? $_GET['task'] : "";
switch($task)
{
	case 'edit':
	if(!isset($actions['configs']['file'][$_GET['config']]['value']) || empty($actions['configs']['file'][$_GET['config']]['value']))
	{
		$_SESSION['msg1'] = T_('Config file not found!');
		$_SESSION['msg2'] = "This config file is not part of our list";
		$_SESSION['msg-type'] = 'error';
		header("Location: serverconfig.php?id=".urlencode($serverid));
		die();
	}
?>
				<div class="span6 offset3">
					<div class="well">
						<div style="text-align: center; margin-bottom: 5px;">
							<span class="label label-info"><?php echo T_('Edit ') . $actions['configs']['file'][$_GET['config']]['value']; ?></span>
						</div>
                        <?php
						$content = $gameInstaller->getConfig($_GET['config']);
						if($content === FALSE)
						{
						?>
						<div style="text-align: center; margin-bottom: 5px;">
							<span class="label label-warning"><?php echo T_('Config file not found') . "<br />" . T_('(Did you started the server once?)'); ?></span>
						</div>
                        <?php
						}else{
						?>
                        <table class="table table-striped table-bordered table-condensed">
                        <?php
							$config = call_user_func($actions['configs']['file'][$_GET['config']]['attributes']['parser'], $content);
							foreach($config as $key => $value)
							{
								if(strpos($key, "port") !== FALSE || strpos($key, "ip") !== FALSE) continue;
							?>
                            	<tr>
                                	<td><label><?php echo $key; ?></label></td>
	                                <td><input type="text" value="<?php echo $value; ?>" /></td>
                                </tr>
                            <?php
							}
						?>
                        <textarea style="width:100%;" rows="15"><?php echo $content; ?></textarea>
                        </table>
                        <?php } ?>
                    </div>
				</div>
<?php
	break;
	
	default:
?>
				<div class="span6 offset3">
					<div class="well">
						<div style="text-align: center; margin-bottom: 5px;">
							<span class="label label-info"><?php echo T_('Available configs'); ?></span>
						</div>
						<table class="table table-striped table-bordered table-condensed">
                       	<?php
						foreach($actions['configs']['file'] as $key => $value)
						{
						?>
							<tr>
								<td width="90%"><?php echo pathinfo($value['value'], PATHINFO_FILENAME).".".pathinfo($value['value'], PATHINFO_EXTENSION); ?></td>
                                <td>
                                	<a href="serverconfig.php?id=<?php echo $serverid; ?>&task=edit&config=<?php echo $key; ?>"><span class="icon icon-pencil" title="Edit"></span></a>
									<a href="serverconfig.php?id=<?php echo $serverid; ?>&task=reset&config=<?php echo $key; ?>"><span class="icon icon-refresh" title="Reset"></span></a>
								</td>
							</tr>
                        <?php
						}
						?>
						</table>
					</div>
				</div>
<?php
	break;
}
?>
			</div>
			<div style="text-align: center;">
				<ul class="pager">
					<li>
						<a href="serverconfig.php?id=<?php echo htmlentities($_GET['id']); ?>"><?php echo T_('Back to Serverconfig'); ?></a>
					</li>
				</ul>
			</div>
<?php
$sftp->disconnect();
include("./bootstrap/footer.php");
?>