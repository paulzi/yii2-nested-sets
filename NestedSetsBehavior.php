<?php
/**
 * @link https://github.com/paulzi/yii2-nested-sets
 * @copyright Copyright (c) 2015 PaulZi <pavel.zimakoff@gmail.com>
 * @license MIT (https://github.com/paulzi/yii2-nested-sets/blob/master/LICENSE)
 */

namespace paulzi\nestedsets;

use Yii;
use yii\base\Behavior;
use yii\base\Exception;
use yii\base\NotSupportedException;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * Nested Sets Behavior for Yii2
 * @author PaulZi <pavel.zimakoff@gmail.com>
 * @author Alexander Kochetov <https://github.com/creocoder>
 *
 * @property ActiveRecord $owner
 */
class NestedSetsBehavior extends Behavior
{
    const OPERATION_MAKE_ROOT       = 1;
    const OPERATION_PREPEND_TO      = 2;
    const OPERATION_APPEND_TO       = 3;
    const OPERATION_INSERT_BEFORE   = 4;
    const OPERATION_INSERT_AFTER    = 5;
    const OPERATION_DELETE_ALL      = 6;


    /**
     * @var string|null
     */
    public $treeAttribute;

    /**
     * @var string
     */
    public $leftAttribute = 'lft';

    /**
     * @var string
     */
    public $rightAttribute = 'rgt';

    /**
     * @var string
     */
    public $depthAttribute = 'depth';

    /**
     * @var string|null
     */
    protected $operation;

    /**
     * @var ActiveRecord|self|null
     */
    protected $node;

    /**
     * @var string
     */
    protected $treeChange;


    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT   => 'beforeInsert',
            ActiveRecord::EVENT_AFTER_INSERT    => 'afterInsert',
            ActiveRecord::EVENT_BEFORE_UPDATE   => 'beforeUpdate',
            ActiveRecord::EVENT_AFTER_UPDATE    => 'afterUpdate',
            ActiveRecord::EVENT_BEFORE_DELETE   => 'beforeDelete',
            ActiveRecord::EVENT_AFTER_DELETE    => 'afterDelete',
        ];
    }

    /**
     * @param int|null $depth
     * @return \yii\db\ActiveQuery
     */
    public function getParents($depth = null)
    {
        $tableName = $this->owner->tableName();
        $condition = [
            'and',
            ['<', "{$tableName}.[[{$this->leftAttribute}]]",  $this->owner->getAttribute($this->leftAttribute)],
            ['>', "{$tableName}.[[{$this->rightAttribute}]]", $this->owner->getAttribute($this->rightAttribute)],
        ];
        if ($depth !== null) {
            $condition[] = ['>=', "{$tableName}.[[{$this->depthAttribute}]]", $this->owner->getAttribute($this->depthAttribute) - $depth];
        }

        $query = $this->owner->find()
            ->andWhere($condition)
            ->andWhere($this->treeCondition())
            ->addOrderBy(["{$tableName}.[[{$this->leftAttribute}]]" => SORT_ASC]);
        $query->multiple = true;

        return $query;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParent()
    {
        $tableName = $this->owner->tableName();
        $query = $this->getParents(1)
            ->orderBy(["{$tableName}.[[{$this->leftAttribute}]]" => SORT_DESC])
            ->limit(1);
        $query->multiple = false;
        return $query;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRoot()
    {
        $tableName = $this->owner->tableName();
        $query = $this->owner->find()
            ->andWhere(["{$tableName}.[[{$this->leftAttribute}]]" => 1])
            ->andWhere($this->treeCondition())
            ->limit(1);
        $query->multiple = false;
        return $query;
    }

    /**
     * @param int|null $depth
     * @param bool $andSelf
     * @param bool $backOrder
     * @return \yii\db\ActiveQuery
     */
    public function getDescendants($depth = null, $andSelf = false, $backOrder = false)
    {
        $tableName = $this->owner->tableName();
        $attribute = $backOrder ? $this->rightAttribute : $this->leftAttribute;
        $condition = [
            'and',
            [$andSelf ? '>=' : '>', "{$tableName}.[[{$attribute}]]",  $this->owner->getAttribute($this->leftAttribute)],
            [$andSelf ? '<=' : '<', "{$tableName}.[[{$attribute}]]",  $this->owner->getAttribute($this->rightAttribute)],
        ];

        if ($depth !== null) {
            $condition[] = ['<=', "{$tableName}.[[{$this->depthAttribute}]]", $this->owner->getAttribute($this->depthAttribute) + $depth];
        }

        $query = $this->owner->find()
            ->andWhere($condition)
            ->andWhere($this->treeCondition())
            ->addOrderBy(["{$tableName}.[[{$attribute}]]" => $backOrder ? SORT_DESC : SORT_ASC]);
        $query->multiple = true;

        return $query;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChildren()
    {
        return $this->getDescendants(1);
    }

    /**
     * @param int|null $depth
     * @return \yii\db\ActiveQuery
     */
    public function getLeaves($depth = null)
    {
        $tableName = $this->owner->tableName();
        $query = $this->getDescendants($depth)
            ->andWhere(["{$tableName}.[[{$this->leftAttribute}]]" => new Expression("{$tableName}.[[{$this->rightAttribute}]] - 1")]);
        $query->multiple = true;
        return $query;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPrev()
    {
        $tableName = $this->owner->tableName();
        $query = $this->owner->find()
            ->andWhere(["{$tableName}.[[{$this->rightAttribute}]]" => $this->owner->getAttribute($this->leftAttribute) - 1])
            ->andWhere($this->treeCondition())
            ->limit(1);
        $query->multiple = false;
        return $query;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getNext()
    {
        $tableName = $this->owner->tableName();
        $query = $this->owner->find()
            ->andWhere(["{$tableName}.[[{$this->leftAttribute}]]" => $this->owner->getAttribute($this->rightAttribute) + 1])
            ->andWhere($this->treeCondition())
            ->limit(1);
        $query->multiple = false;
        return $query;
    }

    /**
     * Populate children relations for self and all descendants
     * @param int $depth = null
     * @param string|array $with = null
     * @return static
     */
    public function populateTree($depth = null, $with = null)
    {
        /** @var ActiveRecord[]|static[] $nodes */
        $query = $this->getDescendants($depth);
        if ($with) {
            $query->with($with);
        }
        $nodes = $query->all();

        $key = $this->owner->getAttribute($this->leftAttribute);
        $relates = [];
        $parents = [$key];
        $prev = $this->owner->getAttribute($this->depthAttribute);
        foreach($nodes as $node)
        {
            $level = $node->getAttribute($this->depthAttribute);
            if ($level <= $prev) {
                $parents = array_slice($parents, 0, $level - $prev - 1);
            }

            $key = end($parents);
            if (!isset($relates[$key])) {
                $relates[$key] = [];
            }
            $relates[$key][] = $node;

            $parents[] = $node->getAttribute($this->leftAttribute);
            $prev = $level;
        }

        $ownerDepth = $this->owner->getAttribute($this->depthAttribute);
        $nodes[] = $this->owner;
        foreach ($nodes as $node) {
            $key = $node->getAttribute($this->leftAttribute);
            if (isset($relates[$key])) {
                $node->populateRelation('children', $relates[$key]);
            } elseif ($depth === null || $ownerDepth + $depth > $node->getAttribute($this->depthAttribute)) {
                $node->populateRelation('children', []);
            }
        }

        return $this->owner;
    }

    /**
     * @return bool
     */
    public function isRoot()
    {
        return $this->owner->getAttribute($this->leftAttribute) === 1;
    }

    /**
     * @param ActiveRecord $node
     * @return bool
     */
    public function isChildOf($node)
    {
        $result = $this->owner->getAttribute($this->leftAttribute) > $node->getAttribute($this->leftAttribute)
            && $this->owner->getAttribute($this->rightAttribute) < $node->getAttribute($this->rightAttribute);

        if ($result && $this->treeAttribute !== null) {
            $result = $this->owner->getAttribute($this->treeAttribute) === $node->getAttribute($this->treeAttribute);
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function isLeaf()
    {
        return $this->owner->getAttribute($this->rightAttribute) - $this->owner->getAttribute($this->leftAttribute) === 1;
    }

    /**
     * @return ActiveRecord
     */
    public function makeRoot()
    {
        $this->operation = self::OPERATION_MAKE_ROOT;
        return $this->owner;
    }

    /**
     * @param ActiveRecord $node
     * @return ActiveRecord
     */
    public function prependTo($node)
    {
        $this->operation = self::OPERATION_PREPEND_TO;
        $this->node = $node;
        return $this->owner;
    }

    /**
     * @param ActiveRecord $node
     * @return ActiveRecord
     */
    public function appendTo($node)
    {
        $this->operation = self::OPERATION_APPEND_TO;
        $this->node = $node;
        return $this->owner;
    }

    /**
     * @param ActiveRecord $node
     * @return ActiveRecord
     */
    public function insertBefore($node)
    {
        $this->operation = self::OPERATION_INSERT_BEFORE;
        $this->node = $node;
        return $this->owner;
    }

    /**
     * @param ActiveRecord $node
     * @return ActiveRecord
     */
    public function insertAfter($node)
    {
        $this->operation = self::OPERATION_INSERT_AFTER;
        $this->node = $node;
        return $this->owner;
    }

    /**
     * Need for paulzi/auto-tree
     */
    public function preDeleteWithChildren()
    {
        $this->operation = self::OPERATION_DELETE_ALL;
    }

    /**
     * @return bool|int
     * @throws \Exception
     * @throws \yii\db\Exception
     */
    public function deleteWithChildren()
    {
        $this->operation = self::OPERATION_DELETE_ALL;
        if (!$this->owner->isTransactional(ActiveRecord::OP_DELETE)) {
            $transaction = $this->owner->getDb()->beginTransaction();
            try {
                $result = $this->deleteWithChildrenInternal();
                if ($result === false) {
                    $transaction->rollBack();
                } else {
                    $transaction->commit();
                }
                return $result;
            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }
        } else {
            $result = $this->deleteWithChildrenInternal();
        }
        return $result;
    }

    /**
     * @throws Exception
     * @throws NotSupportedException
     */
    public function beforeInsert()
    {
        if ($this->node !== null && !$this->node->getIsNewRecord()) {
            $this->node->refresh();
        }
        switch ($this->operation) {
            case self::OPERATION_MAKE_ROOT:
                $condition = array_merge([$this->leftAttribute => 1], $this->treeCondition());
                if ($this->owner->find()->andWhere($condition)->one() !== null) {
                    throw new Exception('Can not create more than one root.');
                }
                $this->owner->setAttribute($this->leftAttribute,  1);
                $this->owner->setAttribute($this->rightAttribute, 2);
                $this->owner->setAttribute($this->depthAttribute, 0);
                break;

            case self::OPERATION_PREPEND_TO:
                $this->insertNode($this->node->getAttribute($this->leftAttribute) + 1, 1);
                break;

            case self::OPERATION_APPEND_TO:
                $this->insertNode($this->node->getAttribute($this->rightAttribute), 1);
                break;

            case self::OPERATION_INSERT_BEFORE:
                $this->insertNode($this->node->getAttribute($this->leftAttribute), 0);
                break;

            case self::OPERATION_INSERT_AFTER:
                $this->insertNode($this->node->getAttribute($this->rightAttribute) + 1, 0);
                break;

            default:
                throw new NotSupportedException('Method "'. $this->owner->className() . '::insert" is not supported for inserting new nodes.');
        }
    }

    /**
     * @throws Exception
     */
    public function afterInsert()
    {
        if ($this->operation === self::OPERATION_MAKE_ROOT && $this->treeAttribute !== null && $this->owner->getAttribute($this->treeAttribute) === null) {
            $id = $this->owner->getPrimaryKey();
            $this->owner->setAttribute($this->treeAttribute, $id);

            $primaryKey = $this->owner->primaryKey();
            if (!isset($primaryKey[0])) {
                throw new Exception('"' . $this->owner->className() . '" must have a primary key.');
            }

            $this->owner->updateAll([$this->treeAttribute => $id], [$primaryKey[0] => $id]);
        }
        $this->operation = null;
        $this->node      = null;
    }

    /**
     * @throws Exception
     */
    public function beforeUpdate()
    {
        if ($this->node !== null && !$this->node->getIsNewRecord()) {
            $this->node->refresh();
        }

        switch ($this->operation) {
            case self::OPERATION_MAKE_ROOT:
                if ($this->treeAttribute === null) {
                    throw new Exception('Can not move a node as the root when "treeAttribute" is not set.');
                }
                if ($this->owner->getOldAttribute($this->treeAttribute) !== $this->owner->getAttribute($this->treeAttribute)) {
                    $this->treeChange = $this->owner->getAttribute($this->treeAttribute);
                    $this->owner->setAttribute($this->treeAttribute, $this->owner->getOldAttribute($this->treeAttribute));
                }
                break;

            case self::OPERATION_INSERT_BEFORE:
            case self::OPERATION_INSERT_AFTER:
                if ($this->node->isRoot()) {
                    throw new Exception('Can not move a node before/after root.');
                }

            case self::OPERATION_PREPEND_TO:
            case self::OPERATION_APPEND_TO:
                if ($this->node->getIsNewRecord()) {
                    throw new Exception('Can not move a node when the target node is new record.');
                }

                if ($this->owner->equals($this->node)) {
                    throw new Exception('Can not move a node when the target node is same.');
                }

                if ($this->node->isChildOf($this->owner)) {
                    throw new Exception('Can not move a node when the target node is child.');
                }
        }
    }

    /**
     *
     */
    public function afterUpdate()
    {
        switch ($this->operation) {
            case self::OPERATION_MAKE_ROOT:
                if ($this->treeChange || !$this->isRoot() || $this->owner->getIsNewRecord()) {
                    $this->moveNodeAsRoot();
                }
                break;

            case self::OPERATION_PREPEND_TO:
                $this->moveNode($this->node->getAttribute($this->leftAttribute) + 1, 1);
                break;

            case self::OPERATION_APPEND_TO:
                $this->moveNode($this->node->getAttribute($this->rightAttribute), 1);
                break;

            case self::OPERATION_INSERT_BEFORE:
                $this->moveNode($this->node->getAttribute($this->leftAttribute), 0);
                break;

            case self::OPERATION_INSERT_AFTER:
                $this->moveNode($this->node->getAttribute($this->rightAttribute) + 1, 0);
                break;
        }
        $this->operation  = null;
        $this->node       = null;
        $this->treeChange = null;
    }

    /**
     * @throws Exception
     */
    public function beforeDelete()
    {
        if ($this->owner->getIsNewRecord()) {
            throw new Exception('Can not delete a node when it is new record.');
        }
        if ($this->isRoot() && $this->operation !== self::OPERATION_DELETE_ALL) {
            throw new Exception('Method "'. $this->owner->className() . '::delete" is not supported for deleting root nodes.');
        }
        $this->owner->refresh();
    }

    /**
     *
     */
    public function afterDelete()
    {
        $left  = $this->owner->getAttribute($this->leftAttribute);
        $right = $this->owner->getAttribute($this->rightAttribute);
        if ($this->operation === static::OPERATION_DELETE_ALL || $this->isLeaf()) {
            $this->shift($right + 1, null, $left - $right - 1);
        } else {
            $this->owner->updateAll(
                [
                    $this->leftAttribute  => new Expression("[[{$this->leftAttribute}]] - 1"),
                    $this->rightAttribute => new Expression("[[{$this->rightAttribute}]] - 1"),
                    $this->depthAttribute => new Expression("[[{$this->depthAttribute}]] - 1"),
                ],
                $this->getDescendants()->where
            );
            $this->shift($right + 1, null, -2);
        }
        $this->operation = null;
        $this->node      = null;
    }

    /**
     * @return int
     */
    protected function deleteWithChildrenInternal()
    {
        if (!$this->owner->beforeDelete()) {
            return false;
        }
        $result = $this->owner->deleteAll($this->getDescendants(null, true)->where);
        $this->owner->setOldAttributes(null);
        $this->owner->afterDelete();
        return $result;
    }

    /**
     * @param int $to
     * @param int $depth
     * @throws Exception
     */
    protected function insertNode($to, $depth = 0)
    {
        if ($this->node->getIsNewRecord()) {
            throw new Exception('Can not create a node when the target node is new record.');
        }

        if ($depth === 0 && $this->node->isRoot()) {
            throw new Exception('Can not insert a node before/after root.');
        }
        $this->owner->setAttribute($this->leftAttribute,  $to);
        $this->owner->setAttribute($this->rightAttribute, $to + 1);
        $this->owner->setAttribute($this->depthAttribute, $this->node->getAttribute($this->depthAttribute) + $depth);
        if ($this->treeAttribute !== null) {
            $this->owner->setAttribute($this->treeAttribute, $this->node->getAttribute($this->treeAttribute));
        }
        $this->shift($to, null, 2);
    }

    /**
     * @param int $to
     * @param int $depth
     * @throws Exception
     */
    protected function moveNode($to, $depth = 0)
    {
        $left  = $this->owner->getAttribute($this->leftAttribute);
        $right = $this->owner->getAttribute($this->rightAttribute);
        $depth = $this->owner->getAttribute($this->depthAttribute) - $this->node->getAttribute($this->depthAttribute) - $depth;
        if ($this->treeAttribute === null || $this->owner->getAttribute($this->treeAttribute) === $this->node->getAttribute($this->treeAttribute)) {
            // same root
            $this->owner->updateAll(
                [$this->depthAttribute => new Expression("-[[{$this->depthAttribute}]]" . sprintf('%+d', $depth))],
                $this->getDescendants(null, true)->where
            );
            $delta = $right - $left + 1;
            if ($left >= $to) {
                $this->shift($to, $left - 1, $delta);
                $delta = $to - $left;
            } else {
                $this->shift($right + 1, $to - 1, -$delta);
                $delta = $to - $right - 1;
            }
            $this->owner->updateAll(
                [
                    $this->leftAttribute  => new Expression("[[{$this->leftAttribute}]]"  . sprintf('%+d', $delta)),
                    $this->rightAttribute => new Expression("[[{$this->rightAttribute}]]" . sprintf('%+d', $delta)),
                    $this->depthAttribute => new Expression("-[[{$this->depthAttribute}]]"),
                ],
                [
                    'and',
                    $this->getDescendants(null, true)->where,
                    ['<', $this->depthAttribute, 0],
                ]
            );
        } else {
            // move from other root
            $tree = $this->node->getAttribute($this->treeAttribute);
            $this->shift($to, null, $right - $left + 1, $tree);
            $delta = $to - $left;
            $this->owner->updateAll(
                [
                    $this->leftAttribute  => new Expression("[[{$this->leftAttribute}]]"  . sprintf('%+d', $delta)),
                    $this->rightAttribute => new Expression("[[{$this->rightAttribute}]]" . sprintf('%+d', $delta)),
                    $this->depthAttribute => new Expression("[[{$this->depthAttribute}]]" . sprintf('%+d', -$depth)),
                    $this->treeAttribute  => $tree,
                ],
                $this->getDescendants(null, true)->where
            );
            $this->shift($right + 1, null, $left - $right - 1);
        }
    }

    /**
     *
     */
    protected function moveNodeAsRoot()
    {
        $left   = $this->owner->getAttribute($this->leftAttribute);
        $right  = $this->owner->getAttribute($this->rightAttribute);
        $depth  = $this->owner->getAttribute($this->depthAttribute);
        $tree   = $this->treeChange ? $this->treeChange : $this->owner->getPrimaryKey();

        $this->owner->updateAll(
            [
                $this->leftAttribute  => new Expression("[[{$this->leftAttribute}]]"  . sprintf('%+d', 1 - $left)),
                $this->rightAttribute => new Expression("[[{$this->rightAttribute}]]" . sprintf('%+d', 1 - $left)),
                $this->depthAttribute => new Expression("[[{$this->depthAttribute}]]" . sprintf('%+d', -$depth)),
                $this->treeAttribute  => $tree,
            ],
            $this->getDescendants(null, true)->where
        );
        $this->shift($right + 1, null, $left - $right - 1);
    }



    /**
     * @param int $from
     * @param int $to
     * @param int $delta
     * @param int|null $tree
     */
    protected function shift($from, $to, $delta, $tree = null)
    {
        if ($delta !== 0 && ($to === null || $to >= $from)) {
            if ($this->treeAttribute !== null && $tree === null) {
                $tree = $this->owner->getAttribute($this->treeAttribute);
            }
            foreach ([$this->leftAttribute, $this->rightAttribute] as $i => $attribute) {
                $this->owner->updateAll(
                    [$attribute => new Expression("[[{$attribute}]]" . sprintf('%+d', $delta))],
                    [
                        'and',
                        $to === null ? ['>=', $attribute, $from] : ['between', $attribute, $from, $to],
                        $this->treeAttribute !== null ? [$this->treeAttribute => $tree] : [],
                    ]
                );
            }
        }
    }

    /**
     * @return array
     */
    protected function treeCondition()
    {
        $tableName = $this->owner->tableName();
        if ($this->treeAttribute === null) {
            return [];
        } else {
            return ["{$tableName}.[[{$this->treeAttribute}]]" => $this->owner->getAttribute($this->treeAttribute)];
        }
    }
}
