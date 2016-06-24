<?php
namespace Craft;

class Neo_GetSearchKeywordsTask extends BaseTask
{
	// Private properties

	private $_blocks;
	private $_keywords;
	private $_originalContentTable;
	private $_originalFieldColumnPrefix;
	private $_originalFieldContext;


	// Public methods

	public function getDescription()
	{
		return Craft::t("Generating search keywords");
	}

	public function getTotalSteps()
	{
		$this->_begin();

		return count($this->_blocks) + 1;
	}

	public function runStep($step)
	{
		if(count($this->_blocks) == $step)
		{
			$this->_end();
		}
		else
		{
			$block = $this->_blocks[$step];

			craft()->content->contentTable = $block->getContentTable();
			craft()->content->fieldColumnPrefix = $block->getFieldColumnPrefix();
			craft()->content->fieldContext = $block->getFieldContext();

			foreach(craft()->fields->getAllFields() as $field)
			{
				$fieldType = $field->getFieldType();

				if($fieldType)
				{
					$fieldType->element = $block;
					$handle = $field->handle;
					$this->_keywords[] = $fieldType->getSearchKeywords($block->getFieldValue($handle));
				}
			}
		}

		return true;
	}


	// Protected methods

	protected function defineSettings()
	{
		return [
			'fieldId' => AttributeType::Number,
			'ownerId' => AttributeType::Number,
			'locale' => AttributeType::Locale,
		];
	}


	// Private methods

	private function _begin()
	{
		$settings = $this->getSettings();

		$this->_blocks = craft()->neo->getBlocks($settings->fieldId, $settings->ownerId, $settings->locale);
		$this->_keywords = [];

		$this->_originalContentTable = craft()->content->contentTable;
		$this->_originalFieldColumnPrefix = craft()->content->fieldColumnPrefix;
		$this->_originalFieldContext = craft()->content->fieldContext;
	}

	private function _end()
	{
		$settings = $this->getSettings();

		craft()->content->contentTable = $this->_originalContentTable;
		craft()->content->fieldColumnPrefix = $this->_originalFieldColumnPrefix;
		craft()->content->fieldContext = $this->_originalFieldContext;

		$keywords = StringHelper::arrayToString($this->_keywords, ' ');

		craft()->search->indexElementFields($settings->ownerId, $settings->locale, [$settings->fieldId => $keywords]);
	}
}
