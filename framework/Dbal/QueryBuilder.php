<?php

namespace T4\Dbal;

use T4\Core\Std;

class QueryBuilder
    extends Std
{

    protected $leftJoin = [];
    protected $rightJoin = [];
    protected $order;
    protected $limitFrom;
    protected $limitCount;

    protected $params = [];

    protected function trim($s)
    {
        return trim($s, " \"'`\t\n\r\0\x0B");
    }

    public function select($select)
    {
        if (is_array($select)) {
            $select = array_map([get_called_class(), 'trim'], $select);
        } else {
            $select = array_map([get_called_class(), 'trim'], preg_split('~[\s]*\,[\s]*~', $select));
        }
        $this->select = array_merge(!empty($this->select) ? $this->select : [], $select);
        $this->mode = 'select';
        return $this;
    }

    public function from($from)
    {
        if (is_array($from)) {
            $from = array_map([get_called_class(), 'trim'], $from);
        } else {
            $from = array_map([get_called_class(), 'trim'], preg_split('~[\s]*\,[\s]*~', $from));
        }
        $this->from = array_merge(!empty($this->from) ? $this->from : [], $from);
        return $this;
    }

    public function leftJoin($table, $on)
    {
        $join = &$this->leftJoin[];
        $join['table'] = $table;
        $join['on'] = $on;
        return $this;
    }

    public function rightJoin($table, $on)
    {
        $join = &$this->rightJoin[];
        $join['table'] = $table;
        $join['on'] = $on;
        return $this;
    }

    public function where($where)
    {
        $this->where = $where;
        return $this;
    }

    public function order($order)
    {
        $this->order = $order;
        return $this;
    }

    public function limit($limit)
    {
        if (empty($limit))
            return $this;

        if (!is_array($limit)) {
            $limit = preg_split('~\,[\s]*~', $limit, -1, \PREG_SPLIT_NO_EMPTY);
        }

        if (count($limit) == 1) {
            $this->limitCount = $limit[0];
        } else {
            $this->limitFrom = $limit[0];
            $this->limitCount = $limit[1];
        }

        return $this;
    }

    public function params($params)
    {
        $this->params = $params;
        return $this;
    }

    public function getQuery()
    {
        /*
         * SELECT statement
         */
        if ( $this->mode == 'select' )
        {
            if (empty($this->select) || empty($this->from)) {
                throw new Exception('SELECT statement must have both \'select\' and \'from\' parts');
            }

            /*
             * SELECT part
             */
            if ( $this->select == '*') {
                $sql = "SELECT *\n";
            } else {
                if (!is_array($this->select)) {
                    $this->select = preg_split('~[\s]\,[\s]*~', $this->select, -1, \PREG_SPLIT_NO_EMPTY);
                }
                // TODO: грамотное экранирование имен полей
                //$sql = "SELECT `" . implode('`, `', $this->select). "`\n";
                $sql = "SELECT " . implode(', ', $this->select). "\n";
            }

            /*
             * FROM part
             */
            $this->from = array_map(function ($x) {
                static $i = 0;
                $i++;
                return $x . ' AS t' . $i;
            }, $this->from);
            // TODO: грамотное экранирование имен таблиц
            //$sql .= "FROM `" . implode('`, `', $this->from). "`\n";
            $sql .= "FROM " . implode(', ', $this->from). "\n";

            /*
             * LEFT JOIN PART
             */
            $this->leftJoin = array_map(function ($x) {
                static $i = 0;
                $i++;
                $x['table'] = $x['table'] . ' AS lj' . $i;
                return $x;
            }, $this->leftJoin);
            foreach ($this->leftJoin as $join) {
                $sql .= "LEFT JOIN " . $join['table'] . " ON " . $join['on'] . "\n";
            }

            /*
             * RIGHT JOIN PART
             */
            $this->rightJoin = array_map(function ($x) {
                static $i = 0;
                $i++;
                $x['table'] = $x['table'] . ' AS rj' . $i;
                return $x;
            }, $this->rightJoin);
            foreach ($this->rightJoin as $join) {
                $sql .= "RIGHT JOIN " . $join['table'] . " ON " . $join['on'] . "\n";
            }

            /*
             * WHERE part
             */
            if (!empty($this->where)) {
                $sql .= "WHERE ".$this->where."\n";
            }

            /*
             * ORDER part
             */
            if (!empty($this->order)) {
                $sql .= "ORDER BY ".$this->order."\n";
            }

            /*
             * LIMIT part
             */
            if (!empty($this->limitFrom) || !empty($this->limitCount)) {
                $sql .= "LIMIT ". (!empty($this->limitFrom) ? intval($this->limitFrom).", ".intval($this->limitCount) : intval($this->limitCount)) ."\n";
            }

            return $sql;
        }
        return '';
    }

    public function getParams()
    {
        return $this->params;
    }

    public function __toString()
    {
        return $this->getQuery();
    }

}