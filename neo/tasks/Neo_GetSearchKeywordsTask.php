<?php
namespace Craft;

class Neo_GetSearchKeywordsTask extends BaseTask
{
	// Private properties

	private $_blocks;
	private $_keywords;


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
			$this->_keywords[] = craft()->neo->getBlockKeywords($block);
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
	}

	private function _end()
	{
		$settings = $this->getSettings();
		$keywords = StringHelper::arrayToString($this->_keywords, ' ');

		craft()->search->indexElementFields($settings->ownerId, $settings->locale, [$settings->fieldId => $keywords]);
	}
}
