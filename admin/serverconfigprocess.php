<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * LICENSE:
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 * @categories	Games/Entertainment, Systems Administration
 * @package		Bright Game Panel
 * @author		warhawk3407 <warhawk3407@gmail.com> @NOSPAM
 * @copyleft	2013
 * @license		GNU General Public License version 3.0 (GPLv3)
 * @version		(Release 0) DEVELOPER BETA 9
 * @link		http://www.bgpanel.net/
 */
$return = TRUE;

require("../configuration.php");
require("./include.php");


if (isset($_POST['task']))
{
	$task = mysql_real_escape_string($_POST['task']);
}
else if (isset($_GET['task']))
{
	$task = mysql_real_escape_string($_GET['task']);
}

switch (@$task)
{
	case 'edit':
		$serverid = mysql_real_escape_string($_POST['id']);
		$configid = mysql_real_escape_string($_POST['config']);
		###
		$error = '';
		###
		if (empty($configid) && $configid == "")
		{
			$error .= T_('No Config specified!');
		}
		else
		{
			if (!is_numeric($configid))
			{
				$error .= T_('Invalid Config.');
			}
		}
		
		if (empty($serverid))
		{
			$error .= T_('No ServerID specified !');
		}
		else
		{
			if (!is_numeric($serverid))
			{
				$error .= T_('Invalid ServerID. ');
			}
			else if (query_numrows( "SELECT `name` FROM `".DBPREFIX."server` WHERE `serverid` = '".$serverid."'" ) == 0)
			{
				$error .= T_('Invalid ServerID. ');
			}
		}
		###
		if (!empty($error))
		{
			$_SESSION['msg1'] = T_('Validation Error!');
			$_SESSION['msg2'] = $error;
			$_SESSION['msg-type'] = 'error';
			unset($error);
			header( 'Location: server.php' );
			die();
		}
		###
		$server = query_fetch_assoc( "SELECT * FROM `".DBPREFIX."server` WHERE `serverid` = '".$serverid."' LIMIT 1" );
		$box = query_fetch_assoc( "SELECT `ip`, `login`, `password`, `sshport` FROM `".DBPREFIX."box` WHERE `boxid` = '".$server['boxid']."' LIMIT 1" );
		$game = query_fetch_assoc( "SELECT * FROM `".DBPREFIX."game` WHERE `gameid` = '".$server['gameid']."' LIMIT 1" );
		###
		
		require_once("../libs/gameinstaller/gameinstaller.php");
		require_once("../libs/phpseclib/SFTP.php");
		require_once("../libs/phpseclib/Crypt/AES.php");
		
		$aes = new Crypt_AES();
		$aes->setKeyLength(256);
		$aes->setKey(CRYPT_KEY);
		
		// Get SSH2 Object OR ERROR String
		$sftp = new Net_SFTP($box['ip'], $box['sshport']);
		if (!$sftp->login($box['login'], $aes->decrypt($box['password'])))
		{
			$_SESSION['msg1'] = T_('Connection Error!');
			$_SESSION['msg2'] = '';
			$_SESSION['msg-type'] = 'error';
			header( "Location: index.php");
			die();
		}
		
		$gameInstaller = new GameInstaller($sftp);
		
		$gameInstaller->setGame($game['game']);
		$gameInstaller->setGameServerPath(dirname($server['path']));
		$actions = $gameInstaller->actions;
		
		if(!isset($actions['configs']['file'][$configid]['value']) || empty($actions['configs']['file'][$configid]['value'])){
			$error .= T_('Invalid Config.');
		}
		###
		if (!empty($error))
		{
			$_SESSION['msg1'] = T_('Validation Error!');
			$_SESSION['msg2'] = $error;
			$_SESSION['msg-type'] = 'error';
			unset($error);
			header( "Location: index.php" );
			die();
		}
		
		require_once("../libs/serverconfigs/serverconfigs.php");
		$config = call_user_func($actions['configs']['file'][$configid]['attributes']['parser'] . "_write", $_POST['data']);
		if($config === -1)
		{
			session_destroy();
			$_SESSION['lockout'] = time();
			$_SESSION['loginattempt'] = 0; //Reseting attempts as the user will be ban for 5 mins
			//--Client$message = T_('Hack attempt Server Config').' ('.$_SESSION['clientid'].')';
			$message = T_('Hack attempt Server Config').' ('.$_SESSION['adminid'].')';
			query_basic( "INSERT INTO `".DBPREFIX."log` SET `message` = '".$message."', `name` = 'System Message', `ip` = '".$_SERVER['REMOTE_ADDR']."'" );
			//--Clientquery_basic("UPDATE `".DBPREFIX."client` SET `status`='Suspended' WHERE `clientid`='".$_SESSION['clientid']."'");
			header( "Location: index.php");
			die();
		}elseif(is_array($config)){
			$_SESSION['msg1'] = T_('Your config contains errors.');
			$_SESSION['msg2'] = T_($config[1]);
			$_SESSION['msg-type'] = 'error';
			header( "Location: serverconfig.php?id=".$serverid);
			die();
		}
		
		$config .= call_user_func($actions['configs']['file'][$configid]['attributes']['parser'] . "_append", $server, $box);
		
		$sftp->put(dirname($server['path']).'/'.$actions['configs']['file'][$configid]['value'],$config);
		$sftp->disconnect();
		

		$_SESSION['msg1'] = T_('Config saved successfull!');
		$_SESSION['msg2'] = T_('The config '.$actions['configs']['file'][$configid]['value'].' is saved.');
		$_SESSION['msg-type'] = 'success';
		header( "Location: serverconfig.php?id=".$serverid);
		die();
	break;
	
	case 'reset':
		$serverid = mysql_real_escape_string($_POST['id']);
		$configid = mysql_real_escape_string($_POST['config']);
		###
		$error = '';
		###
		if (empty($configid) && $configid == "")
		{
			$error .= T_('No Config specified!');
		}
		else
		{
			if (!is_numeric($configid))
			{
				$error .= T_('Invalid Config.');
			}
		}
		
		if (empty($serverid))
		{
			$error .= T_('No ServerID specified !');
		}
		else
		{
			if (!is_numeric($serverid))
			{
				$error .= T_('Invalid ServerID. ');
			}
			else if (query_numrows( "SELECT `name` FROM `".DBPREFIX."server` WHERE `serverid` = '".$serverid."'" ) == 0)
			{
				$error .= T_('Invalid ServerID. ');
			}
		}
		###
		if (!empty($error))
		{
			$_SESSION['msg1'] = T_('Validation Error!');
			$_SESSION['msg2'] = $error;
			$_SESSION['msg-type'] = 'error';
			unset($error);
			header( 'Location: server.php' );
			die();
		}
		###
		$server = query_fetch_assoc( "SELECT * FROM `".DBPREFIX."server` WHERE `serverid` = '".$serverid."' LIMIT 1" );
		$box = query_fetch_assoc( "SELECT `ip`, `login`, `password`, `sshport` FROM `".DBPREFIX."box` WHERE `boxid` = '".$server['boxid']."' LIMIT 1" );
		$game = query_fetch_assoc( "SELECT * FROM `".DBPREFIX."game` WHERE `gameid` = '".$server['gameid']."' LIMIT 1" );
		###
		
		require_once("../libs/gameinstaller/gameinstaller.php");
		require_once("../libs/phpseclib/SFTP.php");
		require_once("../libs/phpseclib/Crypt/AES.php");
		
		$aes = new Crypt_AES();
		$aes->setKeyLength(256);
		$aes->setKey(CRYPT_KEY);
		
		// Get SSH2 Object OR ERROR String
		$sftp = new Net_SFTP($box['ip'], $box['sshport']);
		if (!$sftp->login($box['login'], $aes->decrypt($box['password'])))
		{
			$_SESSION['msg1'] = T_('Connection Error!');
			$_SESSION['msg2'] = '';
			$_SESSION['msg-type'] = 'error';
			header( "Location: index.php");
			die();
		}
		
		$gameInstaller = new GameInstaller($sftp);
		
		$gameInstaller->setGame($game['game']);
		$gameInstaller->setGameServerPath(dirname($server['path']));
		$actions = $gameInstaller->actions;
		
		if(!isset($actions['configs']['file'][$configid]['value']) || empty($actions['configs']['file'][$configid]['value'])){
			$error .= T_('Invalid Config.');
		}
		###
		if (!empty($error))
		{
			$_SESSION['msg1'] = T_('Validation Error!');
			$_SESSION['msg2'] = $error;
			$_SESSION['msg-type'] = 'error';
			unset($error);
			header( "Location: index.php" );
			die();
		}
		
		require_once("../libs/serverconfigs/serverconfigs.php");
				
		$config = call_user_func($actions['configs']['file'][$configid]['attributes']['parser'] . "_reset", $server, $box);

		$sftp->put(dirname($server['path']).'/'.$actions['configs']['file'][$configid]['value'], $config);
		$sftp->disconnect();
		

		$_SESSION['msg1'] = T_('Config reset was successfull!');
		$_SESSION['msg2'] = T_('The config '.$actions['configs']['file'][$configid]['value'].' was successfull resetted.');
		$_SESSION['msg-type'] = 'success';
		header( "Location: serverconfig.php?id=".$serverid);
		die();
	break;

	default:
		exit('<h1><b>Error</b></h1>');
}

exit('<h1><b>403 Forbidden</b></h1>'); //If the task is incorrect or unspecified, we drop the user.
?>