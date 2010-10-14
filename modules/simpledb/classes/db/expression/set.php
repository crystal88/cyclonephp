<?php

class DB_Expression_Set implements DB_Expression {

    protected $arr;

    public function  __construct($arr) {
        $this->arr = $arr;
    }


    public function  compile_expr(DB_Adapter $adapter) {
        $escaped_items = array();
        foreach ($this->arr as $itm) {
            $escaped_items []= $adapter->escape_param($itm);
        }
        return '('.implode(', ', $escaped_items).')';
    }
}