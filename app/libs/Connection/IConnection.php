<?php
/**
 * @package PhpBURN
 * @subpackage Connection
 * 
 * @author klederson
 *
 */
interface IConnection
{
	static public function getInstance();

        /**
         * It connect to the Model Package database, if the connection does not exists
         * it create one.
         */
	public function connect();

        /**
         * It close a Model Package connection
         */
	public function close();

        /**
         * Get the state of a Model Package connection
         */
	public function getState();
	
	public function setDatabase($database);
	public function getDatabase();
	
	public function setUser($user);
	public function getUser();

	public function setPassword($password);
	public function getPassword();

	public function setPort($port);
	public function getPort();
	
	public function setHost($host);
	public function getHost();
	
	public function setOptions($options);
	public function getOptions();
	
	public function setOption($name, $val);
	public function getOption($name);
	
	public function getErrorMsg();
	
	public function getTables();
	public function getForeignKeys($tablename);
	public function getServerInfo($type = null);
	public function describe($tablename);
	
	public function executeSQL($sql);
	public function escape($str);
	public function escapeBlob($blob);
	public function affected_rows();
	public function last_id();
	public function num_rows($rs);
	public function random();
	
	public function begin($transactionID=null);
	public function commit($transactionID=null);
	public function rollback($transactionID=null);
	
	public function getEscapeChar();
	
	public function __destruct();
}
?>