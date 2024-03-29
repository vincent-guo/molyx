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
class functions_showcode
{
	var $rc;
	var $fu = null;

	function showantispam()
	{
		global $forums, $DB, $bboptions;

		if ($bboptions['useantispam'])
		{
			$regimagehash = md5(uniqid(microtime()));
			$imagestamp = mt_rand(1000, 9999);
			$DB->insert(TABLE_PREFIX . 'antispam', array(
				'regimagehash' => $regimagehash,
				'imagestamp' => $imagestamp,
				'host' => IPADDRESS,
				'dateline' => TIMENOW
			));
			if ($bboptions['enableantispam'] == 'gd')
			{
				$show['gd'] = true;
				return array('imagehash' => $regimagehash, 'text' => 1);
			}
			else
			{
				$this->rc = $regimagehash;
				return array('imagehash' => $regimagehash, 'text' => $this->showimage());
			}
		}
	}

	function construct_extrabuttons()
	{
		global $forums, $DB, $bboptions, $bbuserinfo;
		cache::get('bbcode');
		if ($bbuserinfo['canuseflash'])
		{
			$forums->cache['bbcode']['flash'] = array('bbcodetag' => 'flash', 'imagebutton' => 'images/editor/flash.gif');
		}
		$arraynum = count($forums->cache['bbcode']);
		$i = 1;
		foreach ($forums->cache['bbcode'] AS $bbcode)
		{
			if ($bbcode['imagebutton'])
			{
				$bbcode['bbcodetag'] = strtolower($bbcode['bbcodetag']);
				$alt = sprintf($forums->lang['_inserttags'], $bbcode['bbcodetag']);
				if ($bbcode['twoparams'] == 1)
				{
					$extrabuttons[] = 'true';
					if ($i < $arraynum)
					{
						$buttonpush .= "'" . $bbcode['bbcodetag'] . "',";
						$alts .= "'" . $alt . "',";
						$sty .= "'',";
						$i++;
					}
					else
					{
						$buttonpush .= "'" . $bbcode['bbcodetag'] . "'";
						$alts .= "'" . $alt . "'";
						$sty .= "''";
					}
				}
				else
				{
					$extrabuttons[] = 'false';
					if ($i < $arraynum)
					{
						$buttonpush .= "'" . $bbcode['bbcodetag'] . "',";
						$alts .= "'" . $alt . "',";
						$sty .= "'',";
						$i++;
					}
					else
					{
						$buttonpush .= "'" . $bbcode['bbcodetag'] . "'";
						$alts .= "'" . $alt . "'";
						$sty .= "''";
					}
				}
			}
		}
		$extrabuttons = implode(",", (array) $extrabuttons);
		$extrabuttons = "[[" . $buttonpush . "],[" . $alts . "],[" . $extrabuttons . "],[" . $sty . "]]";
		return $extrabuttons;
	}

	function showimage($simg = 0)
	{
		global $forums, $DB, $bboptions;
		$rc = input::str('rc');
		if (!$rc)
		{
			$rc = trim($this->rc);
		}

		if ($rc == '')
		{
			return false;
		}
		$sql = 'SELECT *
			FROM ' . TABLE_PREFIX . 'antispam
			WHERE regimagehash = ' . $DB->validate($rc);
		if (!$row = $DB->queryFirst($sql))
		{
			return false;
		}

		if (is_null($this->fu))
		{
			require_once(ROOT_PATH . 'includes/functions_user.php');
			$this->fu = new functions_user();
		}

		if ($bboptions['enableantispam'] == 'gd')
		{
			$this->fu->show_gd_img($row['imagestamp'], $simg);
		}
		else
		{
			return $this->fu->show_gif_img($row['imagestamp']);
		}
	}
}