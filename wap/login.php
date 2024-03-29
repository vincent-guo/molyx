<?php
# **************************************************************************#
# MolyX2
# ------------------------------------------------------
# @copyright (c) 2009-2012 MolyX Group.
# @official forum http://molyx.com
# @license http://opensource.org/licenses/gpl-2.0.php GNU Public License 2.0
#
# $Id$
# **************************************************************************#
define('THIS_SCRIPT', 'login');
require_once('./global.php');

class login
{
	function show($message = '')
	{
		global $forums;
		if ($bboptions['forcelogin'] == 1)
		{
			$this->message = $forums->lang['forcelogin'];
		}
		switch (input::str('do'))
		{
			case 'login':
				$this->dologin();
				break;
			case 'logout':
				$this->dologout();
				break;
			case 'autologin':
				$this->autologin();
				break;
			default:
				$this->loginpage();
				break;
		}
	}

	function loginpage()
	{
		global $forums, $bboptions, $bbuserinfo;
		if ($bbuserinfo['id'])
		{
			$forums->func->standard_redirect($bboptions['bburl'] . "/wap/index.php{$forums->sessionurl}");
		}

		$forums->lang['login'] = convert($forums->lang['login']);
		$forums->lang['username'] = convert($forums->lang['username']);
		$forums->lang['password'] = convert($forums->lang['password']);
		$forums->lang['boardlogin'] = convert($forums->lang['boardlogin']);
		$forums->lang['newregister'] = convert($forums->lang['newregister']);
		$forums->lang['invisible'] = convert($forums->lang['invisible']);
		$forums->lang['yes'] = convert($forums->lang['yes']);
		$forums->lang['no'] = convert($forums->lang['no']);
		$forums->lang['logintype'] = convert($forums->lang['logintype']);
		$forums->lang['type_username'] = convert($forums->lang['type_username']);
		$forums->lang['type_userid'] = convert($forums->lang['type_userid']);
		$forums->lang['type_email'] = convert($forums->lang['type_email']);

		if ($this->message != "")
		{
			$message = convert($this->message);
			$show['errors'] = true;
		}
		$referer = $forums->url;

		include $forums->func->load_template('wap_login');
		exit;
	}

	function dologin()
	{
		global $DB, $forums, $bboptions;
		$username = input::str('username');
		$password = input::str('password');
		if ($username == "" OR $password == "")
		{
			$forums->func->standard_error("plzinputallform");
		}

		$logintype = input::int('logintype');
		if ($logintype == 2)
		{
			$where = "id=" . intval($username) . "";
		}
		else if ($logintype == 3)
		{
			if (strlen($username) < 6)
			{
				$forums->func->standard_error('erroremail');
			}
			$username = clean_email($username);
			if (! $username)
			{
				$forums->func->standard_error('erroremail');
			}
			$where = "email='" . strtolower($username) . "'";
		}
		else
		{
			$check_name = preg_replace("/&#([0-9]+);/", "-", $username);
			if (strlen($check_name) > 32)
			{
				$forums->func->standard_error("nametoolong");
			}
			$username = addslashes(str_replace('|', '&#124;', $username));
			$where = "LOWER(name)='" . strtolower($username) . "' OR name='" . $username . "'";
		}

		$check_password = preg_replace("/&#([0-9]+);/", "-", $password);
		if (strlen($check_password) > 32)
		{
			$forums->func->standard_error("passwordtoolong");
		}
		$password = md5($password);
		$this->verify_strike_status($username);
		$user = $DB->queryFirst("SELECT id, name, email, usergroupid, password, host, options, salt, avatar from " . TABLE_PREFIX . "user WHERE $where");
		if (empty($user['id']) OR ($user['id'] == ""))
		{
			$this->message = $forums->lang['nouser'];
			$this->exec_strike_user($username);
		}
		if ($user['password'] != md5($password . $user['salt']))
		{
			$this->message = $forums->lang['errorpassword'];
			$this->exec_strike_user($username);
		}
		if ($user['usergroupid'] == 1)
		{
			$this->message = $forums->lang['activation'];
			return $this->loginpage();
		}
		$forums->func->convert_bits_to_array($user, $user['options']);
		$sessionid = input::str('s');
		if ($forums->func->get_cookie('sessionid'))
		{
			$sessionid = $forums->func->get_cookie('sessionid');
		}
		$invisible = input::int('invisible');
		if ($sessionid)
		{
			$DB->queryUnbuffered("UPDATE " . TABLE_PREFIX . "session SET username='" . $user['name'] . "', userid=" . $user['id'] . ", avatar=" . $user['avatar'] . ", lastactivity=" . TIMENOW . ", usergroupid=" . $user['usergroupid'] . ", invisible=" . $invisible . "  WHERE sessionhash='" . $sessionid . "'");
		}
		else
		{
			$sessionid = substr(md5(uniqid(microtime())), 0, 16);
			$sql_array = array(
				'sessionhash' => $sessionid,
				'username' => $user['name'],
				'userid' => $user['id'],
				'avatar' => $user['avatar'],
				'lastactivity' => TIMENOW,
				'usergroupid' => $user['usergroupid'],
				'host' => IPADDRESS,
				'useragent' => USER_AGENT,
				'invisible' => $invisible
			);
			$DB->insert(TABLE_PREFIX . 'session', $sql_array);
		}
		$bbuserinfo = $user;
		$forums->sessionid = $sessionid;
		$bbuserinfo['options'] = $forums->func->convert_array_to_bits(array_merge($bbuserinfo , array('invisible' => $invisible, 'loggedin' => 1)));

		$DB->update("UPDATE " . TABLE_PREFIX . "user", array('options' => $bbuserinfo['options']), 'id=' . $bbuserinfo['id'], SHUTDOWN_QUERY);
		$DB->delete(TABLE_PREFIX . 'useractivation', 'userid=' . $bbuserinfo['id'] . ' AND type=1', SHUTDOWN_QUERY);
		$DB->delete(TABLE_PREFIX . 'strikes', 'strikeip = ' . $DB->validate(IPADDRESS) . ' AND username = ' . $DB->validate($username), SHUTDOWN_QUERY);
		$forums->func->set_cookie("userid", $user['id'], 31536000);
		$forums->func->set_cookie("password", $user['password'], 31536000);
		$forums->func->set_cookie("sessionid", $forums->sessionid, 31536000);
		redirect("index.php?s=" . $forums->sessionid . "&amp;bbuid=" . $user['id'] . "&amp;bbpwd=" . $user['password'] . "", $forums->lang['loginsucess']);
	}

	function dologout()
	{
		global $forums, $DB, $bbuserinfo, $bboptions;
		$bbuserinfo['loggedin'] = 0;
		$bbuserinfo['options'] = $forums->func->convert_array_to_bits($bbuserinfo);
		$DB->update(TABLE_PREFIX . "session", array(
			'username' => '',
			'userid' => 0,
			'invisible' => 0,
			'avatar' => 0
		), "sessionhash='" . $forums->sessionid . "'");

		$DB->update(TABLE_PREFIX . "user", array(
			'options' =>$bbuserinfo['options'],
			'lastvisit' => TIMENOW,
			'lastactivity' => TIMENOW
		), 'id=' . $bbuserinfo['id'], SHUTDOWN_QUERY);

		$forums->func->set_cookie('password' , '-1');
		$forums->func->set_cookie('userid' , '-1');
		$forums->func->set_cookie('sessionid', '-1');
		$forums->func->set_cookie('threadread', '-1');
		$forums->func->set_cookie('invisible' , '-1');
		$forums->func->set_cookie('forumread', '-1');
		if (is_array($_COOKIE))
		{
			foreach($_COOKIE AS $cookie => $value)
			{
				if (preg_match("/^(" . $bboptions['cookieprefix'] . ".*$)/i", $cookie, $match))
				{
					$forums->func->set_cookie(str_replace($bboptions['cookieprefix'], "", $match[0]) , '-', -1);
				}
			}
		}
		redirect("index.php{$forums->sessionurl}", $forums->lang['logoutsucess']);
	}

	function autologin()
	{
		global $forums, $DB, $bboptions, $bbuserinfo;
		if (! $bbuserinfo['id'])
		{
			$userid = intval($forums->func->get_cookie('userid'));
			$password = $forums->func->get_cookie('password');
			If ($userid AND $password)
			{
				$DB->query("SELECT * FROM " . TABLE_PREFIX . "user WHERE id='$userid' AND password='$password'");
				if ($user = $DB->fetch())
				{
					$bbuserinfo = $user;
					$forums->func->load_style();
					$forums->sessionid = "";
					$forums->func->set_cookie('sessionid', '-1');
				}
			}
		}
		$login_success = $forums->lang['loginsuccess'];
		$login_failed = $forums->lang['loginfailed'];
		$show = false;
		switch (input::str('logintype'))
		{
			case 'fromreg':
				$login_success = $forums->lang['regsuccess'];
				$login_failed = $forums->lang['regfailed'];
				$show = true;
				break;
			case 'fromemail':
				$login_success = $forums->lang['mailsuccess'];
				$login_failed = $forums->lang['mailfailed'];
				$show = true;
				break;
			case 'frompass':
				$login_success = $forums->lang['passsuccess'];
				$login_failed = $forums->lang['passfailed'];
				$show = true;
				break;
		}
		if ($bbuserinfo['id'])
		{
			$redirect = input::str('referer');
			if ($show)
			{
				$forums->func->redirect_screen($login_success, $redirect);
			}
			else
			{
				$forums->func->standard_redirect($redirect);
			}
		}
		else
		{
			if ($show)
			{
				$forums->func->redirect_screen($login_failed, 'login.php');
			}
			else
			{
				$forums->func->standard_redirect('login.php' . $forums->sessionurl);
			}
		}
	}

	function verify_strike_status($username = '')
	{
		global $DB, $forums;
		$DB->queryUnbuffered("DELETE FROM " . TABLE_PREFIX . "strikes WHERE striketime < " . (TIMENOW - 3600));
		$strikes = $DB->queryFirst('SELECT COUNT(*) AS strikes, MAX(striketime) AS lasttime
			FROM ' . TABLE_PREFIX . 'strikes
			WHERE strikeip = ' . $DB->validate(IPADDRESS) . '
				AND username = ' . $DB->validate($username));
		$this->strikes = $strikes['strikes'];
		if ($this->strikes >= 5 AND $strikes['lasttime'] > TIMENOW - 900)
		{
			$this->message = $forums->lang['strikefailed1'];
			return $this->loginpage();
		}
		$maxstrikes = $DB->queryFirst("SELECT COUNT(*) AS strikes
			FROM " . TABLE_PREFIX . 'strikes
			WHERE strikeip = ' . $DB->validate(IPADDRESS));
		if ($this->strikes >= 30 AND $strikes['lasttime'] > TIMENOW - 1800)
		{
			$this->message = $forums->lang['strikefailed2'];
			return $this->loginpage();
		}
	}

	function exec_strike_user($username = '')
	{
		global $DB, $forums;
		$DB->insert(TABLE_PREFIX . 'strikes', array(
			'striketime' => TIMENOW,
			'strikeip' => IPADDRESS,
			'username' => $username
		), SHUTDOWN_QUERY);

		$this->strikes++;
		$times = $this->strikes;
		$forums->lang['striketimes'] = sprintf($forums->lang['striketimes'], $times);
		$this->message .= "<br />" . $forums->lang['striketimes'];
		return $this->loginpage();
	}
}

$output = new login();
$output->show();