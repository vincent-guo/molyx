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
$mxajax_register_functions = array(
	'quick_reply',
	'do_change_signature',
	'returnpagetext',
	'do_edit_post',
	'process_post_form',
	'dopreview_post',
	'smiles_page',
	'send_mailto_friend',
); //注册ajax函数

/**
 * 快速回复处理
 *
 * @param array $submit_data 由表单提交的数据
 * @param string $post_content 提交的帖子内容
 * @param string $wmode 编辑器类型
 * @return ajax处理结果
 */
function quick_reply($submit_data, $post_content, $wmode = 'wysiwyg')
{
	global $forums, $DB, $bbuserinfo, $bboptions, $response;
	input::set($submit_data);

	//灌水时间检测
	if ($bboptions['floodchecktime'] > 0)
	{
		if (!$bbuserinfo['passflood'])
		{
			if (TIMENOW - $bbuserinfo['lastpost'] < $bboptions['floodchecktime'])
			{
				$forums->func->load_lang('error');
				return return_process_result(sprintf($forums->lang["floodcheck"] , $bboptions['floodchecktime']));
			}
		}
	}
	//发帖长度限制
	if (($bboptions['maxpostchars'] && utf8::strlen($post_content) > $bboptions['maxpostchars']) || strlen($post_content) > 16777215)
	{
		$forums->func->load_lang('error');
		return return_process_result($forums->lang["_posterror1"]);
	}
	if ($bboptions['minpostchars'] && utf8::strlen($post_content) < $bboptions['minpostchars'] && !input::int('preview'))
	{
		$forums->func->load_lang('error');
		return return_process_result(sprintf($forums->lang["posttooshort"] , $bboptions['minpostchars']));
	}
	$forum = $forums->forum->single_forum($submit_data['f']);
	$forums->func->load_lang('showthread');
	$forums->func->load_lang('post');
	//查找主题帖子所存的表
	$thread = $DB->queryFirst("SELECT t.*, u.usergroupid
		FROM " . TABLE_PREFIX . "thread t
			LEFT JOIN " . TABLE_PREFIX . "user u
				ON u.id = t.postuserid
        WHERE t.tid='" . intval($submit_data['t']) . "'");
	//检查发帖权限
	$check = check_permission($thread, $forum);
	if (true !== $check)
	{
		return return_process_result($check);
	}

	$posttable = $thread['posttable'] ? $thread['posttable'] : 'post';
	//被封禁用户
	if ($bbuserinfo['liftban'])
	{
		$liftban = banned_detect($bbuserinfo['liftban']);
		if ($liftban['forumid'] && $liftban['forumid'] == $forum['id'])
		{
			if (TIMENOW >= $liftban['date_end'])
			{
				if ($liftban['banposts'] > 0)
				{
					$DB->queryUnbuffered('UPDATE ' . TABLE_PREFIX . "$posttable p," . TABLE_PREFIX . "thread t
										   SET p.state=0
										   WHERE p.threadid=t.tid
										   		AND p.userid={$bbuserinfo['id']}
										   		AND t.forumid = {$forum['id']}"
										   );
				}
				$DB->update(TABLE_PREFIX . 'user', array('liftban' => ''), 'id = ' . $bbuserinfo['id']);
			}
			else
			{
				$forums->func->load_lang('error');
				return return_process_result(sprintf($forums->lang["banpost"] , $forums->func->get_date($liftban['date_end'], 2, 1)));
			}
		}
	}
	//检查积分限制
	require_once(ROOT_PATH . "includes/functions_credit.php");

	$credit = new functions_credit();
	$credit->check_credit('newreply', $bbuserinfo['usergroupid'], $forum['id']);

	require_once(ROOT_PATH . "includes/functions_post.php");
	$dopost = new functions_post();
	//检查验证码
	if (!$dopost->validate_antispam())
	{
		return return_process_result($forums->lang['_imagehasherror']);
	}
	//处理引用
	$quote = $dopost->check_multi_quote(0);
	$post_content = $quote . $post_content;
	$post_info = $obj = array();
	$post_info['userid'] = $bbuserinfo['id'];
	$post_info['showsignature'] = input::int('showsignature');
	$post_info['allowsmile'] = input::int('allowsmile');
	$post_info['host'] = IPADDRESS;
	$post_info['dateline'] = TIMENOW;
	$obj['moderate'] = intval($forum['moderatepost']);
	if ($bbuserinfo['passmoderate'])
	{
		$obj['moderate'] = 0;
	}
	if ($bbuserinfo['moderate'])
	{
		if ($bbuserinfo['moderate'] == 1)
		{
			$obj['moderate'] = 1;
		}
		else
		{
			$mod_arr = banned_detect($bbuserinfo['moderate']);
			if (TIMENOW >= $mod_arr['date_end'])
			{
				$DB->update(TABLE_PREFIX . 'user', array('moderate' => 0), 'id = ' . $bbuserinfo['id']);
				$obj['moderate'] = intval($forum['moderatepost']);
			}
			else
			{
				$obj['moderate'] = 1;
			}
		}
	}
	if ($wmode)
	{
		$bbuserinfo['usewysiwyg'] = ($wmode == 'wysiwyg') ? 1 : 0;
	}
	else
	{
		$bbuserinfo['usewysiwyg'] = ($bboptions['mxemode']) ?1 : 0;
	}
	$post = $bbuserinfo['usewysiwyg'] ? $post_content : utf8::htmlspecialchars($post_content);
	$content = $dopost->parser->convert(array(
		'text' => $post,
		'allowsmilies' => input::int('allowsmile'),
		'allowcode' => $forum['allowbbcode']
	));

	$post_info['pagetext'] = $content;
	$post_info['username'] = $bbuserinfo['name'];
	$post_info['threadid'] = $thread['tid'];
	if ($bbuserinfo['cananonymous'] && input::int('anonymous'))
	{
		$post_info['anonymous'] = input::int('anonymous');
		$lastposterid = 0;
		$lastposter = 'anonymous*';
	}
	else
	{
		input::set('anonymous', 0);
		$lastposterid = $bbuserinfo['id'];
		$lastposter = $bbuserinfo['name'];
	}
	$post_info['posthash'] = input::str('imagehash');
	$post_info['moderate'] = ($obj['moderate'] == 1 || $obj['moderate'] == 3) ? 1 : 0;
	$post_info['hidepost'] = '';
	$DB->insert(TABLE_PREFIX . $posttable, $post_info);
	$postid = $DB->insertId();
	$dopost->obj['moderate'] = $obj['moderate'];
	$dopost->forum = $forum;
	$dopost->stats_recount($thread['tid'], 'reply');

	$DB->update(TABLE_PREFIX . 'user', array(
		'lastpost' => $post_info['dateline'],
		'posts' => array(1, '+')
	), 'id = ' . $bbuserinfo['id'], SHUTDOWN_QUERY);
	$DB->update(TABLE_PREFIX . 'thread', array(
		'lastpost' => $post_info['dateline'],
		'post' => array(1, '+'),
		'lastposterid' => $lastposterid,
		'lastposter' => $lastposter,
		'lastpostid' => $postid
	), 'tid = ' . intval($thread['tid']), SHUTDOWN_QUERY);

	$hideposts = $DB->query("SELECT pid, userid, hidepost FROM " . TABLE_PREFIX . "$posttable
							WHERE threadid='" . $thread['tid'] . "' AND hidepost!=''");
	if ($DB->numRows($hideposts))
	{
		while ($hidepost = $DB->fetch($hideposts))
		{
			$hideinfo = unserialize($hidepost['hidepost']);
			if ($hideinfo['type'] == '111' AND $hidepost['userid'] != $bbuserinfo['id'])
			{
				if (is_array($hideinfo['buyers']) AND in_array($bbuserinfo['name'], $hideinfo['buyers'])) continue;
				$hideinfo['buyers'][] = $bbuserinfo['name'];
				$DB->update(TABLE_PREFIX . $posttable, array(
					'hidepost' => serialize($hideinfo)
				), 'pid = ' . $DB->validate($hidepost['pid']), SHUTDOWN_QUERY);
			}
		}
	}
	$credit->update_credit('newreply', $bbuserinfo['id'], $bbuserinfo['usergroupid'], $forum['id']);
	$credit->update_credit('replythread', $thread['postuserid'], $thread['usergroupid'], $forum['id']);
	if ($post_info['moderate'])
	{
		return return_process_result($forums->lang["hasajaxpost"]);
	}
	$postcount = $DB->queryFirst("SELECT count(*) AS total FROM " . TABLE_PREFIX . $posttable . " WHERE threadid=" .$thread['tid']);
	if (input::int('pnum') > $bboptions['maxposts'])
	{
		$last_page = floor($postcount['total'] / $bboptions['maxposts']) * $bboptions['maxposts'];
		$response->redirect("showthread.php?{$forums->js_sessionurl}t=" . $thread['tid'] . "&pp=" . $last_page . "#pid" . $postid);
		return $response;
	}
	$bbuserinfo['lastpost'] = $post_info['dateline'];
	$bbuserinfo['posts'] += 1;
	$credit_expand = $DB->queryFirst("SELECT * FROM " . TABLE_PREFIX . "userexpand
										WHERE id= {$bbuserinfo['id']}");
	foreach ($credit_expand as $name => $value)
	{
		$bbuserinfo[$name] = $value;
	}
	$this_post = $bbuserinfo;
	//处理code
	if (strpos($post_info['pagetext'], '[code') !== false)
	{
		require_once(ROOT_PATH . 'includes/functions_codeparse.php');
		$codeparse = new functions_codeparse();
		$post_info['pagetext'] = preg_replace("#\[code(.+?)?\](.+?)\[/code\]#ies" , "\$codeparse->paste_code('\\1', '\\2')" , $post_info['pagetext']);
	}
	//处理引用
	if (strpos($post_info['pagetext'], '[quote') !== false)
	{
		if (!$codeparse)
		{
			require_once(ROOT_PATH . 'includes/functions_codeparse.php');
			$codeparse = new functions_codeparse();
		}
		$post_info['pagetext'] = preg_replace("#(\[quote(.+?)?\].*\[/quote\])#ies" , "\$codeparse->parse_quotes('\\1')" , $post_info['pagetext']);
	}
	//处理flash
	if (strpos($post_info['pagetext'], '[FLASH') !== false)
	{
		if (!$codeparse)
		{
			require_once(ROOT_PATH . 'includes/functions_codeparse.php');
			$codeparse = new functions_codeparse();
		}
		$pregfind = array("#(\[flash\])(.+?)(\[\/flash\])#ie", "#(\[flash=)(\S+?)(\,)(\S+?)(\])(\S+?)(\[\/flash\])#ie");
		$pregreplace = array("\$codeparse->parse_flash('','','\\2')", "\$codeparse->parse_flash('\\2','\\4','\\6')");
		$post_info['pagetext'] = preg_replace($pregfind, $pregreplace, $post_info['pagetext']);
	}
	$this_post['pagetext'] = str_replace(array('[', ']'), array('&#91;', '&#93;'), $post_info['pagetext']);
	$this_post['pid'] = $postid;
	$this_post['threadid'] = $thread['tid'];
	$this_post['host'] = IPADDRESS;
	$this_post['dateline'] = TIMENOW;

	cache::get('usergroup');
	require_once(ROOT_PATH . 'includes/class_textparse.php');
	$return = parse_row($this_post, $dopost->forum['allowhtml'], $postcount['total'], $forum);
	$return['poster']['status'] = 1;
	$return['row']['name_css'] = "normalname";
	$return['poster']['onlinerankimg'] = array();
	$showpost = array($return);
	$antispam = $dopost->code->showantispam();
	$next_pnum = input::int('pnum') + 1;
	$bboptions['gzipoutput'] = 0;
	ob_end_clean();
	ob_start();
	$pp = input::int('pp');
	include $forums->func->load_template('showthread_post');
	$post_content = ob_get_contents();
	ob_end_clean();
	$post_content = str_replace(array('&lt;', '&gt;', "\r\n", "\n", "\r"), array('&amp;lt;', '&amp;gt;', '', '', ''), $post_content);
	$post_content .= '<div id="ajaxrep' . $next_pnum . '" style="display:none;"><!-- --></div>';
	$response->assign('ajaxrep' . input::int('pnum'), 'innerHTML', $post_content);
	$response->script('$("ajaxrep' . input::int('pnum') . '").style.display = "block"');
	$response->assign('pnum', 'value', $next_pnum);
	if ($antispam['imagehash'])
	{
		$response->assign('antispam', 'value', '');
		$response->assign('imagehash', 'value', $antispam['imagehash']);
		$response->assign('antispamtext_show', 'innerHTML', $antispam['text']);
	}
	$response->script('initData();');
	return return_process_result($forums->lang['reply_succ']);
}

function return_process_result($msg = '')
{
	global $response;
	show_processinfo($msg);
	$response->assign('quick_preview', 'disabled', false);
	$response->assign('submitform', 'disabled', false);
	return $response;
}

function check_permission($thread = array(), $forum = array())
{
	global $forums, $bbuserinfo, $response;
	if ($thread['pollstate'] == 2 && !$bbuserinfo['supermod'])
	{
		return $forums->lang['cannotreply'];
	}
	$usercanreplay = $forums->func->fetch_permissions($forum['canreply'], 'canreply');
	if ($thread['postuserid'] == $bbuserinfo['id'])
	{
		if (!($bbuserinfo['canreplyown'] && $usercanreplay))
		{
			return $forums->lang['cannotreply'];
		}
	}
	else if (!($bbuserinfo['canreplyothers'] && $usercanreplay))
	{
		return $forums->lang['cannotreply'];
	}

	if ($usercanreplay == false)
	{
		return $forums->lang['cannotreply'];
	}
	if (!$thread['open'])
	{
		if (!$bbuserinfo['canpostclosed'])
		{
			return $forums->lang['threadclosed'];
		}
	}
	return true;
}

function parse_row($row = array(), $allowhtml, $postcount, $forum)
{
	global $forums, $DB, $bbuserinfo, $bboptions;
	$poster = array();
	if ($bbuserinfo['id'])
	{
		$row['userid'] = $bbuserinfo['id'];

		$poster = $forums->func->fetch_user($row);
	}
	else
	{
		$poster = $forums->func->set_up_guest($row['username']);
		$row['name_css'] = 'unreg';
	}
	if ($row['anonymous'])
	{
		if ($bbuserinfo['usergroupid'] == 4)
		{
			$poster['name'] = $poster['name'] . " (" . $forums->lang['anonymouspost'] . ")";
		}
		else
		{
			$poster = array();
			$poster['name'] = $forums->lang['anonymous'] . '-' . substr(md5($row['joindate']), 0, 6);
			$poster['id'] = 0;
			$poster['grouptitle'] = $forums->lang['byanonymous'];
			$poster['posts'] = $forums->lang['unknown'];
		}
	}

	$row['post_css'] = ($postcount - 1) % 2 ? 'item_list' : 'item_change';
	$row['altrow'] = 'item_list';
	if ($row['userid'])
	{
		$row['options'] = intval($row['options']);
		$forums->func->convert_bits_to_array($row, $row['options']);
		$poster['status'] = 1;
	}
	else
	{
		$poster['status'] = '';
	}
	$edit_delete_button = edit_delete_button($row, $forum);
	$row['delete_button'] = $edit_delete_button[1];
	$row['edit_button'] = $edit_delete_button[0];
	$row['ajaxeditpostevent'] = $edit_delete_button['ajaxeditpostevent'];
	$row['dateline'] = $forums->func->get_date($row['dateline'], 2);
	cache::get('icon');
	$row['post_icon'] = $row['iconid'] ? 1 : 0;
	$row['post_icon_hash'] = $forums->cache['icon'][$row['iconid']]['image'];
	$row['host'] = "IP: " . $row['host'] . " &#0124;";
	$row['report_link'] = (($bboptions['disablereport'] != 1) AND ($bbuserinfo['id'])) ? 1 : 0;
	$row['signature'] = '';
	if ($poster['id'])
	{
		$poster['name'] = "<a href='profile.php{$forums->sessionurl}u=" . $poster['id'] . "'>" . $poster['name'] . "</a>";
	}
	$row['pagetext'] = textparse::convert_text($row['pagetext'], ($allowhtml && $forums->cache['usergroup'][$poster['usergroupid']]['canposthtml']));
	$row['postcount'] = $postcount;

	return array('row' => $row, 'poster' => $poster);
}

/**
 * 处理编辑和删除的按钮显示
 *
 * @param array $row
 * @param array $forum
 * @return array
 */
function edit_delete_button($row, $forum)
{
	global $forums, $bbuserinfo;

	$ajaxeditpostevent = " ondblclick = \"edit_post_event('{$row['pid']}','{$forum['id']}','{$row['userid']}','{$row['threadid']}', '{$row['dateline']}');\"";
	$edit_btn = $delete_btn = false;
	if ($row['userid'] == $bbuserinfo['id'])
	{
		if (($bbuserinfo['caneditpost']))
		{
			if ($bbuserinfo['edittimecut'] > 0)
			{
				if ($row['dateline'] > (TIMENOW - (intval($bbuserinfo['edittimecut']) * 60)))
				{
					$edit_btn = true;
				}
				else
				{
					$edit_btn = false;
				}
			}
			else
			{
				$edit_btn = true;
			}
		}
		if ($bbuserinfo['candeletepost'])
		{
			$delete_btn = true;
		}
	}
	if (!$edit_btn)
	{
		$ajaxeditpostevent = '';
	}
	return array($edit_btn, $delete_btn, 'ajaxeditpostevent' => $ajaxeditpostevent);
}

function returnpagetext($pid, $fid, $uid, $tid, $dateline)
{
	global $DB, $forums, $bbuserinfo, $bboptions, $response;
	require_once(ROOT_PATH . 'includes/functions_credit.php');
	$forums->credit = new functions_credit();
	/*检查帖子的修改权限
	 超级版主，有编辑帖子的版主，可以自己编辑帖子的会员
	 编辑自己的帖子需要做积分检测
	*/
	$need_update_credit = check_edit_post_prms($fid, $uid, $dateline);

	if ($need_update_credit === 'error')
	{
		return $response;
	}

	require_once(ROOT_PATH . 'includes/functions_codeparse.php');
	$lib = new functions_codeparse();
	$thread = $DB->queryFirst("SELECT posttable,forumid FROM " . TABLE_PREFIX . "thread WHERE tid = " . intval($tid)); //查询当前主题的帖子所在帖子表
	$posttable = $thread['posttable'] ? $thread['posttable'] : 'post';
	$post = $DB->queryFirst("SELECT userid, pagetext, threadid, allowsmile,dateline FROM " . TABLE_PREFIX . $posttable . " WHERE pid = " . intval($pid));
	$forum = $forums->forum->single_forum($thread['forumid']);
	$post['pagetext'] = $lib->unconvert($post['pagetext'], $forum['allowbbcode'], $forum['allowhtml'], 1, 1);
	$post['pagetext'] = preg_replace("#\[code(.+?)?\](.+?)\[/code\]#ies" , "str_replace('&lt;br /&gt;', '<br />', '[code\\1]\\2[/code]')", $post['pagetext']);
	$post['pagetext'] = str_replace(array('&lt;', '&gt;', "\r\n", "\n", "\r"), array('&amp;lt;', '&amp;gt;', '', '', ''), $post['pagetext']);
	$response->call('show_post_text_editor', $pid, intval($post['userid']), intval($post['threadid']), intval($post['dateline']), $post['pagetext']);
	return $response;
}

function do_edit_post($pid, $fid, $uid, $tid, $content, $wMode, $dateline)
{
	global $forums, $DB, $bbuserinfo, $bboptions, $response;
	/*检查帖子的修改权限
	 超级版主，有编辑帖子的版主，可以自己编辑帖子的会员
	 编辑自己的帖子需要做积分检测
	*/
	require_once(ROOT_PATH . 'includes/functions_credit.php');
	$forums->credit = new functions_credit();
	$need_update_credit = check_edit_post_prms($fid, $uid, $dateline);
	if ($need_update_credit === 'error')
	{
		return $response;
	}

	//发帖长度限制
	if (($bboptions['maxpostchars'] && utf8::strlen($content) > $bboptions['maxpostchars']) || strlen($content) > 16777215)
	{
		$forums->func->load_lang('error');
		show_processinfo($forums->lang["_posterror1"]);
		return $response;
	}
	if ($bboptions['minpostchars'] && utf8::strlen($content) < $bboptions['minpostchars'])
	{
		$forums->func->load_lang('error');
		show_processinfo(sprintf($forums->lang["posttooshort"] , $bboptions['minpostchars']));
		return $response;
	}
	$forum = $forums->forum->single_forum($fid);
	require_once(ROOT_PATH . 'includes/functions_codeparse.php');
	require_once(ROOT_PATH . 'includes/class_textparse.php');
	$lib = new functions_codeparse();
	$bbuserinfo['usewysiwyg'] = ($wMode || $bboptions['mxemode']) ? 1 : 0;
	$content = $bbuserinfo['usewysiwyg'] ? $content : utf8::htmlspecialchars($content);
	$post = $lib->convert(array(
		'text' => $content,
		'allowsmilies' => 1,
		'allowcode' => $forum['allowbbcode']
	));

	$uptpost = array('pagetext' => $post);
	//记录最后更新人
	$uptpost['updateuid'] = $bbuserinfo['id'];
	$uptpost['updateuname'] = $bbuserinfo['name'];
	$uptpost['updatetime'] = TIMENOW;
	$thread = $DB->queryFirst("SELECT posttable,forumid FROM " . TABLE_PREFIX . "thread WHERE tid = " . intval($tid));
	$posttable = $thread['posttable'] ? $thread['posttable'] : 'post';
	$DB->update(TABLE_PREFIX . $posttable, $uptpost, 'pid = ' . intval($pid));
	if ($need_update_credit)
	{
		$forums->credit->update_credit('threadhighlight', $bbuserinfo['id'], $bbuserinfo['usergroupid'], $fid);
	}
	require_once(ROOT_PATH . "includes/functions_showthread.php");
	$show = new functions_showthread();
	$post = preg_replace(
		"/<!--emule1-->(.+?)<!--emule2-->/ie",
		"\$show->paste_emule('\\1')",
	$post);
	if ($forum['allowhtml'] && $bbuserinfo['canposthtml'])
	{
		$post = textparse::parse_html($post);
	}
	//处理code
	if (strpos($post, '[code') !== false)
	{
		$post = preg_replace("#\[code(.+?)?\](.+?)\[/code\]#ies" , "\$lib->paste_code('\\1', '\\2')" , $post);
	}
	//处理引用
	if (strpos($post, '[quote') !== false)
	{
		$post = preg_replace("#(\[quote(.+?)?\].*\[/quote\])#ies" , "\$lib->parse_quotes('\\1')" , $post);
	}
	//处理flash
	if (strpos($post, '[FLASH') !== false)
	{
		$pregfind = array("#(\[flash\])(.+?)(\[\/flash\])#ie", "#(\[flash=)(\S+?)(\,)(\S+?)(\])(\S+?)(\[\/flash\])#ie");
		$pregreplace = array("\$lib->parse_flash('','','\\2')", "\$lib->parse_flash('\\2','\\4','\\6')");
		$post = preg_replace($pregfind, $pregreplace, $post);
	}
	show_processinfo($forums->lang['posteditsuc']);
	$response->assign('show' . $pid, 'innerHTML', $post);
	$response->assign('openquick();');
	return $response;
}

/**
 * 检测编辑帖子的权限
 *
 * @param int $fid
 * @param int $uid
 * @param int $dateline
 * @return unknown
 */
function check_edit_post_prms($fid, $uid, $dateline)
{
	global $forums, $bbuserinfo, $bboptions, $response;
	$need_update_credit = $can_edit_post = false;
	if ($bbuserinfo['supermod']) //超级版主
	{
		$can_edit_post = true;
	}
	elseif ($bbuserinfo['_moderator'][$fid] && $bbuserinfo['_moderator'][$fid]['caneditposts']) //有权限的版主
	{
		$can_edit_post = true;
	}
	elseif ($uid == $bbuserinfo['id'] && $bbuserinfo['caneditpost'])//自己的帖子
	{
		if ($bbuserinfo['edittimecut'] > 0) //编辑时间限制
		{
			if ($dateline > (TIMENOW - (intval($bbuserinfo['edittimecut']) * 60)))
			{
				$can_edit_post = true;
				$check_credit = $forums->credit->check_credit('editpost', $bbuserinfo['usergroupid'], $fid, 1, false); //积分检测
				if ($check_credit)
				{
					show_processinfo(sprintf($forums->lang['credit_limit_over'], $check_credit));
					return 'error';
				}
				$need_update_credit = true;
			}
		}
	}
	if(!$can_edit_post) //没有权限
	{
		show_processinfo($forums->lang['noprmsmodpost']);
		return 'error';
	}
	return $need_update_credit;
}

function dopreview_post($content, $fid, $allow_smile)
{
	global $bboptions, $bbuserinfo, $forums, $response;
	require_once(ROOT_PATH . "includes/functions_codeparse.php");
	require_once(ROOT_PATH . 'includes/class_textparse.php');
	$lib = new functions_codeparse();
	$fid = intval($fid);
	$allowsmilies = intval($allow_smile);
	$cookie_mxeditor = $forums->func->get_cookie('mxeditor');
	if ($cookie_mxeditor)
	{
		$bbuserinfo['usewysiwyg'] = ($cookie_mxeditor == 'wysiwyg') ? 1 : 0;
	}
	else if ($bboptions['mxemode'])
	{
		$bbuserinfo['usewysiwyg'] = 1;
	}
	else
	{
		$bbuserinfo['usewysiwyg'] = 0;
	}
	$content = $bbuserinfo['usewysiwyg'] ? $content : utf8::htmlspecialchars($content);
	if ($fid > 0)
	{
		$thisforum = $forums->forum->single_forum($fid);
		$allowcode = intval($thisforum['allowbbcode']);
		$allowhtml = intval($thisforum['allowhtml']) && $bbuserinfo['canposthtml'];
	}
	else if ($fid === 0)
	{
		$allowcode = intval($bboptions['pmallowbbcode']);
		$allowhtml = intval($bboptions['pmallowhtml']);
	}
	else
	{
		$allowcode = 0;
		$allowhtml = 0;
	}
	$content = $lib->convert(array('text' => $content,
		'allowsmilies' => $allowsmilies,
		'allowcode' => $allowcode
	));
	$content = textparse::convert_text($content, $allowhtml);
	//处理code
	if (strpos($content, '[code') !== false)
	{
		$content = preg_replace("#\[code(.+?)?\](.+?)\[/code\]#ies" , "\$lib->paste_code('\\1', '\\2')" , $content);
	}
	//处理引用
	if (strpos($content, '[quote') !== false)
	{
		$content = preg_replace("#(\[quote(.+?)?\].*\[/quote\])#ies" , "\$lib->parse_quotes('\\1')" , $content);
	}
	//处理flash
	if (strpos($content, '[FLASH') !== false)
	{
		$pregfind = array("#(\[flash\])(.+?)(\[\/flash\])#ie", "#(\[flash=)(\S+?)(\,)(\S+?)(\])(\S+?)(\[\/flash\])#ie");
		$pregreplace = array("\$lib->parse_flash('','','\\2')", "\$lib->parse_flash('\\2','\\4','\\6')");
		$content = preg_replace($pregfind, $pregreplace, $content);
	}
	$response->assign('previewpostcontent', 'innerHTML', $content);
	$response->assign('$("previewpost").style.display = "block";');
	$response->script('var top = preview_Div.offsetTop;window.scroll(0,top);');
	return $response;
}

function smiles_page($num, $p)
{
	global $forums, $bboptions, $response;
	cache::get('smile');
	$smile_count = count($forums->cache['smile']);
	$all_smiles = $bboptions['smilenums'];
	if ($smile_count == 0 || $all_smiles == 0)
	{
		return $response;
	}
	$lastpage = floor($smile_count / $all_smiles);
	if ($num == 0 && $p == 0)
	{
		$num = $lastpage - 1;
		$p = 1;
	}
	else if ($num == $lastpage && $p == 1)
	{
		$num = 1;
		$p = 0;
	}
	if ($p == 0)
	{
		$page = --$num;
	}
	else if ($p == 1)
	{
		$page = ++$num;
	}
	for ($i = $page * $all_smiles, $x = $i + $all_smiles; $i < $x; $i++)
	{
		if (isset($forums->cache['smile'][$i]))
		{
			$smiles[$forums->cache['smile'][$i]['id']] = $forums->cache['smile'][$i];
		}
		else
		{
			break;
		}
	}
	$bboptions['gzipoutput'] = 0;
	ob_end_clean();
	ob_start();
	include $forums->func->load_template('show_post_smile');
	$smiles_data = ob_get_contents();
	ob_end_clean();
	$response->assign('smiliespage', 'innerHTML', $smiles_data);
	$response->assign('smileslastpage', 'href', 'javascript:smiles_page(' . $page . ', 0);');
	$response->assign('smilesnextpage', 'href', 'javascript:smiles_page(' . $page . ', 1);');
	return $response;
}

function process_post_form($input, $action, $pid = 0)
{
	global $forums, $bboptions, $response, $bbuserinfo, $DB;
	$forums->func->load_lang('moderate');
	input::set($input);

	$fid = intval($input['f']);
	$forums->func->set_cookie('mqtids', ',', 0);
	if (!$action)
	{
		$action = $input['do'];
	}
	if ($pid)
	{
		$input['pid'] = array($pid);
	}
	if (!$input['pid'])
	{
		$forums->func->load_lang('error');
		show_processinfo($forums->lang['erroroperation']);
		return $response;
	}
	if ($input['t'])
	{
		$forums->this_thread = $DB->queryFirst('SELECT tid, title, posttable, forumid, firstpostid,post FROM '. TABLE_PREFIX . "thread
						  			WHERE tid = " . intval($input['t']));

		if (!$forums->this_thread)
		{
			$forums->func->load_lang('error');
			show_processinfo($forums->lang['erroroperation']);
			return $response;
		}
	}
	else
	{
		$forums->this_thread['posttable'] = trim($input['posttable']) ? trim($input['posttable']) : $forums->func->getposttable();
	}

	$forums->this_thread['posttable'] = $forums->this_thread['posttable'] ? $forums->this_thread['posttable'] : 'post';
	$prms_fids = '';
	$prms_action = array(
			'splitthread' => 'cansplitthreads',	//分割主题
			'movepost' => 'canremoveposts',	//移动帖子
			'deletepost' => 'candeleteposts',	//删除帖子
			'revertpost' => 'candeleteposts',	//恢复帖子
			'approvepostorcancel' => 'canmoderateposts',	//验证帖子
			'approvepost' => 'canmoderateposts',	//验证帖子
			'unapprovepost' => 'canmoderateposts',	//验证帖子
	);
	$prms_fid = check_moderate_prms($prms_action[$action], $fid);
	if (!$prms_fid)
	{
		$forums->func->load_lang('error');
		show_processinfo($forums->lang['noperms']);
		return $response;
	}
	else if(is_array($prms_fid))
	{
		$prms_fids = ' AND forumid IN (' . implode(',', $prms_fid) . ')';
	}

	cache::get('forum');
	if ($input['do'])
	{
		switch ($input['do'])
		{
			case 'movepost' :
				do_movepost();
				break;
			case 'splitthread' :
				do_splitthread();
				break;
			case 'approvepost' :
				do_approvepost('approvepost');
				break;
			case 'unapprovepost' :
				do_approvepost('unapprovepost');
				break;
			case 'deletepost' :
				do_deletepost();
				break;
			case 'revertpost' :
				do_revertpost();
				break;
		}
	}
	else
	{
		if ($input['pid'])
		{
			$mod_action = array(
					'approvepostorcancel' => array(
						'approvepost' => $forums->lang['approvepost'],  //验证帖子
						'unapprovepost' => $forums->lang['unapprovepost'],   //撤销验证
					),	//验证/撤销帖子
			);
			//默认选中的操作
			$action_checked = array(
					'approvepost' => ' checked="checked"',
			);
			if ($action == 'splitthread')
			{
				$forums_info = list_forums();
				$t_title = $forums->this_thread['title'];
			}
			$opreate_name = $forums->lang[$action]; //显示当前操作
			$opreate_description = $forums->lang[$action . 'desc']; //显示当前操作的说明
			$do_actions = $mod_action[$action];
			require_once(ROOT_PATH . 'includes/functions_codeparse.php');
			$codeparse = new functions_codeparse();
			$pids = implode(',', $input['pid']);
			$result = $DB->query('SELECT pagetext, pid, dateline, userid, username
				FROM ' . TABLE_PREFIX . "{$forums->this_thread['posttable']}
				WHERE " . $DB->sql->in('pid', $input['pid']) . '
				ORDER BY dateline');
			$post_count = 0;
			$showpost = array();
			while ($row = $DB->fetch($result))
			{
				if (utf8::strlen($row['pagetext']) > 100)
				{
					$row['pagetext'] = $codeparse->unconvert($row['pagetext']);
					$row['pagetext'] = utf8::substr(strip_tags($row['pagetext']), 0, 100) . '...';
				}

				//处理code
				if (strpos($row['pagetext'], '[code') !== false)
				{
					$row['pagetext'] = preg_replace("#\[code(.+?)?\](.+?)\[/code\]#ies" , "\$codeparse->paste_code('\\1', '\\2')" , $row['pagetext']);
				}
				//处理引用
				if (strpos($row['pagetext'], '[quote') !== false)
				{
					$row['pagetext'] = preg_replace("#(\[quote(.+?)?\].*\[/quote\])#ies" , "\$codeparse->parse_quotes('\\1')" , $row['pagetext']);
				}
				//处理flash
				if (strpos($row['pagetext'], '[FLASH') !== false)
				{
					$pregfind = array("#(\[flash\])(.+?)(\[\/flash\])#ie", "#(\[flash=)(\S+?)(\,)(\S+?)(\])(\S+?)(\[\/flash\])#ie");
					$pregreplace = array("\$codeparse->parse_flash('','','\\2')", "\$codeparse->parse_flash('\\2','\\4','\\6')");
					$row['pagetext'] = preg_replace($pregfind, $pregreplace, $row['pagetext']);
				}
				$row['pagetext'] = str_replace(array('&lt;', '&gt;', "\r\n", "\n", "\r"), array('&amp;lt;', '&amp;gt;', '', '', ''), $row['pagetext']);
				$row['dateline'] = $forums->func->get_date($row['dateline'], 2);
				$row['post_css'] = $post_count % 2 ? 'item_list' : 'item_change';
				$post_count++;
				$showpost[] = $row;
			}
			$bboptions['gzipoutput'] = 0;
			unset($input['tid'], $input['pid'], $input['code']);
			ob_end_clean();
			ob_start();
			include $forums->func->load_template('confirm_operate_post');
			$thread_content = ob_get_contents();
			ob_end_clean();
			$response->assign('show_operation', 'innerHTML', $thread_content);
			$response->call('showElement', 'operation_pannel');
			$response->call('toCenter', 'operation_pannel');
		}
	}
	return $response;
}

function do_movepost()
{
	global $forums, $bboptions, $response, $bbuserinfo, $DB, $mod_func;

	$threadurl = input::int('threadurl');
	if (!$threadurl)
	{
		preg_match("/(\?|&amp;)t=(\d+)($|&amp;)/", $threadurl, $match);
		$old_id = intval(trim($match[2]));
	}
	else
	{
		$old_id = $threadurl;
	}
	if ($old_id == '')
	{
		$forums->func->load_lang('error');
		show_processinfo($forums->lang['erroraddress']);
		return $response;
	}
	$move_to_thread = $DB->queryFirst('SELECT tid, forumid, title, posttable
		FROM ' . TABLE_PREFIX . "thread
		WHERE tid = $old_id");
	$move_to_thread['posttable'] = $move_to_thread['posttable'] ? $move_to_thread['posttable'] : 'post';
	if (!$move_to_thread['tid'] || !$forums->cache['forum'][$move_to_thread['forumid']]['id'])
	{
		$forums->func->load_lang('error');
		show_processinfo($forums->lang['erroraddress']);
		return $response;
	}

	$t = input::int('t');
	$pid = input::arr('pid');

	$affected_ids = count($pid);
	$count = $DB->queryFirst('SELECT COUNT(pid) AS count
		FROM ' . TABLE_PREFIX . "{$forums->this_thread['posttable']}
		WHERE threadid = $t");
	if ($affected_ids >= $count['count'])
	{
		$forums->func->load_lang('error');
		show_processinfo($forums->lang['erroraddress']);
		return $response;
	}
	if ($move_to_thread['posttable'] == $forums->this_thread['posttable'])
	{
		$DB->update(TABLE_PREFIX . $forums->this_thread['posttable'], array('threadid' => $move_to_thread['tid'], 'newthread' => 0), $DB->sql->in('pid', $pid));
	}
	else
	{
		$table_fields = $DB->query('SHOW COLUMNS FROM ' . TABLE_PREFIX . $forums->this_thread['posttable']);
		$fields = array();
		$values = array();
		while ($row = $DB->fetch($table_fields))
		{
			if ($row['Field'] == 'pid')
			{
				continue;
			}
			$fields[] = $row['Field'];
			if ($row['Field'] == 'threadid')
			{
				$values[] = $move_to_thread['tid'];
			}
			else
			{
				$values[] = $row['Field'];
			}
		}
		$sql = 'INSERT INTO ' . TABLE_PREFIX . $move_to_thread['posttable'] . " (" . implode(',', $fields) . ")
	SELECT " . implode(',', $values) . "
		FROM " . TABLE_PREFIX . $forums->this_thread['posttable'] . '
		WHERE pid IN (' . implode(',', $pid) . ')';
		$DB->query($sql);
		$DB->delete(TABLE_PREFIX . $forums->this_thread['posttable'], $DB->sql->in('pid', $pid));
	}

	$DB->update(TABLE_PREFIX . $forums->this_thread['posttable'], array('newthread' => 0), "threadid = $t");

	require_once(ROOT_PATH . "includes/functions_moderate.php");
	$mod_func = new modfunctions();
	$mod_func->rebuild_thread($move_to_thread['tid']);
	$mod_func->rebuild_thread($t);
	forum_recount($forums->this_thread['forumid']);
	if ($forums->this_thread['forumid'] != $move_to_thread['forumid'])
	{
		forum_recount($move_to_thread['forumid']);
	}
	$forums->lang['movepostto'] = sprintf($forums->lang['movepostto'], $forums->this_thread['title'], $move_to_thread['title']);
	add_moderate_log($forums->lang['movepostto']);
	show_processinfo($forums->lang['posthasmoved']);
	$url = "showthread.php?{$forums->js_sessionurl}t=$t";
	$response->redirect($url);
	return $response;
}

function do_splitthread()
{
	global $forums, $bboptions, $response, $bbuserinfo, $DB, $mod_func;
	$title = input::str('title');
	if ($title == "")
	{
		$forums->func->load_lang('error');
		show_processinfo($forums->lang['plzinputallform']);
		return $response;
	}

	$count = $DB->queryFirst('SELECT count(pid) as cnt
		FROM ' . TABLE_PREFIX . $forums->this_thread['posttable'] . "
		WHERE threadid=" . intval($forums->this_thread['tid']) );

	$pid = input::arr('pid');
	if ( count($pid) >= $count['cnt'] )
	{
		$forums->func->load_lang('error');
		show_processinfo($forums->lang['notselectsplit']);
		return $response;
	}
	$pids = implode(',', $pid);
	$fid = input::int('fid');
	if ($fid != input::int('f'))
	{
		$forum = $forums->forum->single_forum($fid);
		if (! $forum['id'])
		{
			$forums->func->load_lang('error');
			show_processinfo($forums->lang['selectsplit']);
			return $response;
		}
		if ($forum['allowposting'] != 1)
		{
			$forums->func->load_lang('error');
			show_processinfo($forums->lang['cannotsplit']);
			return $response;
		}
	}
	$rawthread = $DB->queryFirst('SELECT count(*) as num, threadid
		FROM ' . TABLE_PREFIX . "{$forums->this_thread['posttable']}
		WHERE rawthreadid = $t
		GROUP BY threadid");

	$userecycle = fetch_recycleforum();
	$update_post = array('newthread' => 0);
	if ($userecycle != $fid || ($userecycle == $fid && $rawthread['num'] <= 0))
	{
		$newthread = array('title'				=> $title,
						   'open'				=> 1,
						   'post'				=> 0,
						   'postuserid'         => 0,
						   'postusername'	    => 0,
						   'dateline'			=> TIMENOW,
						   'lastposterid'		=> 0,
						   'lastposter'			=> 0,
						   'lastpost'			=> TIMENOW,
						   'iconid'				=> 0,
						   'pollstate'			=> 0,
						   'lastvote'			=> 0,
						   'views'				=> 0,
						   'forumid'			=> $fid,
						   'visible'			=> 1,
						   'sticky'				=> 0,
						   'posttable'				=> $forums->this_thread['posttable'],
							);
		if ($userecycle == $fid)
		{
			$newthread = array_merge($newthread, array('addtorecycle' => TIMENOW));
		}
		$DB->insert(TABLE_PREFIX . 'thread', $newthread);
		$threadid = $DB->insertId();
		$update_post['threadid'] = $threadid;
	}
	if ($userecycle == $fid)
	{
		$threadid = $rawthread['threadid'] ? $rawthread['threadid'] : $threadid;
		$update_post['threadid'] = $threadid;
		$update_post['rawthreadid'] = $forums->this_thread['tid'];
	}

	$DB->update(TABLE_PREFIX . $forums->this_thread['posttable'], $update_post, $DB->sql->in('pid', $pid));

	require_once(ROOT_PATH . "includes/functions_moderate.php");
	$mod_func = new modfunctions();
	$mod_func->rebuild_thread($threadid);
	$mod_func->rebuild_thread($forums->this_thread['tid']);
	forum_recount($forums->this_thread['forumid']);
	if ($forums->this_thread['forumid'] != $fid)
	{
		forum_recount($fid);
	}
	if ($userecycle == $fid)
	{
		$forums->lang['movethreadtorecycle'] = sprintf( $forums->lang['movethreadtorecycle'], $forums->this_thread['title'] );
		add_moderate_log($forums->lang['movethreadtorecycle']);
		$need_change_posts = array($forums->this_thread['posttable'] => $pid);
		$mod_func->processcredit($need_change_posts, 'delreply', 'post', 1);
		show_processinfo($forums->lang['posthasdeleted']);
		$url = "showthread.php?{$forums->js_sessionurl}t=" . $forums->this_thread['tid'];
	}
	else
	{
		add_moderate_log($forums->lang['splitthread'] . " '" . $forums->this_thread['title'] . "'");
		show_processinfo($forums->lang['hassplited']);
		$url = "showthread.php?{$forums->js_sessionurl}t=" . $threadid;
	}
	$response->redirect($url);
}

function do_approvepost($type = 'approvepost')
{
	global $forums, $bboptions, $response, $bbuserinfo, $DB, $mod_func;
	$posttable = $forums->this_thread['posttable'];
	$at = 1;
	$ap = 0;
	$class_name = 'item_change';
	$message = $forums->lang['hasapproved'];
	if ($type != 'approvepost')
	{
		$at = 0;
		$ap = 1;
		$class_name = 'item_change_shaded';
		$message = $forums->lang['hasunapproved'];
	}
	$pids = input::arr('pid');
	$forums_recount = $threads = array();
	if (!input::int('t'))
	{
		$DB->query( "SELECT p.pid, p.userid, p.dateline, p.newthread, p.username, p.threadid, t.firstpostid, t.forumid
			FROM " . TABLE_PREFIX . $posttable . " p
				LEFT JOIN " . TABLE_PREFIX . "thread t
					ON p.threadid=t.tid
			WHERE pid IN (" . implode(',', $pids) . ')');

		while ($row = $DB->fetch())
		{
			$threads[$row['threadid']][] = $row;
			$forums_recount[$row['forumid']] = 1;
		}
	}
	else
	{
		$threads[$forums->this_thread['tid']][] = $forums->this_thread;
		$forums_recount[$forums->this_thread['forumid']] = 1;
	}
	$tmp = array_flip($pid);
	foreach ($threads AS $tid => $tposts)
	{
		if ($tposts)
		{
			if (in_array($tposts['firstpostid'], $pid))
			{
				$DB->update(TABLE_PREFIX . 'thread', array('visible' => $at),'tid=' . $tposts['tid']);
				unset($tmp[$tposts['firstpostid']]);
			}
		}
	}

	$pid = array_flip($tmp);
	if (count($pid))
	{
		$DB->update(TABLE_PREFIX . $posttable, array('moderate' => $ap), $DB->sql->in('pid', $pid));
	}
	require_once(ROOT_PATH . "includes/functions_moderate.php");
	$mod_func = new modfunctions();

	foreach ($threads AS $tid => $val)
	{
		$mod_func->rebuild_thread($tid);
	}
	foreach ($forums_recount AS $fid => $val)
	{
		forum_recount($fid);
	}

	show_processinfo($message);
	$response->call('hideElement', 'operation_pannel');
	$response->assign('show_operation', 'innerHTML', '');
	foreach ($pids AS $pid)
	{
		$response->assign('post_' . $pid, 'className', $class_name);
		$response->assign('pid_' . $pid, 'checked', false);
	}
	$response->assign('selectall', 'checked', false);
}

function do_deletepost()
{
	global $forums, $bboptions, $response, $bbuserinfo, $DB, $mod_func;
	$threadid = input::int('t');
	$forumid = input::int('f');
	$pids = input::arr('pid');
	if (count($pids) == 0)
	{
		$forums->func->load_lang('error');
		show_processinfo($forums->lang['erroroperation']);
		return $response;
	}

	$posttable = $forums->this_thread['posttable'];
	$result = $DB->query("SELECT p.pid, p.userid, p.dateline, p.newthread, p.username, p.threadid, t.forumid
		FROM " . TABLE_PREFIX . $posttable . " p
			LEFT JOIN " . TABLE_PREFIX . "thread t
				ON p.threadid=t.tid
		WHERE pid IN (" . implode(',', $pids) . ')');

	$pm_touser = $posts = array();
	$threadids = array();
	$this_tids = array();
	while ($row = $DB->fetch($result))
	{
		$posts[] = $row;
		$threadids[$row['forumid']][] = $row['threadid'];
		$this_tids[$row['threadid']] = 1;
		$pm_touser[$row['userid']] = $row['username'];
	}
	$single_delete = false;
	$del_post_num = count($posts);
	if ($del_post_num == 1)
	{
		$post = $posts[0];
		$single_delete = true;
		input::set('p', $post['pid']);
		$threadid = $post['threadid'];
	}

	$passed = ($bbuserinfo['supermod'] || $bbuserinfo['candobatch'] || $bbuserinfo['_moderator'][$forumid]['candeleteposts'] || ($single_delete && $bbuserinfo['candeletepost'] && $bbuserinfo['id'] == $post['userid'])) ? true : false;
	if (!$passed)
	{
		$forums->func->load_lang('error');
		show_processinfo($forums->lang['erroroperation']);
		return $response;
	}
	$recycleforum = fetch_recycleforum();
	require_once(ROOT_PATH . "includes/functions_moderate.php");
	$mod_func = new modfunctions();
	if ($recycleforum && $recycleforum != $forumid)
	{
		$query = $DB->query('SELECT count(*) as num, threadid,rawthreadid
			FROM ' . TABLE_PREFIX . $posttable . "
			WHERE rawthreadid IN (" . implode(',', array_keys($this_tids)) . ")
			GROUP BY rawthreadid");
		$rawthreads = array();
		while ($row = $DB->fetch($query))
		{
			$rawthreads[$row['rawthreadid']] = $row;
		}

		$title = input::str('title');
		if ($forums->this_thread['title'])
		{
			$title = sprintf( $forums->lang['fromdeleted'], $forums->this_thread['title'] );
		}
		else
		{
			if ($single_delete)
			{
				$sigle_thread = $DB->queryFirst('SELECT title
									FROM ' . TABLE_PREFIX . "thread
									WHERE tid={$threadid}");
				$title = sprintf( $forums->lang['fromdeleted'], $sigle_thread['title'] );
			}
			else
			{
				$title = $forums->lang['searchdeleted'];
			}
		}
		$update_post = array('newthread' => 0);
		foreach ($this_tids AS $tid => $val)
		{
			$rawthread = $rawthreads[$tid];
			if ($rawthread['num'] <= 0 || !$rawthread['threadid'])
			{
				$newthread = array('title'				=> $title,
								   'open'				=> 1,
								   'post'				=> 0,
								   'postuserid'         => 0,
								   'postusername'	    => 0,
								   'dateline'			=> TIMENOW,
								   'lastposterid'		=> 0,
								   'lastposter'			=> 0,
								   'lastpost'			=> TIMENOW,
								   'iconid'				=> 0,
								   'pollstate'			=> 0,
								   'lastvote'			=> 0,
								   'views'				=> 0,
								   'forumid'			=> $recycleforum,
								   'visible'			=> 1,
								   'sticky'				=> 0,
								   'addtorecycle' 		=> TIMENOW,
								   'posttable'			=> $posttable,
									);
				$DB->insert(TABLE_PREFIX . 'thread', $newthread);
				$recyle_threadid = $DB->insertId();
				$post_recyle_threadid[$tid] = $recyle_threadid;
			}
			else
			{
				$post_recyle_threadid[$tid] = $rawthread['threadid'];
			}
		}

		foreach ($threadids AS $fid => $tids)
		{
			if ($tids)
			{
				foreach ($tids AS $tid)
				{
					$update_post['threadid'] = $post_recyle_threadid[$tid];
					$update_post['rawthreadid'] = $tid;
					$DB->update(TABLE_PREFIX . $posttable, $update_post, $DB->sql->in('pid', $pids));
					$mod_func->rebuild_thread($tid);
				}
			}
			forum_recount($fid);
		}
		foreach (array_unique($post_recyle_threadid) AS $tid)
		{
			$mod_func->rebuild_thread($tid);
		}

		forum_recount($recycleforum);
		$delete_log = sprintf($forums->lang['delposttorecycle'], $forums->this_thread['title'], implode(',', $pids));
		$message = $forums->lang['delposttorecyclesuc'];
	}
	else
	{
		$mod_func->post_delete($pids, 0, $posttable);
		foreach ($threadids AS $fid => $tids)
		{
			forum_recount($fid);
		}
		$delete_log = sprintf($forums->lang['delete_post'], $forums->this_thread['title'], implode(',', $pids));
		$message = $forums->lang['deletepostsuc'];
	}

	//给删除帖子的用户发送消息
	if (input::is_set('deletepmusers') && !empty($pm_touser))
	{
		require_once(ROOT_PATH . 'includes/functions_private.php');
		$pm = new functions_private();
		input::set('noredirect', 1);
		$bboptions['pmallowhtml'] = 1;
		$bboptions['usewysiwyg'] = 1;
		foreach ($pm_touser AS $userid => $uname)
		{
			input::set('title', $forums->lang['yourpostdeleted']);
			$forums->lang['yourpostdeletedinfo'] = sprintf( $forums->lang['yourpostdeletedinfo'], $forums->this_thread['title'], input::str('deletereason'));
			input::set('post', $forums->lang['yourpostdeletedinfo']);
			input::set('username', $uname);
			$pm->sendpm();
		}
	}

	add_moderate_log($delete_log);
	show_processinfo($message);

	if (intval($forums->this_thread['post']) == ($del_post_num - 1) && $forumid)
	{
		$url = "forumdisplay.php?{$forums->js_sessionurl}f=" . $forumid;
		$response->redirect($url);
	}
	else
	{
		$response->call('hideElement', 'operation_pannel');
		$response->assign('show_operation', 'innerHTML', '');
		foreach ($posts AS $row)
		{
			$response->remove('table_' . $row['pid']);
		}
	}
}

function do_revertpost()
{
	global $forums, $bboptions, $response, $bbuserinfo, $DB, $mod_func;
	require_once(ROOT_PATH . "includes/functions_moderate.php");
	$mod_func = new modfunctions();
	$recountids = array();
	$curtable = $forums->this_thread['posttable'];
	$recycleforumid = fetch_recycleforum();
	$posts = array();

	$pid = input::arr('pid');
	$result = $DB->query('SELECT * FROM ' . TABLE_PREFIX . "$curtable WHERE pid IN (" . implode(',', $pid) . ')');
	while ($row = $DB->fetch($result))
	{
		if (!$row['rawthreadid'])
		{
			$forums->func->load_lang('error');
			show_processinfo($forums->lang['errorrevertpost']);
			return $response;
		}
		else
		{
			$rawthread = $DB->queryFirst('SELECT tid, forumid FROM ' . TABLE_PREFIX . 'thread WHERE tid =' . $row['rawthreadid']);
			if (!$rawthread['tid'])
			{
				$forums->func->load_lang('error');
				show_processinfo($forums->lang['errorrevertpost']);
				return $response;
			}
			$DB->query('UPDATE '.TABLE_PREFIX . "$curtable SET threadid=" . $row['rawthreadid'] . ", rawthreadid='' WHERE pid=" . $row['pid']);
			$mod_func->rebuild_thread($row['threadid']);
			$recountids[] = $rawthread['forumid'];
			$mod_func->rebuild_thread($row['rawthreadid']);
		}
		$posts[] = $row['pid'];
	}
	add_moderate_log($forums->lang['revertpostfromrecycle'] . implode(',', $posts));
	$fids = array_unique($recountids);
	foreach ($fids as $fid)
	{
		forum_recount($rawthread['forumid']);
	}
	//恢复用户积分

	$need_change_posts = array($forums->this_thread['posttable'] => $posts);
	$mod_func->processcredit($need_change_posts, 'newreply', 'post');
	forum_recount($recycleforumid);

	show_processinfo($forums->lang['revertthreadfromrecycle']);

	$f = input::int('f');
	if (intval($forums->this_thread['post']) == (count($posts) - 1) && $f)
	{
		$url = "forumdisplay.php?{$forums->js_sessionurl}f=" . $f;
		$response->redirect($url);
	}
	else
	{
		$response->call('hideElement', 'operation_pannel');
		$response->assign('show_operation', 'innerHTML', '');
		foreach ($posts AS $pid)
		{
			$response->remove('table_' . $pid);
		}
	}
	return $response;
}

function send_mailto_friend($tid, $input = array())
{
	global $forums, $DB, $bbuserinfo, $bboptions, $response;
	if (!$bbuserinfo['id'])
	{
		show_processinfo($forums->lang['noperms']);
		return $response;
	}
	$tid = (int) $tid;
	if (!$tid)
	{
		$forums->func->load_lang('error');
		show_processinfo($forums->lang['erroraddress']);
		return $response;
	}
	if (!$thread = $DB->queryFirst("SELECT * FROM " . TABLE_PREFIX . "thread WHERE tid=" . $tid))
	{
		$forums->func->load_lang('error');
		show_processinfo($forums->lang['erroraddress']);
		return $response;
	}
	$subject = strip_tags($thread['title']);
	$forum = $forums->forum->single_forum($thread['forumid']);
	if (! $forum['id'])
	{
		$forums->func->load_lang('error');
		show_processinfo($forums->lang['erroraddress']);
		return $response;
	}
	$forums->func->load_lang('sendmessage');

	if (is_array($input) && $input)
	{
		if (!$input['to_name'] OR !$input['to_email'] OR !$input['message'] OR !$input['subject'])
		{
			$forums->func->load_lang('error');
			show_processinfo($forums->lang['plzinputallform']);
			return $response;
		}
		$to_email = clean_email($input['to_email']);
		if (!$to_email)
		{
			$forums->func->load_lang('error');
			show_processinfo($forums->lang['erroremail']);
			return $response;
		}
		require_once(ROOT_PATH . "includes/functions_email.php");
		$send_email = new functions_email();
		$message = $send_email->fetch_email_sendtofriend(array(
			'message' => preg_replace("#<br.*>#siU", "\n", str_replace("\r", "", $input['message'])),
			'username' => $input['to_name'],
			'from' => $bbuserinfo['name'],
		));
		$send_email->char_set = 'GBK';
		$send_email->build_message($message);
		$send_email->subject = $input['subject'];
		$send_email->to = $input['to_email'];
		$send_email->from = $bbuserinfo['email'];
		$send_email->send_mail();
		show_processinfo($forums->lang['sendmail']);
		$response->call('hideElement', 'operation_pannel');
	}
	else
	{
		$threadurl = preg_replace('/\?s=\w{32}(&)?/', '?', $forums->url);
		$forums->lang['sendfriendcontent'] = sprintf($forums->lang['sendfriendcontent'], $threadurl, $bboptions['bbtitle'], $bbuserinfo['name']);
		$bboptions['gzipoutput'] = 0;
		$sessionid = $forums->sessionid;
		ob_end_clean();
		ob_start();
		include $forums->func->load_template('sendmail_sendtofriend');
		$content = ob_get_contents();
		ob_end_clean();
		$response->assign('show_operation', 'innerHTML', $content);
		$response->call('showElement', 'operation_pannel');
		$response->call('toCenter', 'operation_pannel');
	}
	return $response;
}