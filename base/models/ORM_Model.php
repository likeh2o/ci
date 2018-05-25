<?php

require_once __DIR__ . '/Db_Model.php';
require_once __DIR__ . '/ORM_TableJoin.php';
require_once __DIR__ . '/ORM_Exception.php';

class ORM_Model extends Db_Model
{
    private $dsnType       = '';
    private $dbConfig      = '';
    private $_table         = '';
    private $queryFields   = array();
    private $conditions    = array();
    private $bindParams    = array();
    private $orderBy       = array();
    private $groupBy       = array();
    private $joins         = array();
    private $preTable      = '';
    private $insertColumns = array();
    private $insertValues  = array();
    private $updateColumns = array();
    private $debug         = false;
    private $lockRowSql    = null;


    public function __construct()
    {
        parent::__construct();
        $this->dsnType = Pdo_Mysql::DSN_TYPE_SLAVE;
    }

    private function clearAll()
    {
        $this->dsnType       = Pdo_Mysql::DSN_TYPE_SLAVE;
        $this->queryFields   = array();
        $this->conditions    = array();
        $this->bindParams    = array();
        $this->groupBy       = array();
        $this->orderBy       = array();
        $this->joins         = array();
        $this->preTable      = '';
        $this->insertColumns = array();
        $this->insertValues  = array();
        $this->updateColumns = array();
        $this->debug         = false;
        $this->lockRowSql    = null;
    }

    public function lockRow($lockRowSql = ' FOR UPDATE ')
    {
        $this->lockRowSql = $lockRowSql;

        return $this;
    }

    public function setDebug($debug)
    {
        $this->debug = $debug;

        return $this;
    }

    public function get_db()
    {
        return $this->dbConfig;
    }

    protected function addJoin($table, $joinType)
    {
        if (!array_key_exists($table, $this->joins)) {
            $this->joins[$table] = new TableJoin($this->_table, $table, $joinType);
            $this->preTable      = $table;
        }

        return $this;
    }

    protected function addJoinCondition($leftColumn, $rightColumn)
    {
        if (empty($this->preTable)) {
            return $this;
        }

        if (!array_key_exists($this->preTable, $this->joins)) {
            return $this;
        }

        $this->joins[$this->preTable]->addJoinCondition($leftColumn, $rightColumn);

        return $this;
    }

    protected function setDsnType($dsnType)
    {
        $this->dsnType = $dsnType;

        return $this;
    }

    protected function setDBConfig($dbConfig)
    {
        $this->dbConfig = $dbConfig;

        return $this;
    }

    /**
     * @param $table
     *
     * @return ORM_Model
     */
    protected function setTable($table)
    {
        $this->_table = $table;

        return $this;
    }

    /**
     * @param      $fields  array | string(split by ',')
     *                      eg:
     *                      array('pk_order', 'ctime')
     *                      'pk_order, ctime'
     *
     * @param null $table   default current set table
     *
     * @return \ORM_Model
     * @throws \ORM_Exception
     */
    protected function addQueryFields($fields, $table = null)
    {
        if (!is_array($fields) && !is_string($fields)) {
            ORM_Exception::getInstance()->throwException(
                __METHOD__ . ' fields' . ORM_Exception::ERR_MSG_MUST_INT_TYPE_ARRAY_STRING
            );
        }

        if (empty($table)) {
            $table = $this->_table;
        }

        if (is_array($fields)) {
            $this->queryFields = array_merge($this->queryFields, $this->addDataPrefix($fields, $table));

            return $this;
        }

        if (is_string($fields)) {
            $this->queryFields = array_merge($this->queryFields, $this->addDataPrefix(explode(',', $fields), $table));

            return $this;
        }

        return $this;
    }

    private function addDataPrefix($data, $prefix)
    {
        return array_map(
            function ($d) use ($prefix) {
                $d = trim($d);

                return "$prefix.$d";
            },
            $data
        );
    }

    /**
     * @param array|string $conditions
     * @param null         $value
     * @param string       $operator
     * @param bool         $isExp
     *
     * @return $this
     * @throws \ORM_Exception
     * @eg 01
     * $columns = 单个字段名称
     * $value = 设定后的字段值
     * $operator = 操作符
     * $isExp = 是否表达式
     * addQueryConditions($columns, $value, $operator, $isExp);
     *
     * @eg 02
     * $columns = [
     *      字段名称1 => 字段值1,
     *      字段名称2 => 字段值2,
     * ];
     * $value = null
     * $operator = null
     * addQueryConditions($columns);
     *
     * @eg 03
     * $columns = [
     *     [ 字段名称1， 字段值1，操作符1],
     *     [ 字段名称2， 字段值2，操作符2],
     * ];
     * $value = null
     * $operator = null
     * addQueryConditions($columns);
     *
     */
    protected function addQueryConditions($conditions, $value = null, $operator = '=', $isExp = false)
    {
        if (empty($conditions)) {
            ORM_Exception::getInstance()->throwException(__METHOD__ . ' conditions' . ORM_Exception::ERR_MSG_NOT_EMPTY);
        }

        if (!is_array($conditions) && !is_string($conditions)) {
            ORM_Exception::getInstance()->throwException(
                __METHOD__ . ' conditions' . ORM_Exception::ERR_MSG_MUST_INT_TYPE_ARRAY_STRING
            );
        }

        if (is_string($conditions)) {
            $this->addQueryCondition($conditions, $value, $operator, $isExp);

            return $this;
        }

        foreach ($conditions as $k => $v) {
            if (is_array($v)) {
                $conditions = $v[0];
                $value      = $v[1];
                $operator   = '=';
                $isExp      = false;
                if (!empty($v[2])) {
                    $operator = $v[2];
                }

                if (isset($v[3])) {
                    $isExp = $v[3];
                }

                if($operator == 'in'){
                    $this->addQueryConditionIn($conditions, $value);
                }else{
                    $this->addQueryCondition($conditions, $value, $operator, $isExp);
                }

            } else {
                $this->addQueryCondition($k, $v);
            }
        }

        return $this;
    }

    private function checkColumnNameWithTableNamePrefix($column)
    {
        $cols = explode('.', $column);
        if (count($cols) > 2) {
            ORM_Exception::getInstance()->throwException(
                __METHOD__ . 'column' . ORM_Exception::ERR_MSG_COLUMN_FORMAT_INVALID
            );
        }
        if (count($cols) == 1) {
            return "`$column`";
        }

        return $cols[0] . ".`{$cols[1]}`";
    }

    /**
     * @param        $column
     * @param        $value
     * @param string $operator
     * @param bool   $isExp
     *
     * @return $this
     * @throws \ORM_Exception
     * @example  ('id', 1, >)
     */
    private function addQueryCondition($column, $value, $operator = '=', $isExp = false)
    {
        if (empty($column)) {
            ORM_Exception::getInstance()->throwException(__METHOD__ . ' column' . ORM_Exception::ERR_MSG_NOT_EMPTY);
        }

        $column_sql_name = $this->checkColumnNameWithTableNamePrefix($column);
        if (true === $isExp) {
            $this->conditions[] = "$column_sql_name $operator $value";

            return $this;
        }

        $bindKey = ':' . str_replace('.', '_', $column);
        if (array_key_exists($bindKey, $this->bindParams)) {
            $bindKey .= '_' . microtime(true) * 10000;
        }

        $this->conditions[]         = "$column_sql_name $operator $bindKey";
        $this->bindParams[$bindKey] = $value;

        return $this;
    }

    /**
     * @param      $conditions
     *              array(
     *              'ctime' => 'desc',
     *              'id' => 'asc',
     *              )
     *
     * @param null $order
     *
     * @return $this
     * @throws \ORM_Exception
     */
    protected function addOrderBy($conditions, $order = null)
    {
        if (empty($conditions)) {
            ORM_Exception::getInstance()->throwException(__METHOD__ . ' conditions' . ORM_Exception::ERR_MSG_NOT_EMPTY);
        }

        if (!is_array($conditions) && !is_string($conditions)) {
            ORM_Exception::getInstance()->throwException(
                __METHOD__ . ' conditions' . ORM_Exception::ERR_MSG_MUST_INT_TYPE_ARRAY_STRING
            );
        }

        if (is_string($conditions)) {
            if (empty($order)) {
                ORM_Exception::getInstance()->throwException(__METHOD__ . ' order' . ORM_Exception::ERR_MSG_NOT_EMPTY);
            }
            $this->addOrderByCondition($conditions, $order);

            return $this;
        }

        foreach ($conditions as $k => $v) {
            if (empty($v)) {
                ORM_Exception::getInstance()->throwException(
                    __METHOD__ . ' conditions[' . $k . ']' . ORM_Exception::ERR_MSG_NOT_EMPTY
                );
            }

            $this->addOrderByCondition($k, $v);
        }

        return $this;
    }

    private function addOrderByCondition($column, $order)
    {
        $column = $this->checkColumnNameWithTableNamePrefix($column);

        $this->orderBy[] = "$column $order";

        return $this;
    }

    /**
     * @param $conditions
     *        array: array('a', 'b')
     *        string: 'a,b'
     *
     * @return $this
     * @throws \ORM_Exception
     */
    protected function addGroupBy($conditions)
    {
        if (empty($conditions)) {
            ORM_Exception::getInstance()->throwException(__METHOD__ . ' conditions' . ORM_Exception::ERR_MSG_NOT_EMPTY);
        }

        if (!is_array($conditions) && !is_string($conditions)) {
            ORM_Exception::getInstance()->throwException(
                __METHOD__ . ' conditions' . ORM_Exception::ERR_MSG_MUST_INT_TYPE_ARRAY_STRING
            );
        }

        if (is_array($conditions)) {
            $this->groupBy = array_merge($this->groupBy, $conditions);
        }

        if (is_string($conditions)) {
            $this->groupBy = array_merge($this->groupBy, explode(',', $conditions));
        }

        return $this;
    }

    /**
     * @param int $pageNumber
     * @param int $pageSize
     *
     * @return array|bool    ###########  always return getAll  ##############
     */
    protected function doSelect($pageNumber = 0, $pageSize = 10)
    {
        $sqlData = $this->makeSelectSql($pageNumber, $pageSize);

        if ($sqlData->error < 0) {
            log_message_v2('error', $sqlData);

            return false;
        }

        $dsnType = $sqlData->dsnType;
        if ($this->isTransBegan()) {
            $dsnType = Pdo_Mysql::DSN_TYPE_MASTER;
        }

        return $this->getAll($dsnType, $sqlData->sql, $sqlData->bindParams);
    }

    protected function makeSelectSql($pageNumber, $pageSize)
    {
        $std        = new stdClass();
        $std->error = 0;
        $std->msg   = '';

        $check = $this->preCheck();
        if ($check->error < 0) {
            return $check;
        }

        $fields     = $this->makeQueryFields();
        $conditions = $this->makeConditions();
        $joinSql    = $this->makeJoinSql();

        $sql = sprintf('select %s from %s %s %s', $fields, $this->_table, $joinSql, $conditions);

        $bindParams = $this->bindParams;

        $std->sql        = $sql;
        $std->bindParams = $bindParams;
        $std->dsnType    = $this->dsnType;

        if ($pageNumber > 0) {
            // 如果参数是字符key
            $params_keys = array_keys($std->bindParams);
            if (isset($params_keys[0]) && is_numeric($params_keys[0])) {
                $std->sql .= ' LIMIT ?, ?';
                $std->bindParams[] = ($pageNumber - 1) * $pageSize;
                $std->bindParams[] = (int)$pageSize;
            } else {
                $std->sql .= ' LIMIT :offset, :size';
                $std->bindParams['offset'] = ($pageNumber - 1) * $pageSize;
                $std->bindParams['size']   = (int)$pageSize;
            }
        }

        if (!empty($this->lockRowSql)) {
            $std->sql .= $this->lockRowSql;
        }

        if ($this->debug) {
            log_message_v2('error', $std);
        }

        $this->clearAll();

        return $std;
    }

    private function preCheck()
    {
        $std        = new stdClass();
        $std->error = 0;
        $std->msg   = '';

        if (empty($this->dbConfig)) {
            $std->error = -1;
            $std->msg   = 'dbConfig not set';

            return $std;
        }

        if (empty($this->_table)) {
            $std->error = -2;
            $std->msg   = 'table not set';

            return $std;
        }

        return $std;
    }

    private function makeQueryFields()
    {
        $fields = '*';
        if (!empty($this->queryFields)) {
            $fields = implode(', ', $this->queryFields);
        }

        return $fields;
    }

    private function makeConditions()
    {
        $condition = '';
        if (!empty($this->conditions)) {
            $condition .= ' where ' . implode(' and ', $this->conditions);
        }

        if (!empty($this->groupBy)) {
            $condition .= ' group by ' . implode(',', $this->groupBy);
        }

        if (!empty($this->orderBy)) {
            $condition .= ' order by ' . implode(',', $this->orderBy);
        }

        return $condition;
    }

    private function makeJoinSql()
    {
        if (empty($this->joins)) {
            return '';
        }

        $joinSql = '';
        foreach ($this->joins as $join) {
            $joinSql .= $join->getJoinSql();
        }

        return $joinSql;
    }

    protected function addQueryFieldCalc($function, $column, $alias = null, $table = null)
    {
        if (empty($alias)) {
            $alias = $column;
        }

        if (empty($table)) {
            $table = $this->_table;
        }

        if (!is_string($alias)) {
            ORM_Exception::getInstance()->throwException(
                __METHOD__ . ' alias' . ORM_Exception::ERR_MSG_MUST_INT_TYPE_STRING
            );
        }

        if ($column != '*' && false === strpos($column, '.')) {
            $column = "$table.$column";
        }

        $this->queryFields[] = "$function($column) as $alias";

        return $this;
    }

    /**
     * @param      $column
     * @param null $alias default $column
     * @param null $table default current set table
     *
     * ('pk_order', 'cnt', 't_order')  => count(t_order.pk_order) as cnt
     *
     * @return $this
     */
    protected function addQueryFieldsCount($column, $alias = null, $table = null)
    {
        return $this->addQueryFieldCalc('count', $column, $alias, $table);
    }

    /**
     * @param      $column
     * @param null $alias default $column
     * @param null $table default current set table
     *
     *  ('amount', 'amount', 't_order') => sum(t_order.amount) as amount
     *
     * @return $this
     */
    protected function addQueryFieldsSum($column, $alias = null, $table = null)
    {
        return $this->addQueryFieldCalc('sum', $column, $alias, $table);
    }

    /**
     * @param $column
     * @param $values  array | string(split by ',')
     *
     * @return $this
     * @throws \ORM_Exception
     */
    protected function addQueryConditionIn($column, $values)
    {
        if (empty($column) || empty($values)) {
            ORM_Exception::getInstance()->throwException(
                __METHOD__ . ' both column and vlaues' . ORM_Exception::ERR_MSG_NOT_EMPTY . ' ' . $this->_table . ' ' . json_encode(debug_backtrace()[1])
            );
        }
        if (!is_array($values) && !is_string($values)) {
            ORM_Exception::getInstance()->throwException(
                __METHOD__ . ' values' . ORM_Exception::ERR_MSG_MUST_INT_TYPE_ARRAY_STRING
            );
        }

        if (is_string($values)) {
            $values = explode(',', $values);
        }

        $values = $this->trimArrayData($values);

        $values = implode(',', $values);

        $this->conditions[] = "$column in ($values)";

        return $this;
    }

    private function trimArrayData($data)
    {
        return array_map(
            function ($d) {
                return trim($d);
            }, $data
        );
    }

    /**
     * @param       $columns
     *                  array(
     *                  'id' => 1,
     *                  'name' => 2,
     *                  )
     * @param array $exceptKeys
     *                  when corresponding value was empty, continue insert
     *                  else skip
     *
     *              eg:
     *              array(
     *                  'name'
     *              )
     *
     * @return $this
     * @throws \ORM_Exception
     */
    protected function addInsertColumns($columns, $exceptKeys = array())
    {
        if (empty($columns)) {
            ORM_Exception::getInstance()->throwException(__METHOD__ . ' columns' . ORM_Exception::ERR_MSG_NOT_EMPTY);
        }

        if (!is_array($columns)) {
            ORM_Exception::getInstance()->throwException(
                __METHOD__ . ' columns' . ORM_Exception::ERR_MSG_MUST_INT_TYPE_ARRAY
            );
        }

        foreach ($columns as $k => $v) {
            // if (empty($v) && !in_array($k, $exceptKeys)) {
            //     continue;
            // }

            $this->addInsertColumn($k, $v);
        }

        return $this;
    }

    private function addInsertColumn($column, $value)
    {
        if (empty($column)) {
            ORM_Exception::getInstance()->throwException(__METHOD__ . ' column' . ORM_Exception::ERR_MSG_NOT_EMPTY);
        }

        $this->insertColumns[] = '`' . $column . '`';

        $bindKey = ':' . $column;

        if (array_key_exists($bindKey, $this->bindParams)) {
            $bindKey .= '_' . microtime(true) * 10000;
        }
        $this->insertValues[]       = $bindKey;
        $this->bindParams[$bindKey] = $value;

        return $this;
    }

    protected function doInsert()
    {
        $sqlData = $this->makeInsertSql();
        if ($sqlData->error < 0) {
            log_message_v2('error', $sqlData);

            return false;
        }

        return $this->insert(Pdo_Mysql::DSN_TYPE_MASTER, $sqlData->sql, $sqlData->bindParams);
    }

    protected function makeInsertSql()
    {
        $std        = new stdClass();
        $std->error = 0;
        $std->msg   = '';

        $check = $this->preCheck();
        if ($check->error < 0) {
            return $check;
        }

        $columns = $this->makeInsertColumns();
        $values  = $this->makeInsertValues();

        $std->sql        = sprintf('insert into %s (%s) values (%s)', $this->_table, $columns, $values);
        $std->bindParams = $this->bindParams;

        if ($this->debug) {
            log_message_v2('error', $std);
        }

        $this->clearAll();

        return $std;
    }

    private function makeInsertColumns()
    {
        return implode(',', $this->insertColumns);
    }

    private function makeInsertValues()
    {
        return implode(',', $this->insertValues);
    }

    /**
     * @param mixed  $columns
     * @param null   $value
     * @param string $operator
     *
     * @return $this
     * @throws \ORM_Exception
     * @eg 01
     * $columns = 单个字段名称
     * $value = 设定后的字段值
     * $operator = 操作符
     * addUpdateColumns($columns, $value, $operator);
     *
     * @eg 02
     * $columns = [
     *      字段名称1 => 字段值1,
     *      字段名称2 => 字段值2,
     * ];
     * $value = null
     * $operator = null
     * addUpdateColumns($columns);
     *
     * @eg 03
     * $columns = [
     *     [ 字段名称1， 字段值1，操作符1],
     *     [ 字段名称2， 字段值2，操作符2],
     * ];
     * $value = null
     * $operator = null
     * addUpdateColumns($columns);
     *
     */
    protected function addUpdateColumns($columns, $value = null, $operator = '=')
    {
        if (empty($columns)) {
            ORM_Exception::getInstance()->throwException(__METHOD__ . ' columns' . ORM_Exception::ERR_MSG_NOT_EMPTY);
        }
        if (!is_array($columns) && !is_string($columns)) {
            ORM_Exception::getInstance()->throwException(
                __METHOD__ . ' columns' . ORM_Exception::ERR_MSG_MUST_INT_TYPE_ARRAY_STRING
            );
        }

        if (is_string($columns) && isset($value)) {
            $this->addUpdateColumn($columns, $value, $operator);

            return $this;
        }

        foreach ($columns as $key => $column) {
            if (is_array($column)) {
                $operator = '=';
                if (count($column) == 3) {
                    $operator = $column[2];
                }

                $this->addUpdateColumn($column[0], $column[1], $operator);
            } else {
                $this->addUpdateColumn($key, $column);
            }
        }

        return $this;
    }

    /**
     * @param        $column
     * @param        $value
     * @param string $operator
     *               eg:
     *               ('state', 1) => state = 1
     *               ('number', 1, '+') => number = number + 1
     *
     * @return $this
     * @throws \ORM_Exception
     */
    protected function addUpdateColumn($column, $value, $operator = '=')
    {
        if (empty($column)) {
            ORM_Exception::getInstance()->throwException(__METHOD__ . ' columns' . ORM_Exception::ERR_MSG_NOT_EMPTY);
        }

        if ($operator != '=') {
            $this->updateColumns[] = "`$column` = $column $operator $value";

            return $this;
        }

        $bindKey = ':' . $column;
        if (array_key_exists($bindKey, $this->bindParams)) {
            $bindKey .= '_' . microtime(true) * 10000;
        }

        $this->updateColumns[]      = "`$column` = $bindKey";
        $this->bindParams[$bindKey] = $value;

        return $this;
    }

    protected function doUpdate()
    {
        $sqlData = $this->makeUpdateSql();
        if ($sqlData->error < 0) {
            log_message_v2('error', $sqlData);

            return false;
        }

        return $this->update(Pdo_Mysql::DSN_TYPE_MASTER, $sqlData->sql, $sqlData->bindParams);
    }

    protected function makeUpdateSql()
    {
        $std        = new stdClass();
        $std->error = 0;
        $std->msg   = '';

        $check = $this->preCheck();
        if ($check->error < 0) {
            return $check;
        }

        $columns    = $this->makeUpdateColumns();
        $conditions = $this->makeConditions();

        if (empty($columns)) {
            $std->error = -1;
            $std->msg   = 'update columns empty';

            return $std;
        }

        $std->sql = sprintf('update %s set %s %s', $this->_table, $columns, $conditions);

        $std->bindParams = $this->bindParams;

        if ($this->debug) {
            log_message_v2('error', $std);
        }

        $this->clearAll();

        return $std;
    }

    private function makeUpdateColumns()
    {
        if (empty($this->updateColumns)) {
            return false;
        }

        return implode(',', $this->updateColumns);
    }

    /*** 通用model操作 ***/
    public function dbInsert($insertColumns, $table = ''){
        $table = empty($table)?$this->table:$table;
        return $this->setTable($table)
             ->addInsertColumns($insertColumns)
             ->doInsert();
    }

    public function dbUpdate($updateColumns, $queryConditions, $table = ''){
        $table = empty($table)?$this->table:$table;
        return $this->setTable($table)
            ->addUpdateColumns($updateColumns)
            ->addQueryConditions($queryConditions)
            ->doUpdate();
    }

    public function dbUpdateAndLog($updateColumns, $queryConditions, $logColumns){
        try {
            $this->beginTransaction();

            $affected_rows = $this->dbUpdate($updateColumns, $queryConditions);

            $this->dbInsert($logColumns, $this->table_state_log);

            $this->commit();
            return $affected_rows;
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function dbSelect(
        $queryFields, $queryConditions, $orderBy = array(), $groupBy = array(), 
        $page = 0, $size = 10, $table = ''){

        $table = empty($table)?$this->table:$table;
        $select = $this->setTable($table);
        if(!empty($queryFields)){
            $select = $select->addQueryFields($queryFields);
        }
        if(!empty($queryConditions)){
            $select = $select->addQueryConditions($queryConditions);
        }
        if(!empty($orderBy)){
            $select = $select->addOrderBy($orderBy);
        }
        if(!empty($groupBy)){
            $select = $select->addGroupBy($groupBy);
        }
        if($page){
            return $select->doSelect($page, $size);
        }

        return $select->doSelect();
    }

    public function dbTotal($queryConditions, $groupBy = array(), $table = ''){
        $table = empty($table)?$this->table:$table;
        $select = $this->setTable($table)
                       ->addQueryFieldsCount('*', 'num');
        if(!empty($queryConditions)){
            $select = $select->addQueryConditions($queryConditions);
        }
        if(!empty($groupBy)){
            $select = $select->addGroupBy($groupBy);
        }

        return $select->doSelect();
    }
    /*** end ***/

}
