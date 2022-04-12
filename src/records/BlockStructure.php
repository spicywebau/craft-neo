<?php

namespace benf\neo\records;

use craft\db\ActiveRecord;

use yii\db\ActiveQueryInterface;

/**
 * Class BlockStructure
 *
 * @package benf\neo\records
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class BlockStructure extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%neoblockstructures}}';
    }

    /**
     * Returns the block structure's owner.
     *
     * @return ActiveQueryInterface
     */
    public function getOwner(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, [ 'id' => 'ownerId' ]);
    }

    /**
     * Returns the block structure's owner's site.
     *
     * @return ActiveQueryInterface
     */
    public function getOwnerSite(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, [ 'id' => 'ownerSiteId' ]);
    }

    /**
     * Returns the block structure's associated field.
     *
     * @return ActiveQueryInterface
     */
    public function getField(): ActiveQueryInterface
    {
        return $this->hasOne(Field::class, [ 'id' => 'fieldId' ]);
    }

    /**
     * Returns the block structure's associated structure.
     *
     * @return ActiveQueryInterface
     */
    public function getStructure(): ActiveQueryInterface
    {
        return $this->hasOne(Structure::class, [ 'id' => 'structureId' ]);
    }
}
