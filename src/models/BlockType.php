<?php

namespace benf\neo\models;

use Craft;
use craft\base\Model;
use craft\behaviors\FieldLayoutBehavior;
use craft\base\GqlInlineFragmentInterface;

use benf\neo\elements\Block;

/**
 * Class BlockType
 *
 * @package benf\neo\models
 * @author Spicy Web <craft@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class BlockType extends Model implements GqlInlineFragmentInterface
{
	/**
	 * @var int|null The block type ID.
	 */
	public $id;
	
	/**
	 * @var int|null The field ID.
	 */
	public $fieldId;
	
	/**
	 * @var int|null The field layout ID.
	 */
	public $fieldLayoutId;
	
	/**
	 * @var string|null The block type's name.
	 */
	public $name;
	
	/**
	 * @var string|null The block type's handle.
	 */
	public $handle;
	
	/**
	 * @var int|null The maximum number of blocks.
	 */
	public $maxBlocks;
	
	/**
	 * @var int|null The maximum number of child blocks.
	 */
	public $maxChildBlocks;
	
	/**
	 * @var array|null The child blocks.
	 */
	public $childBlocks;
	
	/**
	 * @var bool Whether this is at the top level of its field.
	 */
	public $topLevel = true;
	
	/**
	 * @var int|null The sort order.
	 */
	public $sortOrder;
	
	/**
	 * @var string
	 */
	public $uid;
	
	/**
	 * @var bool
	 */
	public $hasFieldErrors = false;
	
	/**
	 * @var Field|null The Neo field associated with this block type.
	 */
	private $_field;
	
	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'fieldLayout' => [
				'class' => FieldLayoutBehavior::class,
				'elementType' => Block::class,
			],
		];
	}
	
	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['id', 'fieldId', 'sortOrder'], 'number', 'integerOnly' => true],
			[['maxBlocks', 'maxChildBlocks'], 'integer', 'min' => 0],
		];
	}
	
	/**
	 * Returns the block type's handle as the string representation.
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		return (string)$this->handle;
	}
	
	/**
	 * Returns whether this block type is new.
	 *
	 * @return bool
	 */
	public function getIsNew(): bool
	{
		return (!$this->id || strpos($this->id, 'new') === 0);
	}
	
	/**
	 * Returns the Neo field associated with this block type.
	 *
	 * @return \benf\neo\Field|null
	 */
	public function getField()
	{
		$fieldsService = Craft::$app->getFields();
		
		if (!$this->_field && $this->fieldId) {
			$this->_field = $fieldsService->getFieldById($this->fieldId);
		}
		
		return $this->_field;
	}
	
	/**
	 * @inheritdoc
	 */
	public function getFieldContext(): string
	{
		return 'neoBlockTypes:' . $this->uid;
	}
	
	/**
	 * @inheritdoc
	 */
	public function getEagerLoadingPrefix(): string
	{
		return $this->handle;
	}
}
