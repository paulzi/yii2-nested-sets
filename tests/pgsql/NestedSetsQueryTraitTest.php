<?php
/**
 * @link https://github.com/paulzi/yii2-nested-sets
 * @copyright Copyright (c) 2015 PaulZi <pavel.zimakoff@gmail.com>
 * @license MIT (https://github.com/paulzi/yii2-nested-sets/blob/master/LICENSE)
 */

namespace paulzi\nestedsets\tests\pgsql;

use paulzi\nestedsets\tests\NestedSetsQueryTraitTestCase;

/**
 * @group pgsql
 *
 * @author PaulZi <pavel.zimakoff@gmail.com>
 */
class NestedSetsQueryTraitTest extends NestedSetsQueryTraitTestCase
{
    protected static $driverName = 'pgsql';
}