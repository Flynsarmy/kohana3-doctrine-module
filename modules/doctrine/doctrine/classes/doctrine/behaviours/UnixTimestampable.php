<?php
class UnixTimestampable extends Doctrine_Template_Timestampable
{
    protected $_options = array(
        'created' =>  array('name'          =>  'created_at',
                            'alias'         =>  null,
                            'type'          =>  'int(10) unsigned',
                            'disabled'      =>  false,
                            'expression'    =>  false,
                            'options'       =>  array('notnull' => true)),
        'updated' =>  array('name'          =>  'updated_at',
                            'alias'         =>  null,
                            'type'          =>  'int(10) unsigned',
                            'disabled'      =>  false,
                            'expression'    =>  false,
                            'onInsert'      =>  true,
                            'options'       =>  array('notnull' => true)));
}
