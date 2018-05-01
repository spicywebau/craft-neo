<?php
namespace benf\neo\elements\db;

use yii\base\Exception;

use Craft;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\models\Site;
use craft\helpers\Db;

use benf\neo\Plugin as Neo;
use benf\neo\models\BlockType;

class BlockQuery extends ElementQuery
{
	public $fieldId;
	public $ownerId;
	public $ownerSiteId;
	public $typeId;

	public function __set($name, $value)
	{
		$deprecatorService = Craft::$app->getDeprecator();

		switch ($name)
		{
			case 'ownerSite':
			{
				$this->ownerSite($value);
			}
			break;
			case 'type':
			{
				$this->type($value);
			}
			break;
			case 'ownerLocale':
			{
				$deprecatorService->log('BlockQuery::ownerLocale()', "The “ownerLocale” Neo block query param has been deprecated. Use “ownerSite” or “ownerSiteId” instead.");
				$this->ownerSite($value);
			}
			break;
			default:
			{
				parent::__set($name, $value);
			}
		}
	}

	public function init()
	{
		$this->withStructure = true;

		parent::init();
	}

	public function fieldId($value)
	{
		$this->fieldId = $value;

		return $this;
	}

	public function ownerId($value)
	{
		$this->ownerId = $value;

		return $this;
	}

	public function ownerSiteId($value)
	{
		$this->ownerSiteId = $value;

		if ($value && strtolower($value) !== ':empty:')
		{
			$this->siteId = (int)$value;
		}

		return $this;
	}

	public function ownerSite($value)
	{
		if ($value instanceof Site)
		{
			$this->ownerSiteId($value->id);
		}
		else
		{
			$site = Craft::$app->getSites()->getSiteByHandle($value);

			if (!$site)
			{
				throw new Exception("Invalid site handle: $value");
			}

			$this->ownerSiteId($site->id);
		}

		return $this;
	}

	public function ownerLocale($value)
	{
		Craft::$app->getDeprecator()->log('ElementQuery::ownerLocale()', "The “ownerLocale” Neo block query param has been deprecated. Use “site” or “siteId” instead.");
		$this->ownerSite($value);

		return $this;
	}

	public function owner(ElementInterface $owner)
	{
		$this->ownerId = $owner->id;
		$this->siteId = $owner->siteId;

		return $this;
	}

	public function type($value)
	{
		if ($value instanceof BlockType)
		{
			$this->typeId = $value->id;
		}
		else if ($value !== null)
		{
			$this->typeId = (new Query())
				->select(['id'])
				->from(['{{%neoblocktypes}}'])
				->where(Db::parseParam('handle', $value))
				->column();
		}
		else
		{
			$this->typeId = null;
		}

		return $this;
	}

	public function typeId($value)
	{
		$this->typeId = $value;

		return $this;
	}

	protected function beforePrepare(): bool
	{
		$this->joinElementTable('neoblocks');

		$isSaved = $this->id && is_numeric($this->id);

		if ($isSaved)
		{
			foreach (['fieldId', 'ownerId', 'ownerSiteId'] as $idProperty)
			{
				if (!$this->$idProperty)
				{
					$this->$idProperty = (new Query())
						->select([$idProperty])
						->from(['{{%neoblocks}}'])
						->where(['id' => $this->id])
						->scalar();
				}
			}

			if (!$this->structureId && $this->fieldId && $this->ownerId && $this->ownerSiteId)
			{
				$blockStructure = Neo::$plugin->blocks->getStructure($this->fieldId, $this->ownerId, $this->ownerSiteId);

				if ($blockStructure)
				{
					$this->structureId = $blockStructure->structureId;
				}
			}
		}

		$this->query->select([
			'neoblocks.fieldId',
			'neoblocks.ownerId',
			'neoblocks.ownerSiteId',
			'neoblocks.typeId',
		]);

		if ($this->fieldId)
		{
			$this->subQuery->andWhere(Db::parseParam('neoblocks.fieldId', $this->fieldId));
		}

		if ($this->ownerId)
		{
			$this->subQuery->andWhere(Db::parseParam('neoblocks.ownerId', $this->ownerId));
		}

		if ($this->ownerSiteId)
		{
			$this->subQuery->andWhere(Db::parseParam('neoblocks.ownerSiteId', $this->ownerSiteId));
		}

		if ($this->typeId !== null)
		{
			// If typeId is an empty array, it's because type() was called but no valid type handles were passed in
			if (is_array($this->typeId) && empty($this->typeId))
			{
				return false;
			}

			$this->subQuery->andWhere(Db::parseParam('neoblocks.typeId', $this->typeId));
		}

		return parent::beforePrepare();
	}
}
