<?php
namespace Craft;

class Neo_CriteriaModel extends ElementCriteriaModel
{
	private $_allElements;
	private $_currentFilters = [];

	private $_ancestor = null;
	private $_descendant = null;

	// (*) Unsure what these filters are or how they work
	protected $filterOrder = [
		'id',
		'fieldId',
		'status',
		'with', // *

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
	];

	protected $outputOrder = [
		'order',
		'fixedOrder', // *
		'indexBy', // *
	];
	
	public function __construct($attributes)
	{
		$elementType = craft()->elements->getElementType(Neo_ElementType::NeoBlock);

		parent::__construct($attributes, $elementType);
	}

	public function copy()
	{
		$copy = parent::copy();
		$copy->setState($this);

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

				$this->_runCriteria();
			}

			return true;
		}

		return false;
	}

	public function setAllElements($elements)
	{
		$this->_allElements = $elements;

		$this->_runCriteria();
	}

	protected function getState()
	{
		return [
			'elements' => $this->_allElements,
			'filters' => $this->_currentFilters,
			'ancestorOf' => $this->_ancestor,
			'descendantOf' => $this->_descendant,
		];
	}

	protected function setState($state)
	{
		if($state instanceof self)
		{
			$state = $state->getState();
		}

		$this->_currentFilters = $state['filters'];
		$this->_ancestor = $state['ancestorOf'];
		$this->_descendant = $state['descendantOf'];

		$this->setAllElements($state['elements']);
	}

	private function _runCriteria()
	{
		if(!empty($this->_allElements))
		{
			$elements = $this->_allElements;

			foreach($this->filterOrder as $filter)
			{
				if(isset($this->_currentFilters[$filter]))
				{
					$value = $this->_currentFilters[$filter];
					$method = '__' . $filter;

					$elements = $this->$method($elements, $value);
				}

				if(empty($elements))
				{
					break;
				}
			}

			$this->setMatchedElements($elements);
		}
	}

	/*
	 * Criteria methods
	 */

	protected function __ancestorDist($elements, $value)
	{
		if(!$value || !$this->_ancestor)
		{
			return $elements;
		}

		return array_filter($elements, function($element) use($value)
		{
			return $element->level >= $this->_ancestor->level - $value;
		});
	}

	protected function __ancestorOf($elements, $value)
	{
		$this->_ancestor = $value;

		if(!$value)
		{
			return $elements;
		}

		$newElements = [];
		$found = false;
		$level = $value->level - 1;

		foreach(array_reverse($elements) as $element)
		{
			if($level < 1)
			{
				break;
			}
			else if($element === $value)
			{
				$found = true;
			}
			else if($found)
			{
				if($element->level == $level)
				{
					$newElements[] = $element;
					$level--;
				}
			}
		}

		return $newElements;
	}

	protected function __childField($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}
		
		return []; // TODO
	}

	protected function __childOf($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}
		
		return []; // TODO
	}

	protected function __collapsed($elements, $value)
	{
		if(!is_bool($value))
		{
			return $elements;
		}

		return array_filter($elements, function($element) use($value)
		{
			return $element->collapsed == $value;
		});
	}

	protected function __dateCreated($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}
		
		return []; // TODO
	}

	protected function __dateUpdated($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}
		
		return []; // TODO
	}

	protected function __depth($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}
		
		return []; // TODO
	}

	protected function __descendantDist($elements, $value)
	{
		if(!$value || !$this->_descendant)
		{
			return $elements;
		}

		return array_filter($elements, function($element) use($value)
		{
			return $element->level <= $this->_descendant->level + $value;
		});
	}

	protected function __descendantOf($elements, $value)
	{
		$this->_descendant = $value;

		if(!$value)
		{
			return $elements;
		}

		$newElements = [];
		$found = false;

		foreach($elements as $element)
		{
			if($element === $value)
			{
				$found = true;
			}
			else if($found)
			{
				if($element->level > $value->level)
				{
					$newElements[] = $element;
				}
				else
				{
					break;
				}
			}
		}

		return $newElements;
	}

	protected function __fieldId($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return array_filter($elements, function($element) use($value)
		{
			return $element->fieldId == $value;
		});
	}

	protected function __fixedOrder()
	{
		// TODO
	}

	protected function __id($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return array_filter($elements, function($element) use($value)
		{
			return $element->id == $value;
		});
	}

	protected function __indexBy()
	{
		// TODO
	}

	protected function __level($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return array_filter($elements, function($element) use($value)
		{
			return $element->level == $value; // TODO Support comparison operators `>=4` etc
		});
	}

	protected function __limit($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return array_slice($elements, 0, $value);
	}

	protected function __nextSiblingOf($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return []; // TODO
	}

	protected function __offset($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return array_slice($elements, $value);
	}

	protected function __order()
	{
		// TODO
	}

	protected function __parentField($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return []; // TODO
	}

	protected function __parentOf($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}
		
		return []; // TODO
	}

	protected function __positionedAfter($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return []; // TODO
	}

	protected function __positionedBefore($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return []; // TODO
	}

	protected function __prevSiblingOf($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return []; // TODO
	}

	protected function __relatedTo($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return []; // TODO
	}

	protected function __ref($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return []; // TODO
	}

	protected function __search($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return []; // TODO
	}

	protected function __siblingOf($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return []; // TODO
	}

	protected function __status($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return array_filter($elements, function($element) use($value)
		{
			return $element->status == $value;
		});
	}

	protected function __type($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		if(!empty($elements))
		{
			$types = craft()->neo->getBlockTypesByFieldId($elements[0]->fieldId, 'handle');
			$type = isset($types[$value]) ? $types[$value] : false;

			if($type)
			{
				return array_filter($elements, function($element) use($type)
				{
					return $element->typeId == $type->id;
				});
			}
		}

		return [];
	}

	protected function __typeId($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return array_filter($elements, function($element) use($value)
		{
			return $element->typeId == $value;
		});
	}

	protected function __with($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return []; // TODO
	}
}
