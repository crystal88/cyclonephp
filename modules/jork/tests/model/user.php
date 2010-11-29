<?php


class Model_User extends JORK_Model_Abstract {


    protected function setup() {
        $this->_schema->table = 't_users';
        $this->_schema->secondary_tables = array('user_contact_info');
        $this->_schema->columns = array(
            'id' => array(
                'type' => 'int',
                'primary' => true,
                'geneneration_strategy' => 'auto'
            ),
            'name' => array(
                'type' => 'string',
                'constraints' => array(
                    'not null' => true,
                    'max_length' => 64
                )
            ),
            'password' => array(
                'type' => 'string',
                'constraints' => array(
                    'not null' => true,
                    'length' => 32
                )
            ),
            'created_at' => array(
                'type' => 'datetime',
                'constraints' => array(
                    'not null' => true
                )
            ),
            'email' => array(
                'type' => 'string',
                'table' => 'user_contact_info',
                'constraints' => array(
                    'max_length' => 128
                )
            ),
            'phone_num' => array(
                'type' => 'string',
                'table' => 'user_contact_info',
                'constraints' => array(
                    'regex' => '/^\d{2}-\d{2}-\d{3}-\d{4}$/'
                )
            )
        );
        $this->_schema->components = array(
            'posts' => array(
                'class' => 'Model_Post',
                'type' => JORK::ONE_TO_MANY,
                'join_column' => 'user_fk'
            )
        );
    }

    public static function inst() {
        return parent::_inst(__CLASS__);
    }
    
}
