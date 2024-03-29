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
define('THIS_SCRIPT', 'newpoll');
require_once('./global.php');

class newpoll
{
	var $posthash = '';
	var $post = array();
	var $thread = array();
	var $pollcount = 0;

	function show()
	{
		global $forums, $bboptions;
		$forums->func->load_lang('post');
		require_once(ROOT_PATH . 'includes/xfunctions_hide.php');
		$this->hidefunc = new hidefunc();

		$this->posthash = input::get('posthash', '');
		if (empty($this->posthash))
		{
			$this->posthash = md5(microtime());
		}

		$bboptions['maxpolloptions'] = $bboptions['maxpolloptions'] ? $bboptions['maxpolloptions'] : 10;
		require_once(ROOT_PATH . "includes/functions_credit.php");
		$this->credit = new functions_credit();

		$do = input::get('do', '');
		switch ($do)
		{
			case 'add':
				$this->addpoll();
				break;
			default:
				require ROOT_PATH . "includes/functions_post.php";
				$this->lib = new functions_post();
				$this->lib->dopost($this);
				break;
		}
	}

	function showform()
	{
		global $forums, $DB, $bboptions, $bbuserinfo;
		if (! $this->lib->forum['allowpoll'] OR ! $bbuserinfo['canpostpoll'])
		{
			$forums->func->standard_error("cannotpostpoll");
		}
		if ($forums->func->fetch_permissions($this->lib->forum['canstart'], 'canstart') != true)
		{
			$forums->func->standard_error("cannotpostpoll");
		}
		cache::get('usergroup');
		$usergrp = $forums->cache['usergroup'];
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

		$title = input::get('title', '');
		$description = input::get('description', '');

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
		if ($_POST['post'])
		{
			$content = utf8::htmlspecialchars($_POST['post']);
		}
		$show['title'] = true;
		if ($bboptions['enablepolltags'])
		{
			$extra = $forums->lang['enablepolltags'];
		}
		$poll = utf8::htmlspecialchars($_POST['polloptions']);
		if ($this->lib->obj['errors'])
		{
			$show['errors'] = true;
			$errors = $this->lib->obj['errors'];
		}
		if ($this->lib->moderator['caneditthreads'] OR $bbuserinfo['supermod'])
		{
			$show['colorpicker'] = true;
			$titlecolor = input::str('titlecolor');
		}
		if ($this->lib->obj['preview'])
		{
			$show['preview'] = true;
			require_once(ROOT_PATH . 'includes/class_textparse.php');
			$preview = textparse::convert_text($this->post['pagetext']);
		}
		$show['poll'] = true;
		$form_start = $this->lib->fetch_post_form(array(
			1 => array('do', 'update'),
			2 => array('f', $this->lib->forum['id']),
			3 => array('t', $this->thread['tid']),
			4 => array('posthash', $this->posthash),
		));
		$forums->lang['polldesc'] = sprintf($forums->lang['polldesc'], $this->lib->forum['name']);
		$postdesc = $forums->lang['polldesc'];
		$modoptions = $this->lib->modoptions();
		if ($this->lib->canupload)
		{
			$show['upload'] = true;
			$upload = $this->lib->fetch_upload_form($this->posthash, 'new');
		}
		$upload['maxnum'] = intval($bbuserinfo['attachnum']);
		if ($this->lib->forum['threadprefix'])
		{
			$threadprefix = explode('||', $this->lib->forum['threadprefix']);
		}
		if ($this->lib->forum['specialtopic'])
		{
			cache::get('st');
			$special_selected[0] = ' selected="selected"';
			$specialtopic = explode(',', $this->lib->forum['specialtopic']);
			$forumsspecial = $forums->cache['st'];
		}
		$forums->lang['optionsdesc'] = sprintf($forums->lang['optionsdesc'], $bboptions['maxpolloptions']);

		$credit_list = $this->credit->show_credit('newpoll', $bbuserinfo['usergroupid'], input::int('f'));
		$smiles = $this->lib->construct_smiles();
		$smile_count = $smiles['count'];
		$all_smiles = $smiles['all'];
		$smiles = $smiles['smiles'];
		$icons = $this->lib->construct_icons();
		$checked = $this->lib->construct_checkboxes();
		$pagetitle = $forums->lang['newpoll'] . " - " . $bboptions['bbtitle'];
		$nav = array_merge($this->lib->nav, array($forums->lang['newpoll']));
		$extrabuttons = $this->lib->code->construct_extrabuttons();
		$previewfunc = ' onclick="preview_post(' . $this->lib->forum['id'] . ');"';
		$antispam = $this->lib->code->showantispam();

		//加载ajax
		$mxajax_register_functions = array('dopreview_post', 'smiles_page', 'set_hidden_condition'); //注册ajax函数
		require_once(ROOT_PATH . 'includes/ajax/ajax.php');
		add_head_element('js', ROOT_PATH . 'scripts/mxajax_post.js');

		$referer = SCRIPTPATH;
		//加载编辑器js
		load_editor_js($extrabuttons);

		if (!$bbuserinfo['id'])
		{
			$username = input::str('username');
		}
		$question = input::str('question');
		include $forums->func->load_template('add_post');
		exit;
	}

	function process()
	{
		global $forums, $DB, $bbuserinfo, $bboptions;

		$f = input::int('f');
		$this->credit->check_credit('newpoll', $bbuserinfo['usergroupid'], $f);
		if (! $this->lib->forum['allowpoll'])
		{
			$forums->func->standard_error("cannotpostpoll");
		}
		if ($forums->func->fetch_permissions($this->lib->forum['canstart'], 'canstart') != true)
		{
			$forums->func->standard_error("cannotpostpoll");
		}
		$this->post = $this->lib->compile_post();
		$hidepostinfo = $this->hidefunc->check_hide_condition();
		if (!$hidepostinfo)
		{
			$this->post['hidepost'] = '';
		}
		else if (is_string($hidepostinfo) && strlen($hidepostinfo) > 0)
		{
			$this->lib->obj['errors'] = $hidepostinfo;
		}
		else
		{
			$hidepostinfo = serialize($hidepostinfo);
			$this->post['hidepost'] = $hidepostinfo;
		}
		if (($this->lib->obj['errors'] != "") OR ($this->lib->obj['preview'] != ""))
		{
			return $this->showform();
		}

		$title = input::get('title', '');
		if (utf8::strlen($title) < 2 OR !$title)
		{
			$this->lib->obj['errors'] = $forums->lang['musttitle'];
		}
		if (strlen(preg_replace("/&#([0-9]+);/", "-", $title)) > 80)
		{
			$this->lib->obj['errors'] = $forums->lang['titletoolong'];
		}
		$polloptions = array();
		$count = 0;
		$polls = explode('<br />', input::get('polloptions', ''));
		foreach ($polls as $options)
		{
			if (trim($options) == '')
			{
				continue;
			}
			$polloptions[] = array($count , $this->lib->parser->convert(array(
				'text' => $options,
				'allowsmilies' => $bboptions['enablepolltags'],
				'allowcode' => $bboptions['enablepolltags'],
			)), 0);
			$count++;
		}
		if ($count > $bboptions['maxpolloptions'])
		{
			$this->lib->obj['errors'] = $forums->lang['polltoomore'];
		}
		if ($count < 2)
		{
			$this->lib->obj['errors'] = $forums->lang['polltooless'];
		}
		if ($bboptions['useantispam'])
		{
			$antispam = $this->lib->validate_antispam();
			if (!$antispam)
			{
				$this->lib->obj['errors'] = $forums->lang['badimagehash'];
			}
		}
		if (($this->lib->obj['errors'] != "") OR ($this->lib->obj['preview'] != ""))
		{
			return $this->showform();
		}

		$title = $this->lib->parser->censoredwords($title);
		$description = input::get('description', '');
		$description = $this->lib->parser->censoredwords($description);

		if ($bboptions['disablenoreplypoll'] != 1)
		{
			$pollstate = input::int('allow_disc') == 0 ? 1 : 2;
		}
		else
		{
			$pollstate = 1;
		}
		$multipoll = input::int('allowmultipoll') ? 1 : 0;
		$sticky = 0;
		$open = 1;

		switch (input::get('modoptions', ''))
		{
			case 'gstick':
				$sticky = 99;
				$this->lib->moderate_log($forums->lang['gstickthread'] . ' - ', $title);
				break;
			case 'stick':
				$sticky = 1;
				$this->lib->moderate_log($forums->lang['stickthread'] . ' - ', $title);
				break;
			case 'close':
				if ($bbuserinfo['supermod'] OR $this->lib->moderator['canopenclose'])
				{
					$open = 0;
					$this->lib->moderate_log($forums->lang['closethread'] . ' - ', $title);
				}
				break;
			case 'gstickclose':
				if ($bbuserinfo['supermod'])
				{
					$sticky = 99;
					$open = 0;
					$this->lib->moderate_log($forums->lang['gstickclose'] . ' - ', $title);
				}
				break;
			case 'stickclose':
				if ($bbuserinfo['supermod'] OR ($this->lib->moderator['canstickthread'] AND $this->lib->moderator['canopenclose']))
				{
					$sticky = 1;
					$open = 0;
					$this->lib->moderate_log($forums->lang['stickclose'] . ' - ', $title);
				}
				break;
		}
		if ($bbuserinfo['cananonymous'] AND input::int('anonymous'))
		{
			$sql_array = array(
				'postuserid' => 0,
				'postusername' => 'anonymous*',
			);
			$newpuserid = $bbuserinfo['id'];
			$bbuserinfo['id'] = 0;
			$username = 'anonymous*';
		}
		else
		{
			$newpuserid = $bbuserinfo['id'];
			$username = input::get('username', '');
		}

		$splittable = $forums->func->getposttable();
		$posttable = $splittable['name'] ? $splittable['name'] : 'post';
		$this->thread = array(
			'title' => $title,
			'description' => $description,
			'open' => $open,
			'post' => 0,
			'postuserid' => $bbuserinfo['id'],
			'postusername' => $bbuserinfo['id'] ? $bbuserinfo['name'] : $username,
			'dateline' => TIMENOW,
			'lastposterid' => $bbuserinfo['id'],
			'lastposter' => $bbuserinfo['id'] ? $bbuserinfo['name'] : $username,
			'lastpost' => TIMENOW,
			'iconid' => input::int('iconid'),
			'pollstate' => $pollstate,
			'lastvote' => 0,
			'views' => 0,
			'forumid' => $this->lib->forum['id'],
			'visible' => $this->lib->obj['moderate'] ? 0 : 1,
			'sticky' => $sticky,
			'posttable' => $posttable,
		);
		$DB->insert(TABLE_PREFIX . 'thread', $this->thread);
		$this->post['threadid'] = $DB->insertId();
		$this->thread['tid'] = $this->post['threadid'];
		$this->post['posthash'] = $this->posthash;
		$this->post['newthread'] = 1;
		if ($this->lib->obj['moderate'] == 3)
		{
			$this->post['moderate'] = 0;
		}
		$DB->insert(TABLE_PREFIX . $posttable, $this->post);
		$this->post['pid'] = $DB->insertId();

		$sql_array['firstpostid'] = $this->post['pid'];
		$sql_array['lastpostid'] = $this->post['pid'];
		$DB->update(TABLE_PREFIX . 'thread', $sql_array, 'tid = ' . intval($this->thread['tid']));

		$sql_array = array(
			'tid' => $this->thread['tid'],
			'forumid' => $this->lib->forum['id'],
			'dateline' => TIMENOW,
			'options' => serialize($polloptions),
			'votes' => 0,
			'question' => $this->lib->parser->censoredwords(input::get('question', '')),
			'multipoll' => $multipoll,
		);
		$DB->insert(TABLE_PREFIX . 'poll', $sql_array);
		$this->lib->stats_recount($this->thread, 'new');
		$this->lib->attachment_complete(array($this->posthash), $this->thread['tid'], $this->post['pid'], $posttable);
		$this->lib->posts_recount();
		$this->credit->update_credit('newpoll', $newpuserid, $bbuserinfo['usergroupid'], $f);
		if ($this->lib->obj['moderate'] == 1 OR $this->lib->obj['moderate'] == 2)
		{
			$forums->lang['haspost'] = sprintf($forums->lang['haspost'], $forums->lang['poll']);
			$forums->func->redirect_screen($forums->lang['haspost'], "forumdisplay.php{$forums->sessionurl}&f=" . $this->lib->forum['id'] . "");
		}
		if (input::int('redirect'))
		{
			$forums->func->standard_redirect("forumdisplay.php{$forums->sessionurl}f={$this->lib->forum['id']}");
		}
		else
		{
			$forums->func->standard_redirect("showthread.php{$forums->sessionurl}t={$this->thread['tid']}");
		}
	}

	function addpoll()
	{
		global $forums, $DB, $bbuserinfo;
		if (!$bbuserinfo['canvote'])
		{
			$forums->func->standard_error("cannotvotepoll");
		}
		if (!input::int('nullvote'))
		{
			if (!input::is_set('poll_vote'))
			{
				$forums->func->standard_error("notselectpoll");
			}
		}
		$t = input::int('t');
		if (!$t)
		{
			$forums->func->standard_error("erroraddress");
		}
		$this->thread = $DB->queryFirst("SELECT f.allowpollup, t.*, p.pollid, p.options, p.votes, p.voters, u.usergroupid
			FROM " . TABLE_PREFIX . "poll p,
				" . TABLE_PREFIX . "thread t,
				" . TABLE_PREFIX . "user u,
				" . TABLE_PREFIX . "forum f
			WHERE t.tid = $t
				AND p.tid = t.tid
				AND t.postuserid = u.id
				AND t.forumid = f.id");
		$this->credit->check_credit('replypoll', $bbuserinfo['usergroupid'], $this->thread['forumid']);
		if (!$this->thread['tid'] OR ! $this->thread['pollstate'])
		{
			$forums->func->standard_error("erroraddress");
		}
		if ($this->thread['open'] != 1)
		{
			$forums->func->standard_error("cannotvote");
		}
		if (preg_match("#\," . $bbuserinfo['id'] . "\,#", "\," . $this->thread['voters']))
		{
			$forums->func->standard_error("alreadyvote");
		}
		$polloptions = unserialize($this->thread['options']);
		reset($polloptions);
		$newpolloptions = array();

		$poll_vote = input::get('poll_vote', array(0));
		foreach ($polloptions as $entry)
		{
			$id = $entry[0];
			$choice = $entry[1];
			$votes = $entry[2];

			if (in_array($id, $poll_vote))
			{
				$votes++;
			}

			$newpolloptions[] = array($id, $choice, $votes);
		}
		$this->thread['options'] = serialize($newpolloptions);
		$this->thread['voters'] = $this->thread['voters'] . $bbuserinfo['id'] . ',';
		$pollcount = count($poll_vote);

		$DB->update(TABLE_PREFIX . 'poll', array(
			'votes' => array($pollcount,'+'),
			'options' => $this->thread['options'],
			'voters' => $this->thread['voters']
		), "pollid='{$this->thread['pollid']}'", SHUTDOWN_QUERY);

		$sql_array = array();
		if ($this->thread['allowpollup'])
		{
			$sql_array['lastpost'] = TIMENOW;
		}
		$sql_array['lastvote'] = TIMENOW;
		$DB->update(TABLE_PREFIX . 'thread', $sql_array, "tid={$this->thread['tid']}", SHUTDOWN_QUERY);
		$this->credit->update_credit('replypoll', $bbuserinfo['id'], $bbuserinfo['usergroupid'], $this->thread['forumid']);
		$this->credit->update_credit('threadpoll', $this->thread['postuserid'], $this->thread['usergroupid'], $this->thread['forumid']);
		$forums->func->standard_redirect("showthread.php{$forums->sessionurl}f={$this->thread['forumid']}&amp;t={$this->thread['tid']}&amp;pp=" . input::int('pp'));
	}
}

$output = new newpoll();
$output->show();