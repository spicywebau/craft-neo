<?php
namespace benf\neo\records;

use yii\db\ActiveQueryInterface;

use craft\db\ActiveRecord;

/**
 * Class BlockTypeGroup
 *
 * @package benf\neo
 * @author Spicy Web <craft@spicyweb.com.au>
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
