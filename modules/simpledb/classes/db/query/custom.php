<?php


class DB_Query_Custom extends DB_Query {

    protected $sql;

    public function  __construct($sql) {
        $this->sql = $sql;
    }

    public function compile($database = 'default') {
        return $this->sql;
    }

    public function exec($database = 'default') {
        DB::inst($database)->exec_custom($this->sql);
    }
}