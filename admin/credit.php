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

class credit
{
	function show()
	{
		global $forums, $DB, $bbuserinfo;
		$forums->func->load_lang('admin_credit');
		$admin = explode(',', SUPERADMIN);
		if (!in_array($bbuserinfo['id'], $admin) && !$forums->adminperms['caneditusers'])
		{
			$forums->admin->print_cp_error($forums->lang['nopermissions']);
		}
		require_once(ROOT_PATH . 'includes/functions_credit.php');
		$this->credit = new functions_credit();
		switch (input::get('do', ''))
		{
			case 'add':
				$this->credit_form('add');
			break;
			case 'edit':
				$this->credit_form('edit');
			break;
			case 'delete':
				$this->delete_credit();
			break;
			case 'deleterule':
				$this->delete_creditrule();
			break;
			case 'doedit':
				$this->doedit();
			break;
			case 'eventlist':
				$this->event_list();
			break;
			case 'rulelist':
				$this->rulelist();
			break;
			case 'addrule':
				$this->rule_form('add');
			break;
			case 'editrule':
				$this->rule_form('edit');
			break;
			case 'editdefaultrule':
				$this->rule_form('default');
			break;
			case 'doeditrule':
				$this->doeditrule();
			break;
			case 'editevalset':
				$this->editevalset();
			break;
			default:
				$this->creditlist();
			break;
		}
	}

	function creditlist()
	{
		global $forums, $DB;

		$pp = input::int('pp');

		$pagetitle = $forums->lang['managecredit'];
		$forums->admin->nav[] = array('credit.php' , $forums->lang['creditlist']);

		$forums->admin->print_cp_header($pagetitle);
		$forums->admin->print_form_header(array(1 => array('do' , 'add')));

		$row = $DB->queryFirst('SELECT count(creditid) as total
			FROM ' . TABLE_PREFIX . 'credit');
		$row_count = $row['total'];
		$links = $forums->func->build_pagelinks(array('totalpages' => $row_count,
			'perpage' => 10,
			'curpage' => $pp,
			'pagelink' => "credit.php?" . $forums->sessionurl,
			)
		);
		$forums->admin->print_cells_single_row($links, 'right', 'pformstrip');
		$forums->admin->columns[] = array($forums->lang['credit_name'], '18%');
		$forums->admin->columns[] = array($forums->lang['credit_tag_name'], '18%');
		$forums->admin->columns[] = array($forums->lang['credit_used'], '10%');
		$forums->admin->columns[] = array($forums->lang['credit_unit'], '10%');
		$forums->admin->columns[] = array($forums->lang['creditlimit'], '10%');
		$forums->admin->columns[] = array($forums->lang['action'], '34%');

		$forums->admin->print_table_start($pagetitle);

		$result = $DB->query('SELECT *
			FROM ' . TABLE_PREFIX . "credit
			LIMIT $pp, 10");
		if ($row_count > 0)
		{
			while ($credit = $DB->fetch($result))
			{
				$used = $credit['used']?"<font color='red'>".$forums->lang['yes']."</font>":$forums->lang['no'];
				$action = "<center><a href='credit.php?{$forums->sessionurl}do=editdefaultrule&amp;creditid={$credit['creditid']}'>{$forums->lang['editdefaultrule']}</a>
					| <a href='credit.php?{$forums->sessionurl}do=editevalset&amp;creditid={$credit['creditid']}'>{$forums->lang['editevalset']}</a>
					| <a href='credit.php?{$forums->sessionurl}do=edit&amp;id={$credit['creditid']}'>{$forums->lang['edit']}</a>" .
					($credit['tag'] == 'reputation' ? '' : "| <a href='credit.php?{$forums->sessionurl}do=delete&amp;id={$credit['creditid']}' onclick=\"if (!confirm('".$forums->lang['delcreditdesc']."')) {return false;}\">{$forums->lang['delete']}</a>") .
					"</center>";
				$forums->admin->print_cells_row(array(
						"<center>" . $credit['name'] . "</center>",
						"<center>" . $credit['tag'] . "</center>",
						"<center>" . $used . "</center>",
						"<center>" . $unit = $credit['unit']!=''?$credit['unit']:'&nbsp;' . "</center>",
						"<center>" . $temp = $credit['downlimit']!=''?$credit['downlimit']:'0&nbsp;' . "</center>",
						$action,
						));
			}
		}
		else
		{
			$forums->admin->print_cells_single_row("<strong>{$forums->lang['no_any_credits']}</strong>", 'center');
		}
		$forums->admin->print_form_submit($forums->lang['add_new_credit']);
		$forums->admin->print_table_footer();
		$forums->admin->print_form_end();
		$forums->admin->print_cp_footer();
	}

	function credit_form($type = 'add')
	{
		global $forums, $DB;
		if ($type == "edit")
		{
			$id = input::int('id');
			$credit = $DB->queryFirst("SELECT * FROM " . TABLE_PREFIX . "credit WHERE creditid = $id");
			if (!$credit['creditid'])
			{
				$forums->admin->print_cp_error($forums->lang['noids']);
			}
			$pagetitle = $forums->lang['edit_credit'];
			$button = $forums->lang['edit_credit'];
		}
		else
		{
			$pagetitle = $forums->lang['add_credit'];
			$button = $forums->lang['add_credit'];
		}

		$forums->admin->nav[] = array('credit.php' , $forums->lang['creditlist']);
		$forums->admin->print_cp_header($pagetitle);
		$forums->admin->print_form_header(array(1 => array('do' , 'doedit'),
		                                        2 => array('id', $credit['creditid']),
		                                 ));
		$forums->admin->columns[] = array('&nbsp;', '40%');
		$forums->admin->columns[] = array('&nbsp;', '60%');
		$forums->admin->print_table_start($forums->lang['set_cash_info']);

		$forums->admin->print_cells_row(array("<strong>{$forums->lang['credit_name']}</strong>", $forums->admin->print_input_row('name', input::get('name', $credit['name']))));

		$forums->admin->print_cells_row(array("<strong>{$forums->lang['credit_tag_name']}</strong><div class='description'>{$forums->lang['credit_tag_name_desc']}</div>", $credit['tag'] ? $credit['tag'] : $forums->admin->print_input_row('tag', input::get('tag', ''))));

		$forums->admin->print_cells_row(array("<strong>{$forums->lang['credit_unit']}</strong><div class='description'>{$forums->lang['credit_unit_desc']}</div>", $forums->admin->print_input_row('unit', input::get('unit', $credit['unit']))));

		$downlimit = input::get('downlimit', $credit['downlimit']);
		$forums->admin->print_cells_row(array("<strong>{$forums->lang['credit_limit']}</strong>", $forums->admin->print_input_row('downlimit', $downlimit)));

		$forums->admin->print_cells_row(array("<strong>{$forums->lang['creditinitvalue']}</strong>", $forums->admin->print_input_row('initvalue', input::get('initvalue', $credit['initvalue']))));

		$forums->admin->print_cells_row(array("<strong>{$forums->lang['creditinittime']}</strong><div class='description'>{$forums->lang['creditinittimedesc']}</div>", $forums->admin->print_input_row('inittime', input::get('inittime', $credit['inittime']))));

		$forums->admin->print_cells_row(array("<strong>{$forums->lang['credit_used']}</strong>", $forums->admin->print_yes_no_row('used', input::get('used', $credit['used']))));
		$forums->admin->print_form_submit($button);
		$forums->admin->print_table_footer();
		$forums->admin->print_form_end();
		$forums->admin->print_cp_footer();
	}

	function doedit($redirect = true)
	{
		global $forums, $DB;
		$id = input::int('id');
		input::set('name', trim(input::str('name')));
		input::set('tag', strtolower(trim(input::str('tag'))));
		if (!input::get('name', ''))
		{
			$forums->admin->print_cp_error($forums->lang['require_credit_name']);
		}
		if (!input::get('tag', '') && input::get('do', '')!='doedit')
		{
			$forums->admin->print_cp_error($forums->lang['require_credit_tag']);
		}
		if ($id)
		{
			$credit = $DB->queryFirst('SELECT * FROM ' . TABLE_PREFIX . "credit WHERE creditid = $id");
			if (!$credit['creditid'])
			{
				$forums->admin->print_cp_error($forums->lang['noids']);
			}
		}
		else
		{
			if (!preg_match('#^[A-Za-z][\w]*$#i', input::get('tag', '')))
			{
				$forums->admin->print_cp_error($forums->lang['only_letter_num']);
			}
			$key_already_used = false;
			$result = $DB->query('DESCRIBE ' . TABLE_PREFIX . 'user');
			while ($row = $DB->fetch($result))
			{
				if ($row['Field'] == input::get('tag', ''))
				{
					$key_already_used = true;
					break;
				}
			}
			$DB->freeResult($result);
			if (!$key_already_used)
			{
				$result = $DB->queryFirst('SELECT creditid FROM ' . TABLE_PREFIX . "credit WHERE tag = '" . input::get('tag', '') . "'");
				if ($result['creditid'])
				{
					$key_already_used = true;
				}
			}
			if (!$key_already_used)
			{
				$result = $DB->query('DESCRIBE ' . TABLE_PREFIX . 'userexpand');
				while ($row = $DB->fetch($result))
				{
					if ($row['Field'] == input::get('tag', ''))
					{
						$key_already_used = true;
					}
				}
				$DB->freeResult($result);
			}

			if ($key_already_used)
			{
				$forums->admin->print_cp_error($forums->lang['key_already_used']);
			}
		}
		$sql_array = array(
				'name' => trim(input::str('name')),
				'unit' => trim(input::str('unit')),
				'downlimit' => input::int('downlimit'),
				'initvalue' => input::int('initvalue'),
				'inittime' => input::int('inittime'),
				'used' => input::int('used'));
		if ($credit['creditid'])
		{
			$DB->update(TABLE_PREFIX . 'credit', $sql_array, 'creditid = ' . $credit['creditid']);
			$type = 'edited';
		}
		else
		{
			$sql_array['tag'] = input::get('tag', '');
			$sql_array['initevalvalue'] = 20;
			$sql_array['initevaltime'] = 24;
			$DB->insert(TABLE_PREFIX . 'credit', $sql_array);
			$id = $DB->insertId();
			$DB->queryUnbuffered('ALTER TABLE ' . TABLE_PREFIX . 'userexpand
				ADD `' . input::get('tag', '') . '` FLOAT( 10, 2 ) NOT NULL default 0, ADD `eval' . input::get('tag', '') . '` INT( 10 ) NOT NULL default 0');
			//添加一套积分的默认规则
			$result = $DB->query('SELECT eventtag, defaultvalue FROM ' . TABLE_PREFIX . "creditevent");
			$params = array();

			while ($r = $DB->fetch($result))
			{
				$params[$r['eventtag']] = $r['defaultvalue'];
			}
			$params = serialize($params);
			$defaultrule = array('creditid' => $id,
								 'type' => 0,
								 'parameters' => $params);
			$DB->insert(TABLE_PREFIX . 'creditrule', $defaultrule);
			$type = 'added';
			cache::update('creditrule');
		}
		cache::update('creditlist');
		$forums->admin->redirect('credit.php', $forums->lang['creditlist'], $forums->lang['credit_' . $type]);
	}

	function rulelist()
	{
		global $forums, $DB;

		$pp = input::int('pp');
		$pagetitle = $forums->lang['managecredit'];
		$detail = $forums->lang['managecreditruledesc'];
		$forums->admin->nav[] = array('credit.php?do=rulelist' , $forums->lang['creditrulelist']);

		$forums->admin->print_cp_header($pagetitle, $detail);
		$forums->admin->print_form_header(array(1 => array('do' , 'addrule')));

		$row = $DB->queryFirst('SELECT count(*) as total FROM ' . TABLE_PREFIX . 'creditrule');
		$row_count = $row['total'];
		$links = $forums->func->build_pagelinks(array('totalpages' => $row_count,
			'perpage' => 10,
			'curpage' => $pp,
			'pagelink' => "credit.php?do=rulelist&amp;" . $forums->sessionurl,
			)
		);
		$forums->admin->print_cells_single_row($links, 'right', 'pformstrip');
		$forums->admin->columns[] = array($forums->lang['credit_name'], '20%');
		$forums->admin->columns[] = array($forums->lang['credit_rule_group'], '15%');
		$forums->admin->columns[] = array($forums->lang['creditrulerange'], '50%');
		$forums->admin->columns[] = array($forums->lang['action'], '15%');
		$forums->admin->print_table_start($forums->lang['creditrulelist']);

		$result = $DB->query('SELECT cr.*, c.name, c.tag FROM ' . TABLE_PREFIX . "creditrule cr
			LEFT JOIN " . TABLE_PREFIX . "credit c
			ON cr.creditid = c.creditid
			ORDER BY cr.creditid ASC, cr.type ASC
			LIMIT $pp, 10");
		if ($DB->numRows($result))
		{
			while ($rule = $DB->fetch($result))
			{
				$rulerange = '&nbsp;';
				$range = array();
				if ($rule['type'] && $rule['lists'])
				{
					$lists = explode(',', $rule['lists']);
					if ($rule['type']==2)
					{
						foreach ($lists as $id)
						{
							$range[] = $forums->adminforum->forumcache[$id]['name'];
						}
					}
					else
					{
						cache::get('usergroup');
						foreach ($lists as $id)
						{
							$range[] = $forums->lang[ $forums->cache['usergroup'][$id]['grouptitle'] ];
						}
					}
					if (!empty($range))
					{
						$rulerange = implode(',', $range);
					}
				}
				switch ($rule['type'])
				{
					case 2:
						$ruletype = $forums->lang['credit_forum'];
						break;
					case 1:
						$ruletype = $forums->lang['credit_usergroup'];
						break;
					default:
						$ruletype = "<font color='red'>".$forums->lang['credit_global']."</font>";
				}
				$extra = !$rule['type']?'tdrow1shaded':'';
				$forums->admin->print_cells_row(array(
					"<center>" . $rule['name'] . "({$rule['tag']})</center>",
					"<center>" . $ruletype . "</center>",
					"<center>" . $rulerange . "</center>",
					"<center><a href='credit.php?{$forums->sessionurl}do=editrule&amp;ruleid={$rule['ruleid']}'>{$forums->lang['edit']}</a> |
					<a href='credit.php?{$forums->sessionurl}do=deleterule&amp;ruleid={$rule['ruleid']}' onclick=\"if (!confirm('".$forums->lang['delcreditruledesc']."')) {return false;}\">{$forums->lang['delete']}</a></center>",
				), $extra);
			}
		}
		else
		{
			$forums->admin->print_cells_single_row("<strong>{$forums->lang['no_any_creditrules']}</strong>", 'center');
		}
		$forums->admin->print_form_submit($forums->lang['add_new_creditrule']);
		$forums->admin->print_table_footer();
		$forums->admin->print_form_end();
		$forums->admin->print_cp_footer();
	}

	function rule_form($type = 'add')
	{
		global $forums, $DB;

		$pagetitle = $forums->lang['addcreditrule'];
		$button = $forums->lang['addcreditrule'];
		if ($type == "edit" || $type == "default")
		{
			if ($type == "edit")
			{
				$wheresql = "WHERE ruleid = '" . input::get('ruleid', '') . "'";
			}
			else
			{
				$wheresql = "WHERE creditid = '" . input::get('creditid', '') . "' AND type = 0";
			}
			$rule = $DB->queryFirst("SELECT *
					FROM " . TABLE_PREFIX . "creditrule
					$wheresql");
			if ($type == "edit" && $rule['type'])
			{
				$pagetitle = $forums->lang['editcreditrule'];
				$button = $forums->lang['editcreditrule'];
			}
			else
			{
				$pagetitle = $forums->lang['editdefaultrule'];
				$button = $forums->lang['editdefaultrule'];
			}
			if (!$rule['creditid'])
			{
				$forums->admin->print_cp_error($forums->lang['noids']);
			}
		}
		$forums->admin->nav[] = array('credit.php?do=rulelist&amp;' , $forums->lang['creditrulelist']);
		$forums->admin->print_cp_header($pagetitle);
		$forums->admin->print_form_header(array(1 => array('do' , 'doeditrule'),
		                                        2 => array('edittype', $rule['type']),
		                                        3 => array('editruleid', $rule['ruleid']),
		                                 		4 => array('editid', $rule['creditid']),
		                                 ));
		$forums->admin->columns[] = array('&nbsp;', '40%');
		$forums->admin->columns[] = array('&nbsp;', '60%');
		$forums->admin->print_table_start($forums->lang['set_rule_info']);

		$creditlist = $forum = $usergroup = array();
		$rs = $DB->query("SELECT * FROM " . TABLE_PREFIX . "credit");
		while ($r = $DB->fetch($rs))
		{
			$creditlist[] = array($r['creditid'], $r['name']);
		}
		foreach ($forums->adminforum->forumcache as $k => $v)
		{
			$forum[] = array($v['id'], $v['name'], $v['depth']);
		}
		cache::get('usergroup');
		foreach ($forums->cache['usergroup'] as $k => $v)
		{
			$usergroup[] = array($v['usergroupid'], $forums->lang[ $v['grouptitle'] ]);
		}
		$disable= $rule['creditid']?'disabled="disabled"':'';

		$forums->admin->print_cells_row(array("<strong>" . $forums->lang['conncreditname'] . "</strong>" , $forums->admin->print_input_select_row("creditid", $creditlist, $rule['creditid'], $disable)));

		$seltype = $rule['type']?$rule['type']:1;
		if ($type == 'add')
		{
			$radiolist = "<input type='radio' name='type' value='2' onclick='changeruletype(this.value)'/>" . $forums->lang['credittypeforum'] . "&nbsp;&nbsp;<input type='radio' name='type' value='1' checked='checked' onclick='changeruletype(this.value)'/>" . $forums->lang['credittypegroup'] . "";
			$forums->admin->print_cells_row(array("<strong>" . $forums->lang['creditruletype'] . "</strong>", $radiolist));
		}
		else
		{
			if ($rule['type'] == 2)
			{
				$creditruletype = $forums->lang['credittypeforum'];
			}
			elseif ($rule['type'] == 1)
			{
				$creditruletype = $forums->lang['credittypegroup'];
			}
			else
			{
				$creditruletype = $forums->lang['credit_global'];
			}
			$forums->admin->print_cells_row(array("<strong>" . $forums->lang['creditruletype'] . "</strong>", $creditruletype));
		}
		//用户组或版面列表
		if ($type != 'default' && ((isset($rule['type']) && $rule['type']) || !isset($rule['type'])))
		{
			$grouplists = $rule['type'] == 1 ? explode(',', $rule['lists']):array();
			$forumlists = $rule['type'] == 2 ? explode(',', $rule['lists']):array();
			$showforum = $this->search_forum_jump('lists[]', $forum, $forumlists, 8, 'id="forumtype" style="display:none"');
			$forums->admin->print_cells_row(array("<strong>" . $forums->lang['creditrulerange'] . "</strong>", $showforum . $forums->admin->print_multiple_select_row('lists[]', $usergroup, $grouplists, 8, 'id="grouptype" style="display:none"')));
			echo "<script type=\"text/javascript\">\n";
			echo "function changeruletype(type)\n";
			echo "{\n";
			echo "var forumtype = \$(\"forumtype\");\n";
			echo "var grouptype = \$(\"grouptype\");\n";
			echo "if (type==1){\n";
			echo "forumtype.style.display='none'\n";
			echo "grouptype.style.display=''\n";
			echo "}else{\n";
			echo "forumtype.style.display=''\n";
			echo "grouptype.style.display='none'\n";
			echo "}\n";
			echo "}\n";
			echo "changeruletype('" . $seltype . "')\n";
			echo "</script>\n";
		}
		$forums->admin->print_table_footer();
		$forums->admin->columns[] = array('&nbsp;', '40%');
		$forums->admin->columns[] = array('&nbsp;', '60%');
		$forums->admin->print_table_start($forums->lang['creditruledetail']);
		//显示积分事件列表
		$parms = $rule['parameters'] ? unserialize($rule['parameters']) : array();
		$result = $DB->query("SELECT * FROM " . TABLE_PREFIX . "creditevent ORDER BY eventtype ASC");
		while($row = $DB->fetch($result))
		{
			$tag = $row['eventtag'];
			$eventvalue = input::get($tag, $parms[$tag]);
			$eventvalue = $eventvalue != '100%' ? $eventvalue : '';
			$forums->admin->print_cells_row(array("<strong>{$row['eventname']}</strong>", $forums->admin->print_input_row($tag, $eventvalue)));
		}
		$forums->admin->print_form_submit($button);
		$forums->admin->print_table_footer();
		$forums->admin->print_form_end();

		$forums->admin->print_cp_footer();
	}

	function doeditrule()
	{
		global $forums, $DB;
		$type = input::get('edittype', input::int('type'));
		$creditid = input::get('editid', input::int('creditid'));
		$ruleid = input::int('editruleid');
		if (input::str('lists'))
		{
			$lists = implode(',', input::get('lists', ''));
		}
		if (input::str('editruleid'))
		{
			$msg = $forums->lang['creditrule_edited'];
		}
		else
		{
			$msg = $forums->lang['creditrule_added'];
		}
		if (!$creditid)
		{
			$forums->admin->print_cp_error($forums->lang['noids']);
		}
		//判断选择的版面或用户组是否在其他规则中已定义
		$tmplists = input::str('lists');

		if (!empty($tmplists) && $type)
		{
			$result = $DB->query('SELECT lists
				FROM ' . TABLE_PREFIX . "creditrule
				WHERE creditid=$creditid AND type=$type AND ruleid!=$ruleid AND lists!=''");
			if ($DB->numRows())
			{
				while ($row = $DB->fetch($result))
				{
					foreach (input::get('lists', '') as $id)
					{
						if (strpos(','.$row['lists'].',', ','.$id.',') !== false)
						{
							if ($type == 1)
							{
								cache::get('usergroup');
								$iddesc = $forums->lang[$forums->cache['usergroup'][$id]['grouptitle']].$forums->lang['credittypegroup'];
							}
							else
							{
								$iddesc = $forums->adminforum->forumcache[$id]['name'].$forums->lang['credittypeforum'];
							}
							$forums->admin->print_cp_error(sprintf($forums->lang['rulelistidexsite'], $iddesc));
						}
					}
				}
			}
		}
		$params = $rule = array();
		$result = $DB->query('SELECT eventtag FROM ' . TABLE_PREFIX . "creditevent");
		while ($r = $DB->fetch($result))
		{
			$value = trim($_REQUEST[$r['eventtag']]);
			if (!$type && $ruleid)
			{
				$value = intval($value);
			}
			else
			{
				if ($value)
				{
					$value = substr($value, -1, 1) != '%'?intval($value):intval($value).'%';
				}
				else
				{
					$value = '100%';
				}
			}
			$params[$r['eventtag']] = $value;
		}
		$params = serialize($params);
		$rule = array('lists' => $lists,
					  'parameters' => $params);
		if (!$ruleid)
		{
			$rule = array_merge($rule, array('creditid' => $creditid, 'type' => $type));
			$DB->insert(TABLE_PREFIX . 'creditrule', $rule);
		}
		else
		{
			$DB->update(TABLE_PREFIX . 'creditrule', $rule, "ruleid=$ruleid");
		}
		cache::update('creditrule');
		$forums->admin->redirect('credit.php?do=rulelist', $forums->lang['creditrulelist'], $msg);
	}

	function editevalset()
	{
		global $forums, $DB;

		$id = input::int('creditid');
		$credit = $DB->queryFirst("SELECT * FROM " . TABLE_PREFIX . "credit WHERE creditid = $id");
		if (!$credit['creditid'])
		{
			$forums->admin->print_cp_error($forums->lang['noids']);
		}
		if (!input::get('update', ''))
		{
			$forums->admin->nav[] = array('credit.php' , $forums->lang['creditlist']);
			$forums->admin->print_cp_header($forums->lang['editevalset']);
			$forums->admin->print_form_header(array(1 => array('do' , 'editevalset'),
													2 => array('update' , 1),
			                                        3 => array('creditid', $credit['creditid']),
			                                 ));
			$forums->admin->columns[] = array('&nbsp;', '40%');
			$forums->admin->columns[] = array('&nbsp;', '60%');
			$forums->admin->print_table_start($forums->lang['editevalset']);

			$forums->admin->print_cells_row(array("<strong>{$forums->lang['evalinitvalue']}</strong><div class='description'>{$forums->lang['evalinitvaluedesc']}</div>", $forums->admin->print_input_row('initevalvalue', $credit['initevalvalue'])));

			$forums->admin->print_cells_row(array("<strong>{$forums->lang['evalinittime']}</strong><div class='description'>{$forums->lang['evalinittimedesc']}</div>", $forums->admin->print_input_row('initevaltime', $credit['initevaltime'])));

			$forums->admin->print_form_submit($forums->lang['editevalset']);
			$forums->admin->print_table_footer();
			$forums->admin->print_form_end();
			$forums->admin->print_cp_footer();
		}
		else
		{
			$initvalue = input::int('initevalvalue');
			$inittime = input::int('initevaltime');
			if ($inittime <= 0)
			{
				$forums->admin->print_cp_error($forums->lang['noids']);
			}
			$array = array('initevalvalue' => $initvalue,
						   'initevaltime' => $inittime);
			$DB->update(TABLE_PREFIX . 'credit', $array, "creditid=$id");
			cache::update('creditlist');
			$forums->admin->redirect('credit.php', $forums->lang['creditlist'], $forums->lang['evalsetsuccess']);
		}
	}

	function delete_credit()
	{
		global $forums, $DB;

		$id = input::int('id');
		if (!$id)
		{
			$forums->admin->print_cp_error($forums->lang['noids']);
		}
		$credit = $DB->queryFirst('SELECT * FROM ' . TABLE_PREFIX . 'credit WHERE creditid=' . $id);
		$DB->delete(TABLE_PREFIX . 'credit', 'creditid = ' . $id);
		$DB->delete(TABLE_PREFIX . 'creditrule', 'creditid = ' . $id);
		$DB->queryUnbuffered('ALTER TABLE ' . TABLE_PREFIX . "userexpand DROP " . $credit['tag'] . ',DROP eval' . $credit['tag']);
		cache::update('creditlist');
		cache::update('creditrule');
		$forums->admin->redirect('credit.php', $forums->lang['creditlist'], $forums->lang['credit_deleted']);
	}

	function delete_creditrule()
	{
		global $forums, $DB;

		if (!input::get('ruleid', ''))
		{
			$forums->admin->print_cp_error($forums->lang['noids']);
		}
		$DB->delete(TABLE_PREFIX . 'creditrule', "ruleid = " . input::get('ruleid', ''));
		cache::update('creditrule');
		$forums->admin->redirect('credit.php?do=rulelist', $forums->lang['creditrulelist'], $forums->lang['creditrule_deleted']);
	}

	function event_list()
	{
		global $forums, $DB;

		$pp = input::int('pp');
		$pagetitle = $forums->lang['managecredit'];
		$forums->admin->nav[] = array('credit.php?do=eventlist' , $forums->lang['crediteventlists']);

		$forums->admin->print_cp_header($pagetitle);
		$row = $DB->queryFirst('SELECT count(eventid) as total FROM ' . TABLE_PREFIX . 'creditevent');
		$row_count = $row['total'];
		$links = $forums->func->build_pagelinks(array('totalpages' => $row_count,
			'perpage' => 10,
			'curpage' => $pp,
			'pagelink' => "credit.php?do=eventlist&amp;" . $forums->sessionurl,
			)
		);
		$forums->admin->print_cells_single_row($links, 'right', 'pformstrip');
		$forums->admin->columns[] = array($forums->lang['credit_rule_name'], '35%');
		$forums->admin->columns[] = array($forums->lang['credit_tag_name'], '35%');
		$forums->admin->columns[] = array($forums->lang['settingdefault'], '30%');
		$forums->admin->print_table_start($forums->lang['crediteventlists']);
		$result = $DB->query('SELECT * FROM ' . TABLE_PREFIX . "creditevent Limit $pp, 10");
		if ($DB->numRows($result))
		{
			while ($event = $DB->fetch($result))
			{
				$forums->admin->print_cells_row(array(
					"<center>" . $event['eventname'] . "</center>",
					"<center>" . $event['eventtag'] . "</center>",
					"<center>" . $event['defaultvalue'] . "</center>",
				));
			}
		}
		else
		{
			$forums->admin->print_cells_single_row("<strong>{$forums->lang['no_any_creditevents']}</strong>", 'center');
		}
		$forums->admin->print_cells_single_row($links, 'right', 'pformstrip');
		$forums->admin->print_table_footer();
		$forums->admin->print_form_end();
		$forums->admin->print_cp_footer();
	}

	function search_forum_jump($name, $list = array(), $default = array(), $size = 5, $js = "")
	{
		global $forums;

		$select = "<select name='{$name}' {$js} class='dropdown' multiple='multiple' size='{$size}'>\n";
		foreach ($list AS $k => $v)
		{
			$selected = "";
			if (count($default) > 0)
			{
				if (in_array($v[0], $default))
				{
					$selected = ' selected="selected"';
				}
			}
			$select .= "<option value='{$v[0]}'{$selected}>" . depth_mark($v[2], '---') . ' ' . $v[1] . "</option>\n";
		}
		$select .= "</select>\n\n";

		return $select;
	}
}

$output = new credit();
$output->show();
?>