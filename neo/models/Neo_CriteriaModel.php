<?php
namespace Craft;

class Neo_CriteriaModel extends ElementCriteriaModel
{
	private $_allElements;
	private $_currentFilters = [];

	public function __construct($attributes, $_ = null)
	{
		$elementType = craft()->elements->getElementType(Neo_ElementType::NeoBlock);

		parent::__construct($attributes, $elementType);
	}

	public function copy()
	{
		$copy = parent::copy();

		if(!empty($this->_allElements))
		{
			$copy->setAllElements($this->_allElements);
		}

		return $copy;
	}

	public function setAttribute($name, $value)
	{
		if(parent::setAttribute($name, $value))
		{
			$method = '_filter' . ucfirst($name);

			if(craft()->request->isLivePreview() && method_exists($this, $method))
			{
				$this->_currentFilters[$method] = $value;

				$this->_runFilters();
			}

			return true;
		}

		return false;
	}

	public function setAllElements($elements)
	{
		$this->_allElements = $elements;

		$this->_runFilters();
	}

	private function _runFilters()
	{
		if(!empty($this->_allElements))
		{
			$elements = array_filter($this->_allElements, function($element)
			{
				return $this->_elementFilters($element);
			});

			$this->setMatchedElements($elements);
		}
	}

	private function _elementFilters($element)
	{
		foreach($this->_currentFilters as $method => $value)
		{
			if(!$this->$method($element, $value))
			{
				return false;
			}
		}

		return true;
	}

	private function _filterLevel($element, $value)
	{
		return $element->level == $value;
	}

	private function _filterDescendantOf($element, $value)
	{
		return true; // TODO
	}

	private function _filterDescendantDist($element, $value)
	{
		return true; // TODO
	}

	private function _filterLocale($element, $value)
	{
		return true; // TODO
	}
}
