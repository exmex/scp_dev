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
require_once("../libs/phpseclib/SFTP.php");
require_once("../libs/phpseclib/Crypt/AES.php");
require_once("../libs/gameinstaller/gameinstaller.php");

require_once("../libs/serverconfigs/serverconfigs.php");


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
$pydio = query_fetch_assoc( "SELECT `value` FROM `".DBPREFIX."config` WHERE `setting` = 'pydio' LIMIT 1" );

if ($rows['panelstatus'] == 'Started')
{
	$_SESSION['msg1'] = T_('Server is started!');
	$_SESSION['msg2'] = T_('The configs can only be changed if the server is not running!');
	$_SESSION['msg-type'] = 'error';
	header( "Location: index.php");
	die();	
}

$aes = new Crypt_AES();
$aes->setKeyLength(256);
$aes->setKey(CRYPT_KEY);

$sftp = new Net_SFTP($box['ip'], $box['sshport']);
if (!$sftp->login($box['login'], $aes->decrypt($box['password'])))
{
	$_SESSION['msg1'] = T_('Connection Error!');
	$_SESSION['msg2'] = '';
	$_SESSION['msg-type'] = 'error';
	header( "Location: index.php");
	die();
}

$gameInstaller = new GameInstaller( $sftp );

$gameInstaller->setGame($game['game']);
$gameInstaller->setGameServerPath(dirname($rows['path']));
$actions = $gameInstaller->actions;

include("./bootstrap/header.php");
include("./bootstrap/notifications.php");
?>
			<ul class="nav nav-tabs">
				<li><a href="serversummary.php?id=<?php echo $serverid; ?>"><?php echo T_('Summary'); ?></a></li>
				<li><a href="serverprofile.php?id=<?php echo $serverid; ?>"><?php echo T_('Profile'); ?></a></li>
				<li><a href="servermanage.php?id=<?php echo $serverid; ?>"><?php echo T_('Manage'); ?></a></li>
				<?php if($type['querytype'] != 'none'){ ?><li><a href="serverlgsl.php?id=<?php echo $serverid; ?>">LGSL</a></li><?php } ?>
				<?php if($rows['panelstatus'] == 'Started'){ ?><li><a href="utilitiesrcontool.php?serverid=<?php echo $serverid; ?>"><?php echo T_('RCON Tool'); ?></a></li><?php } ?>
				<?php if($pydio['value'] == '0'){ ?><li><a href="#" onclick="ajxp()"><?php echo T_('WebFTP'); ?></a></li><?php } ?>
				<li><a href="serverlog.php?id=<?php echo $serverid; ?>"><?php echo T_('Activity Logs'); ?></a></li>
                <?php if($rows['panelstatus'] != 'Started'){ ?><li class="active"><a href="serverconfig.php?id=<?php echo $serverid; ?>"><?php echo T_('Server config'); ?></a></li><?php } ?>
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
                        <form method="post" action="serverconfigprocess.php">
                        <input type="hidden" name="task" value="edit" />
                        <input type="hidden" name="id" value="<?php echo urlencode($serverid); ?>" />
                        <input type="hidden" name="config" value="<?php echo urlencode($_GET['config']); ?>" />
                        <?php
							$parser = $actions['configs']['file'][$_GET['config']]['attributes']['parser'];
							$config = call_user_func($parser, $content);
							if(is_array($config))
							{
								echo "<table class=\"table table-striped table-bordered table-condensed\">";
								foreach($config as $key => $value)
								{
									if(is_array($maxvals[$parser][$key]))
									{
										echo "<tr><td><label>".$key."</label></td>";
										echo "<td><select name=\"data[".$key."]\">";
										foreach($maxvals[$parser][$key] as $val)
										{
											echo "<option value=\"".$val."\"";
											if(strcmp($val, $value) == 0) echo " selected ";
											echo ">".$val."</option>";
										}
										echo "<select></td></tr>";
									}elseif(strcmp($maxvals[$parser][$key], "jquery_append") == 0){
									?>
                                    <script type="text/javascript">
									$(document).ready(function(){
										$('#add<?php echo $key; ?>').on('click', function(e)
										{
											//var afterAdd = "<tr class='<?php echo $key; ?>elem' id='elem"+ cnt +"'><td><a href='#' onClick='return remove<?php echo $key; ?>(this, "+cnt+");'><span class='icon icon-minus'></span></a></td><td><input type='text' name='data[<?php echo $key; ?>]["+cnt+"]'/></td></tr>";
											var cnt = document.getElementsByClassName('<?php echo $key; ?>elem').length;
											var afterAdd = "<tr class='<?php echo $key; ?>elem' id='elem"+ cnt +"'><td><a href='#' onClick='return remove<?php echo $key; ?>(this, "+cnt+");'><span class='icon icon-minus'></span></a></td><td>";
											<?php
											if(is_array($value) && sizeof($value) >= 1)
											{
												$i = 0;
												foreach($value[0] as $ke => $va)
												{
													if(is_int($va))
													{
														echo 'afterAdd += \'<label>'.$ke.'</label><input type="number" name="data['.$key.'][\'+cnt+\']['.$i.']"/>\';';
													}else{
														echo 'afterAdd += \'<label>'.$ke.'</label><input type="text" name="data['.$key.'][\'+cnt+\']['.$i.']"/>\';';
													}
													$i++;
												}
												echo 'afterAdd += "</td></tr>";';
											}else{
												echo 'afterAdd += \'<input type="text" name="data['.$key.']" /></td></tr>\';';
											}
											?>
											$('#<?php echo $key; ?>').after(afterAdd);
											e.preventDefault();
											return false;
										});
									});
									function remove<?php echo $key; ?>(data, id)
									{
										$('#elem' + id).remove();
										return false;
									}
									</script>
                                    <tr id="<?php echo $key; ?>">
                                    	<td><label><?php echo $key; ?></label></td>
                                        <td><a href="#" id="add<?php echo $key; ?>"><span style="float:right;" class="icon icon-plus"></span></a></td>
                                    </tr>
                                    <?php
									if(is_array($value) && sizeof($value) >= 1)
									{
										$arr_val = array_values($value[0]);
										if(!empty($arr_val[0]))
										{
											$i = 0;
											foreach($value as $k => $v)
											{
												if(is_array($v))
												{
													echo "<tr class='".$key."elem' id='elem".$i."'><td><a href='#' onClick='return remove".$key."(this, ".$i.");'><span class='icon icon-minus'></span></a></td><td>";
													$i2 = 0;
													foreach($v as $ke => $va)
													{
														if(is_int($va)){
															echo "<label>".$ke."</label><input type='number' name='data[".$key."][".$i."][".$i2."]' value='".$va."'/>";
														}elseif(is_string($va)){
															echo "<label>".$ke."</label><input type='text' name='data[".$key."][".$i."][".$i2."]' value='".$va."'/>";
														}
														$i2++;
													}
													echo "</td></tr>";
												}else{
													echo "<tr class='".$key."elem' id='elem".$i."'><td><a href='#' onClick='return remove".$key."(this, ".$i.");'><span class='icon icon-minus'></span></a></td><td><input type='text' name='data[".$key."][".$i."]' value='".$v."'/></td></tr>";
												}
												$i++;
											}
										}
									}
									}elseif(is_string($maxvals[$parser][$key])){
										echo "<tr><td><label>".$key."</label></td>";
										echo "<td><input type=\"text\" name=\"data[".$key."]\" value=\"".$value."\" maxlength=\"".strlen($maxvals[$parser][$key])."\"/></td></tr>";
									}elseif(is_int($maxvals[$parser][$key])){
										echo "<tr><td><label>".$key."</label></td>";
										echo "<td><input type=\"number\" name=\"data[".$key."]\" value=\"".$value."\" max=\"".$maxvals[$parser][$key]."\"/></td></tr>";
									}
								}
							}else{
						?>
                        	<table class="table table-striped table-bordered table-condensed\">
                        	<tr>
                            	<td><textarea style="width:98%;" rows="50" name="data"><?php echo $config; ?></textarea></td>
                            </tr>
                            </table>
                            <table class="table table-striped table-bordered table-condensed">
                        <?php
							}
						?>
                        	<tr>
                            	<td></td>
                            	<td style="text-align:right;"><button name="submit" type="submit" class="btn btn-success">Save</button></td>
                            </tr>
                        </table>
                        </form>
                        <?php } ?>
                    </div>
				</div>
<?php
	break;
	
	case 'reset':
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
							<span class="label label-info"><?php echo T_('Reset ') . $actions['configs']['file'][$_GET['config']]['value']; ?></span>
						</div>
                        <form method="post" action="serverconfigprocess.php">
						<input type="hidden" name="task" value="reset" />
                        <input type="hidden" name="id" value="<?php echo urlencode($serverid); ?>" />
                        <input type="hidden" name="config" value="<?php echo urlencode($_GET['config']); ?>" />
                        <table class="table table-striped table-bordered table-condensed">
                        	<tr>
                            	<td>Are you sure you want to reset the config '<?php echo $actions['configs']['file'][$_GET['config']]['value']; ?>'</td>
                            	<td><button name="submit" type="submit" class="btn btn-success">Yes</button></td>
                            </tr>
                        </table>
                        </form>
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
								<td width="90%"><?php echo (pathinfo($value['value'], PATHINFO_EXTENSION)) ? pathinfo($value['value'], PATHINFO_FILENAME).".".pathinfo($value['value'], PATHINFO_EXTENSION) : $value['value']; ?></td>
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