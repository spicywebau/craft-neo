<?php
namespace Craft;

class Neo_CriteriaModel extends ElementCriteriaModel
{
	private $_allElements;
	private $_currentFilters = [];

	private $_ancestor = null;
	private $_descendant = null;

	protected $filterOrder = [
		'id',
		'fieldId',
		'status',
		'collapsed',
		'level',
		'type',

		'ancestorOf',
		'ancestorDist',
		'descendantOf',
		'descendantDist',
		'positionedAfter',
		'positionedBefore',
		'prevSiblingOf',
		'nextSiblingOf',
		'siblingOf',

		'relatedTo',
		'search',

		'offset',
		'limit',
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

			if(method_exists($this, $method))
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
		if(craft()->request->isLivePreview() && !empty($this->_allElements))
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

	private function _getBlock($block)
	{
		if(is_int($block))
		{
			$block = craft()->neo->getBlockById($block);
		}

		if($block instanceof Neo_BlockModel)
		{
			return $block;
		}

		return false;
	}

	private function _indexOfBlock($elements, Neo_BlockModel $block)
	{
		foreach($elements as $i => $element)
		{
			if($element->id === $block->id)
			{
				return $i;
			}
		}

		return -1;
	}

	private function _indexOfRootBlock($elements, Neo_BlockModel $block)
	{
		$index = $this->_indexOfBlock($elements, $block);

		if($block->level == 1)
		{
			return $index;
		}

		for($i = $index - 1; $i >= 0; $i--)
		{
			$element = $elements[$i];

			if($element->level == 1)
			{
				return $i;
			}
		}

		return -1;
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
		$value = $this->_getBlock($value);

		if(!$value)
		{
			return $elements;
		}

		$index = $this->_indexOfBlock($elements, $value);
		$total = count($elements);

		for($i = $index + 1; $i < $total; $i++)
		{
			$element = $elements[$i];

			if($element->level < $value->level)
			{
				break;
			}

			if($element->level == $value->level)
			{
				return [$element];
			}
		}

		return [];
	}

	protected function __offset($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return array_slice($elements, $value);
	}

	protected function __positionedAfter($elements, $value)
	{
		$value = $this->_getBlock($value);

		if(!$value)
		{
			return $elements;
		}

		$index = $this->_indexOfRootBlock($elements, $value);

		if($index < 0)
		{
			return [];
		}

		$root = $elements[$index];
		$nextRoot = $this->__nextSiblingOf($elements, $root);

		if(empty($nextRoot))
		{
			return [];
		}

		$nextIndex = $this->_indexOfBlock($elements, $nextRoot[0]);

		return array_slice($elements, $nextIndex);
	}

	protected function __positionedBefore($elements, $value)
	{
		$value = $this->_getBlock($value);

		if(!$value)
		{
			return $elements;
		}

		$index = $this->_indexOfRootBlock($elements, $value);

		if($index <= 0)
		{
			return [];
		}

		return array_slice($elements, 0, $index);
	}

	protected function __prevSiblingOf($elements, $value)
	{
		$value = $this->_getBlock($value);

		if(!$value)
		{
			return $elements;
		}

		$index = $this->_indexOfBlock($elements, $value);

		for($i = $index - 1; $i >= 0; $i--)
		{
			$element = $elements[$i];

			if($element->level < $value->level)
			{
				break;
			}

			if($element->level == $value->level)
			{
				return [$element];
			}
		}

		return [];
	}

	protected function __relatedTo($elements, $value)
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
		$value = $this->_getBlock($value);

		if(!$value)
		{
			return $elements;
		}

		$newElements = [];
		$mid = $this->_indexOfBlock($elements, $value);
		$total = count($elements);

		if($mid < 0)
		{
			return [];
		}

		// Previous siblings
		for($i = $mid - 1; $i >= 0; $i--)
		{
			$element = $elements[$i];

			if($element->level < $value->level)
			{
				break;
			}

			if($element->level == $value->level)
			{
				array_unshift($newElements, $element);
			}
		}

		// Next siblings
		for($i = $mid + 1; $i < $total; $i++)
		{
			$element = $elements[$i];

			if($element->level < $value->level)
			{
				break;
			}

			if($element->level == $value->level)
			{
				$newElements[] = $element;
			}
		}

		return $newElements;
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
}
