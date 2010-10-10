<?php


class DB_Expression_Helper {

    public static function compile_operand($operand, DB_Adapter $adapter) {
        if ($operand instanceof DB_Expression) {
            return $operand->compile_expr($adapter);
        } else {
            return $adapter->escape_identifier($operand);
        }
    }
}