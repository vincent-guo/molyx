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
define('THIS_SCRIPT', 'editpost');
require_once('./global.php');

class editpost
{
	var $getpost = '';
	var $thread = array();
	var $post = array();
	var $posthash = '';
	var $edittitle = '';
	var $moderator = array();
	var $edittype = '';
	var $posttable = '';
	var $poster = '';

	function show()
	{
		global $forums, $DB;
		$forums->func->load_lang('post');
		require_once(ROOT_PATH . 'includes/xfunctions_hide.php');
		$this->hidefunc = new hidefunc();

		$tid = input::get('t', 0);
		$this->thread = $DB->queryFirst("SELECT * FROM " . TABLE_PREFIX . "thread WHERE tid = $tid");
		$this->posttable = $this->thread['posttable'] ? $this->thread['posttable'] : 'post';

		$pid = input::get('p', 0);
		$this->getpost = $DB->queryFirst("SELECT * FROM " . TABLE_PREFIX . $this->posttable . " WHERE pid = $pid");
		if (! $this->getpost)
		{
			$forums->func->standard_error("cannoteditpost");
		}
		$this->poster = $DB->queryFirst("SELECT id, usergroupid FROM " . TABLE_PREFIX . "user
		                                  WHERE id=" . intval($this->getpost['userid']));
		if($this->getpost['state'] == 1)
		{
			$forums->func->standard_error("isdeleted");
		}
		if (! $this->getpost['posthash'])
		{
			$this->posthash = md5(microtime());
			$DB->queryUnbuffered("UPDATE " . TABLE_PREFIX . $this->posttable . " SET posthash='" . $this->posthash . "' WHERE pid='" . $this->getpost['pid'] . "'");
			$DB->queryUnbuffered("UPDATE " . TABLE_PREFIX . "attachment SET posthash='" . $this->posthash . "' WHERE postid='" . $this->getpost['pid'] . "'");
		}
		else
		{
			$this->posthash = $this->getpost['posthash'];
		}
		if ($this->thread['firstpostid'] == $pid)
		{
			$this->edittype = 'editthread';
		}
		else
		{
			$this->edittype = 'editreply';
		}
		require_once(ROOT_PATH . 'includes/functions_credit.php');
		$this->credit = new functions_credit();
		require_once(ROOT_PATH . "includes/functions_post.php");
		$this->lib = new functions_post();
		$this->lib->dopost($this);
	}

	function showform()
	{
		global $forums, $DB, $bbuserinfo, $bboptions;
		$this->fetch_permission();
		cache::get('usergroup');
		$usergrp = $forums->cache['usergroup'];
		foreach ($usergrp AS $k => $v)
		{
			$v['grouptitle'] = $forums->lang[$v['grouptitle']];
			$usergrp[$k] = $v;
		}
		cache::get('creditlist');
		$hidecredit = array();
		if ($forums->cache['creditlist'])
		{
			foreach ($forums->cache['creditlist'] as $k => $v)
			{
				$hidecredit[$v['tag']] = $v['name'];
			}
		}

		$hidetypes = $this->hidefunc->generate_hidetype_list();

		if ($this->getpost['hidepost'])
		{
			$hideinfo = unserialize($this->getpost['hidepost']);
		}
		else
		{
			$hideinfo = array();
		}
		$this->cookie_mxeditor = $forums->func->get_cookie('mxeditor');
		if ($this->cookie_mxeditor)
		{
			$bbuserinfo['usewysiwyg'] = ($this->cookie_mxeditor == 'wysiwyg') ? 1 : 0;
		}
		else if ($bboptions['mxemode'])
		{
			$bbuserinfo['usewysiwyg'] = 1;
		}
		else
		{
			$bbuserinfo['usewysiwyg'] = 0;
		}

		if (!isset($_POST['post']))
		{
			$_POST['post'] = $this->lib->parser->unconvert($this->getpost['pagetext'], $this->lib->forum['allowbbcode'], $this->lib->forum['allowhtml'], $bbuserinfo['usewysiwyg']);
		}
		if ($_POST['post'])
		{
			$content = $_POST['post'];
		}

		if (isset($content))
		{
			if (!$bbuserinfo['usewysiwyg'])
			{
				$content = preg_replace("#\[code(.+?)?\](.+?)\[/code\]#ies" , "str_replace('<br />', '&lt;br /&gt;', '[code\\1]\\2[/code]')", $content);
				$content = preg_replace("#<br.*>#siU", "\n", $content);
			}
			$content = $this->lib->init_post($content);
		}
		if ($this->docredit)
		{
			$show['docredit'] = true;
		}

		if ($this->edittitle)
		{
			if ($this->moderator['caneditthreads'] || $bbuserinfo['supermod'])
			{
				$show['colorpicker'] = true;
				$titlecolor = input::str('titlecolor');
			}

			$show['title'] = true;
			$title = input::get('title', $this->thread['title']);

			$title = utf8::unhtmlspecialchars($title);
			if (preg_match('#<strong>(.*)</strong>#siU', $title))
			{
				$title = preg_replace('#<strong>(.*)</strong>#siU', '\\1', $title);
				input::set('titlebold', 'checked="checked"');
			}
			if (preg_match('#<font[^>]+color=(\'|")(.*)(\\1)>(.*)</font>#esiU', $title))
			{
				$titlecolor = preg_replace('#<font[^>]+color=(\'|")(.*)(\\1)>(.*)</font>#siU', '\\2', $title);
			}

			$title = strip_tags($title);
			if ($this->lib->forum['threadprefix'])
			{
				$threadprefix = explode('||', $this->lib->forum['threadprefix']);
			}
			if ($this->lib->forum['specialtopic'])
			{
				cache::get('st');
				$special_selected[$this->thread['stopic']] = ' selected="selected"';
				$specialtopic = explode(',', $this->lib->forum['specialtopic']);
				$forumsspecial = $forums->cache['st'];
			}
		}
		if ($this->lib->obj['errors'])
		{
			$show['errors'] = true;
			$errors = $this->lib->obj['errors'];
		}
		if ($this->lib->obj['preview'])
		{
			$show['preview'] = true;
			require_once(ROOT_PATH . 'includes/class_textparse.php');
			$preview = textparse::convert_text($this->post['pagetext']);
		}
		$form_start = $this->lib->fetch_post_form(array(1 => array('do', 'update'),
				2 => array('t', $this->thread['tid']),
				3 => array('p', input::get('p', 0)),
				4 => array('pp', input::get('pp', 0)),
				5 => array('posthash', $this->posthash))
			);
		$postdesc = $forums->lang['editpost'];
		if ($this->lib->canupload)
		{
			$show['upload'] = true;
			$upload = $this->lib->fetch_upload_form($this->posthash, 'edit', $this->getpost['pid']);
		}
		$upload['maxnum'] = intval($bbuserinfo['attachnum']);
		if ($bbuserinfo['canappendedit'])
		{
			$show['appendedit'] = true;
		}
		$credit_list = $this->credit->show_credit($this->edittype, $this->poster['usergroupid'], input::get('f', 0));
		$smiles = $this->lib->construct_smiles();
		$smile_count = $smiles['count'];
		$all_smiles = $smiles['all'];
		$smiles = $smiles['smiles'];
		$iconid = input::get('iconid', $this->getpost['iconid']);
		input::set('iconid', $iconid);

		cache::get('icon');
		if (!$this->getpost['iconid'])
		{
			$this->getpost['iconid'] = 2;
		}
		$icons = $this->lib->construct_icons();
		$checked = $this->lib->construct_checkboxes();
		$pagetitle = $forums->lang['editpost'] . " - " . $bboptions['bbtitle'];
		$nav = array_merge($this->lib->nav, array("<a href='showthread.php{$forums->sessionurl}t=" . $this->thread['tid'] . "' title='" . strip_tags($this->thread['title']) . "'>" . $forums->func->fetch_trimmed_title($this->thread['title'], 12) . "</a>", $forums->lang['editpost']));
		$extrabuttons = $this->lib->code->construct_extrabuttons();
		$previewfunc = ' onclick="preview_post(' . $this->lib->forum['id'] . ');"';

		if ($bbuserinfo['usewysiwyg'])
		{
			$content = preg_replace("#\[code(.+?)?\](.+?)\[/code\]#ies" , "str_replace('&lt;br /&gt;', '<br />', '[code\\1]\\2[/code]')", $content);
			$content = str_replace(array('&lt;', '&gt;'), array('&amp;lt;', '&amp;gt;'), $content);
		}
		$forum = $this->lib->forum;

		//加载ajax
		$mxajax_register_functions = array('dopreview_post', 'smiles_page'); //注册ajax函数
		require_once(ROOT_PATH . 'includes/ajax/ajax.php');
		add_head_element('js', ROOT_PATH . 'scripts/mxajax_post.js');

		$referer = SCRIPTPATH;
		//加载编辑器js
		load_editor_js($extrabuttons);

		if (!$bbuserinfo['id'])
		{
			$username = input::str('username');
		}
		include $forums->func->load_template('add_post');
		exit;
	}

	function process()
	{
		global $forums, $DB, $bbuserinfo;
		$this->fetch_permission();

		$f = input::get('f', 0);
		if (!$bbuserinfo['supermod'] && !$this->moderator['caneditposts'])
		{
			$this->credit->check_credit($this->edittype, $this->poster['usergroupid'], $f);
		}
		$this->post = $this->lib->compile_post();//这里加入修改人和时间

		$title = input::get('title', '');
		if ($this->edittitle)
		{
			if ((utf8::strlen($title) < 2) || !$title)
			{
				$this->lib->obj['errors'] = $forums->lang['musttitle'];
			}
			if (strlen($title) > 250)
			{
				$this->lib->obj['errors'] = $forums->lang['titletoolong'];
			}
		}

		$hidepostinfo = $this->hidefunc->check_hide_condition();
		if (is_string($hidepostinfo) && strlen($hidepostinfo) > 0)
		{
			$this->lib->obj['errors'] = $hidepostinfo;
		}
		else if (!$this->getpost['hidepost'] && is_array($hidepostinfo))
		{
			$hidepostinfo = serialize($hidepostinfo);
			$this->post['hidepost'] = $hidepostinfo;
		}
		else if ($this->getpost['hidepost'] && is_array($hidepostinfo))
		{
			$oldhideinfo = unserialize($this->getpost['hidepost']);
			if (($oldhideinfo['type'] == 1 || $oldhideinfo['type'] == 2) && ($hidepostinfo['type'] == 1 || $hidepostinfo['type'] == 2))
			{
				$newhideinfo = $hidepostinfo;
				$newhideinfo['buyers'] = $oldhideinfo['buyers'];
			}
			else
			{
				$newhideinfo = $hidepostinfo;
			}
			$newhideinfo = serialize($newhideinfo);
			$this->post['hidepost'] = $newhideinfo;
		}
		else
		{
			$this->post['hidepost'] = '';
		}
		if (($this->lib->obj['errors'] != "") OR ($this->lib->obj['preview'] != ""))
		{
			return $this->showform();
		}
		$time = $forums->func->get_date(TIMENOW, 2, 1);
		$this->post['host'] = $this->getpost['host'];
		$this->post['threadid'] = $this->getpost['threadid'];
		$this->post['userid'] = $this->getpost['userid'];
		$this->post['pid'] = $this->getpost['pid'];
		$this->post['dateline'] = $this->getpost['dateline'];
		$this->post['username'] = $this->getpost['username'];
		$this->post['moderate'] = $this->getpost['moderate'];

		$uptitle = array();
		if ($this->getpost['newthread'] == 1)
		{
			if ($this->post['iconid'] != $this->getpost['iconid'] AND $this->post['iconid'] != '')
			{
				$uptitle['iconid'] = intval($this->post['iconid']);
			}
		}

		if ($this->edittitle)
		{
			$specialtopic = input::get('specialtopic', 0);
			if ($this->lib->forum['forcespecial'])
			{
				$specialtopic = $this->thread['stopic'];
			}

			$title = $this->lib->parser->censoredwords($title);
			$this->lib->moderator = $this->moderator;

			$title = $this->lib->compile_title($title);
			if (($title != $this->thread['title']) OR ($title != $this->thread['stopic']))
			{
				if ($title != $this->thread['title'])
				{
					$uptitle['title'] = $title;
					$uptitle['titletext'] = implode(' ', duality_word(strip_tags($title)));
				}

				if ($specialtopic != $this->thread['stopic'])
				{
					$uptitle['stopic'] = $specialtopic;
				}

				if ($this->thread['tid'] == $this->lib->forum['lastthreadid'] && $this->lib->forum['id'] > 0)
				{
					$DB->update(TABLE_PREFIX . 'forum', array(
						'lastthread' => strip_tags($title)
					). "id='" . $this->lib->forum['id'] . "'");
				}

				if (($this->moderator['caneditthreads'] == 1) OR ($bbuserinfo['supermod'] == 1))
				{
					$sql_array = array(
						'forumid' => $this->lib->forum['id'],
						'threadid' => $this->thread['tid'],
						'postid' => $this->post['pid'],
						'userid' => $bbuserinfo['id'],
						'username' => $bbuserinfo['name'],
						'host' => IPADDRESS,
						'referer' => REFERRER,
						'dateline' => TIMENOW,
						'title' => $this->thread['title'],
						'action' => $forums->lang['changetitle'] . '"' . $this->thread['title'] . '"' . $forums->lang['changetitleto'] . '"' . $title . '"',
					);
					$DB->insert(TABLE_PREFIX . 'moderatorlog', $sql_array);
				}
			}
		}

		if (!empty($uptitle))
		{
			$DB->update(TABLE_PREFIX . 'thread', $uptitle, "tid=" . $this->thread['tid']);
		}

		//记录最后更新人
		$this->post['updateuid'] = $bbuserinfo['id'];
		$this->post['updateuname'] = $bbuserinfo['name'];
		$this->post['updatetime'] = TIMENOW;
		if ((($bbuserinfo['canappendedit'] && input::get('appendedit', 0)) || !$bbuserinfo['canappendedit']) && (THIS_SCRIPT == 'editpost' || THIS_SCRIPT == 'editor'))
		{
			$this->post['displayuptlog'] = 1;
		}
		else
		{
			$this->post['displayuptlog'] = 0;
		}
		$DB->update(TABLE_PREFIX . $this->posttable, $this->post, "pid = " . $this->post['pid']);
		$this->credit->update_credit($this->edittype, $this->poster['id'], $this->poster['usergroupid'], $f);
		$this->lib->attachment_complete(array($this->posthash), $this->thread['tid'], $this->post['pid'], $this->posttable);
		if (input::get('redirect', 0))
		{
			$forums->func->standard_redirect("forumdisplay.php{$forums->sessionurl}f={$this->lib->forum['id']}");
		}
		else
		{
			$pp = input::get('pp', 0);
			$forums->func->standard_redirect("showthread.php{$forums->sessionurl}t={$this->thread['tid']}&amp;pp={$pp}#pid{$this->post['pid']}");
		}
	}

	function fetch_permission()
	{
		global $forums, $bbuserinfo;
		$canedit = false;
		if (($bbuserinfo['id']) AND ($bbuserinfo['supermod'] != 1))
		{
			$this->moderator = $bbuserinfo['_moderator'][ $this->lib->forum['id'] ];
		}
		if ($bbuserinfo['supermod'] OR $this->moderator['caneditposts'])
		{
			$canedit = true;
		}
		else if (($this->getpost['userid'] == $bbuserinfo['id']) AND ($bbuserinfo['caneditpost']))
		{
			if ($bbuserinfo['edittimecut'] > 0)
			{
				if ($this->getpost['dateline'] > (TIMENOW - (intval($bbuserinfo['edittimecut']) * 60)))
				{
					$canedit = true;
				}
			}
			else
			{
				$canedit = true;
			}
		}
		if ($canedit != true)
		{
			$forums->func->standard_error("exceededitpost");
		}
		if (($this->thread['open'] != 1) AND (!$bbuserinfo['supermod']))
		{
			if ($bbuserinfo['canpostclosed'] != 1)
			{
				$forums->func->standard_error("threadeditclosed");
			}
		}
		$this->edittitle = false;
		/*
		* 2010-01-13修改此处
		* 修改者：Dahong
		* 修复问题：后台设置版主无权修改帖子的标题后，版主仍然能够修改帖子标题
		*/
		if ($this->getpost['newthread'] == 1 && $bbuserinfo['caneditthread'] == 1)
		{
			if ($bbuserinfo['supermod'] == 1)
			{
				$this->edittitle = true;
			}
			else if ($this->moderator['caneditthreads'] == 1)
			{
				$this->edittitle = true;
			}
		}
		$this->docredit = false;
		if ($this->getpost['userid'] != $bbuserinfo['id'])
		{
			$this->docredit = true;
		}
		return;
	}
}

$output = new editpost();
$output->show();