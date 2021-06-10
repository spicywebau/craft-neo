import $ from 'jquery'
import Garnish from 'garnish'

const BlockSort = Garnish.Drag.extend({

  $container: null,
  blocks: null,
  maxTopBlocks: 0,

  _draggeeBlocks: null,

  init (items, settings) {
    if (typeof settings === 'undefined' && $.isPlainObject(items)) {
      settings = items
      items = null
    }

    settings = $.extend({}, BlockSort.defaults, settings)
    settings.axis = Garnish.Y_AXIS

    this.base(items, settings)

    this.$container = settings.container
    this.blocks = []
    this.maxTopBlocks = settings.maxTopBlocks
  },

  getHelperTargetX () {
    return this.$draggee.offset().left
  },

  getHelperTargetY () {
    const magnet = this.settings.magnetStrength

    if (magnet !== 1) {
      const draggeeOffsetY = this.$draggee.offset().top
      return draggeeOffsetY + ((this.mouseY - this.mouseOffsetY - draggeeOffsetY) / magnet)
    }

    return this.base()
  },

  getBlockByElement ($block) {
    return this.blocks.find(block => block.$container.is($block))
  },

  getParentBlock (block) {
    const $parentBlock = block.$container.parent().closest('.ni_block')

    return $parentBlock.length > 0 ? this.getBlockByElement($parentBlock) : false
  },

  onDragStart () {
    const that = this

    this._draggeeBlocks = []
    this.$draggee.each(function () {
      that._draggeeBlocks.push(that.getBlockByElement(this))
    })

    this.base()
    this._calculateMidpoints()
  },

  onDrag () {
    const midpoint = this._getClosestMidpoint()

    if (midpoint) {
      this._moveDraggeeToBlock(midpoint.block, midpoint.type, midpoint.direction)
    }

    this.base()
  },

  onDragStop () {
    const that = this
    this.$draggee.each(function () {
      const $block = $(this)
      const block = that.getBlockByElement($block)
      const isRoot = $block.parent().is(that.$container)

      if (isRoot) {
        block.setLevel(0)
      } else {
        const parentBlock = that.getParentBlock(block)

        block.setLevel(parentBlock.getLevel() + 1)
      }

      $block.find('.ni_block').each(function () {
        const $childBlock = $(this)
        const childBlock = that.getBlockByElement($childBlock)
        const parentBlock = that.getParentBlock(childBlock)

        childBlock.setLevel(parentBlock.getLevel() + 1)
      })
    })

    this.returnHelpersToDraggees()

    this.base()
  },

  addBlock (block) {
    this.blocks.push(block)

    this.addItems(block.$container)
  },

  removeBlock (block) {
    this.blocks = this.blocks.filter(b => b !== block)

    this.removeItems(block.$container)
  },

  _getClosestMidpoint () {
    let minDistance = Number.MAX_VALUE
    let maxDistance = Number.MIN_VALUE
    let closest = null

    for (const midpoint of this._currentMidpoints) {
      if (midpoint.direction === BlockSort.DIRECTION_UP) {
        const compareY = this.mouseY - this.mouseOffsetY

        if (compareY < midpoint.position && midpoint.position < minDistance) {
          minDistance = midpoint.position
          closest = midpoint
        }
      } else {
        const compareY = this.mouseY - this.mouseOffsetY + this._draggeeBlockHeight

        if (compareY > midpoint.position && midpoint.position > maxDistance) {
          maxDistance = midpoint.position
          closest = midpoint
        }
      }
    }

    return closest
  },

  _calculateMidpoints () {
    const margin = 10

    this._draggeeBlockY = this.$draggee.offset().top
    this._draggeeBlockHeight = this.$draggee.height() + margin

    this._currentMidpoints = []

    for (const block of this.blocks) {
      if (block.$container.closest(this.$draggee).length === 0) {
        const midpoints = this._getBlockMidpoints(block)

        for (const type of Object.keys(midpoints)) {
          const position = midpoints[type]
          const direction = this._draggeeBlockY > position
            ? BlockSort.DIRECTION_UP
            : BlockSort.DIRECTION_DOWN

          this._currentMidpoints.push({
            block: block,
            position: position,
            type: type,
            direction: direction
          })
        }
      }
    }

    const endMidpoint = this.$container.offset().top + this.$container.height() + (margin / 2)
    this._currentMidpoints.push({
      block: null,
      position: endMidpoint,
      type: BlockSort.TYPE_END,
      direction: BlockSort.DIRECTION_DOWN
    })
  },

  _getBlockMidpoints (block) {
    const midpoints = {}

    const border = 1
    const margin = 10
    const padding = 14

    const isAncestorCollapsed = (block.$container.parent().closest('.ni_block.is-collapsed').length > 0)

    if (!isAncestorCollapsed) {
      const offset = block.$container.offset().top

      const isExpanded = block.isExpanded()

      const blockHeight = block.$container.height()
      const topbarHeight = block.$topbarContainer.height()
      const childrenHeight = isExpanded ? block.$childrenContainer.height() : 0
      const preChildrenContentHeight = !(isExpanded && block.$contentContainer.length > 0)
        ? 0
        : block.$childrenContainer.length > 0
          ? block.$childrenContainer.offset().top - block.$contentContainer.offset().top
          : block.$contentContainer.height()

      const parentBlock = this.getParentBlock(block)

      if (!parentBlock || this._validateDraggeeChildren(parentBlock)) {
        midpoints[BlockSort.TYPE_CONTENT] = offset + (topbarHeight + preChildrenContentHeight) / 2
      }

      if (childrenHeight > 0 && block.isExpanded() && this._validateDraggeeChildren(block)) {
        const buttonsHeight = block.getButtons().$container.height()
        midpoints[BlockSort.TYPE_CHILDREN] = offset + blockHeight - border - (padding + buttonsHeight + margin) / 2
      }
    }

    return midpoints
  },

  _moveDraggeeToBlock: function (block, type = BlockSort.TYPE_CONTENT, direction = BlockSort.DIRECTION_DOWN) {
    const parentBlock = block ? this.getParentBlock(block) : null
    const validChild = this._validateDraggeeChildren(parentBlock)

    switch (type) {
      case BlockSort.TYPE_CHILDREN:
        if (this.$draggee.closest(block.$container).length === 0) {
          block.$blocksContainer.append(this.$draggee)
        } else if (validChild) {
          block.$container.after(this.$draggee)
        }
        break
      case BlockSort.TYPE_END:
        if (validChild) {
          this.$container.append(this.$draggee)
        }
        break
      default:
      {
        if (direction === BlockSort.DIRECTION_UP) {
          if (validChild) {
            block.$container.before(this.$draggee)
          }
        } else {
          if (block.getBlockType().isParent() && block.isExpanded() && this._validateDraggeeChildren(block)) {
            block.$blocksContainer.prepend(this.$draggee)
          } else if (validChild) {
            block.$container.after(this.$draggee)
          }
        }
      }
    }

    this._updateHelperAppearance()
    this._calculateMidpoints()
  },

  _validateDraggeeChildren (block) {
    // If any of the draggee blocks would exceed the field's max levels, we can't allow the move
    const field = block ? block.getField() : this._draggeeBlocks[0].getField()
    const maxLevels = field.getMaxLevels()

    if (maxLevels > 0) {
      const parentLevel = block ? block.getLevel() : -1
      const firstDraggeeLevel = this._draggeeBlocks[0].getLevel()
      const blockExceedsMax = b => b.getLevel() - firstDraggeeLevel + parentLevel + 1 >= maxLevels
      const blockOrDescendantExceedsMax = b => {
        const descendants = b.getChildren(field.getBlocks(), true)

        return blockExceedsMax(b) || descendants.some(blockOrDescendantExceedsMax)
      }

      if (this._draggeeBlocks.filter(blockOrDescendantExceedsMax).length > 0) {
        return false
      }
    }

    // If no block, then we're checking at the top level
    if (!block) {
      const that = this
      const topBlocks = this.$container.children('.ni_block:not(.is-disabled)')
      let topBlocksCount = topBlocks.length

      for (const draggeeBlock of this._draggeeBlocks) {
        // Is this block allowed at the top level?
        if (!draggeeBlock.getBlockType().getTopLevel()) {
          return false
        }
      }

      // If the block is already at the top level, don't count it for max top level block check purposes
      topBlocks.each(function () {
        if (that._draggeeBlocks.includes(that.getBlockByElement(this))) {
          topBlocksCount--
        }
      })

      // If this move would exceed the field's max top level blocks, we can't allow it
      if (this.maxTopBlocks > 0 && topBlocksCount >= this.maxTopBlocks) {
        return false
      }

      return true
    }

    const blockType = block.getBlockType()
    const maxChildBlocks = blockType.getMaxChildBlocks()

    const blockChildren = block.$childrenContainer.children('.ni_blocks').children('.ni_block')
    let blockChildCount = blockChildren.length
    const blockChildrenWithoutDraggees = []
    const that = this

    // If the block is already a child block, don't count it for validation purposes
    blockChildren.each(function () {
      const childBlock = that.getBlockByElement(this)

      if (that._draggeeBlocks.includes(childBlock)) {
        blockChildCount--
      } else {
        blockChildrenWithoutDraggees.push(childBlock)
      }
    })

    // Check whether the move would make the potential parent block exceed its max child blocks
    if (maxChildBlocks > 0) {
      // Exceeds max child blocks?  Can't move it here, then
      if (blockChildCount >= maxChildBlocks) {
        return false
      }
    }

    const checkedDraggeeBlocks = []

    for (const draggeeBlock of this._draggeeBlocks) {
      // Check whether the block is a valid child block for the parent's block type
      if (!blockType.isValidChildBlock(draggeeBlock)) {
        return false
      }

      // Check whether this move would cause any max sibling block type violations, unless we
      // checked this block already
      if (checkedDraggeeBlocks.includes(draggeeBlock)) {
        continue
      }

      const draggeeBlockType = draggeeBlock.getBlockType()
      const maxSiblingBlocks = draggeeBlockType.getMaxSiblingBlocks()

      // Also don't bother checking for max sibling block type violations if max sibling
      // blocks hasn't been set
      if (maxSiblingBlocks === 0) {
        continue
      }

      const draggeeBlocksOfType = this._draggeeBlocks.filter(b => b.getBlockType().getHandle() === draggeeBlockType.getHandle())
      const siblingBlocksOfType = blockChildrenWithoutDraggees.filter(b => b.getBlockType().getHandle() === draggeeBlockType.getHandle())

      if (siblingBlocksOfType.length + draggeeBlocksOfType.length > maxSiblingBlocks) {
        return false
      }

      checkedDraggeeBlocks.push(...draggeeBlocksOfType)
    }

    return true
  },

  _updateHelperAppearance () {
    for (const $helper of this.helpers) {
      const id = $helper.data('neo-b-id')
      const block = this.blocks.find(b => b.$container.data('neo-b-id') === id)

      $helper.css({
        width: block.$container.width() + 1,
        height: block.$container.height()
      })
    }
  }

}, {

  TYPE_CONTENT: 'content',
  TYPE_CHILDREN: 'children',
  TYPE_END: 'end',
  DIRECTION_UP: 'up',
  DIRECTION_DOWN: 'down',

  defaults: {
    container: null,
    magnetStrength: 1
  }
})

export default BlockSort
