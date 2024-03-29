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
class db
{
	private static $db = array();

	public static function __callstatic($name, $args)
	{
		return self::get($name, $args[0]);
	}

	public static function get($name, $config)
	{
		if (!isset(self::$db[$name]))
		{
			if (empty($config))
			{
				return null;
			}

			$classname = 'Db_' . ucfirst($config['type']);
			self::$db[$name] = new $classname($config);

			$config['prefix'] = isset($config['prefix']) ? $config['prefix'] : '';
			if ($name == 'base')
			{
				define('TABLE_PREFIX', $config['prefix']);
			}
			else
			{
				define('TABLE_PREFIX_' . strtoupper($name), $config['prefix']);
			}
		}

		return self::$db[$name];
	}

	public static function slave($master, $slave)
	{
		if (!isset(self::$db[$master]) || self::$db[$slave])
		{
			return;
		}

		self::$db[$master]->setSlave(self::$db[$slave]);
	}

	public static function close($name = '')
	{
		if ($name && isset(self::$db[$name]))
		{
			self::$db[$name]->close();
			unset(self::$db[$name]);
		}
		else if (!empty(self::$db))
		{
			foreach (self::$db as $k => $db)
			{
				$db->close();
			}
			self::$db[$k] = array();
		}
	}
}