<?php
class Pdo_Mysql
{
    const DSN_TYPE_MASTER = "W";
    const DSN_TYPE_SLAVE  = "R";

    /*
     * The default does not support persistent connections.
     * if the application is the backend daemon process, the proposal is set to
     * a persistent connection
     */
    private $persistent = false;

    /*
     * The connection to the database character set
     * The default use utf8
     */
    private $character = 'utf8';

    /*
     * The main node in mysql Master-Slave structure
     */
    private $master = '';

    /**
     * The slave nodes in mysql Master-Slave Structure
     */
    private $slaves = array();

    /**
     * A PDO instance representing a connection to a database
     */
    private $connection = null;

    /**
     * Represents a prepared statement , after the statement is executed
     * an associated result set
     */
    private $statement = null;

    /**
     * Set up the DB connection timeout, 5 seconds by default
     */
    private $timeout = 5;

    /**
     * connection dsn type in current Pdo_Mysql instance
     */
    private $dsntype = null;

    /**
     * hold a private flag to check if a transaction is already started
     */
    private $hasActiveTransaction  = false;

    /**
     *
     */
    public function __construct($config = array())
    {
        if (empty($config["master"])) {
            throw new Exception("Invalid DB config , The master node is empty.");
        }
        
        if (!isset($config["slaves"]) || !is_array($config["slaves"])) {
            throw new Exception("Invalid DB config, The Slaves node is empty or not array .");
        }

        $this->master     = $config["master"];
        $this->slaves     = $config["slaves"];
        $this->character  = $config["character"];
        $this->persistent = $config["persistent"];
        $this->timeout    = $config["timeout"];
    }

    /**
     *
     */
    public function Prepare($dsnType, $query, array $opts=array())
    {
        /*
        // The Pdo_Mysql object can only one prepare operation
        if(!empty($this->connection) && is_object($this->connection)){
            throw new Exception("Are Banned from operation! The PDO connection object already exists.");
        }
        */
      
        if ($this->hasActiveTransaction && (self::DSN_TYPE_SLAVE == $dsnType)) {
            throw new Exception('Are Banned from operation! The DSN Type in transaction must be a master.');
        }
 
        try {
            $this->statement = $this->Connect($dsnType)->prepare($query, $opts);
        } catch (Exception $e) {
            $message = "Prepares a statement for execution faile.";
            throw new Exception($message, 0, $e);
        }

        $this->dsntype = $dsnType;

        return $this;
    }

    /**
     *
     */
    private function Connect($dsnType)
    {
        if (!empty($this->connection) && is_object($this->connection)) {
            return $this->connection;
        }
        // change the query type
        if (self::DSN_TYPE_MASTER == $dsnType) {
            $dsn      = $this->master['dsn'];
            $username = $this->master['user'];
            $password = $this->master['pwd'];
        } elseif (self::DSN_TYPE_SLAVE == $dsnType) {
            $server = $this->slaves[array_rand($this->slaves)];

            $dsn      = $server['dsn'];
            $username = $server['user'];
            $password = $server['pwd'];
        } else {
            throw new Exception("Invalid Connect Type , out of the master/slave structure.");
        }
        
        /**
         * PDO connectioin options
         */
        $connOpts = array();

        $connOpts[PDO::ATTR_TIMEOUT]                  = $this->timeout;
        $connOpts[PDO::ATTR_PERSISTENT]               = $this->persistent;
        $connOpts[PDO::ATTR_ERRMODE]                  = PDO::ERRMODE_EXCEPTION;
        $connOpts[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        $connOpts[PDO::MYSQL_ATTR_INIT_COMMAND]       = "SET NAMES '" . $this->character . "'";

        // create PDO Mysql instance
        try {
            $this->connection = new PDO($dsn, $username, $password, $connOpts);
        } catch (PDOException $e) {
            $message = "Create PDO Mysql instance faild.";
            throw new Exception($message, 0, $e);
        }
       
        // check the PDO driver, if not mysql dsn driver, throw exception
        if (strcasecmp($this->connection->getAttribute(PDO::ATTR_DRIVER_NAME), "mysql") !== 0) {
            throw new Exception("Invalid PDO driver, The Pdo_Mysql class only supports mysql PDO driver.");
        }

        return $this->connection;
    }
    
    /**
     *
     */
    public function BindParams(array $params = array())
    {
        // check PDOStatement
        if (empty($this->statement)) {
            throw new Exception("The binds PHP variables before must first be prepare SQL.");
        }

        // bind params
        foreach ($params as $key=>&$value) {
            // 兼容整数传递
            if (is_int($key)) {
                $key = $key + 1;
            }
            if (!$this->statement->bindParam($key, $value, $this->GetPDOConstantType($value))) {
                throw new Exception("Binds a parameter to the specified variable name faild.");
            }
        }

        return $this;
    }

    /**
     *
     */
    private function GetPDOConstantType($value)
    {
        if (is_numeric($value)) {
            return PDO::PARAM_INT;
        }
        if (is_bool($value)) {
            return PDO::PARAM_BOOL;
        }
        if (is_null($value)) {
            return PDO::PARAM_NULL;
        }

        //Default
        return PDO::PARAM_STR;
    }

    /**
     *
     */
    public function Execute()
    {
        // check PDOStatement
        if (empty($this->statement)) {
            throw new Exception("The execute action before must first be prepared SQL.");
        }

        // Execute Prepared statement
        if (!$this->statement->execute()) {
            throw new PDOException($this->statement->errorInfo(), $this->statement->errorCode());
        }

        return $this;
    }
    
    /**
     *
     */
    public function LastInsertId(&$insertId)
    {
        if (empty($this->connection) || !is_object($this->connection)) {
            throw new Exception("Must prepared Pdo_Mysql instance after that call LastInsertId.");
        }

        if (empty($this->dsntype) || self::DSN_TYPE_MASTER !== $this->dsntype) {
            throw new Exception("At the LastInsertId can only run the master dsn type .");
        }

        $insertId = $this->connection->lastInsertId();

        return $this;
    }

    /**
     *
     */
    public function AffectRows(&$rows)
    {
        // check PDOStatement
        if (empty($this->statement)) {
            throw new Exception("The AffectRow function call before must first be prepare SQL.");
        }
        
        if (empty($this->dsntype) || self::DSN_TYPE_MASTER !== $this->dsntype) {
            throw new Exception("At the AffectRow function can only run the master dsn type(DELETE/INSERT/UPDATE) .");
        }

        $rows = $this->statement->rowCount();

        return $this;
    }

    /**
     *
     */
    public function FetchSingle(&$result, $fetchAssoc=true)
    {
        // check PDOStatement
        if (empty($this->statement)) {
            throw new Exception("The FetchAll function call before must first be prepare SQL.");
        }
        
        /*
        if(empty($this->dsntype) || self::DSN_TYPE_SLAVE !== $this->dsntype){
            throw new Exception("At the FetchAll function can only run the slave dsn type .");
        }
        */

        $fetchStyle = $fetchAssoc ? PDO::FETCH_ASSOC : PDO::FETCH_BOTH;

        $result = $this->statement->fetch($fetchStyle);

        return $this;
    }

    /**
     *
     */
    public function FetchAll(&$result, $fetchAssoc=true)
    {
        // check PDOStatement
        if (empty($this->statement)) {
            throw new Exception("The FetchAll function call before must first be prepare SQL.");
        }
        
        /*
        if(empty($this->dsntype) || self::DSN_TYPE_SLAVE !== $this->dsntype){
            throw new Exception("At the FetchAll function can only run the slave dsn type .");
        }
        */

        $fetchStyle = $fetchAssoc ? PDO::FETCH_ASSOC : PDO::FETCH_BOTH;

        $result = $this->statement->fetchAll($fetchStyle);
        
        return $this;
    }

    /**
     *
     */
    public function BeginTransaction()
    {
        if ($this->hasActiveTransaction ||
            (is_object($this->connection) && $this->connection->inTransaction())) {
            throw new Exception("A transaction is already started.");
        }

        try {
            $this->hasActiveTransaction = $this->Connect(self::DSN_TYPE_MASTER)->beginTransaction();
        } catch (Exception $e) {
            $message = "begin transaction faile.";
            throw new Exception($message, 0, $e);
        }

        return $this;
    }

    /**
     *
     */
    public function Commit()
    {
        if (!$this->hasActiveTransaction || !$this->connection->inTransaction()) {
            throw new Exception("No activity in the transaction.");
        }

        try {
            $this->connection->commit();
            $this->hasActiveTransaction = false;
        } catch (Exception $e) {
            $message = "commit transaction faile.";
            throw new Exception($message, 0, $e);
        }

        return $this;
    }

    /**
     *
     */
    public function RollBack()
    {
        if (!$this->hasActiveTransaction || !$this->connection->inTransaction()) {
            throw new Exception("No activity in the transaction.");
        }

        try {
            $this->connection->rollBack();
            $this->hasActiveTransaction = false;
        } catch (Exception $e) {
            $message = "rollback transaction faile.";
            throw new Exception($message, 0, $e);
        }

        return $this;
    }

    /**
     *
     */
    public function Close()
    {
        if (!empty($this->statement) && is_object($this->statement)) {
            $this->statement->closeCursor();
        }

        $this->statement  = null;
        $this->connection = null;
    }

    /**
     *
     */
    public function __destruct()
    {
        $this->Close();
    }
    
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
    }
}

/*
class CI_Pdo_Mysql extends Pdo_Mysql
{
}
*/
