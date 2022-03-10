<?php

namespace benf\neo\records;

use craft\db\ActiveRecord;

use yii\db\ActiveQueryInterface;

/**
 * Class BlockTypeGroup
 *
 * @package benf\neo\records
 * @author Spicy Web <craft@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class BlockTypeGroup extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%neoblocktypegroups}}';
    }

    /**
     * Returns the block type group's field.
     *
     * @return ActiveQueryInterface
     */
    public function getField(): ActiveQueryInterface
    {
        return $this->hasOne(Field::class, [ 'id' => 'fieldId' ]);
    }
}
