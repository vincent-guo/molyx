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
define('THIS_SCRIPT', 'attachment');
require_once('./global.php');

class attachment
{
	function show()
	{
		global $DB, $forums;
		$id = input::int('id');
		$tid = input::int('tid');

		if (!$tid)
		{
			$forums->func->load_lang('error');
			$forums->lang['wapinfo'] = convert($forums->lang['wapinfo']);
			$contents = convert($forums->lang['errorthreadlink']);
			include $forums->func->load_template('wap_info');
			exit;
		}
		$this->thread = $DB->queryFirst("SELECT * FROM " . TABLE_PREFIX . "thread WHERE tid={$tid}");
		if (!$this->thread['attach'])
		{
			$forums->func->load_lang('error');
			$forums->lang['wapinfo'] = convert($forums->lang['wapinfo']);
			$contents = convert($forums->lang['erroraddress']);
			include $forums->func->load_template('wap_info');
			exit;
		}
		$this->forum = $forums->forum->single_forum($this->thread['forumid']);
		if (!$this->forum['id'])
		{
			$forums->func->load_lang('error');
			$forums->lang['wapinfo'] = convert($forums->lang['wapinfo']);
			$contents = convert($forums->lang['erroraddress']);
			include $forums->func->load_template('wap_info');
			exit;
		}

		switch (input::str('do'))
		{
			case 'view':
				$this->listattachment();
				break;
			default:
				$this->showattachment();
				break;
		}
	}

	function listattachment()
	{
		global $DB, $forums, $bboptions, $bbuserinfo;

		$thread_title = convert(strip_tags($this->thread['title']));
		$threadtitle = "<a href='thread.php{$forums->sessionurl}t={$this->thread['tid']}&amp;extra=" . input::str('extra') . "'>{$thread_title}</a>";

		$attachments = $DB->query("SELECT a.*, t.*, p.threadid, p.pid FROM " . TABLE_PREFIX . "attachment a
													LEFT JOIN " . TABLE_PREFIX . "post p ON ( a.postid=p.pid )
													LEFT JOIN " . TABLE_PREFIX . "thread t ON ( t.tid=p.threadid )
													WHERE p.threadid={$this->thread['tid']}
													ORDER BY a.dateline"
			);
		while ($row = $DB->fetch($attachments))
		{
			if ($forums->func->fetch_permissions($forums->forum->foruminfo[ $row['forumid'] ]['canread'], 'canread') != true)
			{
				continue;
			}
			$row['dateline'] = $forums->func->get_date($row['dateline'], 1);
			$row['filesize'] = fetch_number_format($row['filesize'], true);
			$attach[] = $row;
		}
		if (is_array($attach))
		{
			foreach ($attach AS $a)
			{
				$attachlink .= "<p><a href='attach.php{$forums->sessionurl}id={$a['attachmentid']}&amp;tid={$this->thread['tid']}'>{$a['filename']}</a><br />\r\n";
				$attachlink .= "<small>size: {$a['filesize']}</small><br />\r\n";
				$attachlink .= "<small>{$a['dateline']}</small></p>\r\n";
			}
		}
		$attachlink = convert($attachlink);

		$otherlink = $this->otherlink();
		include $forums->func->load_template('wap_attachment');
		exit;
	}

	function showattachment()
	{
		global $DB, $forums, $bbuserinfo, $bboptions;
		$forums->noheader = 1;

		$id = input::int('id');
		$attachment = $DB->queryFirst("SELECT a.*, t.*, p.threadid, p.pid
			FROM " . TABLE_PREFIX . "attachment a
				LEFT JOIN " . TABLE_PREFIX . "post p
					ON ( a.postid=p.pid )
				LEFT JOIN " . TABLE_PREFIX . "thread t
					ON ( t.tid=p.threadid )
			WHERE a.attachmentid = $id"
		);

		if (!$attachment['attachmentid'] OR $attachment['threadid'] != $this->thread['tid'])
		{
			$forums->func->load_lang('error');
			$forums->lang['wapinfo'] = convert($forums->lang['wapinfo']);
			$contents = convert($forums->lang['erroraddress']);
			include $forums->func->load_template('wap_info');
			exit;
		}
		if (!$bbuserinfo['candownload'])
		{
			$forums->func->load_lang('error');
			$forums->lang['wapinfo'] = convert($forums->lang['wapinfo']);
			$contents = convert($forums->lang['erroraddress']);
			include $forums->func->load_template('wap_info');
			exit;
		}
		if ($bboptions['remoteattach'])
		{
			$subpath = implode('/', preg_split('//', intval($attachment['userid']), -1, PREG_SPLIT_NO_EMPTY));
			$subpath = $bboptions['remoteattach'] . "/" . $subpath;
			$attachment['location'] = str_replace("\\", "/", $attachment['location']);
			$attachment['location'] = str_replace("/", "", substr($attachment['location'], strrpos($attachment['location'], '/')));
			$showfile = $subpath . "/" . $attachment['location'];
			redirect($showfile);
		}
		else
		{
			$subpath = $attachment['attachpath'] ? $attachment['attachpath'] : '';
			$path = $bboptions['uploadfolder'] . '/' . $subpath;
			$attachment['location'] = str_replace("\\", "/", $attachment['location']);
			$attachment['location'] = str_replace("/", "", substr($attachment['location'], strrpos($attachment['location'], '/')));
			$showfile = $path . "/" . $attachment['location'];
			cache::get('attachmenttype');
			if (file_exists($showfile) AND ($forums->cache['attachmenttype'][ $attachment['extension'] ]['mimetype'] != ""))
			{
				@ob_end_clean();
				@ob_start();
				@ob_implicit_flush(0);
				if ($bboptions['attachmentviewsdelay'])
				{
					if (@$fp = fopen(ROOT_PATH . 'cache/cache/attachmentviews.txt', 'a'))
					{
						fwrite($fp, $id . "\n");
						fclose($fp);
					}
				}
				else
				{
					$DB->update(TABLE_PREFIX . 'attachment', array('counter' => array(1, '+')), "attachmentid = $id", SHUTDOWN_QUERY);
				}
				@header('Cache-control: max-age=31536000');
				@header('Expires: ' . $forums->func->get_time(TIMENOW + 31536000, "D, d M Y H:i:s") . ' GMT');
				@header('Content-Type: ' . $forums->cache['attachmenttype'][ $attachment['extension'] ]['mimetype']);
				@header('Content-Disposition: inline; filename=' . $attachment['filename']);
				@header('Content-Length: ' . (string)(filesize($showfile)));
				@readfile($showfile);
				exit();
			}
			else
			{
				$forums->func->load_lang('error');
				$forums->lang['wapinfo'] = convert($forums->lang['wapinfo']);
				$contents = convert($forums->lang['erroraddress']);
				include $forums->func->load_template('wap_info');
				exit;
			}
		}
	}

	function otherlink()
	{
		global $forums, $DB, $bbuserinfo;
		$extra = input::str('extra');

		$otherlink = "<p>";
		$otherlink .= "{$forums->lang['forum']}: <a href='forum.php{$forums->sessionurl}f={$this->forum['id']}{$this->extra}' title='{$forums->lang['go']}'>" . strip_tags($this->forum['name']) . "</a><br />";
		$otherlink .= "{$forums->lang['thread']}: <a href='thread.php{$forums->sessionurl}t={$this->thread['tid']}&amp;extra=$extra' title='{$forums->lang['go']}'>" . strip_tags($this->thread['title']) . "</a><br />";
		if ($prevthread = $DB->queryFirst("SELECT tid, title FROM " . TABLE_PREFIX . "thread WHERE forumid='" . $this->forum['id'] . "' AND visible=1 AND open != 2 AND lastpost < '" . $this->thread['lastpost'] . "' ORDER BY lastpost DESC LIMIT 0, 1"))
		{
			$otherlink .= "{$forums->lang['prevthread']}: <a href='thread.php{$forums->sessionurl}t={$prevthread['tid']}&amp;extra=$extra' title='{$forums->lang['go']}'>" . strip_tags($prevthread['title']) . "</a><br />";
		}
		if ($nextthread = $DB->queryFirst("SELECT tid, title FROM " . TABLE_PREFIX . "thread WHERE forumid='" . $this->forum['id'] . "' AND visible=1 AND open != 2 AND lastpost > '" . $this->thread['lastpost'] . "' ORDER BY lastpost LIMIT 0, 1"))
		{
			$otherlink .= "{$forums->lang['nextthread']}: <a href='thread.php{$forums->sessionurl}t={$nextthread['tid']}&amp;extra=$extra' title='{$forums->lang['go']}'>" . strip_tags($nextthread['title']) . "</a><br />";
		}
		$otherlink .= "</p>\n";
		return convert($otherlink);
	}
}

$output = new attachment();
$output->show();

?>