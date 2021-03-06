<?php
PhpBURN::load('Connection.IConnection');
/**
 * This class is responsable for connect the application to the database and perform the queries in a driver-level
 * Than translate the results and resultSets to the application trought Dialect
 * 
 * 
 * This class has been adapted from Lumine 1.0
 * The original idea is from Hugo Ferreira da Silva in Lumine
 * 
 * @package PhpBURN
 * @subpackage Connection
 * 
 * It has been modified and implemented into PhpBURN by:
 * 
 * @author Klederson Bueno Bezerra da Silva
 *
 */
class PhpBURN_Connection_MySQLi implements IConnection
{

	const CLOSED					= 100201;
	const OPEN					= 100202;

	const SERVER_VERSION				= 10;
	const CLIENT_VERSION				= 11;
	const HOST_INFO					= 12;
	const PROTOCOL_VERSION                          = 13;
	const RANDOM_FUNCTION                           = 'rand()';
	
	const ESCAPE_CHAR				= '\\';
	
	protected $_event_types = array(
		'preExecute','posExecute','preConnect','onConnectSucess','preClose','posClose',
		'onExecuteError','onConnectError'
	);
	
	private $conn_id;
	private $database;
	private $user;
	private $password;
	private $port;
	private $host;
	private $options;
	private $state;
	
	public $mode = MYSQL_ASSOC;
	
	private static $instance = null;
	
	static public function getInstance()
	{
		if(self::$instance == null)
		{
			self::$instance = new PhpBURN_Connection_MySQLi();
		}
		
		return self::$instance;
	}
	
	public function connect()
	{
		
		if($this->conn_id && $this->state == self::OPEN)
		{
			mysqli_select_db($this->conn_id,$this->getDatabase());
			return true;
		}
		
		//TODO preConnect actions should be called from here
		
		$hostString = $this->getHost();
		if($this->getPort() != '') 
		{
			$hostString .=  ':' . $this->getPort();
		}
		if(isset($this->options['socket']) && $this->options['socket'] != '')
		{
			$hostString .= ':' . $this->options['socket'];
		}
		$flags = isset($this->options['flags']) ? $this->options['flags'] : null;
					
		if(isset($this->options['persistent']) && $this->options['persistent'] == true)
		{
			$this->conn_id = @mysqli_pconnect($hostString, $this->getUser(), $this->getPassword(), $flags);
		} else {
			$this->conn_id = @mysqli_connect($hostString, $this->getUser(), $this->getPassword(), $flags);
		}
		
		if( !$this->conn_id )
		{
			$this->state = self::CLOSED;
			$msg = '[!Database connection error!]: ' . $this->getDatabase().' - '.$this->getErrorMsg();
			
			PhpBURN_Message::output($msg, PhpBURN_Message::ERROR);			
			return false;
		}
		
		//Selecting database
		mysqli_select_db($this->conn_id,$this->getDatabase());
		$this->state = self::OPEN;
		
		//TODO onConnectSucess actions should be called from here
		
		return true;
	}
	
	public function close()
	{
		//$this->dispatchEvent('preClose', $this);
		if($this->conn_id && $this->state != self::CLOSED)
		{
			$this->state = self::CLOSED;
			mysqli_close($this->conn_id);
		}
		//$this->dispatchEvent('posClose', $this);
	}
	
	public function getState()
	{
		return $this->state;
	}
	
	public function setDatabase($database)
	{
		$this->database = $database;
	}
	
	public function getDatabase()
	{
		return $this->database;
	}
	
	public function setUser($user)
	{
		$this->user = $user;
	}
	public function getUser()
	{
		return $this->user;
	}

	public function setPassword($password)
	{
		$this->password = $password;
	}
	public function getPassword()
	{
		return $this->password;
	}

	public function setPort($port)
	{
		$this->port = $port;
	}
	public function getPort()
	{
		return $this->port;
	}
	
	public function setHost($host)
	{
		$this->host = $host;
	}
	public function getHost()
	{
		return $this->host;
	}
	
	public function setOptions($options)
	{
		$this->options = $options;
	}
	
	public function getOptions()
	{
		return $this->options;
	}
	
	public function setOption($name, $val)
	{
		$this->options[ $name ] = $val;
	}
	
	public function getOption($name)
	{
		if(empty($this->options[$name]))
		{
			return null;
		}
		return $this->options[$name];
	}

	public function getErrorMsg()
	{
		$msg = '';
		if($this->conn_id) 
		{
			$msg = mysqli_connect_error();
		} else {
			$msg = mysqli_connect_error();
		}
		return $msg;
	}
	
	public function getTables()
	{
		if( ! $this->connect() )
		{
			return false;
		}
		
		$rs = $this->executeSQL("show tables");
		
		$list = array();
		
		while($row = mysqli_fetch_row($rs))
		{
			$list[] = $row[0];
		}
		return $list;
	}
	
	public function getForeignKeys($tablename)
	{
		if( ! $this->connect() )
		{
			return false;
		}
                
		
		$fks = array();
                $sql = sprintf("SHOW CREATE TABLE `%s`",$tablename);
		$rs = $this->executeSQL($sql);
		$result = mysqli_fetch_row($rs);
		$result[0] = preg_replace("(\r|\n)",'\n', $result[0]);

                $matches = array();

		preg_match_all('@FOREIGN KEY \(`([a-z,A-Z,0-9,_]+)`\) REFERENCES (`([a-z,A-Z,0-9,_]+)`\.)?`([a-z,A-Z,0-9\.,_]+)` \(`([a-z,A-Z,0-9,_]+)`\)(.*?)(\r|\n|\,)@i', $result[1], $matches);

                for($i=0; $i<count($matches[0]); $i++)
		{
                    $name = $matches[4][$i];
                    if(isset($fks[ $name ]))
                    {
                            $name = $name . '_' . $matches[4][$i];
                    }

                    $fks[ $name ]['thisColumn'] = $matches[1][$i];
                    $fks[ $name ]['thisTable'] = $tablename;
                    $fks[ $name ]['toDatabase'] = !empty($matches[2][$i]) ? preg_replace("([`\.])", '', $matches[2][$i]) : $this->database;
                    $fks[ $name ]['references'] = !empty($matches[3][$i]) ? $matches[3][$i].'.'.$matches[4][$i] : $matches[4][$i];
                    $fks[ $name ]['referencedTable'] = $matches[4][$i];
                    $fks[ $name ]['referencedColumn'] = $matches[5][$i];


                    $reg = array();
                    if(preg_match('@(.*?)ON UPDATE (RESTRICT|CASCADE)@i', $matches[6][$i], $reg))
                    {
                            $fks[ $name ]['onUpdate'] = strtoupper($reg[2]);
                    } else {
                            $fks[ $name ]['onUpdate'] = 'RESTRICT';
                    }
                    if(preg_match('@(.*?)ON DELETE (RESTRICT|CASCADE)@i', $matches[6][$i], $reg))
                    {
                            $fks[ $name ]['onDelete'] = strtoupper($reg[2]);
                    } else {
                            $fks[ $name ]['onDelete'] = 'RESTRICT';
                    }
			
		}
		return $fks;
	}
	
	public function getServerInfo($type = null)
	{
		if($this->conn_id && $this->state == self::OPEN)
		{
			switch($type)
			{
				case self::CLIENT_VERSION:
					return mysqli_get_client_info();
					break;
				case self::HOST_INFO:
					return mysqli_get_host_info($this->conn_id);
					break;
				case self::PROTOCOL_VERSION:
					return mysqli_get_proto_info($this->conn_id);
					break;
				case self::SERVER_VERSION:
				default:
					return mysqli_get_server_info($this->conn_id);
					break;
			}
			return '';
			
		} 
		//throw new PhPBURN_Exception() TODO CREATE EXCETION CLASS AND INPUT AN EXCEPTION HERE;
	}
	
	public function describe($tablename)
	{
            $sql = sprintf("DESCRIBE `%s`", $tablename);
            $rs = $this->executeSQL( $sql );

            $data = array();
            while($row = mysqli_fetch_row($rs))
            {
                $name           = $row[0];
                $type_native    = $row[1];
                if(preg_match('@(\w+)\((\d+)\)@', $row[1], $r))
                {
                    $type       = $r[1];
                    $length     = $r[2];
                } else {
                    $type       = $row[1];
                    $length     = null;
                }

                switch( strtolower($type) )
                {
                    case 'tinyblob': $length = 255; break;
                    case 'tinytext': $length = 255; break;
                    case 'blob': $length = 65535; break;
                    case 'text': $length = 65535; break;
                    case 'mediumblob': $length = 16777215; break;
                    case 'mediumtext': $length = 16777215; break;
                    case 'longblob': $length = 4294967295; break;
                    case 'longtext': $length = 4294967295; break;
                    case 'enum': $length = 65535; break;
                }

                $notnull        = $row[2] == 'YES' ? false : true;
                $primary        = $row[3] == 'PRI' ? true : false;
                $default        = $row[4] == 'NULL' ? null : $row[4];
                $autoincrement  = $row[5] == 'auto_increment' ? true : false;

                $data[] = array($name, $type_native, $type, $length, $primary, $notnull, $default, $autoincrement);
            }
            return $data;
	}
	
	public function executeSQL($sql)
	{
		//$this->dispatchEvent('preExecute', $this, $sql);
		$this->connect();
		$rs = @mysqli_query($sql, $this->conn_id);
		if( ! $rs )
		{	
			$msg = "[!Database error:!] " . $this->getErrorMsg();
			PhpBURN_Message::output($msg, PhpBURN_Message::ERROR);
			return false;
			//$this->dispatchEvent('onExecuteError', $this, $sql, $msg);
		} 
		//$this->close();
		//$this->dispatchEvent('posExecute', $this, $sql);
		return $rs;
	}

        public function unbuffExecuteSQL($sql) {
            //$this->dispatchEvent('preExecute', $this, $sql);
		$this->connect();
		$rs = @mysqli_unbuffered_query($sql, $this->conn_id);
		if( ! $rs )
		{
			$msg = "[!Database error:!] " . $this->getErrorMsg();
			PhpBURN_Message::output($msg, PhpBURN_Message::ERROR);
			return false;
			//$this->dispatchEvent('onExecuteError', $this, $sql, $msg);
		}
		//$this->close();
		//$this->dispatchEvent('posExecute', $this, $sql);
		return $rs;
        }
	
	public function escape($str) 
	{
		if($this->state == self::OPEN)
		{
			return mysqli_real_escape_string($str, $this->conn_id);
		} else {
			return mysqli_escape_string($str);
		}
	}
	
	public function escapeBlob($blob)
	{
		return $this->escape( $blob );
	}
	
	public function escapeBFile($bfile) {
		
	}
	
	public function affected_rows()
	{
		if($this->state == self::OPEN)
		{
			return mysqli_affected_rows($this->conn_id);
		}
		//throw new PhpBURN_Exception() TODO CREATE EXCETION CLASS AND INPUT AN EXCEPTION HERE;
	}
	
	public function num_rows($rs)
	{
		return mysqli_num_rows($rs);
	}
	
	public function random()
	{
		return self::RANDOM_FUNCTION;
	}
	
	public function getEscapeChar()
	{
		return self::ESCAPE_CHAR;
	}
	
	public function fetch($rs) {
		return mysqli_fetch_array($rs, $this->mode);
	}
	
	//Transactions
	public function begin($transactionID=null)
	{
		$this->executeSQL("BEGIN");
	}
	public function commit($transactionID=null)
	{
		$this->executeSQL("COMMIT");
	}
	public function rollback($transactionID=null)
	{
		$this->executeSQL("ROLLBACK");
	}
	
	//Utils
	public function last_id() {
		return mysqli_insert_id($this->conn_id);
	}
	
	public function __destruct() {
		self::close();
	}
}


?>
