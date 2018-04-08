<?php
namespace benf\neo\services;

use yii\base\Component;

use Craft;
use craft\base\ElementInterface;
use craft\helpers\Html;

use benf\neo\Plugin as Neo;
use benf\neo\Field;
use benf\neo\helpers\Memoize;

class Fields extends Component
{
	public function validate(Field $field): bool
	{
		$isValid = true;

		$handles = [];

		foreach ($field->getBlockTypes() as $blockType)
		{
			$isBlockTypeValid = Neo::$plugin->blockTypes->validate($blockType, false);
			$isValid = $isValid && $isBlockTypeValid;

			if (isset($handles[$blockType->handle]))
			{
				$blockType->addError('handle', Craft::t('neo', "{label} \"{value}\" has already been taken.", [
					'label' => $blockType->getAttributeLabel('handle'),
					'value' => Html::encoder($blockType->handle),
				]));

				$isValid = false;
			}
			else
			{
				$handles[$blockType->handle] = true;
			}
		}

		return $isValid;
	}

	public function validateValue(Field $field): bool
	{
		return true;
	}

	public function save(Field $field, bool $validate = true): bool
	{
		$dbService = Craft::$app->getDb();

		$isValid = !$validate || $this->validate($field);

		if ($isValid)
		{
			$transaction = $dbService->beginTransaction();
			try
			{
				// Delete the old block types first, in case there's a handle conflict with one of the new ones
				$oldBlockTypes = Neo::$plugin->blockTypes->getByFieldId($field->id);
				$oldBlockTypesById = [];

				foreach ($oldBlockTypes as $blockType)
				{
					$oldBlockTypesById[$blockType->id] = $blockType;
				}

				foreach ($field->getBlockTypes() as $blockType)
				{
					if (!$blockType->getIsNew())
					{
						unset($oldBlockTypesById[$blockType->id]);
					}
				}

				foreach ($oldBlockTypesById as $blockType)
				{
					Neo::$plugin->blockTypes->delete($blockType);
				}

				// Delete all groups to be replaced with what's new
				Neo::$plugin->blockTypes->deleteGroupsByFieldId($field->id);

				// Save the new block types and groups
				foreach ($field->getBlockTypes() as $blockType)
				{
					$blockType->fieldId = $field->id;
					Neo::$plugin->blockTypes->save($blockType, false);
				}

				foreach ($field->getGroups() as $blockTypeGroup)
				{
					$blockTypeGroup->fieldId = $field->id;
					Neo::$plugin->blockTypes->saveGroup($blockTypeGroup);
				}

				$transaction->commit();

				Memoize::$blockTypesByFieldId[$field->id] = $field->getBlockTypes();
				Memoize::$blockTypeGroupsByFieldId[$field->id] = $field->getGroups();
			}
			catch (\Throwable $e)
			{
				$transaction->rollBack();

				throw $e;
			}
		}

		return $isValid;
	}

	public function delete(Field $field): bool
	{
		$dbService = Craft::$app->getDb();

		$transaction = $dbService->beginTransaction();
		try
		{
			$blockTypes = Neo::$plugin->blockTypes->getByFieldId($field->id);

			foreach ($blockTypes as $blockType)
			{
				Neo::$plugin->blockTypes->delete($blockType);
			}

			Neo::$plugin->blockTypes->deleteGroupsByFieldId($field->id);

			$transaction->commit();
		}
		catch (\Throwable $e)
		{
			$transaction->rollBack();

			throw $e;
		}

		return true;
	}

	public function saveValue(Field $field, ElementInterface $value, bool $isNew)
	{

	}
}
