<?php
namespace Craft;

class Neo_CriteriaModel extends ElementCriteriaModel
{
	private $_allElements;
	private $_currentFilters = [];

	public static function convert(ElementCriteriaModel $ecm)
	{
		$attributes = array_filter($ecm->getAttributes(), function($value)
		{
			return (bool) $value;
		});

		return new Neo_CriteriaModel($attributes);
	}

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
			$method = '__' . $name;

			if(craft()->request->isLivePreview() && method_exists($this, $method))
			{
				$this->_currentFilters[$name] = $value;

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
			$index = 0;
			$elements = array_filter($this->_allElements, function($element) use(&$index)
			{
				return $this->_elementFilters($element, $index++);
			});

			$this->setMatchedElements($elements);
		}
	}

	private function _elementFilters($element, $index)
	{
		foreach($this->filterOrder as $filter)
		{
			if(isset($this->_currentFilters[$filter]))
			{
				$value = $this->_currentFilters[$filter];
				$method = '__' . $filter;

				if(!$this->$method($element, $value, $index))
				{
					return false;
				}
			}
		}

		return true;
	}

	/*
	 * Filtering methods
	 */

	private $_ancestor = null;
	private $_descendant = null;

	// (*) Unsure what these filters are or how they work
	protected $filterOrder = [
		'id',
		'fieldId',
		'locale',
		'ownerId',
		'ownerLocale',
		'slug',
		'status',
		'title',
		'uri',
		'with', // *

		'archived',
		'collapsed',
		'level',
		'depth',
		'type',

		'parentOf', // *
		'parentField', // *
		'childOf', // *
		'childField', // *

		'ancestorOf',
		'ancestorDist',
		'descendantOf',
		'descendantDist',
		'positionedAfter',
		'positionedBefore',
		'prevSiblingOf',
		'nextSiblingOf',
		'siblingOf',

		'dateCreated',
		'dateUpdated',

		'ref', // *
		'relatedTo',
		'search',

		'offset',
		'limit',

		'order', // *
		'fixedOrder', // *
		'indexBy', // *
	];

	protected function __ancestorDist($element, $value)
	{
		return true; // TODO
	}

	protected function __ancestorOf($element, $value)
	{
		return true; // TODO
	}

	protected function __archived($element, $value)
	{
		return true; // Not applicable to Neo blocks
	}

	protected function __childField($element, $value)
	{
		return true; // TODO
	}

	protected function __childOf($element, $value)
	{
		return true; // TODO
	}

	protected function __collapsed($element, $value)
	{
		if(!is_bool($value))
		{
			return true;
		}

		return $element->collapsed == $value;
	}

	protected function __dateCreated($element, $value)
	{
		return true; // TODO
	}

	protected function __dateUpdated($element, $value)
	{
		return true; // TODO
	}

	protected function __depth($element, $value)
	{
		return true; // TODO
	}

	protected function __descendantOf($element, $value)
	{
		$this->_descendant = $value;

		if(!$value)
		{
			return true;
		}

		$elements = $this->_allElements;
		$found = false;

		foreach($elements as $searchElement)
		{
			if($searchElement === $value)
			{
				$found = true;
			}
			else if($found)
			{
				if($searchElement->level > $value->level)
				{
					if($searchElement === $element)
					{
						return true;
					}
				}
				else
				{
					break;
				}
			}
		}

		return false;
	}

	protected function __descendantDist($element, $value)
	{
		if(!$value || !$this->_descendant)
		{
			return true;
		}

		return $element->level <= $this->_descendant->level + $value;
	}

	protected function __fieldId($element, $value)
	{
		if(!$value)
		{
			return true;
		}

		return $element->fieldId == $value;
	}

	protected function __fixedOrder($element, $value)
	{
		return true; // TODO
	}

	protected function __id($element, $value)
	{
		if(!$value)
		{
			return true;
		}

		return $element->id == $value;
	}

	protected function __indexBy($element, $value)
	{
		return true; // TODO
	}

	protected function __level($element, $value)
	{
		if(!$value)
		{
			return true;
		}

		return $element->level == $value; // TODO Support comparison operators `>=4` etc
	}

	protected function __limit($element, $value, $index)
	{
		if(!$value)
		{
			return true;
		}

		return $index < $value;
	}

	protected function __locale($element, $value)
	{
		return true; // Just return true because the blocks will already be locale filtered
	}

	protected function __localeEnabled($element, $value)
	{
		return true; // Just return true because the blocks will already be locale filtered
	}

	protected function __nextSiblingOf($element, $value)
	{
		return true; // TODO
	}

	protected function __offset($element, $value)
	{
		return true; // TODO
	}

	protected function __order($element, $value)
	{
		return true; // TODO
	}

	protected function __ownerId($element, $value)
	{
		return true; // Just return true because the blocks will already be owner ID filtered
	}

	protected function __ownerLocale($element, $value)
	{
		return true; // Just return true because the blocks will already be locale filtered
	}

	protected function __parentField($element, $value)
	{
		return true; // TODO
	}

	protected function __parentOf($element, $value)
	{
		return true; // TODO
	}

	protected function __positionedAfter($element, $value)
	{
		return true; // TODO
	}

	protected function __positionedBefore($element, $value)
	{
		return true; // TODO
	}

	protected function __prevSiblingOf($element, $value)
	{
		return true; // TODO
	}

	protected function __relatedTo($element, $value)
	{
		return true; // TODO
	}

	protected function __ref($element, $value)
	{
		return true; // TODO
	}

	protected function __search($element, $value)
	{
		return true; // TODO
	}

	protected function __siblingOf($element, $value)
	{
		return true; // TODO
	}

	protected function __slug($element, $value)
	{
		return true; // Not applicable to Neo blocks
	}

	protected function __status($element, $value)
	{
		if(!$value)
		{
			return true;
		}

		return $element->status == $value;
	}

	protected function __title($element, $value)
	{
		return true; // Not applicable to Neo blocks
	}

	protected function __type($element, $value)
	{
		if(!$value)
		{
			return true;
		}

		$types = craft()->neo->getBlockTypesByFieldId($element->fieldId, 'handle');
		$type = isset($types[$value]) ? $types[$value] : false;

		return $type && $element->typeId == $type->id;
	}

	protected function __typeId($element, $value)
	{
		if(!$value)
		{
			return true;
		}

		return $element->typeId == $value;
	}

	protected function __uri($element, $value)
	{
		return true; // Not applicable to Neo blocks
	}

	protected function __with($element, $value)
	{
		return true; // TODO
	}
}
