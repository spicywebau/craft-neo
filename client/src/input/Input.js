import $ from 'jquery'
import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import BlockSort from './BlockSort'
import BlockType from './BlockType'
import Group from './Group'
import Block from './Block'
import Buttons from './Buttons'

import './styles/input.scss'

const _defaults = {
  name: null,
  namespace: [],
  blockTypes: [],
  groups: [],
  blocks: [],
  inputId: null,
  maxBlocks: 0,
  maxTopBlocks: 0,
  minLevels: 0,
  maxLevels: 0
}

export default Garnish.Base.extend({

  _templateNs: [],
  _name: null,
  _siteId: null,

  init (settings = {}) {
    settings = Object.assign({}, _defaults, settings)

    this._templateNs = NS.parse(settings.namespace)
    this._blockTypes = []
    this._groups = settings.groups.map(gInfo => new Group(gInfo))
    this._blocks = []
    this._name = settings.name
    this._maxBlocks = settings.maxBlocks
    this._maxTopBlocks = settings.maxTopBlocks
    this._minLevels = settings.minLevels
    this._maxLevels = settings.maxLevels
    this._ownerId = null
    this._showBlockTypeHandles = settings.showBlockTypeHandles

    const ownerIdElement = $('[name="setId"], [name="entryId"], [name="categoryId"]')
    if (ownerIdElement.length) {
      this._ownerId = ownerIdElement.val()
    }

    this.$container = $('#' + settings.inputId)

    const setGroupIds = {}
    this._groups.forEach(group => {
      setGroupIds[group.getId()] = true
    })

    const tempBlockTypes = {}

    for (const btInfo of settings.blockTypes) {
      // Filter out the block type if its group isn't included
      if (btInfo.groupId === null || typeof setGroupIds[btInfo.groupId] !== 'undefined') {
        const blockType = new BlockType(btInfo)
        this._blockTypes.push(blockType)
        tempBlockTypes[blockType.getHandle()] = blockType
      }
    }

    this.$form = this.$container.closest('form')
    this._siteId = this.$form.find('input[name="siteId"]').val()

    const $neo = this.$container.find('[data-neo]')
    this.$blocksContainer = $neo.filter('[data-neo="container.blocks"]')
    this.$buttonsContainer = $neo.filter('[data-neo="container.buttons"]')

    this._buttons = new Buttons({
      blockTypes: this.getBlockTypes(true),
      groups: this.getGroups(),
      maxBlocks: this.getMaxBlocks(),
      maxTopBlocks: this.getMaxTopBlocks()
    })

    this.$buttonsContainer.append(this._buttons.$container)
    this._buttons.on('newBlock', e => this['@newBlock'](e))
    this._buttons.initUi()

    this._blockSort = new BlockSort({
      container: this.$blocksContainer,
      handle: '[data-neo-b$=".button.move"]',
      maxTopBlocks: this.getMaxTopBlocks(),
      filter: () => {
        // Only return all the selected items if the target item is selected
        if (this._blockSort.$targetItem.hasClass('is-selected')) {
          return this.blockSelect.getSelectedItems()
        }

        return this._blockSort.$targetItem
      },
      collapseDraggees: true,
      magnetStrength: 4,
      helperLagBase: 1.5,
      helperOpacity: 0.9,
      onDragStop: () => {
        this._updateBlockOrder()
        this._updateButtons()
      }
    })

    this.blockSelect = new Garnish.Select(this.$blocksContainer, null, {
      multi: true,
      vertical: true,
      handle: '> .ni_block_topbar [data-neo-b$=".select"]',
      checkboxMode: true,
      selectedClass: 'is-selected sel'
    })

    this.$blocksContainer.find('.ni_block').each((i, blockDiv) => {
      const $block = $(blockDiv)
      const bInfo = {}
      bInfo.id = $block.attr('data-neo-b-id')
      bInfo.sortOrder = i
      bInfo.collapsed = $block.hasClass('is-collapsed')
      bInfo.enabled = !!$block.find(`[data-neo-b="${bInfo.id}.input.enabled"]`).val()
      bInfo.level = parseInt($block.find(`[data-neo-b="${bInfo.id}.input.level"]`).val())
      bInfo.field = this
      bInfo.namespace = [...this._templateNs, bInfo.id]

      const blockTypeHandle = $block.find(`[data-neo-b="${bInfo.id}.input.type"]`).val()
      const blockType = tempBlockTypes[blockTypeHandle]

      // If the block type data isn't there, it's been filtered out and the blocks shouldn't be included
      if (typeof blockType === 'undefined') {
        $block.remove()
        return
      }

      bInfo.blockType = new BlockType({
        id: blockType.getId(),
        fieldLayoutId: blockType.getFieldLayoutId(),
        name: blockType.getName(),
        handle: blockType.getHandle(),
        enabled: blockType.getEnabled(),
        description: blockType.getDescription(),
        maxBlocks: blockType.getMaxBlocks(),
        maxSiblingBlocks: blockType.getMaxSiblingBlocks(),
        maxChildBlocks: blockType.getMaxChildBlocks(),
        childBlocks: blockType.getChildBlocks(),
        topLevel: blockType.getTopLevel(),
        hasChildBlocksUiElement: blockType.hasChildBlocksUiElement()
      })
      bInfo.buttons = new Buttons({
        items: blockType.getChildBlockItems(this.getItems()),
        maxBlocks: this.getMaxBlocks()
      })
      bInfo.showButtons = !this.atMaxLevels(bInfo.level)

      const block = new Block(bInfo)
      block.initUi(false)
      this._setBlockEvents(block)

      this._blocks.push(block)
      this._blockSort.addBlock(block)
      this.blockSelect.addItems(block.$container)
    })

    this._updateBlockOrder()
    this._updateBlockChildren()
    this._updateButtons()

    // Make sure menu states (for pasting blocks) are updated when changing browser tabs
    this.addListener(document, 'visibilitychange.input', () => this._updateButtons())

    this.addListener(this.$container, 'resize', () => this.updateResponsiveness())

    const serialized = typeof this.$form.data('serializer') === 'function'
      ? this.$form.data('serializer')()
      : this.$form.serialize()
    this.$form.data('initialSerializedValue', serialized)

    // Add error highlight for Matrix fields within Neo
    this._setMatrixClassErrors()
    this._setBlockTypeClassErrors()

    this._blocks
      .filter(block => !block.isExpanded())
      .forEach(block => block.updatePreview())

    this.trigger('afterInit')
  },

  getName () {
    return this._name
  },

  updateResponsiveness () {
    for (const block of this._blocks) {
      block.updateResponsiveness()
      block.getButtons()?.updateResponsiveness()
    }

    this._buttons.updateResponsiveness()
    this._tempButtons?.updateResponsiveness()
  },

  addBlock (block, index = -1, level = 1, animate = null) {
    const blockCount = this._blocks.length
    index = index >= 0 ? Math.max(0, Math.min(index, blockCount)) : blockCount
    animate = !Garnish.prefersReducedMotion() && (typeof animate === 'boolean' ? animate : true)

    const prevBlock = index > 0 ? this._blocks[index - 1] : false
    const nextBlock = index < blockCount ? this._blocks[index] : false

    if (!prevBlock) {
      this.$blocksContainer.prepend(block.$container)
    } else {
      const minLevel = nextBlock ? nextBlock.getLevel() : 1
      const maxLevel = prevBlock.getLevel() + (prevBlock.getBlockType().isParent() ? 1 : 0)

      level = Math.max(minLevel, Math.min(level, maxLevel))

      const prevBlockOnLevel = this._findPrevBlockOnLevel(index, level)

      if (prevBlockOnLevel) {
        prevBlockOnLevel.$container.after(block.$container)
      } else {
        prevBlock.$blocksContainer.prepend(block.$container)
      }
    }

    block.setLevel(level)

    this._blocks.push(block)
    this._blockSort.addBlock(block)
    this.blockSelect.addItems(block.$container)

    block.initUi()
    this._setBlockEvents(block)
    this._destroyTempButtons()
    this._updateBlockOrder()
    this._updateBlockChildren()
    this._updateButtons()

    if (animate) {
      block.$container
        .css({
          opacity: 0,
          marginBottom: -(block.$container.outerHeight())
        })
        .velocity({
          opacity: 1,
          marginBottom: 10
        }, 'fast', e => Garnish.requestAnimationFrame(() => Garnish.scrollContainerToElement(block.$container)))
    }

    this.trigger('addBlock', {
      block,
      index
    })
  },

  removeBlock (block, animate = null, _delayAnimate = null) {
    this.$form.data('elementEditor')?.pause()

    animate = !Garnish.prefersReducedMotion() && (typeof animate === 'boolean' ? animate : true)
    _delayAnimate = typeof _delayAnimate === 'boolean' ? _delayAnimate : false

    const childBlocks = this._findChildBlocks(this._blocks.indexOf(block))
    for (const childBlock of childBlocks) {
      this.removeBlock(childBlock, true, true)
    }

    block.off('.input')

    this._blocks = this._blocks.filter(b => b !== block)
    this._blockSort.removeItems(block.$container)
    this.blockSelect.removeItems(block.$container)

    this._destroyTempButtons()
    this._updateButtons()

    const finishTheRemoval = () => {
      block.$container.remove()
      this._updateBlockChildren()
      this.$form.data('elementEditor')?.resume()
    }

    if (animate) {
      block.$container
        .css({
          opacity: 1,
          marginBottom: 10
        })
        .velocity({
          opacity: 0,
          marginBottom: _delayAnimate ? 10 : -(block.$container.outerHeight())
        }, 'fast', _ => finishTheRemoval())
    } else {
      finishTheRemoval()
    }

    block.destroy()

    this.trigger('removeBlock', {
      block
    })
  },

  _setBlockEvents (block) {
    block.on('removeBlock.input', _ => {
      if (this.getSelectedBlocks().length > 1) {
        if (window.confirm(Craft.t('neo', 'Are you sure you want to delete the selected blocks?'))) {
          this._blockBatch(block, b => this.removeBlock(b))
        }
      } else {
        this.removeBlock(block)
      }
    })
    block.on('toggleEnabled.input', e => this._blockBatch(block, b => b.toggleEnabled(e.enabled)))
    block.on('toggleExpansion.input', e => this._blockBatch(block, b => b.toggleExpansion(e.expanded)))
    block.on('moveUpBlock.input', _ => this._moveBlock(block, 'up'))
    block.on('moveDownBlock.input', _ => this._moveBlock(block, 'down'))
    block.on('newBlock.input', e => this['@newBlock'](Object.assign(e, { index: this._getNextBlockIndex(block) })))
    block.on('addBlockAbove.input', e => this['@addBlockAbove'](e))
    block.on('copyBlock.input', e => this['@copyBlock'](e))
    block.on('pasteBlock.input', e => this['@pasteBlock'](e))
    block.on('duplicateBlock.input', e => this['@duplicateBlock'](e))
    block.on('change.input', () => this.trigger('change', { block }))
  },

  _moveBlock (block, direction, animate = true) {
    if (!['up', 'down'].includes(direction)) {
      return
    }

    this.$form.data('elementEditor')?.pause()

    const siblings = block.getSiblings(this.getBlocks())
    const index = siblings.indexOf(block)
    const moveUp = index > 0 && direction === 'up'
    const moveDown = index < siblings.length - 1 && direction === 'down'

    if (index === -1 || moveUp === moveDown) {
      return
    }

    const animateMove = !Garnish.prefersReducedMotion() && (typeof animate === 'boolean' ? animate : true)
    const $block = block.$container

    const startTheMove = () => {
      $block.detach()

      if (moveUp) {
        siblings[index - 1].$container.before($block)
      } else {
        siblings[index + 1].$container.after($block)
      }
    }

    const finishTheMove = () => {
      this._updateBlockOrder()
      this._updateButtons()
      this.$form.data('elementEditor')?.resume()
    }

    if (animateMove) {
      $block
        .css({
          opacity: 1,
          marginBottom: 10
        })
        .velocity({
          opacity: 0,
          marginBottom: -($block.outerHeight())
        }, 'fast', _ => {
          startTheMove()

          $block
            .css({
              opacity: 0,
              marginBottom: -($block.outerHeight())
            })
            .velocity({
              opacity: 1,
              marginBottom: 10
            }, 'fast', _ => {
              finishTheMove()
              Garnish.requestAnimationFrame(() => Garnish.scrollContainerToElement($block))
            })
        })
    } else {
      startTheMove()
      finishTheMove()
    }
  },

  getBlockByElement ($block) {
    return this._blocks.find(block => block.$container.is($block))
  },

  getBlocks (level = 0) {
    return level > 0 ? this._blocks.filter(b => b.getLevel() === level) : Array.from(this._blocks)
  },

  getBlockTypeById (id) {
    return this._blockTypes.find(bt => bt.getId() === id)
  },

  getBlockTypeByHandle (handle) {
    return this._blockTypes.find(bt => bt.getHandle() === handle)
  },

  getBlockTypes (topLevelOnly) {
    topLevelOnly = typeof topLevelOnly === 'boolean' ? topLevelOnly : false

    return topLevelOnly
      ? this._blockTypes.filter(bt => bt.getTopLevel())
      : Array.from(this._blockTypes)
  },

  getGroups () {
    return Array.from(this._groups)
  },

  getItems () {
    return [...this.getBlockTypes(), ...this.getGroups()].sort((a, b) => a.getSortOrder() - b.getSortOrder())
  },

  getMaxBlocks () {
    return this._maxBlocks
  },

  getMaxTopBlocks () {
    return this._maxTopBlocks
  },

  getMinLevels () {
    return this._minLevels
  },

  getMaxLevels () {
    return this._maxLevels
  },

  atMaxLevels (level) {
    return this._maxLevels > 0 && level + 1 > this._maxLevels
  },

  getSelectedBlocks () {
    const $selectedBlocks = this.blockSelect.getSelectedItems()
    return this._blocks.filter(block => block.$container.closest($selectedBlocks).length > 0)
  },

  _setMatrixClassErrors () {
    // TODO: will need probably need to find a method within php instead of JS
    // temp solution for now.
    const matrixErrors = $('.ni_block_body .matrix-field .input.errors')

    matrixErrors.each(function () {
      const _this = $(this)
      const tabContainer = _this.closest('.ni_block_content_tab')
      const tabData = tabContainer.data('neo-b-info')
      const closestContainer = _this.closest('.ni_block')
      const bar = closestContainer.find('.tabs .tab[data-neo-b-info="' + tabData + '"]')

      if (bar.length) {
        bar.addClass('has-errors')
      }
    })
  },

  _setBlockTypeClassErrors () {
    const tabErrors = $('.ni_block .tab.has-errors')

    tabErrors.each(function () {
      const parents = tabErrors.parents('.ni_block.is-collapsed')

      parents.each(function () {
        const _this = $(this)
        _this.find('> .ni_block_topbar .title .blocktype').addClass('has-errors')
      })
    })
  },

  _updateBlockOrder () {
    const blocks = []

    this.$blocksContainer.find('.ni_block').each((index, element) => {
      const block = this.getBlockByElement(element)
      blocks.push(block)
    })

    this._blocks = blocks
    this.trigger('updateBlockOrder')
    this.trigger('change', { block: null })
  },

  _updateBlockChildren () {
    for (const block of this._blocks) {
      const children = block.$blocksContainer.children('.ni_block')
      const collapsedCount = Math.min(children.length, 8) // Any more than 8 and it's a little too big
      const collapsedChildren = []

      for (let i = 0; i < collapsedCount; i++) {
        collapsedChildren.push('<div class="ni_block_collapsed-children_child"></div>')
      }

      block.$collapsedChildrenContainer.html(collapsedChildren.join(''))
    }
  },

  _checkMaxChildren (block) {
    if (!block) {
      return true
    }

    const blockType = block.getBlockType()
    const maxChildren = blockType.getMaxChildBlocks()

    if (maxChildren > 0) {
      const children = this._findChildBlocks(block)

      return children.length < maxChildren
    }

    return true
  },

  _updateButtons () {
    const blocks = this.getBlocks()
    this._buttons.updateButtonStates(blocks)
    this._tempButtons?.updateButtonStates(blocks, this._checkMaxChildren(this._tempButtonsBlock))

    for (const block of blocks) {
      block.updateActionsMenu()
      block.getButtons()?.updateButtonStates(blocks, this._checkMaxChildren(block), block)
      block.toggleShowButtons(!this.atMaxLevels(block.getLevel()))
    }
  },

  _blockBatch (block, callback) {
    const blocks = block.isSelected() ? this.getSelectedBlocks() : [block]

    for (const b of blocks) {
      callback(b)
    }
  },

  _destroyTempButtons (animate = null) {
    animate = !Garnish.prefersReducedMotion() && (typeof animate === 'boolean' ? animate : true)

    if (this._tempButtons) {
      const buttons = this._tempButtons
      buttons.off('newBlock')

      if (animate) {
        buttons.$container
          .css({
            opacity: 1,
            marginBottom: 10
          })
          .velocity({
            opacity: 0,
            marginBottom: -(buttons.$container.outerHeight())
          }, 'fast', e => buttons.$container.remove())
      } else {
        buttons.$container.remove()
      }

      this._tempButtons = null
      this._tempButtonsBlock = null
    }
  },

  _findPrevBlockOnLevel (index, level) {
    if (index instanceof Block) {
      index = this._blocks.indexOf(index)
    }

    const blocks = this._blocks

    let block = blocks[--index]
    let lowestLevel = Number.MAX_VALUE

    while (block) {
      const blockLevel = block.getLevel()

      if (blockLevel < lowestLevel) {
        if (blockLevel === level) {
          return block
        }

        lowestLevel = blockLevel
      }

      block = this._blocks[--index]
    }

    return false
  },

  _findChildBlocks (index, descendants = null) {
    if (index instanceof Block) {
      index = this._blocks.indexOf(index)
    }

    descendants = (typeof descendants === 'boolean' ? descendants : false)
    const block = this._blocks[index]

    return block ? block.getChildren(this._blocks, descendants) : []
  },

  _findParentBlock (index) {
    if (index instanceof Block) {
      index = this._blocks.indexOf(index)
    }

    const blocks = this._blocks
    const block = blocks[index]

    if (block) {
      const level = block.getLevel()

      if (level > 1) {
        let i = index
        let currentBlock = block

        while (currentBlock && currentBlock.getLevel() >= level) {
          currentBlock = blocks[--i]
        }

        return currentBlock
      }
    }

    return null
  },

  _getNextBlockIndex (index) {
    if (index instanceof Block) {
      index = this._blocks.indexOf(index)
    }

    const descendants = this._findChildBlocks(index, true)
    const lastDescendant = descendants[descendants.length - 1]

    return (lastDescendant ? this._blocks.indexOf(lastDescendant) : index) + 1
  },

  _duplicate (data, block) {
    this.$form.data('elementEditor')?.pause()

    const animate = !Garnish.prefersReducedMotion()
    const $spinner = $(`<div class="ni_spinner">${animate ? '<div class="spinner"></div>' : 'Loading block'}</div>`)

    block.$container.after($spinner)

    let spinnerComplete = false
    let spinnerCallback = function () {}

    if (animate) {
      $spinner
        .css({
          opacity: 0,
          marginBottom: -($spinner.outerHeight())
        })
        .velocity({
          opacity: 1,
          marginBottom: 10
        }, 'fast', () => {
          spinnerComplete = true
          spinnerCallback()
        })
    } else {
      spinnerComplete = true
      spinnerCallback()
    }

    Craft.postActionRequest('neo/input/render-blocks', data, e => {
      if (e.success && e.blocks.length > 0) {
        const newBlocks = []

        for (const renderedBlock of e.blocks) {
          const newId = Block.getNewId()

          const blockType = this.getBlockTypeById(renderedBlock.type)
          const newBlockType = new BlockType({
            id: blockType.getId(),
            fieldLayoutId: blockType.getFieldLayoutId(),
            name: blockType.getName(),
            handle: blockType.getHandle(),
            maxBlocks: blockType.getMaxBlocks(),
            maxSiblingBlocks: blockType.getMaxSiblingBlocks(),
            maxChildBlocks: blockType.getMaxChildBlocks(),
            childBlocks: blockType.getChildBlocks(),
            topLevel: blockType.getTopLevel(),
            hasChildBlocksUiElement: blockType.hasChildBlocksUiElement(),
            tabs: renderedBlock.tabs
          })

          const newButtons = new Buttons({
            items: newBlockType.getChildBlockItems(this.getItems()),
            maxBlocks: this.getMaxBlocks()
          })

          const newBlock = new Block({
            namespace: [...this._templateNs, newId],
            field: this,
            blockType: newBlockType,
            id: newId,
            level: renderedBlock.level | 0,
            buttons: newButtons,
            enabled: !!renderedBlock.enabled,
            collapsed: !!renderedBlock.collapsed,
            showButtons: !this.atMaxLevels(renderedBlock.level | 0),
            showBlockTypeHandle: this._showBlockTypeHandles
          }, true)

          newBlocks.push(newBlock)
        }

        spinnerCallback = () => {
          let newIndex = this._getNextBlockIndex(block)

          for (const newBlock of newBlocks) {
            this.addBlock(newBlock, newIndex++, newBlock.getLevel(), false)
          }

          if (animate) {
            const firstBlock = newBlocks[0]

            firstBlock.$container
              .css({
                opacity: 0,
                marginBottom: $spinner.outerHeight() - firstBlock.$container.outerHeight() + 10
              })
              .velocity({
                opacity: 1,
                marginBottom: 10
              }, 'fast', _ => Garnish.requestAnimationFrame(() => Garnish.scrollContainerToElement(firstBlock.$container)))
          }

          $spinner.remove()
          this.$form.data('elementEditor')?.resume()
        }

        if (spinnerComplete) {
          spinnerCallback()
        }
      }
    })
  },

  '@newBlock' (e) {
    const blockId = Block.getNewId()
    const block = new Block({
      namespace: [...this._templateNs, blockId],
      field: this,
      blockType: e.blockType,
      id: blockId,
      buttons: new Buttons({
        items: e.blockType.getChildBlockItems(this.getItems()),
        maxBlocks: this.getMaxBlocks()
      }),
      showButtons: !this.atMaxLevels(e.level),
      showBlockTypeHandle: this._showBlockTypeHandles
    }, true)

    this.addBlock(block, e.index, e.level)
  },

  '@addBlockAbove' (e) {
    this._destroyTempButtons()

    const animate = !Garnish.prefersReducedMotion() && e.animate !== false
    const block = e.block
    const index = this._blocks.indexOf(block)
    const parent = this._findParentBlock(index)
    const blocks = this.getBlocks()
    const buttons = new Buttons({
      blockTypes: !parent ? this.getBlockTypes(true) : [],
      blocks,
      groups: !parent ? this.getGroups() : [],
      items: parent ? parent.getBlockType().getChildBlockItems(this.getItems()) : null,
      maxBlocks: this.getMaxBlocks()
    })

    block.$container.before(buttons.$container)

    buttons.on('newBlock', e => this['@newBlock']({
      blockType: e.blockType,
      index,
      level: block.getLevel()
    }))

    buttons.initUi()

    if (animate) {
      buttons.$container
        .css({
          opacity: 0,
          marginBottom: -(buttons.$container.outerHeight())
        })
        .velocity({
          opacity: 1,
          marginBottom: 10
        }, 'fast', _ => Garnish.requestAnimationFrame(() => Garnish.scrollContainerToElement(buttons.$container)))
    }

    this._tempButtons = buttons
    this._tempButtonsBlock = this._findParentBlock(block)

    this._tempButtons.updateButtonStates(blocks, this._checkMaxChildren(this._tempButtonsBlock), this._tempButtonsBlock)
  },

  '@copyBlock' (e) {
    // Get the selected blocks and their descendants
    const blockGroups = []
    let blockCount = 0
    const ownerId = this._ownerId

    this._blockBatch(e.block, (block) => {
      // To prevent block descendants from being copied multiple times, determine whether the current block has
      // been added to the most recently added group.
      const blockAdded = blockCount > 0 && blockGroups[blockGroups.length - 1].indexOf(block) !== -1

      if (!blockAdded) {
        const newGroup = []
        newGroup.push(block, ...this._findChildBlocks(block, true))
        blockGroups.push(newGroup)
        blockCount += newGroup.length
      }
    })

    // Collect the relevant data from those blocks to be stored for pasting
    const data = {
      field: this._name,
      blocks: []
    }

    for (const group of blockGroups) {
      const firstBlockLevel = group[0].getLevel() - 1

      for (const block of group) {
        const blockData = {
          type: block.getBlockType().getId(),
          level: block.getLevel() - firstBlockLevel,
          content: block.getContent(),
          ownerId
        }

        if (block.isEnabled()) {
          blockData.enabled = 1
        }

        if (!block.isExpanded()) {
          blockData.collapsed = 1
        }

        data.blocks.push(blockData)
      }
    }

    window.localStorage.setItem('neo:copy', JSON.stringify(data))

    this._updateButtons()

    const notice = blockCount === 1 ? '1 block copied' : '{n} blocks copied'
    Craft.cp.displayNotice(Craft.t('neo', notice, { n: blockCount }))
  },

  '@pasteBlock' (e) {
    const block = e.block
    const baseLevel = block.getLevel() - 1
    const copyData = window.localStorage.getItem('neo:copy')

    if (copyData) {
      const { blocks } = JSON.parse(copyData)

      for (const pasteBlock of blocks) {
        pasteBlock.level += baseLevel
      }

      NS.enter(this._templateNs)

      const data = {
        namespace: NS.toFieldName(),
        siteId: this._siteId,
        blocks
      }

      NS.leave()

      this._duplicate(data, block)
    }
  },

  '@duplicateBlock' (e) {
    const block = e.block
    const blockIndex = this._blocks.indexOf(block)
    const subBlocks = this._findChildBlocks(blockIndex, true)
    const ownerId = this._ownerId

    const getBlockData = block => {
      return {
        collapsed: !block.isExpanded() | 0,
        content: block.getContent(),
        enabled: block.isEnabled() | 0,
        level: block.getLevel(),
        ownerId,
        type: block.getBlockType().getId()
      }
    }

    NS.enter(this._templateNs)

    const data = {
      namespace: NS.toFieldName(),
      siteId: this._siteId,
      blocks: [
        getBlockData(block),
        ...subBlocks.map(getBlockData)
      ]
    }

    NS.leave()

    this._duplicate(data, block)
  }
})
