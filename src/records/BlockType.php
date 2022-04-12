<?php

namespace benf\neo\records;

use craft\db\ActiveRecord;

use craft\validators\HandleValidator;
use yii\db\ActiveQueryInterface;

/**
 * Class BlockType
 *
 * @package benf\neo\records
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class BlockType extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%neoblocktypes}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['handle'], 'unique', 'targetAttribute' => ['handle', 'fieldId']],
            [['name', 'handle'], 'required'],
            [['name', 'handle'], 'string', 'max' => 255],
            [
                ['handle'],
                HandleValidator::class,
                'reservedWords' => [
                    'id',
                    'dateCreated',
                    'dateUpdated',
                    'uid',
                    'title',
                ],
            ],
        ];
    }

    /**
     * Returns the block type's associated field.
     *
     * @return ActiveQueryInterface
     */
    public function getField(): ActiveQueryInterface
    {
        return $this->hasOne(Field::class, ['id' => 'fieldId']);
    }

    /**
     * Returns the block type's field layout.
     *
     * @return ActiveQueryInterface
     */
    public function getFieldLayout(): ActiveQueryInterface
    {
        return $this->hasOne(FieldLayout::class, ['id' => 'fieldLayoutId']);
    }
}
