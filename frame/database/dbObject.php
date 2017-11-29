<?php 
namespace frame\database;

use frame\base\MysqlPool;
use frame\log\Log;
class dbObject extends MysqliDb
{
    public $connkey;
    public $reskey;
    public $argv;
    
    public function __construct($dbConfig)
    {
        parent::__construct($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], $dbConfig['db'], $dbConfig['port']);
        $this->argv['config'] = $dbConfig;
        $this->argv['timeout'] = $dbConfig['pool']['timeout'];
        $this->argv['max'] = $dbConfig['pool']['max'];
        $this->argv['min'] = $dbConfig['pool']['min'];
        $this->argv['db'] = $this;
        if(empty($dbConfig['instance'])) $dbConfig['instance'] = 'default';
        $this->getResource($dbConfig['instance'], $this->argv);
    }
    
    public function connect()
    {
        $dbConfig = $this->argv['config'];
        $mysqli = new \mysqli($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], $dbConfig['db'], $dbConfig['port']);
        if ($mysqli->connect_error)
        {
            Log::error('mysql connect error ' . $mysqli->connect_error);
            throw new \Exception('mysql connect error' . $mysqli->connect_error);
        }
        if ($dbConfig['charset']) {
            $mysqli->set_charset($dbConfig['charset']);
        }
        return $mysqli;
    }

    /**
     * Retry connect
     */
    public function retryConnect()
    {
        $db = MysqlPool::updateConnect($this->connkey, $this->reskey, $this->argv);
        $this->_mysqli = $db;
    }

    public function mysqli()
    {
        if (!$this->_mysqli) {
            $this->getResource($this->connkey, $this->argv);
        }
        return $this->_mysqli;
    }

    /**
     * Pushes a unprepared statement to the mysqli stack.
     * WARNING: Use with caution.
     * This method does not escape strings by default so make sure you'll never use it in production.
     *
     * @author Jonas Barascu
     * @param [[Type]] $query [[Description]]
     */
    private function queryUnprepared($query)
    {
        // Execute query
        $stmt = $this->mysqli()->query($query);

        // Failed?
        if(!$stmt){
            throw new Exception("Unprepared Query Failed, ERRNO: ".$this->mysqli()->errno." (".$this->mysqli()->error.")", $this->mysqli()->errno);
        };

        //retry twice connect
        if ($this->mysqli()->errno == 2013 || $this->mysqli()->errno == 2006)
        {
            $this->retryConnect();
            return $this->queryUnprepared($query);
        }
        // return stmt for future use
        return $stmt;
    }

    /**
     * Method attempts to prepare the SQL query
     * and throws an error if there was a problem.
     *
     * @return mysqli_stmt
     */
    protected function _prepareQuery()
    {
        if (!$stmt = @$this->mysqli()->prepare($this->_query)) {
            //retry twice connect
            if (!$this->_mysqli || !$this->_mysqli->errno || $this->_mysqli->errno == 2013 || $this->_mysqli->errno == 2006)
            {
                $this->retryConnect();
                return $this->_prepareQuery();
            }
            $msg = __METHOD__ . ' error: ' . $this->_mysqli->error . " query: " . $this->_query;
            $num = $this->_mysqli->errno;
            $this->reset();
            throw new \Exception($msg, $num);
        }

        if ($this->traceEnabled) {
            $this->traceStartQ = microtime(true);
        }
        Log::debug(__METHOD__ . ' smt:' . print_r($stmt, true));
        return $stmt;
    }
    
    public function free()
    {
        MysqlPool::freeResource($this->connkey, $this->reskey);
    }

    public function close()
    {
        if ($this->_mysqli) {
            @$this->_mysqli->close();
            $this->_mysqli = null;
        }
    }
    
    private function getResource($connkey, $argv)
    {
        $res = MysqlPool::getResource($connkey, $argv);
        $this->connkey = $connkey;
        if($res['r'] == 0)
        {
            $this->reskey = $res['key'];
            $this->_mysqli = $res['data'];
            try
            {
                $this->_mysqli->real_escape_string("test");
            }
            catch (\Exception $e)
            {
                $this->retryConnect();
            }
        }
        else
        {
            Log::error('get mysql resource error, connection key is ' . $connkey );
            throw new \Exception('get mysql resource error, connection key is ' . $connkey );
        }
    }

    /**
     * Close connection
     *
     * @return void
     */
    public function __destruct()
    {
        return;
        /*if ($this->isSubQuery) {
            return;
        }*/

        /*if ($this->_mysqli) {
            @$this->_mysqli->close();
            $this->_mysqli = null;
        }*/
    }
}