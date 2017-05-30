# Yii2 Nested Sets Behavior

Implementation of nested sets algorithm for storing the trees in DB tables.

[![Packagist Version](https://img.shields.io/packagist/v/paulzi/yii2-nested-sets.svg)](https://packagist.org/packages/paulzi/yii2-nested-sets)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/paulzi/yii2-nested-sets/master.svg)](https://scrutinizer-ci.com/g/paulzi/yii2-nested-sets/?branch=master)
[![Build Status](https://img.shields.io/travis/paulzi/yii2-nested-sets/master.svg)](https://travis-ci.org/paulzi/yii2-nested-sets)
[![Total Downloads](https://img.shields.io/packagist/dt/paulzi/yii2-nested-sets.svg)](https://packagist.org/packages/paulzi/yii2-nested-sets)

## Install

Install via Composer:

```bash
composer require paulzi/yii2-nested-sets
```

or add

```bash
"paulzi/yii2-nested-sets" : "^1.0"
```

to the `require` section of your `composer.json` file.

## Migrations example

**Warning! `depth` attribute can not be unsigned!**

Single tree migration:

```php
class m150722_150000_single_tree extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }
        $this->createTable('{{%single_tree}}', [
            'id'    => Schema::TYPE_PK,
            'lft'   => Schema::TYPE_INTEGER . ' NOT NULL',
            'rgt'   => Schema::TYPE_INTEGER . ' NOT NULL',
            'depth' => Schema::TYPE_INTEGER . ' NOT NULL', // not unsigned!
            'name'  => Schema::TYPE_STRING . ' NOT NULL', // example field
        ], $tableOptions);
        $this->createIndex('lft', '{{%single_tree}}', ['lft', 'rgt']);
        $this->createIndex('rgt', '{{%single_tree}}', ['rgt']);
    }
}
```

Multiple tree migration:

```php
class m150722_150100_multiple_tree extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }
        $this->createTable('{{%multiple_tree}}', [
            'id'    => Schema::TYPE_PK,
            'tree'  => Schema::TYPE_INTEGER . ' NULL',
            'lft'   => Schema::TYPE_INTEGER . ' NOT NULL',
            'rgt'   => Schema::TYPE_INTEGER . ' NOT NULL',
            'depth' => Schema::TYPE_INTEGER . ' NOT NULL', // not unsigned!
            'name'  => Schema::TYPE_STRING . ' NOT NULL', // example field
        ], $tableOptions);
        $this->createIndex('lft', '{{%multiple_tree}}', ['tree', 'lft', 'rgt']);
        $this->createIndex('rgt', '{{%multiple_tree}}', ['tree', 'rgt']);
    }
}
```

## Configuring

```php
use paulzi\nestedsets\NestedSetsBehavior;

class Sample extends \yii\db\ActiveRecord
{
    public function behaviors() {
        return [
            [
                'class' => NestedSetsBehavior::className(),
                // 'treeAttribute' => 'tree',
            ],
        ];
    }

    public function transactions()
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_ALL,
        ];
    }
}
```

Optional you can setup Query for finding roots:

```php
class Sample extends \yii\db\ActiveRecord
{
    public static function find()
    {
        return new SampleQuery(get_called_class());
    }
}
```

Query class:

```php
use paulzi\nestedsets\NestedSetsQueryTrait;

class SampleQuery extends \yii\db\ActiveQuery
{
    use NestedSetsQueryTrait;
}
```

## Options

- `$treeAttribute = null` - setup tree attribute for multiple tree in table schema.
- `$leftAttribute = 'lft'` - left attribute in table schema.
- `$rightAttribute = 'rgt'` - right attribute in table schema.
- `$depthAttribute = 'depth'` - depth attribute in table schema (note: it must be signed int).

## Usage

### Selection

**Getting the root nodes**

If you connect `NestedSetsQueryTrait`, you can get all the root nodes:

```php
$roots = Sample::find()->roots()->all();
```

**Getting ancestors of a node**

To get ancestors of a node:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$parents = $node11->parents; // via relation
$parents = $node11->getParents()->all(); // via query
$parents = $node11->getParents(2)->all(); // get 2 levels of ancestors
```

To get parent of a node:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$parent = $node11->parent; // via relation
$parent = $node11->getParent()->one(); // via query
```

To get root of a node:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$root = $node11->root; // via relation
$root = $node11->getRoot()->one(); // via query
```

**Getting descendants of a node**

To get all the descendants of a node:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$descendants = $node11->descendants; // via relation
$descendants = $node11->getDescendants()->all(); // via query
$descendants = $node11->getDescendants(2, true)->all(); // get 2 levels of descendants and self node
$descendants = $node11->getDescendants(3, false, true)->all(); // get 3 levels of descendants in back order
```

To populate `children` relations for self and descendants of a node:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$tree = $node11->populateTree(); // populate all levels
$tree = $node11->populateTree(2); // populate 2 levels of descendants
```

To get the children of a node:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$children = $node11->children; // via relation
$children = $node11->getChildren()->all(); // via query
```

**Getting the leaves nodes**

To get all the leaves of a node:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$leaves = $node11->leaves; // via relation
$leaves = $node11->getLeaves(2)->all(); // get 2 levels of leaves via query
```

**Getting the neighbors nodes**

To get the next node:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$next = $node11->next; // via relation
$next = $node11->getNext()->one(); // via query
```

To get the previous node:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$prev = $node11->prev; // via relation
$prev = $node11->getPrev()->one(); // via query
```

### Some checks

```php
$node1 = Sample::findOne(['name' => 'node 1']);
$node11 = Sample::findOne(['name' => 'node 1.1']);
$node11->isRoot() - return true, if node is root
$node11->isLeaf() - return true, if node is leaf
$node11->isChildOf($node1) - return true, if node11 is child of $node1
```


### Modifications

To make a root node:

```php
$node11 = new Sample();
$node11->name = 'node 1.1';
$node11->makeRoot()->save();
```

*Note: if you allow multiple trees and attribute `tree` is not set, it automatically takes the primary key value.*

To prepend a node as the first child of another node:

```php
$node1 = Sample::findOne(['name' => 'node 1']);
$node11 = new Sample();
$node11->name = 'node 1.1';
$node11->prependTo($node1)->save(); // inserting new node
```

To append a node as the last child of another node:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$node12 = Sample::findOne(['name' => 'node 1.2']);
$node12->appendTo($node11)->save(); // move existing node
```

To insert a node before another node:

```php
$node13 = Sample::findOne(['name' => 'node 1.3']);
$node12 = new Sample();
$node12->name = 'node 1.2';
$node12->insertBefore($node13)->save(); // inserting new node
```

To insert a node after another node:

```php
$node13 = Sample::findOne(['name' => 'node 1.3']);
$node14 = Sample::findOne(['name' => 'node 1.4']);
$node14->insertAfter($node13)->save(); // move existing node
```

To delete a node with descendants:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$node11->delete(); // delete node, children come up to the parent
$node11->deleteWithChildren(); // delete node and all descendants 
```