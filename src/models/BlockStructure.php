<?php
namespace benf\neo\models;

use Craft;
use craft\base\Model;

/**
 * Class BlockStructure
 *
 * @package benf\neo\models
 * @author Spicy Web <craft@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class BlockStructure extends Model
{
	/**
	 * @var int|null The block structure ID.
	 */
	public $id;

	/**
	 * @var int|null The structure ID.
	 */
	public $structureId;

	/**
	 * @var int|null The field ID.
	 */
	public $fieldId;

	/**
	 * @var int|null The owner ID.
	 */
	public $ownerId;

	/**
	 * @var int|null The owner site ID.
	 */
	public $ownerSiteId;

	/**
	 * @var \craft\models\Structure|null The associated structure.
	 */
	private $_structure;

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['id', 'structureId', 'fieldId', 'ownerId', 'ownerSiteId'], 'number', 'integerOnly' => true],
		];
	}

	/**
	 * Returns the associated structure.
	 *
	 * @return \craft\models\Structure|null
	 */
	public function getStructure()
	{
		$structuresService = Craft::$app->getStructures();

		if (!$this->_structure && $this->structureId)
		{
			$this->_structure = $structuresService->getStructureById($this->structureId);
		}

		return $this->_structure;
	}
}
