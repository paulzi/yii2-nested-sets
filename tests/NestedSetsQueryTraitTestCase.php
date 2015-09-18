<?php
/**
 * @link https://github.com/paulzi/yii2-nested-sets
 * @copyright Copyright (c) 2015 PaulZi <pavel.zimakoff@gmail.com>
 * @license MIT (https://github.com/paulzi/yii2-nested-sets/blob/master/LICENSE)
 */

namespace paulzi\nestedsets\tests;

use paulzi\nestedsets\tests\models\Node;
use paulzi\nestedsets\tests\models\MultipleTreeNode;
use Yii;

/**
 * @author PaulZi <pavel.zimakoff@gmail.com>
 */
class NestedSetsQueryTraitTestCase extends BaseTestCase
{
    public function testRoots()
    {
        $this->assertEquals([1], array_map(function ($value) { return $value->id; }, Node::find()->roots()->all()));
        $this->assertEquals([1, 26], array_map(function ($value) { return $value->id; }, MultipleTreeNode::find()->roots()->all()));
    }
}