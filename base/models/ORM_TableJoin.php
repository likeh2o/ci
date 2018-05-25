<?php
class TableJoin
{
    private $leftTable      = '';
    private $rightTable     = '';
    private $joinType       = '';
    private $joinConditions = array();

    public function __construct($leftTable, $rightTable, $joinType)
    {
        $this->leftTable  = $leftTable;
        $this->rightTable = $rightTable;
        $this->joinType   = $joinType;
    }

    public function addJoinCondition($leftColumn, $rightColumn)
    {
        $this->joinConditions[] = "$this->leftTable.$leftColumn = $this->rightTable.$rightColumn";
    }

    public function getJoinSql()
    {
        if (empty($this->joinConditions)) {
            return '';
        }

        return " $this->joinType $this->rightTable on (" . implode(' and ', $this->joinConditions) . ") ";
    }
}
