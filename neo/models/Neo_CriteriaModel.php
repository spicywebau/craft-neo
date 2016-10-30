<?php
namespace Craft;

/**
 * Class Neo_CriteriaModel
 * An extension to the ElementCriteriaModel that supports querying in Live Preview mode.
 *
 * @package Craft
 */
class Neo_CriteriaModel extends ElementCriteriaModel
{
	// Private properties

	private $_allElements;
	private $_currentFilters = [];
	private $_ancestor = null;
	private $_descendant = null;
	private $_useMemoized = false;


	// Protected properties

	/**
	 * Defines the order in which Live Preview filters are run.
	 * This is important as changing the order can affect the results of certain filters, such as limit and offset. It
	 * also allows increased performance by allowing filters with high filtering potential to be run earlier.
	 *
	 * @var array
	 */
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


	// Public methods

	/**
	 * Initialises the criteria model, forcing the element type to be a Neo block.
	 *
	 * @param array|null $attributes
	 */
	public function __construct($attributes = null)
	{
		$elementType = craft()->elements->getElementType(Neo_ElementType::NeoBlock);

		parent::__construct($attributes, $elementType);
	}

	/**
	 * Returns a clone of the criteria model.
	 *
	 * @return Neo_CriteriaModel
	 */
	public function copy()
	{
		$copy = parent::copy();
		$copy->setState($this);

		return $copy;
	}

	/**
	 * Returns the total number of elements matched by this criteria.
	 * Fixes live preview mode which broke in Craft 2.6.2793 due to this method. If live preview mode is detected, it
	 * uses the old method which worked in live preview mode.
	 *
	 * @return int
	 */
	public function count()
	{
		if(craft()->neo->isPreviewMode() || $this->isUsingMemoized())
		{
			return count($this->find());
		}

		return parent::count();
	}

	/**
	 * Sets a filter value for the criteria model, then reruns Live Preview filtering.
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return bool
	 */
	public function setAttribute($name, $value)
	{
		if(parent::setAttribute($name, $value))
		{
			$method = '__' . $name;

			// Only bother setting and rerunning the filter if there exists a filtering method for it.
			if(method_exists($this, $method))
			{
				$this->_currentFilters[$name] = $value;

				$this->_runCriteria();
			}

			return true;
		}

		return false;
	}

	/**
	 * Sets all the elements (blocks) to be filtered against in Live Preview mode.
	 * This becomes the main data source for Live Preview, instead of the database.
	 *
	 * @param array $elements
	 */
	public function setAllElements($elements)
	{
		$this->_allElements = $elements;

		$this->_runCriteria();
	}

	/**
	 * Whether the criteria model is operating on a memoized data set.
	 *
	 * @return bool
	 */
	public function isUsingMemoized()
	{
		return $this->_useMemoized;
	}

	/**
	 * Sets whether the criteria model operates on a memoized data set.
	 *
	 * @param bool|true $use - Either a boolean to enable/disable, or a dataset to use (which results in enabling)
	 */
	public function useMemoized($use = true)
	{
		if(is_array($use))
		{
			$this->setAllElements($use);
			$use = true;
		}

		$this->_useMemoized = $use;
	}


	// Protected methods

	/**
	 * Returns all saved, private settings for the criteria model, to be used when copying.
	 *
	 * @return array
	 */
	protected function getState()
	{
		return [
			'elements' => $this->_allElements,
			'filters' => $this->_currentFilters,
			'ancestorOf' => $this->_ancestor,
			'descendantOf' => $this->_descendant,
		];
	}

	/**
	 * Sets all saved, private settings to the criteria model, to be used when copying.
	 *
	 * @param Neo_CriteriaModel,array $state
	 */
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


	// Private methods

	/**
	 * Runs Live Preview filtering and saves it's output to the criteria model.
	 */
	private function _runCriteria()
	{
		if((craft()->neo->isPreviewMode() || $this->isUsingMemoized()) && !empty($this->_allElements))
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

	/**
	 * Returns a block model given an ID, or an actual block model.
	 * Saves having to check if some value is an integer or a block model instance.
	 *
	 * @param Neo_BlockModel,int $block
	 * @return Neo_BlockModel,bool
	 */
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

	/**
	 * Finds the position of a block inside a list of blocks.
	 * It checks using the block's ID, so the passed block doesn't have to be strictly the same instance it matches to.
	 * If no match is found, `-1` is returned.
	 *
	 * @param array $elements
	 * @param Neo_BlockModel $block
	 * @return int
	 */
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

	/**
	 * Finds the position of the block who is it's furthest ancestor to the passed block.
	 * If no match is found, `-1` is returned.
	 *
	 * @param array $elements
	 * @param Neo_BlockModel $block
	 * @return int
	 */
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

	/**
	 * Compares an integer against a criteria model integer comparison string, or integer.
	 * Takes in comparison inputs such as `1`, `'>=23'`, and `'< 4'`.
	 *
	 * @param int $value
	 * @param int,string $comparison
	 * @return bool
	 */
	private function _compareInt($value, $comparison)
	{
		if(is_int($comparison))
		{
			return $value == $comparison;
		}

		if(is_string($comparison))
		{
			$matches = [];
			preg_match('/([><]=?)\\s*([0-9]+)/', $comparison, $matches);

			if(count($matches) == 3)
			{
				$comparator = $matches[1];
				$comparison = (int) $matches[2];

				switch($comparator)
				{
					case '>': return $value > $comparison;
					case '<': return $value < $comparison;
					case '>=': return $value >= $comparison;
					case '<=': return $value <= $comparison;
				}
			}
		}

		return false;
	}


	// Live Preview methods
	// These methods must be prefixed with two underscores. They will automatically be detected and used when filtering.

	/**
	 *
	 * @param array $elements
	 * @param int $value
	 * @return array
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

	/**
	 *
	 * @param array $elements
	 * @param Neo_BlockModel $value
	 * @return array
	 */
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

	/**
	 *
	 * @param array $elements
	 * @param Neo_BlockModel $value
	 * @return array
	 */
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

	/**
	 *
	 * @param array $elements
	 * @param int $value
	 * @return array
	 */
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

	/**
	 *
	 * @param array $elements
	 * @param Neo_BlockModel $value
	 * @return array
	 */
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

	/**
	 *
	 * @param array $elements
	 * @param int $value
	 * @return array
	 */
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

	/**
	 *
	 * @param array $elements
	 * @param int $value
	 * @return array
	 */
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

	/**
	 *
	 * @param array $elements
	 * @param int $value
	 * @return array
	 */
	protected function __level($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return array_filter($elements, function($element) use($value)
		{
			return $this->_compareInt($element->level, $value);
		});
	}

	/**
	 *
	 * @param array $elements
	 * @param int $value
	 * @return array
	 */
	protected function __limit($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return array_slice($elements, 0, $value);
	}

	/**
	 *
	 * @param array $elements
	 * @param Neo_BlockModel,int $value
	 * @return array
	 */
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

	/**
	 *
	 * @param array $elements
	 * @param int $value
	 * @return array
	 */
	protected function __offset($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return array_slice($elements, $value);
	}

	/**
	 *
	 * @param array $elements
	 * @param Neo_BlockModel,int $value
	 * @return array
	 */
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

	/**
	 *
	 * @param array $elements
	 * @param Neo_BlockModel,int $value
	 * @return array
	 */
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

	/**
	 *
	 * @param array $elements
	 * @param Neo_BlockModel,int $value
	 * @return array
	 */
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

	/**
	 *
	 * @param array $elements
	 * @param $value
	 * @return array
	 */
	protected function __relatedTo($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return []; // TODO
	}

	/**
	 *
	 * @param array $elements
	 * @param $value
	 * @return array
	 */
	protected function __search($elements, $value)
	{
		if(!$value)
		{
			return $elements;
		}

		return []; // TODO
	}

	/**
	 *
	 * @param array $elements
	 * @param Neo_BlockModel,int $value
	 * @return array
	 */
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

	/**
	 *
	 * @param array $elements
	 * @param Neo_BlockModel $value
	 * @return array
	 */
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

	/**
	 *
	 * @param array $elements
	 * @param string $value
	 * @return array
	 */
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

	/**
	 *
	 * @param array $elements
	 * @param int $value
	 * @return array
	 */
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
