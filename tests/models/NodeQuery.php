<?php
/**
 * @link https://github.com/paulzi/yii2-nested-sets
 * @copyright Copyright (c) 2015 PaulZi <pavel.zimakoff@gmail.com>
 * @license MIT (https://github.com/paulzi/yii2-nested-sets/blob/master/LICENSE)
 */

namespace paulzi\nestedsets\tests\models;

use paulzi\nestedsets\NestedSetsQueryTrait;

/**
 * @author PaulZi <pavel.zimakoff@gmail.com>
 */
class NodeQuery extends \yii\db\ActiveQuery
{
    use NestedSetsQueryTrait;
}