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
require ('./global.php');

class sptopic
{
	var $allforum = array();

	function show()
	{
		global $forums, $bbuserinfo;
		$forums->func->load_lang('admin_sptopic');
		$this->allforum = $forums->adminforum->forumcache;
		switch (input::get('do', ''))
		{
			case 'doadd':
				$this->doadd();
				break;
			case 'add':
				$this->add('add');
				break;
			case 'edit':
				$this->add('edit');
				break;
			case 'delete':
				$this->delete_st();
				break;
			default:
				$this->show_list();
				break;
		}
	}

	function delete_st()
	{
		global $forums, $DB;

		if (!input::int('id'))
		{
			$forums->admin->print_cp_error($forums->lang['noids']);
		}
		$st = $DB->queryFirst("SELECT * FROM " . TABLE_PREFIX . "specialtopic WHERE id = " . input::int('id') . "");
		if (!$st['id'])
		{
			$forums->admin->print_cp_error($forums->lang['noids']);
		}
		if (input::str('update'))
		{
			if (input::str('other') == $st['id'])
			{
				input::set('other', 0);
			}
			$DB->queryUnbuffered("DELETE FROM " . TABLE_PREFIX . "specialtopic WHERE id = '" . $st['id'] . "'");
			$DB->queryUnbuffered("UPDATE " . TABLE_PREFIX . "thread SET stopic=" . input::get('other', '') . " WHERE stopic = '" . $st['id'] . "'");
			if ($st['forumids'] == -1)
			{
				foreach($this->allforum AS $fid => $forum)
				{
					if (preg_match("/," . $st['id'] . ",/i", "," . $forum['specialtopic'] . ","))
					{
						$forum['specialtopic'] = preg_replace("#(^|,)(" . $st['id'] . ")(,|$)#is", ",", $forum['specialtopic']);
						$forum['specialtopic'] = preg_replace("#^(,|)(.*)(,|)$#is", "\\2", $forum['specialtopic']);
						$DB->queryUnbuffered("UPDATE " . TABLE_PREFIX . "forum SET specialtopic = '" . $forum['specialtopic'] . "' WHERE id={$fid}");
					}
				}
			}
			else
			{
				$forumids = explode(",", $st['forumids']);
				foreach($forumids AS $fid)
				{
					if (preg_match("/," . $st['id'] . ",/i", "," . $this->allforum[$fid]['specialtopic'] . ","))
					{
						$forum['specialtopic'] = preg_replace("#(^|,)(" . $st['id'] . ")(,|$)#is", ",", $this->allforum[$fid]['specialtopic']);
						$forum['specialtopic'] = preg_replace("#^(,|)(.*)(,|)$#is", "\\2", $forum['specialtopic']);
						$DB->queryUnbuffered("UPDATE " . TABLE_PREFIX . "forum SET specialtopic = '" . $forum['specialtopic'] . "' WHERE id={$fid}");
					}
				}
			}
			cache::update('forum');
			cache::update('st');
			$forums->admin->redirect("sptopic.php", $forums->lang['st_deleted'], $forums->lang['st_deleted']);
		}
		else
		{
			$pagetitle = $forums->lang['confirm_deleted'];

			$forums->admin->print_cp_header($pagetitle);
			$forums->admin->print_form_header(array(1 => array('do' , 'delete'), 2 => array('id', $st['id']), 3 => array('update', 1)));
			$forums->admin->columns[] = array("" , "40%");
			$forums->admin->columns[] = array("" , "60%");
			$forums->admin->print_table_start($pagetitle);

			$forums->admin->print_cells_single_row($forums->lang['confirm_deleted_desc'], "center");

			$stlist[] = array('0', $forums->lang['other_default']);
			cache::get('st');
			foreach($forums->cache['st'] AS $id => $value)
			{
				if ($value['id'] == $st['id']) continue;
				$stlist[] = array($value['id'], $value['name']);
			}

			$forums->admin->print_cells_row(array("<strong>" . $forums->lang['to_other_st'] . "</strong><br />",
					$forums->admin->print_input_select_row('other', $stlist)
					));

			$forums->admin->print_form_end($forums->lang['confirm_deleted']);
			$forums->admin->print_table_footer();
			$forums->admin->print_cp_footer();
		}
	}

	function show_list()
	{
		global $forums, $DB;
		$pagetitle = $forums->lang['sptopicmanage'];
		$detail = $forums->lang['sptopicmanagedesc'];
		$forums->admin->print_cp_header($pagetitle, $detail);
		$forums->admin->print_form_header(array(1 => array('do' , 'add')));

		$forums->admin->columns[] = array($forums->lang['sptopictitle'], "45%");
		$forums->admin->columns[] = array($forums->lang['forumlist'], "55%");
		$forums->admin->print_table_start($pagetitle);
		$DB->query("SELECT * FROM " . TABLE_PREFIX . "specialtopic");
		if ($DB->numRows())
		{
			while ($r = $DB->fetch())
			{
				$forum_info = array();
				if ($r['forumids'] == '-1')
				{
					$forum_info[] = $forums->lang['allforums'];
				}
				else if ($r['forumids'])
				{
					$forumids = explode(",", $r['forumids']);
					foreach ($forumids AS $id)
					{
						if ($this->allforum[$id]['id'])
						{
							$forum_info[] = "<strong><a href='forums.php?{$forums->sessionurl}&amp;do=edit&amp;f=$id' target='_blank'>" . $this->allforum[$id]['name'] . "</a></strong>";
						}
					}
				}
				$forums->admin->print_cells_row(array("<strong>" . $r['name'] . "</strong> [<a href='sptopic.php?{$forums->sessionurl}&amp;do=edit&amp;id={$r['id']}'>" . $forums->lang['edit'] . "</a>] [<a href='sptopic.php?{$forums->sessionurl}&amp;do=delete&amp;id={$r['id']}'>" . $forums->lang['delete'] . "</a>]", implode("<br />", $forum_info)));
			}
		}
		else
		{
			$forums->admin->print_cells_single_row($forums->lang['no_any_speical_topic'], "center");
		}

		$forums->admin->print_form_submit($forums->lang['addnewsptopic']);
		$forums->admin->print_table_footer();
		$forums->admin->print_form_end();

		$forums->admin->print_cp_footer();
	}

	function add($type = 'edit')
	{
		global $forums, $DB;
		if ($type == 'edit')
		{
			$st = $DB->queryFirst("SELECT * FROM " . TABLE_PREFIX . "specialtopic WHERE id=" . input::int('id') . "");
			if (!$st['id'])
			{
				$forums->admin->print_cp_error($forums->lang['noids']);
			}
			$pagetitle = $forums->lang['editsptopic'];
			$arr = explode(",", $st['forumids']);
		}
		else
		{
			$pagetitle = $forums->lang['addsptopic'];
		}
		$forums->admin->print_cp_header($pagetitle);
		$forums->admin->print_form_header(array(1 => array('do' , 'doadd'), 2 => array('id' , input::get('id', ''))));
		$forums->admin->columns[] = array("", "40%");
		$forums->admin->columns[] = array("", "60%");
		$forums->admin->print_table_start($pagetitle);
		$forums->admin->print_cells_row(array("<strong>" . $forums->lang['sptopicname'] . "</strong>", $forums->admin->print_input_row("name", $st['name'])));
		$forum_list[] = array('-1' , $forums->lang['allforums']);
		foreach($this->allforum AS $key => $value)
		{
			$forum_list[] = array($value[id], depth_mark($value['depth'], '--') . $value[name]);
		}

		$forums->admin->print_cells_row(array("<strong>" . $forums->lang['useinforum'] . "</strong><div class='description'>" . $forums->lang['useinforumdesc'] . "</div>", $forums->admin->print_multiple_select_row("forum_list[]", $forum_list, $arr, 5)));
		if ($type == 'edit')
		{
			$forums->admin->print_form_submit($forums->lang['editsptopic']);
		}
		else
		{
			$forums->admin->print_form_submit($forums->lang['addsptopic']);
		}
		$forums->admin->print_table_footer();
		$forums->admin->print_form_end();

		$forums->admin->print_cp_footer();
	}

	function doadd()
	{
		global $forums, $DB;
		$id = input::int('id');
		$name = trim(input::str('name'));
		if (!$name OR !is_array(input::arr('forum_list')))
		{
			$forums->admin->print_cp_error($forums->lang['require_fields']);
		}
		if (in_array("-1", input::get('forum_list', array())))
		{
			$allids = array_keys($this->allforum);
			$update_ids = "-1";
		}
		else if (count(input::arr('forum_list')) == count($this->allforum))
		{
			$allids = array_keys($this->allforum);
			$update_ids = "-1";
		}
		else
		{
			$allids = input::get('forum_list', array());
			$update_ids = implode(',', $allids);
		}
		$data = array(
			'name' => $name,
			'forumids' => $update_ids,
		);
		if ($id)
		{
			$DB->update(TABLE_PREFIX . 'specialtopic', $data, "id=$id");
		}
		else
		{
			$DB->insert(TABLE_PREFIX . 'specialtopic', $data);
			$id = $DB->insertId();
		}
		$this->update_forum($id, $allids);
		$forums->admin->redirect("sptopic.php", $forums->lang['sptopicmanage'], $forums->lang['sptopicupdated']);
	}

	function update_forum($id = 0, $forumids = array())
	{
		global $forums, $DB;
		if (!$id OR !is_array($forumids)) return;

		foreach($this->allforum AS $fid => $forum)
		{
			if (preg_match("/," . $id . ",/i", "," . $forum['specialtopic'] . ",") AND !in_array($fid, $forumids))
			{
				$forum['specialtopic'] = preg_replace("#(^|,)(" . $id . ")(,|$)#is", ",", $forum['specialtopic']);
				$forum['specialtopic'] = preg_replace("#^(,|)(.*)(,|)$#is", "\\2", $forum['specialtopic']);
				$DB->queryUnbuffered("UPDATE " . TABLE_PREFIX . "forum SET specialtopic = '" . $forum['specialtopic'] . "' WHERE id={$fid}");
				continue;
			}
			if (in_array($fid, $forumids))
			{
				$spids = array();
				$update = false;
				if ($this->allforum[$fid]['specialtopic'])
				{
					$spids = explode(",", $this->allforum[$fid]['specialtopic']);
				}
				if (in_array($id, $spids))
				{
					continue;
				}
				else
				{
					$spids[] = $id;
					$update = true;
				}
				if ($update)
				{
					asort($spids);
					$DB->queryUnbuffered("UPDATE " . TABLE_PREFIX . "forum SET specialtopic = '" . implode(",", $spids) . "' WHERE id={$fid}");
				}
				continue;
			}
		}
		cache::update('forum');
		cache::update('st');
	}
}

$output = new sptopic();
$output->show();

?>