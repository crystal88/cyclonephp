<?php

/**
 * The base class for all JORK model classes.
 * 
 * @author Bence Eros <crystal@cyclonephp.com>
 * @package JORK
 */
abstract class JORK_Model_Abstract {

    /**
     * Mapping schema should be populated in the implementation of this method.
     *
     * It will only be called when the singleton instance is created. In the
     * method the schema object if accessible via <code>$this->_schema</code>.
     *
     * @usedby JORK_Model_Abstract::_inst()
     */
    protected abstract function setup();

    /**
     * Stores the singleton instances per-class.
     *
     * @var array<JORK_Model_Abstract>
     * @usedby JORK_Model_Abstract::_inst()
     */
    private static $_instances = array();

    /**
     * It should be called only by the subclasses. All subclasses should contain
     * a static method with this code:
     * <code>pubic static function inst() {
     *      return parent::_inst(__CLASS__);
     * }</code>
     *
     * @param string $classname
     * @return JORK_Model_Abstract
     */
    protected static function _inst($classname) {
        if ( ! array_key_exists($classname, self::$_instances)) {
            $inst = new $classname;
            $inst->_schema = new JORK_Mapping_Schema;
            $inst->_schema->class = $classname;
            $inst->setup();
            foreach ($inst->_schema->components as $k => &$v) {
                if (is_string($v)) { // embedded component class found
                    $emb_inst = call_user_func(array($v, 'inst'));
                    $emb_schema = new JORK_Mapping_Schema_Embeddable($inst->_schema, $v);
                    $emb_inst->_schema = $emb_schema;
                    $emb_inst->setup();
                    $emb_schema->table = $inst->_schema->table;
                    $v = $emb_schema;
                }
            }
            self::$_instances[$classname] = $inst;
        }
        return self::$_instances[$classname];
    }

    /**
     * @param string $class
     * @return JORK_Mapping_Schema
     */
    public static function schema_by_class($class) {
        if ( ! array_key_exists($class, self::$_instances)) {
            self::_inst($class);
        }
        return self::$_instances[$class]->_schema;
    }

    /**
     * Gets the mapping schema of the current entity.
     * 
     * @return JORK_Mapping_Schema
     */
    public function schema() {
        if ( ! array_key_exists(get_class($this), self::$_instances)) {
            self::_inst(get_class($this));
        }
        return self::$_instances[get_class($this)]->_schema;
    }

    /**
     * Only to be used by the singleton instance. Other instances should use
     * <code>$this->schema()</code> to get their own mapping schema.
     *
     * @var JORK_Mapping_Schema
     */
    protected $_schema;

    /**
     * Used to store the atomic properties of the entity. All items are 2-item
     * arrays with the following keys:
     * * 'value': the typecasted value of the property
     * * 'persistent': (boolean) determines if the property has been saved since
     * it has been loaded from the database of not. Also FALSE if it hasn't been
     * loaded from the database but it was set by the user.
     *
     * @var array
     */
    protected $_atomics = array();

    /**
     * Used to store the loaded components of the entity. The items are instances
     * of JORK_Model_Abstract (for to-one components) or JORK_Model_Collection
     * (for to-many components).
     *
     * @var array
     */
    protected $_components = array();

    /**
     * Determines if the properties of the entity are all persistent or not.
     * If they are, then there is nothing to do when saving the entity.
     *
     * @var boolean
     */
    protected $_persistent = FALSE;

    /**
     * A set of listeners to be notified when the primary key of the model changes.
     *
     * @var array<JORK_Model_Collection>
     */
    protected $_pk_change_listeners = array();

    /**
     * Indicates if the saving process of the entity has already been started
     * or not. It's used to prevent infinite recursion in the case of saving
     * bidirectional relationships.
     *
     * @var boolean
     */
    protected $_save_in_progress = FALSE;

    /**
     * @return mixed the primary key of the entity
     */
    public function pk() {
        $pk = $this->schema()->primary_key();
        return array_key_exists($pk, $this->_atomics)
                ? $this->_atomics[$pk]['value']
                : NULL;
    }

    public function add_pk_change_listener($listener) {
        $this->_pk_change_listeners []= $listener;
    }

    /**
     * Only for internal usage.
     *
     * Used by <code>JORK_Mapper_Entity::map_row()</code> to initialize the component
     * collections, to be ready when the method calls
     * <code>JORK_Model_Abstract::add_to_component_collections()</code>.
     *
     * @param array $prop_names
     * @usedby JORK_Mapper_Entity::map_row()
     * @see JORK_Model_Abstract::add_to_component_collections()
     */
    public function init_component_collections(&$prop_names) {
        foreach (array_diff_key($prop_names, $this->_components) as $prop => $dummy) {
            if ( ! array_key_exists($prop, $this->_components)) {
                $this->_components[$prop] = array('value' =>
                    JORK_Model_Collection::for_component($this, $prop));
            }
        }
    }

    /**
     * Only for internal usage.
     *
     * Used by <code>JORK_Mapper_Entity::map_row()</code> to quickly load the atomic properties
     * instead of executing <code>JORK_Model_Abstract::__set()</code> each time.
     *
     * @param array<JORK_Model_Abstract> $components
     * @usedby JORK_Mapper_Entity::map_row()
     */
    public function populate_atomics($atomics) {
        $schema = $this->schema();
        foreach ($atomics as $k => $v) {
            $this->_atomics[$k] = array(
                'value' => $this->force_type($v, $schema->atomics[$k]['type']),
                'persistent' => TRUE
            );
        }
    }

    /**
     * Only for internal usage.
     *
     * Used by <code>JORK_Mapper_Entity::map_row()</code> to quickly load the to-one components
     * instead of executing <code>JORK_Model_Abstract::__set()</code> each time.
     *
     * @param array<JORK_Model_Abstract> $components
     * @usedby JORK_Mapper_Entity::map_row()
     */
    public function set_components($components) {
        foreach ($components as $k => $v) {
            $this->_components[$k] = array(
                'value' => $v,
                'persistent' => TRUE
            );
        }
    }

    /**
     * Only for internal usage.
     *
     * Used by <code>JORK_Mapper_Entity::map_row()</code> to quickly load the to-many components
     * instead of executing <code>JORK_Model_Abstract::__set()</code> each time.
     *
     * @param array<JORK_Model_Abstract> $components
     * @usedby JORK_Mapper_Entity::map_row()
     */
    public function add_to_component_collections($components) {
        foreach ($components as $prop_name => $new_comp) {
            $this->_components[$prop_name]['value'][$new_comp->pk()] = $new_comp;
        }
    }

    /**
     *
     * @param string $key
     * @param JORK_Model_Abstract $val
     * @param array $comp_schema
     */
    protected function update_component_fks_reverse($key, $val, $comp_schema) {
        $remote_schema = $val->schema()->components[$comp_schema['mapped_by']];
        switch($remote_schema['type']) {
            case JORK::ONE_TO_MANY:
                $this->_atomics[$remote_schema['join_column']]['value'] = array_key_exists('inverse_join_column', $remote_schema)
                    ? $val->_atomics[$remote_schema['inverse_join_column']]['value']
                    : $val->pk();
                $this->_atomics[$remote_schema['join_column']]['persistent'] = FALSE;
                break;
            case JORK::ONE_TO_ONE:
                $val->_atomics[$remote_schema['join_column']]['value'] = array_key_exists('inverse_join_column', $remote_schema)
                    ? $this->_atomics[$remote_schema['inverse_join_column']]['value']
                    : $this->pk();
                $val->_atomics[$remote_schema['join_column']]['persistent'] = FALSE;
                break;
        }
    }

    /**
     * Updates the foreign keys when the value of a component changes.
     *
     * @param string $key the name of the component
     * @param JORK_Model_Abstract $val
     * @see JORK_Model_Abstract::__set()
     */
    protected function update_component_fks($key, $val) {
        $schema = $this->schema();
        $comp_schema = $schema->components[$key];
        if (array_key_exists('mapped_by', $comp_schema)) {
            $this->update_component_fks_reverse($key, $val, $comp_schema);
            return;
        }
        switch ($comp_schema['type']) {
            case JORK::MANY_TO_ONE:
                $this->_atomics[$comp_schema['join_column']]['value'] = array_key_exists('inverse_join_column', $comp_schema)
                    ? $val->_atomics[$comp_schema['inverse_join_column']]['value']
                    : $val->pk();
                $this->_atomics[$comp_schema['join_column']]['persistent'] = FALSE;
                break;
            case JORK::ONE_TO_ONE:
                $this->_atomics[$comp_schema['join_column']]['value'] = array_key_exists('inverse_join_column', $comp_schema)
                    ? $val->_atomics[$comp_schema['inverse_join_column']]['value']
                    : $val->pk();
                $this->_atomics[$comp_schema['join_column']]['persistent'] = FALSE;
                break;
        }
    }

    /**
     * Magic getter implementation for the entity.
     * 
     * First checks the atomics in
     * the schema, if it finds one then returns the value from this entity, or
     * NULL if not found. Then it checks the components of the schema, and if it
     * founds one with <code>$key</code> then checks if the component exists in
     * the entity or not. If it exists, then it returns it, otherwise it returns
     * NULL or an empty JORK_Model_Collection instance (the latter case happens
     * if the component is a to-many component).
     *
     * If it doesn't find the property in the schema then throws a JORK_Exception.
     *
     * @param string $key
     * @return mixed
     * @throws JORK_Exception
     */
    public function  __get($key) {
        $schema = $this->schema();
        if (array_key_exists($key, $schema->atomics)) {
            return array_key_exists($key, $this->_atomics)
                    ? $this->_atomics[$key]['value']
                    : NULL;
        }
        if (array_key_exists($key, $schema->components)) {
            if (array_key_exists($key, $this->_components))
                // return if the component value is already initialized
                return $this->_components[$key]['value'];
            if ($schema->is_to_many_component($key)) {
                // it's a to-many relation and initialize an
                // empty component collection
                $this->_components[$key] = array(
                    'value' => JORK_Model_Collection::for_component($this, $key)
                );
            } else {
                $this->_components[$key] = array(
                    'persistent' => TRUE, // default NULL must not be persisted
                    'value' => NULL
                );
            }
            return $this->_components[$key]['value'];
                   
        }
        throw new JORK_Exception("class '{$schema->class}' has no property '$key'");
    }

    /**
     * Used to force typecasting of atomic properties. Used when the entity
     * is loaded from the database and when the value of the atomic property
     * is changed.
     *
     * @param mixed $val
     * @param string $type
     * @return mixed
     * @see JORK_Model_Abstract::__set()
     * @see JORK_Model_Abstract::populate_atomics()
     */
    private function force_type($val, $type) {
        if (NULL === $val) {
            return NULL;
        } else {
            // doing type casts
            switch ($type) {
                case 'string':
                    return (string) $val;
                case 'int':
                    return (int) $val;
                case 'float':
                    return (float) $val;
                case 'bool':
                    return (bool) $val;
                case 'datetime':
                    return (string) $val;
                default:
                    throw new JORK_Exception("invalid type for atomic propery '$key' in class '{$schema->class}': '{$schema->atomics[$key]['type']}'.
                    It must be one of the followings: string, int, float, bool, datetime");
            }
        }
    }

    public function __set($key, $val) {
        $schema = $this->schema();
        if (array_key_exists($key, $schema->atomics)) {
            if ( ! array_key_exists($key, $this->_atomics)) {
                $this->_atomics[$key] = array();
            }
            $this->_atomics[$key]['value'] = $this->force_type($val, $schema->atomics[$key]['type']);
            $this->_atomics[$key]['persistent'] = FALSE;
            $this->_persistent = FALSE;
        } elseif (array_key_exists($key, $schema->components)) {
            if ( ! $val instanceof  $schema->components[$key]['class'])
                throw new JORK_Exception("value of {$schema->class}::$key must be an instance of {$schema->components[$key]['class']}");
            if ( ! array_key_exists($key, $this->_components)) {
                $this->_components[$key] = array(
                    'value' => $val,
                    'persistent' => FALSE
                );
                $this->update_component_fks($key, $val);
            } else {
                $this->_components[$key]['value'] = $val;
                $this->_components[$key]['persistent'] = FALSE;
            }
            $this->_persistent = FALSE;
        } else
            throw new JORK_Exception("class '{$schema->class}' has no property '$key'");
    }

    /**
     * The <code>insert()</code> method should be called explicitly called typically
     * in one case: if this entity is in one-to-one relation with an other entity
     * (the owner) and it's joined to the owner by it's primary key, therefore the
     * primary key is set manually instead of being auto-generated. In this case
     * you have to call <code>insert()</code> instead of <code>save()</code> since
     * <code>save()</code> will call <code>update()</code> in this case (since
     * the primary key exists in the entity).
     *
     * The method doesn't do anything if the entity is persistent.
     *
     * @usedby JORK_Model_Abstract::save()
     * 
     */
    public function insert() {
        if ( ! ($this->_persistent  || $this->_save_in_progress)) {
            $this->_save_in_progress = TRUE;

            $schema = $this->schema();
            $insert_sqls = JORK_Query_Cache::inst(get_class($this))->insert_sql();
            $ins_tables = array();
            $values = array();
            $prim_table = NULL;
            foreach ($schema->atomics as $col_name => $col_def) {
                if (array_key_exists($col_name, $this->_atomics)) {
                    if ($this->_atomics[$col_name]['persistent'] == FALSE) {
                        $ins_table = array_key_exists('table', $col_def) 
                                ? $col_def['table']
                                : $schema->table;
                        if ( ! in_array($ins_table, $ins_tables)) {
                            $ins_tables [] = $ins_table;
                        }
                        if ( ! array_key_exists($ins_table, $values)) {
                            $values[$ins_table] = array();
                        }
                        $col = array_key_exists('column', $col_def)
                                ? $col_def['column']
                                : $col_name;
                        $values[$ins_table][$col] = $this->_atomics[$col_name]['value'];

                        // In fact the value is not yet persistent, but we assume
                        // that no problem will happen until the insertions
                        $this->_atomics[$col_name]['persistent'] = TRUE;
                    }
                } elseif (array_key_exists('primary', $col_def)) {
                    // The primary key does not exist in the record
                    // therefore we save the table name for the table
                    // containing the primary key
                    $prim_table = $schema->table_name_for_column($col_name);
                }
            }
            if (NULL === $prim_table) {
                foreach ($values as $tbl_name => $ins_values) {
                    $insert_sqls[$tbl_name]->values = array($ins_values);
                    $insert_sqls[$tbl_name]->exec($schema->db_conn);
                }
            } else {
                foreach ($values as $tbl_name => $ins_values) {
                    $insert_sqls[$tbl_name]->values = array($ins_values);
                    $tmp_id = $insert_sqls[$tbl_name]->exec($schema->db_conn);
                    if ($prim_table == $tbl_name) {
                        $this->_atomics[$schema->primary_key()] = array(
                            'value' => $tmp_id,
                            'persistent' => TRUE
                        );
                        foreach ($this->_pk_change_listeners as $listener) {
                            $listener->notify_pk_creation($this);
                        }
                    }
                }
            }
            // The insert process finished, the entity is now persistent
            $this->_persistent = TRUE;
            $this->_save_in_progress = FALSE;
        }
    }

    /**
     * Typically this method should never be called from outside, just made public
     * for edge-cases.
     *
     * @usedby JORK_Model_Abstract::save()
     */
    public function update() {
        if ( ! ($this->_persistent  || $this->_save_in_progress)) {
            $this->_save_in_progress = TRUE;

            $schema = $this->schema();
            $update_sqls = JORK_Query_Cache::inst(get_class($this))->update_sql();

            $upd_tables = array();
            $values = array();
            foreach ($schema->atomics as $col_name => $col_def) {
                if (array_key_exists($col_name, $this->_atomics)
                        && (FALSE == $this->_atomics[$col_name]['persistent'])) {
                    $tbl_name = array_key_exists('table', $col_def)
                            ? $col_def['table']
                            : $schema->table;
                    if ( ! in_array($tbl_name, $upd_tables)) {
                        $upd_tables []= $tbl_name;
                    }
                    if ( ! array_key_exists($tbl_name, $values)) {
                        $values[$tbl_name] = array();
                    }
                    $col = array_key_exists('column', $col_def)
                                ? $col_def['column']
                                : $col_name;
                    $values[$tbl_name][$col] = $this->_atomics[$col_name]['value'];
                    $this->_atomics[$col_name]['persistent'] = TRUE;
                }
            }

            foreach ($values as $tbl_name => $upd_vals) {
                $update_sqls[$tbl_name]->values = $upd_vals;
                $update_sqls[$tbl_name]->where($schema->primary_key(), '='
                        , DB::esc($this->pk()));
                $update_sqls[$tbl_name]->exec($schema->db_conn);
            }

            // cascade save
            foreach ($this->_components as $comp) {
                $comp['value']->save();
            }

            $this->_persistent = TRUE;
            $this->_save_in_progress = FALSE;
        }
    }

    /**
     * Saves the entity. Performs an SQL INSERT statement if the primary key of
     * the entity is not set, otherwise an SQL UPDATE is executed.
     *
     * The <code>update()</code> and <code>insert()</code> methods are also public,
     * but these should be rarely used.
     *
     * @see JORK_Model_Abstract::insert()
     * @see JORK_Model_Abstract::update()
     */
    public function save() {
        if ($this->pk() === NULL) {
            $this->insert();
        } else {
            $this->update();
        }
    }

    public function delete() {
        $this->delete_by_pk($this->pk());
    }

    public function delete_by_pk($pk) {
        if ($pk === NULL)
            return;

        $schema = $this->schema();
        $delete_sqls = JORK_Query_Cache::inst(get_class($this))->delete_sql();
        $pk = new DB_Expression_Param($pk);
        foreach ($delete_sqls as $del_stmt) {
            $del_stmt->conditions[0]->right_operand = $pk;
            $del_stmt->exec($schema->db_conn);
        }

        foreach ($schema->components as $comp_name => $comp_def) {
            if (array_key_exists('on_delete', $comp_def)) {
                $on_delete = $comp_def['on_delete'];
                if (JORK::CASCADE === $on_delete) {
                    
                    //$component['value']->delete();
                } elseif (JORK::SET_NULL == $on_delete) {
                    if ($schema->is_to_many_component($comp_name)) {
                        // to-many component
                        if ( ! array_key_exists($comp_name, $this->_components)) {
                            $this->_components[$comp_name] = array(
                                'value' => JORK_Model_Collection::for_component($this, $comp_name)
                            );
                        }
                        $this->_components[$comp_name]['value']->notify_owner_deletion($pk);
                    } elseif (array_key_exists('mapped_by', $comp_def)) {
                        // we handle reverse one-to-one components here
                        $remote_class_schema = self::schema_by_class($comp_def['class']);
                        if (JORK::ONE_TO_ONE == $remote_class_schema
                                ->components[$comp_def['mapped_by']]['type']) {
                            $this->set_null_fk_for_reverse_one_to_one($remote_class_schema
                                    , $comp_def, $pk);
                        }
                    }
                }
            } 
        }
    }

    private function set_null_fk_for_reverse_one_to_one(JORK_Mapping_Schema $remote_class_schema
            , $comp_def, DB_Expression_Param $pk) {
        $remote_comp_schema = $remote_class_schema
                                ->components[$comp_def['mapped_by']];
        $schema = $this->schema();

        $upd_stmt = new DB_Query_Update;

        $remote_atomic_schema = $remote_class_schema->atomics[$remote_comp_schema['join_column']];

        $remote_join_col = array_key_exists('column', $remote_atomic_schema) ? $remote_atomic_schema['column'] : $remote_comp_schema['join_column'];

        $upd_stmt->values = array(
            $remote_join_col => NULL
        );

        $local_join_atomic = array_key_exists('inverse_join_column'
                        , $remote_comp_schema) ? $remote_comp_schema['join_column'] : $schema->primary_key();

        $local_join_col = array_key_exists('column'
                        , $schema->atomics[$local_join_atomic]) ? $schema->atomics[$local_join_atomic]['column'] : $local_join_atomic;

        $upd_stmt->table = array_key_exists('table', $remote_atomic_schema) ? $remote_atomic_schema['table'] : $remote_class_schema->table;

        if ($local_join_atomic == $schema->primary_key()) {
            // we are simply happy, the primary key is the
            // join column and we have it
            $local_join_cond = $pk;
        } else {
            // the local join column is not the primary key
            if (array_key_exists($local_join_atomic, $this->_atomics)) {
                // but if it's loaded then we are still happy
                $local_join_cond = new DB_Expression_Param($this->_atomics[$local_join_atomic]);
            } else {
                // otherwise we have to create a subselect to
                // get the value of the local join column based on the primary key
                // and we hope that the local join column is unique
                $local_join_cond = new DB_Query_Select;
                $local_join_cond->columns = array($local_join_col);
                $local_join_cond->tables = array(
                    array_key_exists('table', $schema->atomics[$local_join_atomic]) ? $schema->atomics[$local_join_atomic]['table'] : $schema->table
                );
                $local_join_cond->where_conditions = array(
                    new DB_Expression_Binary($schema->primary_key()
                            , '=', $pk)
                );
            }
        }

        $upd_stmt->conditions = array(
            new DB_Expression_Binary($remote_join_col, '=', $local_join_cond)
        );

        $upd_stmt->exec($schema->db_conn);
    }

}
