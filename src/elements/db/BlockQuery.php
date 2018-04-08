<?php
namespace benf\neo\elements\db;

use Craft;
use craft\base\ElementInterface;
use craft\elements\db\ElementQuery;
use craft\models\Site;

class BlockQuery extends ElementQuery
{
	public $fieldId;
	public $ownerId;
	public $ownerSiteId;
	public $typeId;

	public function __set($name, $value)
	{
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
				Craft::$app->getDeprecator()->log("BlockQuery::ownerLocale()', 'The “ownerLocale” Neo block query param has been deprecated. Use “ownerSite” or “ownerSiteId” instead.");
				$this->ownerSite($value);
			}
			break;
			default:
			{
				parent::__set($name, $value);
			}
		}
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
			
		}
	}
}
