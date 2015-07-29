<?php
/**
 * @link https://github.com/paulzi/yii2-nested-sets
 * @copyright Copyright (c) 2015 PaulZi <pavel.zimakoff@gmail.com>
 * @license MIT (https://github.com/paulzi/yii2-nested-sets/blob/master/LICENSE)
 */

namespace paulzi\nestedsets;

/**
 * @author PaulZi <pavel.zimakoff@gmail.com>
 */
trait NestedSetsQueryTrait
{
    /**
     * @return \yii\db\ActiveQuery
     */
    public function roots()
    {
        /** @var \yii\db\ActiveQuery $this */
        $class = $this->modelClass;
        $model = new $class;
        return $this->andWhere([$model->leftAttribute => 1]);
    }
}
