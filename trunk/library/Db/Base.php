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
define('INSERT_NORMAL', 0);
define('INSERT_IGNORE', 1);
define('INSERT_DELAYED', 2);
define('SHUTDOWN_QUERY', true);
define('SELECT_DISTINCT', true);
define('NORMAL_QUERY', false);

abstract class Db_Base
{
	private $error = '';
	private $shutdown_queries = array();

	private $transaction = false;
	private $transactions = 0;
	private $cache_prefix = '';
	private $cached = array();
	private $query_result = '';
	private $open_queries = array();

	private $debug = true;
	private $shutdown = false;
	private $cache = NULL;

	private $type = 'mysql';
	private $version = 0;

	protected $query_id = 0;
	protected $connect_id = '';

	protected $table_locked = false;
	protected $sql_buffered;
	protected $any_char;
	protected $one_char;

	protected $config;
	private $count = array(
		'query' => 0,
		'cached' => 0,
	);
	private $sub_class = array(
		'sql' => 'Db_Sql',
		'explain' => 'Db_Explain',
		'cache' => 'Db_Cache',
	);

	public function __construct($config)
	{
		$this->any_char = chr(0) . '%';
		$this->one_char = chr(0) . '_';

		$this->type = strtolower(substr(get_class($this), 3));
		$this->cache_prefix = md5($config['server'] . $config['database']);

		$this->config = $config;

		if (defined('DEVELOPER_MODE') && DEVELOPER_MODE)
		{
			$this->debug = true;
			if (!empty($_GET['explain']))
			{
				define('USE_SHUTDOWN', false);
			}
		}
	}

	public function __destruct()
	{
		foreach ($this->sub_class as $k => $v)
		{
			if (isset($this->$k))
			{
				$this->$k->__destruct();
				unset($this->$k);
			}
		}
	}

	public function __get($name)
	{
		if (isset($this->sub_class[$name]))
		{
			$class = $this->sub_class[$name];
			$this->$name = new $class($this);

			return $this->$name;
		}

		return NULL;
	}

	/**
	 * 执行 INSERT.
	 */
	public function insert($table, $sql, $shutdown = NORMAL_QUERY, $special = INSERT_NORMAL)
	{
		$prefix = 'INSERT';
		switch ($special)
		{
			case INSERT_IGNORE:
				$prefix .= ' IGNORE';
			break;

			case INSERT_DELAYED:
				$prefix .= ' DELAYED';
			break;
		}

		$sql = $this->sql->insert($table, $sql, 'INSERT', $prefix);
		$func = ($shutdown == SHUTDOWN_QUERY) ? 'shutdownQuery' : 'query';
		return $this->$func($sql);
	}

	/**
	 * 多列 INSERT
	 */
	public function insertMulti($table, $array)
	{
		$sql = $this->sql->insert($table, $array, 'MULTI_INSERT');
		return $this->query($sql);
	}

	/**
	 * 多列 INSERT
	 */
	public function insertSelect($table, $array, $from_table, $where)
	{
		$sql = $this->sql->insert($table, $array, 'INSERT_SELECT') . "
			FROM $from_table
			WHERE $where";
		return $this->query($sql);
	}

	/**
	 * 执行 REPLACE.
	 */
	public function replace($table, $array)
	{
		$sql = $this->sql->insert($table, $array, 'INSERT', 'REPLACE');
		return $this->query($sql);
	}

	/**
	 * 多列 REPLACE
	 */
	public function replaceMulti($table, $array)
	{
		$sql = $this->sql->insert($table, $array, 'MULTI_INSERT', 'REPLACE');
		return $this->query($sql);
	}

	/**
	 * 执行 UPDATE.
	 */
	public function update($table, $array, $where = '', $shutdown = NORMAL_QUERY)
	{
		if ($shutdown == SHUTDOWN_QUERY)
		{
			return $this->shutdownQuery(array(
				'TABLE' => $table,
				'ARRAY' => $array,
				'WHERE' => $where,
			));
		}
		else
		{
			$sql = $this->sql->update($table, $array, $where);
			return $this->query($sql);
		}
	}

	/**
	 * 执行 UPDATE, 使用 CASE 语法根据不同的 id_filed 更新不同的值
	 */
	public function updateCase($table, $id_filed, $sql_array)
	{
		$sql = $this->sql->updateCase($table, $id_filed, $sql_array);
		return $this->query($sql);
	}

	/**
	 * 执行 DELETE.
	 */
	public function delete($table, $where = '', $shutdown = NORMAL_QUERY)
	{
		$sql = $this->sql->delete($table, $where);
		$func = ($shutdown == SHUTDOWN_QUERY) ? 'shutdownQuery' : 'query';
		return $this->$func($sql);
	}

	/**
	 * 执行 SELECT
	 *
	 * @param mixed $sql 构造 SELECT 的数组或 SQL 语句
	 */
	public function select($array, $cache_ttl = false, $cache_prefix = '', $distinct = NORMAL_QUERY)
	{
		$sql = $this->sql->select(($distinct == SELECT_DISTINCT) ? 'SELECT DISTINCT' : 'SELECT', $array);

		if (isset($array['LIMIT']))
		{
			return $this->queryLimit($sql, $array['LIMIT'], $cache_ttl, $cache_prefix);
		}
		else
		{
			return $this->query($sql, $cache_ttl, $cache_prefix);
		}
	}

	/**
	 * 执行 SELECT
	 *
	 * @param mixed $sql 构造 SELECT 的数组或 SQL 语句
	 */
	public function selectLimit($sql, $limit = array(), $cache_ttl = false, $cache_prefix = '', $distinct = NORMAL_QUERY)
	{
		$sql = $this->sql->select(($distinct) ? 'SELECT DISTINCT' : 'SELECT', $sql);
		return $this->queryLimit($sql, $limit, $cache_ttl, $cache_prefix);
	}

	/**
	 * 执行 SELECT 读取第一行
	 *
	 * @param mixed $sql 构造 SELECT 的数组或 SQL 语句
	 */
	public function selectFirst($sql, $cache_ttl = false, $cache_prefix = '')
	{
		$sql = $this->sql->select('SELECT', $sql);
		return $this->queryFirst($sql, $cache_ttl, $cache_prefix);
	}

	// shutdown_insert
	// shutdown_update
	// shutdown_delete
	// select_distinct


	/**
	 * 执行 SQL 读取类查询
	 *
	 * @param string $sql SQL 语句
	 * @param mixed $cache_ttl false 不缓存, 数字标示缓存过期时间, 0 表示永久缓存
	 * @param string $cache_prefix
	 * @return unknown
	 */
	public function query($sql, $cache_ttl = false, $cache_prefix = '', $sql_buffered = NULL)
	{
		if (defined('DEBUG_EXTRA'))
		{
			$this->sql_buffered = true;
			$this->explain->start($sql, $this->shutdown);
		}
		else if (is_bool($sql_buffered))
		{
			$this->sql_buffered = $sql_buffered;
		}
		else
		{
			$this->sql_buffered = (strpos($sql, 'SELECT') === 0);
		}

		$this->query_id = false;
		if ($this->sql_buffered && $cache_ttl !== false)
		{
			$cache_prefix = $this->type . '/' . $this->cache_prefix . '/' . $cache_prefix;
			$this->query_id = $this->cache->load($sql, $cache_prefix);
		}

		if ($this->query_id === false)
		{
			if (!$this->connect_id)
			{
				$this->connect();
			}

			if ($this->sql_buffered)
			{
				$this->_query($sql);
			}
			else
			{
				$this->_queryUnbuffered($sql);
			}

			if ($this->query_id === false)
			{
				$this->halt("Query Errors:\n$sql");
			}

			if (defined('DEBUG_EXTRA'))
			{
				$this->explain->stop($sql);
			}

			if ($this->sql_buffered)
			{
				if ($cache_ttl !== false)
				{
					$rowset = array();
					while ($row = $this->fetch($this->query_id))
					{
						$rowset[] = $row;
					}
					$this->freeResult($this->query_id);
					$this->query_id = $this->cache->save($sql, $rowset, $cache_ttl, $cache_prefix);
					unset($row, $rowset);
				}
				else
				{
					$this->open_queries[(int) $this->query_id] = $this->query_id;
				}
			}
			++$this->count['query'];
		}
		else
		{
			$this->cached[(int) $this->query_id] = true;
			$this->count['cached']++;
			if (defined('DEBUG_EXTRA'))
			{
				$this->explain->fromCache($sql);
			}
		}

		$this->shutdown = false;
		return $this->query_id;
	}

	public function queryUnbuffered($sql, $cache_ttl = false, $cache_prefix = '')
	{
		return $this->query($sql, $cache_ttl, $cache_prefix, false);
	}

	/**
	 * 使用 limit 查询
	 *
	 * @param string $sql
	 * @param array/integer $limit
	 * @param integer $cache_ttl
	 * @param string $cache_prefix
	 */
	public function queryLimit($sql, $limit, $cache_ttl = false, $cache_prefix = '')
	{
		$offset = $total = 0;
		if (is_array($limit))
		{
			if (isset($limit[1]))
			{
				$offset = (int) $limit[0];
				$total = (int) $limit[1];
			}
			else
			{
				$total = (int) $limit[0];
			}
		}
		else
		{
			$total = (int) $limit;
		}

		$total = ($total < 0) ? 0 : $total;
		$offset = ($offset < 0) ? 0 : $offset;

		return $this->_queryLimit($sql, $total, $offset, $cache_ttl, $cache_prefix);
	}

	/**
	 * 读取第一行
	 *
	 * @param mixed $sql SQL 语句或者构造 SELECT 的数组
	 */
	public function queryFirst($sql, $cache_ttl = false, $cache_prefix = '')
	{
		$result = $this->_queryLimit($sql, 1, 0, $cache_ttl, $cache_prefix);
		$row = $this->fetch($result);
		$this->freeResult($result);
		return $row;
	}

	public function shutdownQuery($sql = '')
	{
		if (USE_SHUTDOWN)
		{
			if (is_array($sql))
			{
				if (!isset($this->shutdown_queries[$sql['TABLE']]))
				{
					$this->shutdown_queries[$sql['TABLE']] = array();
				}

				if (!isset($this->shutdown_queries[$sql['TABLE']][$sql['WHERE']]))
				{
					$this->shutdown_queries[$sql['TABLE']][$sql['WHERE']] = array();
				}

				$this->shutdown_queries[$sql['TABLE']][$sql['WHERE']] = array_merge($this->shutdown_queries[$sql['TABLE']][$sql['WHERE']], $sql['ARRAY']);
			}
			else
			{
				$this->shutdown_queries[] = $sql;
			}
		}
		else
		{
			$this->shutdown = true;
			$this->queryUnbuffered($sql);
		}
	}

	public function fetch($query_id = '')
	{
		if ($query_id == '')
		{
			$query_id = $this->query_id;
		}

		if (isset($this->cached[(int) $query_id]))
		{
			return $this->cache->fetch($query_id);
		}
		else
		{
			return $this->_fetch($query_id);
		}
	}

	public function numRows($query_id = '')
	{
		if ($query_id == '')
		{
			$query_id = $this->query_id;
		}

		if (isset($this->cached[(int) $query_id]))
		{
			return $this->cache->numRows($query_id);
		}
		else
		{
			return $this->_num_rows($query_id);
		}
	}

	public function freeResult($query_id = '')
	{
		if ($query_id == '')
		{
			$query_id = $this->query_id;
		}

		$key = (int) $query_id;
		if (isset($this->cached[$key]))
		{
			$this->cache->freeResult($key);
			return true;
		}

		if (isset($this->open_queries[$key]))
		{
			unset($this->open_queries[$key]);
			return $this->_freeResult($query_id);
		}
		return false;
	}

	public function fetchFields($query_id = '')
	{
		if ($query_id == '')
		{
			$query_id = $this->query_id;
		}

		$fields = array();
		if (isset($this->cached[$query_id]))
		{
			$fields = $this->cache->fetchFields($query_id);
		}
		else
		{
			while ($field = $this->_fetchField($query_id))
			{
				$fields[] = $field;
			}
		}
		return $fields;
	}

	public function transaction($status = 'begin')
	{
		switch ($status)
		{
			case 'begin':
				// If we are within a transaction we will not open another one, but enclose the current one to not loose data (prevening auto commit)
				if ($this->transaction)
				{
					$this->transactions++;
					return true;
				}

				$result = $this->_transaction('begin');

				if (!$result)
				{
					$this->halt('Transaction begin error');
				}

				$this->transaction = true;
			break;

			case 'commit':
				// If there was a previously opened transaction we do not commit yet... but count back the number of inner transactions
				if ($this->transaction && $this->transactions)
				{
					$this->transactions--;
					return true;
				}

				// Check if there is a transaction (no transaction can happen if there was an error, with a combined rollback and error returning enabled)
				// This implies we have transaction always set for autocommit db's
				if (!$this->transaction)
				{
					return false;
				}

				$result = $this->_transaction('commit');

				if (!$result)
				{
					$this->halt('Transaction commit error');
				}

				$this->transaction = false;
				$this->transactions = 0;
			break;

			case 'rollback':
				$result = $this->_transaction('rollback');
				$this->transaction = false;
				$this->transactions = 0;
			break;

			default:
				$result = $this->_transaction($status);
			break;
		}

		return $result;
	}

	/**
	 * 关闭数据库连接
	 */
	public function close()
	{
		if (!$this->connect_id)
		{
			return false;
		}

		if (!empty($this->shutdown_queries))
		{
			foreach ($this->shutdown_queries as $table => $query)
			{
				if (is_array($query))
				{
					foreach ($query as $where => $array)
					{
						$this->update($table, $array, $where);
					}
				}
				else
				{
					$this->queryUnbuffered($query);
				}
			}
		}
		$this->shutdown_queries = array();

		if ($this->transaction)
		{
			do
			{
				$this->transaction('commit');
			}
			while ($this->transaction);
		}

		if (!empty($this->open_queries))
		{
			foreach ($this->open_queries as $query_id)
			{
				$this->freeResult($query_id);
			}
		}

		$result = $this->_close();
		if ($result)
		{
			$this->connect_id = '';
		}

		return $result;
	}

	/**
	 * 返回执行过的查询数
	 */
	function getCount($type = '')
	{
		$this->count['total'] = array_sum($this->count);

		if (isset($this->count[$type]))
		{
			return $this->count[$type];
		}

		return $this->count;
	}

	function getType()
	{
		return $this->type;
	}

	function halt($message = '')
	{
		global $bbuserinfo;

		if ($bbuserinfo['usergroupid'] == 4 || $this->debug)
		{
			$this->error = $message . "\n" . $this->get_error();
			trigger_error($db->error, E_USER_ERROR);
		}
		else
		{
			global $forums, $bboptions;

			if (empty($bboptions['language']))
			{
				$bboptions['language'] = 'zh-cn';
			}

			if (isset($forums))
			{
				$lang = $forums->func->load_lang('db', true);
			}
			else
			{
				@include(ROOT_PATH . "cache/languages/{$bboptions['language']}/db.php");
			}

			$message = $lang['db_errors'] . ": \n\n";
			$message .= $message . "\n\n";
			$message .= $lang['mysql_errors'] . ': ' . $this->error . "\n\n";
			echo "<html><head><title>{$bboptions['bbtitle']} {$lang['mysql_errors']}</title><style type=\"text/css\"><!--.error { font: 11px tahoma, verdana, arial, sans-serif, simsun; }--></style></head>\r\n<body>\r\n<blockquote><p class=\"error\">&nbsp;</p><p class=\"error\"><strong>{$bboptions['bbtitle']} {$lang['db_found_errors']}</strong><br />\r\n";
			$db_sendmail = sprintf($lang['db_sendmail'], $this->config['email']);
			echo $db_sendmail . "</p>";
			echo "<p class=\"error\">{$lang['db_apologies']}</p>";
			echo "\r\n\r\n</body></html>";
			exit();
		}
	}

	/**
	 * 读取使用数据库缓存的数据
	 */
	public function readCache($name = '')
	{
		$sql_array = array(
			'SELECT' => '`title`, `data`, `is_array`, `time`',
			'FROM' => CACHE_TABLE,
		);

		if (!empty($name))
		{
			$sql_array['WHERE'] = $this->sql->in('title', $name);
		}

		$cache = array();
		$result = $this->select($sql_array);
		while ($row = $this->fetch($result))
		{
			if ($row['time'] == 0 || $row['time'] > TIMENOW)
			{
				if ($row['is_array'])
				{
					$row['data'] = unserialize($row['data']);
				}
				$cache[$row['title']] = $row['data'];
			}
			else
			{
				$cache[$row['title']] = false;
			}
		}
		$this->freeResult($result);

		return $cache;
	}

	/**
	 * 更新使用数据库缓存的数据
	 * array(
	 * 	array('名称', '数据', '存活时间')
	 * )
	 */
	public function updateCache($data, $value = '', $ttl = 0)
	{
		if (!is_array($data))
		{
			$data = array(array($data, $value, $ttl));
		}

		$sql_array = array();
		foreach ($data as $cache)
		{
			if (is_array($cache[1]))
			{
				$data = serialize($cache[1]);
				$is_array = 1;
			}
			else
			{
				$data = $cache[1];
				$is_array = 0;
			}

			$sql_array[] = array(
				'title' => $cache[0],
				'data' => $data,
				'is_array' => $is_array,
				'time' => (isset($cache[2]) && $cache[2] > 0) ? (TIMENOW + $cache[2]) : 0
			);
		}
		return $this->replaceMulti(CACHE_TABLE, $sql_array);
	}

	abstract protected function _query($sql);
	abstract protected function _queryUnbuffered($sql);
	abstract protected function _queryLimit($sql, $total, $offset = 0, $cache_ttl = false, $cache_prefix = '');
	abstract protected function _fetch($query_id);
	abstract protected function _freeResult($query_id);
	abstract protected function _fetchField($query_id);
	abstract protected function _likeExpression($expression);

	abstract public function connect($config);
	abstract public function escape($str);
}