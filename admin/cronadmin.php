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

class cronadmin
{
	function show()
	{
		global $forums, $bbuserinfo;
		$admin = explode(',', SUPERADMIN);
		if (!in_array($bbuserinfo['id'], $admin) && !$forums->adminperms['caneditcrons'])
		{
			$forums->admin->print_cp_error($forums->lang['nopermissions']);
		}
		require_once(ROOT_PATH . 'includes/functions_cron.php');
		$this->functions = new functions_cron();
		$pagetitle = $forums->lang['managecron'];
		$detail = $forums->lang['managecrondesc'];
		$forums->admin->nav[] = array('cronadmin.php', $forums->lang['managecron']);
		$forums->admin->print_cp_header($pagetitle, $detail);
		switch (input::get('do', ''))
		{
			case 'edit':
				$this->cronform('edit');
				break;
			case 'doeditcron':
				$this->dosavecron('edit');
				break;
			case 'addcron':
				$this->cronform('add');
				break;
			case 'doaddcron':
				$this->dosavecron('add');
				break;
			case 'delete':
				$this->deletecron();
				break;
			case 'run':
				$this->docron();
				break;
			default:
				$this->show_crons();
				break;
		}
	}

	function docron()
	{
		global $forums, $DB;
		if (! input::int('id'))
		{
			$forums->main_msg = $forums->lang['noids'];
			$this->show_crons();
		}
		$cron = $DB->queryFirst("SELECT * FROM " . TABLE_PREFIX . "cron WHERE cronid=" . input::int('id'));
		if (! $cron['cronid'])
		{
			$forums->main_msg = $forums->lang['noids'];
			$this->show_crons();
		}
		$newdate = $this->functions->next_run($cron);
		$DB->update(TABLE_PREFIX . 'cron', array('nextrun' => $newdate), 'cronid = ' . $cron['cronid']);
		$this->functions->save_next_run();
		if (file_exists(ROOT_PATH . 'includes/cron/' . $cron['filename']))
		{
			require_once(ROOT_PATH . 'includes/cron/' . $cron['filename']);
			$class_name = 'cron_' . substr($cron['filename'], 0, strrpos($cron['filename'], '.'));
			$runcron = new $class_name();
			$runcron->register_class($this->functions);
			$runcron->pass_cron($cron);
			$runcron->docron();

			$forums->main_msg = $forums->lang['cronrun'];
			$this->show_crons();
		}
		else
		{
			$forums->main_msg = $forums->lang['cannotfindfile'] . ': ' . ROOT_PATH . 'includes/cron/' . $cron['filename'];
			$this->show_crons();
		}
	}

	function deletecron()
	{
		global $forums, $DB;
		if (! input::int('id'))
		{
			$forums->main_msg = $forums->lang['noids'];
			$this->show_crons();
		}
		$DB->queryUnbuffered("DELETE FROM " . TABLE_PREFIX . "cron WHERE cronid=" . input::int('id') . "");
		$this->functions->save_next_run();
		$forums->main_msg = $forums->lang['crondeleted'];
		$this->show_crons();
	}

	function dosavecron($type = 'edit')
	{
		global $forums, $DB;
		if ($type == 'edit')
		{
			if (! input::int('id'))
			{
				$forums->main_msg = $forums->lang['noids'];
				$this->cronform();
			}
		}
		if (! input::get('title', ''))
		{
			$forums->main_msg = $forums->lang['mustcronnamed'];
			$this->cronform();
		}
		if (! input::get('filename', ''))
		{
			$forums->main_msg = $forums->lang['mustrunnamed'];
			$this->cronform();
		}
		$save = array(
			'title' => input::get('title', ''),
			'description' => input::get('description', ''),
			'filename' => input::get('filename', ''),
			'weekday' => input::get('weekday', ''),
			'monthday' => input::get('monthday', ''),
			'hour' => input::get('hour', ''),
			'minute' => input::get('minute', ''),
			'loglevel' => input::get('loglevel', ''),
			'cronhash' => input::get('cronhash', md5(microtime())),
			'enabled' => input::get('enabled', ''),
		);
		$save['nextrun'] = $this->functions->next_run($save);
		if ($type == 'edit')
		{
			$DB->update(TABLE_PREFIX . 'cron', $save, 'cronid=' . input::int('id'));
			$forums->main_msg = $forums->lang['cronedited'];
		}
		else
		{
			$DB->insert(TABLE_PREFIX . 'cron', $save);
			$forums->main_msg = $forums->lang['cronsaved'];
		}
		$this->functions->save_next_run();
		$this->show_crons();
	}

	function cronform($type = 'edit')
	{
		global $forums, $DB;
		if ($type == 'edit')
		{
			$id = input::int('id');
			$cron = $DB->queryFirst("SELECT * FROM " . TABLE_PREFIX . "cron WHERE cronid=" . $id . "");
			$button = $forums->lang['doedited'];
			$code = 'doeditcron';
			$title = $forums->lang['editcron'] . ': ' . $cron['title'];
		}
		else
		{
			$cron = array();
			$button = $forums->lang['addcron'];
			$code = 'doaddcron';
			$title = $forums->lang['addnewcron'];
		}
		$dd_minute = array(0 => array('-1', $forums->lang['everyminute']));
		$dd_hour = array(0 => array('-1', $forums->lang['everyhour']), 1 => array('0', '0 - ' . $forums->lang['morning']));
		$dd_weekday = array(0 => array('-1', $forums->lang['everyday']));
		$dd_monthday = array(0 => array('-1', $forums->lang['everyday']));
		$dd_month = array(0 => array('-1', $forums->lang['everymonth']));
		for($i = 0 ; $i < 60; $i++)
		{
			$dd_minute[] = array($i, $i);
		}
		for($i = 1 ; $i < 24; $i++)
		{
			if ($i < 12)
			{
				$ampm = $forums->lang['am'] . $i . ' ' . $forums->lang['dot'];
			}
			else if ($i == 12)
			{
				$ampm = $forums->lang['noon'];
			}
			else
			{
				$ampm = $forums->lang['pm'] . $i - 12 . ' ' . $forums->lang['dot'];
			}
			$dd_hour[] = array($i, $i . ' - (' . $ampm . ')');
		}
		for($i = 1 ; $i < 32; $i++)
		{
			$dd_monthday[] = array($i, $i);
		}
		$dd_weekday[] = array('0', $forums->lang['sunday']);
		$dd_weekday[] = array('1', $forums->lang['monday']);
		$dd_weekday[] = array('2', $forums->lang['tuesday']);
		$dd_weekday[] = array('3', $forums->lang['wednesday']);
		$dd_weekday[] = array('4', $forums->lang['thursday']);
		$dd_weekday[] = array('5', $forums->lang['friday']);
		$dd_weekday[] = array('6', $forums->lang['saturday']);
		$forums->admin->print_form_header(array(1 => array('do', $code), 2 => array('id', $id), 3 => array('cronhash', $cron['cronhash'])));
		$input = "<input type='text' name='showcron' class='button' size='40' style='font-size:12px;width:auto;font-weight:blod'/>";
		$forums->admin->columns[] = array("&nbsp;" , "60%");
		$forums->admin->columns[] = array("&nbsp;" , "40%");
		$forums->admin->print_table_start("$title", "", "<div style='float:right'>$input&nbsp;</div>");
		$forums->admin->print_cells_row(array("<strong>" . $forums->lang['crontitle'] . "</strong>",
				$forums->admin->print_input_row('title', input::get('title', $cron['title']))
				));
		$forums->admin->print_cells_row(array("<strong>" . $forums->lang['crondesc'] . "</strong>",
				$forums->admin->print_input_row('description', input::get('description', $cron['description']))
				));
		$forums->admin->print_cells_row(array("<strong>" . $forums->lang['cronfile'] . "</strong><div class='description'>" . $forums->lang['cronfiledesc'] . "</div>",
				"./includes/cron/ " . $forums->admin->print_input_row('filename', input::get('filename', $cron['filename']), '', '', 20)
				));
		$forums->admin->print_cells_row(array("<strong>" . $forums->lang['cronminute'] . "</strong><div class='description'>" . $forums->lang['cronminutedesc'] . "</div>",
				$forums->admin->print_input_select_row('minute', $dd_minute, input::get('minute', $cron['minute']), 'onchange="updatepreview()"')
				));
		$forums->admin->print_cells_row(array("<strong>" . $forums->lang['cronhour'] . "</strong><div class='description'>" . $forums->lang['cronhourdesc'] . "</div>",
				$forums->admin->print_input_select_row('hour', $dd_hour, input::get('hour', $cron['hour']), 'onchange="updatepreview()"')
				));
		$forums->admin->print_cells_row(array("<strong>" . $forums->lang['cronweek'] . "</strong><div class='description'>" . $forums->lang['cronweekdesc'] . "</div>",
				$forums->admin->print_input_select_row('weekday', $dd_weekday, input::get('weekday', $cron['weekday']), 'onchange="updatepreview()"')
				));
		$forums->admin->print_cells_row(array("<strong>" . $forums->lang['cronmonth'] . "</strong><div class='description'>" . $forums->lang['cronmonthdesc'] . "</div>",
				$forums->admin->print_input_select_row('monthday', $dd_monthday, input::get('monthday', $cron['monthday']), 'onchange="updatepreview()"')
				));
		$forums->admin->print_cells_row(array("<strong>" . $forums->lang['cronlog'] . "</strong><div class='description'>" . $forums->lang['cronlogdesc'] . "</div>",
				$forums->admin->print_yes_no_row('loglevel', input::get('loglevel', $cron['loglevel']))
				));
		$forums->admin->print_cells_row(array("<strong>" . $forums->lang['cronenabled'] . "</strong><div class='description'>" . $forums->lang['cronenableddesc'] . "</div>",
				$forums->admin->print_yes_no_row('enabled', input::get('enabled', $cron['enabled']))
				));
		$forums->admin->print_table_footer();
		echo "<div style='tableborder'><div align='center' class='pformstrip'><input type='submit' value='$button' class='button' /></div></div></form>";
		$forums->admin->print_cp_footer();
	}

	function show_crons()
	{
		global $forums, $DB, $bboptions;
		$forums->admin->checkdelete();
		$forums->admin->print_form_header(array(1 => array('do' , 'addcron')));
		$forums->admin->columns[] = array($forums->lang['title'], "48%");
		$forums->admin->columns[] = array($forums->lang['nextrun'], "17%");
		$forums->admin->columns[] = array($forums->lang['minutes'], "5%");
		$forums->admin->columns[] = array($forums->lang['hours'], "5%");
		$forums->admin->columns[] = array($forums->lang['weeks'], "5%");
		$forums->admin->columns[] = array($forums->lang['months'], "5%");
		$forums->admin->columns[] = array($forums->lang['option'], "25%");
		$forums->admin->print_table_start($forums->lang['managecron']);
		$DB->query("SELECT * FROM " . TABLE_PREFIX . "cron ORDER BY enabled, nextrun ASC");
		while ($row = $DB->fetch())
		{
			$row['minute'] = $row['minute'] != '-1' ? $row['minute'] : '-';
			$row['hour'] = $row['hour'] != '-1' ? $row['hour'] : '-';
			$row['monthday'] = $row['monthday'] != '-1' ? $row['monthday'] : '-';
			$row['weekday'] = $row['weekday'] != '-1' ? $row['weekday'] : '-';
			if (TIMENOW > $row['nextrun'])
			{
				$image = 'cron_run_now.gif';
			}
			else
			{
				$image = 'cron_run.gif';
			}
			$class = "";
			$title = "";
			$next_run = $forums->func->get_date($row['nextrun'], 2, 1);
			if ($row['enabled'] != 1)
			{
				$class = " class='description'";
				$title = " (" . $forums->lang['stopcron'] . ")";
				$image = 'cron_stop.gif';
				$next_run = "<span class='description'><s>$next_run</s></span>";
			}
			$deletebutton = "<input type='button' class='button' value='" . $forums->lang['delete'] . "' onclick='checkdelete(\"cronadmin.php\",\"do=delete&amp;id={$row['cronid']}\")' />";
			$editbutton = $forums->admin->print_button($forums->lang['edit'], "cronadmin.php?{$forums->sessionurl}do=edit&amp;id=" . $row['cronid'], 'button');
			$forums->admin->print_cells_row(array("<table cellpadding='0' cellspacing='0' border='0' width='100%'>
																	<tr>
																	 <td width='99%'>
																	  <strong{$class}>{$row['title']}{$title}</strong><div class='description'>{$row['description']}</div>
																	 </td>
																	 <td width='1%' nowrap='nowrap'>
																	   <a href='cronadmin.php?{$forums->sessionurl}do=run&amp;id={$row['cronid']}' title='" . $forums->lang['runcron'] . " (id: {$row['cronid']})'><img src='{$forums->imageurl}/$image'  border='0' alt='" . $forums->lang['runcron'] . "' /></a>
																	 </td>
																	</tr>
																	</table>",
					"<center>" . $next_run . "</center>",
					"<center>" . $row['minute'] . "</center>",
					"<center>" . $row['hour'] . "</center>",
					"<center>" . $row['monthday'] . "</center>",
					"<center>" . $row['weekday'] . "</center>",
					"<center>{$editbutton} {$deletebutton}</center>"
					));
		}
		$forums->admin->print_form_submit($forums->lang['addnewcron']);
		$forums->admin->print_table_footer();
		$forums->admin->print_form_end();
		$forums->admin->print_cp_footer();
	}
}

$output = new cronadmin();
$output->show();

?>