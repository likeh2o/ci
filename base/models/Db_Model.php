<?php
include_once __DIR__ . '/Pdo_Mysql.php';
class Db_Model extends CI_Model
{
    private $pdo   = null;
    private $trans = false;

    public function __construct()
    {
        parent::__construct();
    }

    protected function isTransBegan()
    {
        return $this->trans;
    }

    public function insert($dsn_type, $sql, $params)
    {
        try {
            $lastid = false;
            $pdo = $this->pdodb();
            $pdo->Prepare($dsn_type, $sql)
                ->BindParams($params)
                ->Execute()
                ->LastInsertId($lastid);
            if (!$this->trans) {
                $pdo->Close();
            }
            return $lastid;
        } catch (Exception $e) {
            $this->throwException($e, $sql);
        }
    }

    private function throwException(Exception $e, $sql)
    {
        $msg  = $e->getMessage() . ',' . $sql;
        $code = $e->getCode();

        $msg = '[' . $code . '] ' . $msg;
        log_message('error', $msg);

        // 错误日志记录log
        $msg = '系统繁忙';

        throw new Exception($msg, Base_Error_Code::ERROR_DB);
    }

    public function update($dsn_type, $sql, $params)
    {
        try {
            $affected = 0;
            $pdo = $this->pdodb();
            $pdo->Prepare($dsn_type, $sql)
                ->BindParams($params)
                ->Execute()
                ->AffectRows($affected);
            if (!$this->trans) {
                $pdo->Close();
            }
            return $affected;
        } catch (Exception $e) {
            $this->throwException($e, $sql);
        }
    }

    public function getSingle($dsn_type, $sql, $params)
    {
        try {
            $result = false;
            $pdo = $this->pdodb();
            $pdo->Prepare($dsn_type, $sql)
                ->BindParams($params)
                ->Execute()
                ->FetchSingle($result);
            if (!$this->trans) {
                $pdo->Close();
            }
            return ($result === false) ? array() : $result;
        } catch (Exception $e) {
            $this->throwException($e, $sql);
        }
    }

    public function getAll($dsn_type, $sql, $params, $page = null, $size = null)
    {
        try {
            $result = false;

            /**
             * 兼容 老式请求
             */
            if ($page > 0) {
                // 如果参数是字符key
                $params_keys = array_keys($params);
                if (isset($params_keys[0]) && is_numeric($params_keys[0])) {
                    $sql .= ' LIMIT ?, ?';
                    $params[] = ($page - 1) * $size;
                    $params[] = (int)$size;
                } else {
                    $sql .= ' LIMIT :offset, :size';
                    $params['offset'] = ($page - 1) * $size;
                    $params['size']   = (int)$size;
                }
            }

            $pdo = $this->pdodb();
            $pdo->Prepare($dsn_type, $sql)
                ->BindParams($params)
                ->Execute()
                ->FetchAll($result);
            if (!$this->trans) {
                $pdo->Close();
            }
            return ($result === false) ? array() : $result;
        } catch (Exception $e) {
            $this->throwException($e, $sql);
        }
    }

    public function delete($dsn_type, $sql, $params)
    {
        try {
            $affected = 0;
            $pdo = $this->pdodb();
            $pdo->Prepare($dsn_type, $sql)
                ->BindParams($params)
                ->Execute()
                ->AffectRows($affected);
            if (!$this->trans) {
                $pdo->Close();
            }
            return $affected;
        } catch (Exception $e) {
            $this->throwException($e, $sql);
        }
    }

    protected function beginTransaction()
    {
        $this->pdodb()
             ->BeginTransaction();
        $this->trans = true;
    }

    private function pdodb()
    {
        if ($this->pdo === null) {
            $db        = $this->get_db();
            $db_config = $this->config->item($db);
            $this->pdo = new Pdo_Mysql($db_config);
        }

        return $this->pdo;
    }

    protected function commit()
    {
        $this->pdodb()
             ->Commit();
        $this->trans = false;
    }

    protected function rollBack()
    {
        $this->pdodb()
             ->RollBack();
        $this->trans = false;
    }
}
