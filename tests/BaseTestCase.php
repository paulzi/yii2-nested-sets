<?php
/**
 * @link https://github.com/paulzi/yii2-nested-sets
 * @copyright Copyright (c) 2015 PaulZi <pavel.zimakoff@gmail.com>
 * @license MIT (https://github.com/paulzi/yii2-nested-sets/blob/master/LICENSE)
 */

namespace paulzi\nestedsets\tests;

use paulzi\nestedsets\tests\migrations\TestMigration;
use Yii;
use yii\db\Connection;

/**
 * @author PaulZi <pavel.zimakoff@gmail.com>
 */
class BaseTestCase extends \PHPUnit_Extensions_Database_TestCase
{
    protected static $driverName = 'sqlite';


    /**
     * @inheritdoc
     */
    public function getConnection()
    {
        return $this->createDefaultDBConnection(Yii::$app->getDb()->pdo);
    }

    /**
     * @inheritdoc
     */
    public function getDataSet()
    {
        return new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(require(__DIR__ . '/data/data.php'));
    }

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        if (Yii::$app->get('db', false) === null) {
            $this->markTestSkipped();
        } else {
            (new TestMigration())->up();
            if (Yii::$app->db->driverName === 'pgsql') {
                Yii::$app->db->createCommand("SELECT setval(pg_get_serial_sequence('tree', 'id'), 25)")->execute();
                Yii::$app->db->createCommand("SELECT setval(pg_get_serial_sequence('multiple_tree', 'id'), 39)")->execute();
            }
            parent::setUp();
        }
    }

    /**
     * @inheritdoc
     */
    public static function setUpBeforeClass()
    {
        $config = require(__DIR__ . '/data/config.php');
        $config = $config[static::$driverName];
        $config['class'] = Connection::className();
        try {
            Yii::$app->set('db', $config);
            Yii::$app->getDb()->open();
        } catch (\Exception $e) {
            Yii::$app->clear('db');
        }
    }
}