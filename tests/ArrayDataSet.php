<?php
/**
 * @link https://github.com/paulzi/yii2-nested-sets
 * @copyright Copyright (c) 2015 PaulZi <pavel.zimakoff@gmail.com>
 * @license MIT (https://github.com/paulzi/yii2-nested-sets/blob/master/LICENSE)
 */

namespace paulzi\nestedsets\tests;

/**
 * @author PaulZi <pavel.zimakoff@gmail.com>
 */
class ArrayDataSet extends \PHPUnit_Extensions_Database_DataSet_ArrayDataSet
{
    public function __construct(array $data)
    {
        foreach ($data as $tableName => $rows) {
            $columns = array();
            if (isset($rows['columns'])) {
                $columns = $rows['columns'];
                unset($rows['columns']);
            } elseif (isset($rows[0])) {
                $columns = array_keys($rows[0]);
            }

            $metaData = new \PHPUnit_Extensions_Database_DataSet_DefaultTableMetaData($tableName, $columns);
            $table    = new \PHPUnit_Extensions_Database_DataSet_DefaultTable($metaData);

            foreach ($rows AS $row) {
                $table->addRow($row);
            }
            $this->tables[$tableName] = $table;
        }
    }
}