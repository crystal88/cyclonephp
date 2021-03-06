<?php

/**
 * @author Bence Eros <crystal@cyclonephp.com>
 * @package JORK
 */
class JORK_Mapper_Result_Default extends JORK_Mapper_Result {

    /**
     *
     * @var JORK_Query_Select
     */
    private $_jork_query;

    /**
     * @var DB_Query_Result
     */
    private $_db_result;

    /**
     * @var boolean
     */
    private $_has_implicit_root;

    /**
     * @var array<JORK_Mapper_Row>
     */
    private $_root_mappers;

    /**
     * @var array<JORK_Mapper_Row>
     */
    private $_entity_mappers = array();

    /**
     * @var array<JORK_Mapper_Row>
     */
    private $_atomic_mappers = array();

    /**
     * @var array<string>
     */
    private $_atomic_props = array();

    public function  __construct(JORK_Query_Select $jork_query
            , DB_Query_Result $db_result, $has_implicit_root
            , $mappers) {
        $this->_jork_query = $jork_query;
        $this->_db_result = $db_result;
        $this->_has_implicit_root = $has_implicit_root;
        $this->_root_mappers = $mappers;
        $this->extract_mappers($mappers);
    }

    private function extract_mappers($mappers) {
        foreach ($this->_jork_query->select_list as $select_itm) {
            if (array_key_exists('expr', $select_itm)) { // database expression
                $alias = $select_itm['alias'];
                $this->_entity_mappers[$alias] = $mappers[$alias];
                continue;
            }

            $alias = array_key_exists('alias', $select_itm)
                    ? $select_itm['alias']
                    : $select_itm['prop_chain']->as_string();
            
            $prop_chain = $select_itm['prop_chain']->as_array();
            if ($this->_has_implicit_root) {
                $root_mapper = $mappers[NULL];
            } else {
                $root_prop = array_shift($prop_chain);
                $root_mapper = $mappers[$root_prop];
            }
            if (empty($prop_chain)) {
                $itm_mapper = $root_mapper;
            } else {
                list($itm_mapper, $atomic_prop) = $root_mapper
                        ->get_mapper_for_propchain($prop_chain);
                if (FALSE === $atomic_prop) {
                    $this->_entity_mappers[$alias] = $itm_mapper;
                } else {
                    $this->_atomic_mappers[$alias] = $itm_mapper;
                    $this->_atomic_props[$alias] = $atomic_prop;
                }
            }
        }
    }

    public function map() {
        $obj_result = array();
        $prev_row = NULL;
        foreach ($this->_db_result as $row) {
            $is_new_row = FALSE;

            foreach ($this->_root_mappers as $mapper) {
                list($entity, $is_new) = $mapper->map_row($row);
                $is_new_row = $is_new_row || $is_new;
            }

            if ($is_new_row) {
                $obj_result_row = array();
                foreach ($this->_entity_mappers as $alias => $mapper) {
                    $obj_result_row[$alias] = $mapper->get_last_entity();
                }
                foreach ($this->_atomic_mappers as $alias => $mapper) {
                    $last_entity = $mapper->get_last_entity();
                    $obj_result_row[$alias] = NULL === $last_entity
                            ? NULL
                            : $mapper->get_last_entity()
                                ->{$this->_atomic_props[$alias]};
                }
                $obj_result [] = &$obj_result_row;
            }
        }
        return $obj_result;
    }
    
}