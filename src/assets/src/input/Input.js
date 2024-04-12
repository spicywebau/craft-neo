/*
The `_registerDynamicBlockConditions()` and `_updateVisibleElements()` methods are based on a large
section of `Craft.ElementEditor.saveDraft()` from Craft CMS 4.3.6.1, by Pixel & Tonic, Inc.
https://github.com/craftcms/cms/blob/4.3.6.1/src/web/assets/cp/src/js/ElementEditor.js#L1144
Craft CMS is released under the terms of the Craft License, a copy of which is included below.
https://github.com/craftcms/cms/blob/4.3.6.1/LICENSE.md

Copyright © Pixel & Tonic

Permission is hereby granted to any person obtaining a copy of this software
(the “Software”) to use, copy, modify, merge, publish and/or distribute copies
of the Software, and to permit persons to whom the Software is furnished to do
so, subject to the following conditions:

1. **Don’t plagiarize.** The above copyright notice and this license shall be
   included in all copies or substantial portions of the Software.

2. **Don’t use the same license on more than one project.** Each licensed copy
   of the Software shall be actively installed in no more than one production
   environment at a time.

3. **Don’t mess with the licensing features.** Software features related to
   licensing shall not be altered or circumvented in any way, including (but
   not limited to) license validation, payment prompts, feature restrictions,
   and update eligibility.

4. **Pay up.** Payment shall be made immediately upon receipt of any notice,
   prompt, reminder, or other message indicating that a payment is owed.

5. **Follow the law.** All use of the Software shall not violate any applicable
   law or regulation, nor infringe the rights of any other person or entity.

Failure to comply with the foregoing conditions will automatically and
immediately result in termination of the permission granted hereby. This
license does not include any right to receive updates to the Software or
technical support. Licensees bear all risk related to the quality and
performance of the Software and any modifications made or obtained to it,
including liability for actual and consequential harm, such as loss or
corruption of data, and any necessary service, repair, or correction.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES, OR OTHER
LIABILITY, INCLUDING SPECIAL, INCIDENTAL AND CONSEQUENTIAL DAMAGES, WHETHER IN
AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

import $ from 'jquery'
import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import BlockSort from './BlockSort'
import BlockType from './BlockType'
import Group from './Group'
import Block from './Block'
import Buttons from './Buttons'
import ButtonsGrid from './ButtonsGrid'
import ButtonsList from './ButtonsList'

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
  maxLevels: 0,
  ownerId: null
}

export default Garnish.Base.extend({

  _templateNs: [],
  _name: null,
  _siteId: null,
  _visibleLayoutElements: {},
  _newBlockCount: 0,

  init (settings = {}) {
    settings = Object.assign({}, _defaults, settings)

    this._templateNs = NS.parse(settings.namespace)
    this._blockTypes = []
    this._groups = settings.groups.map(gInfo => new Group(gInfo))
    this._blocks = []
    this._id = settings.id
    this._name = settings.name
    this._minBlocks = settings.minBlocks
    this._maxBlocks = settings.maxBlocks
    this._maxTopBlocks = settings.maxTopBlocks
    this._minLevels = settings.minLevels
    this._maxLevels = settings.maxLevels
    this._ownerId = settings.ownerId
    this._showBlockTypeHandles = settings.showBlockTypeHandles

    const animate = !Garnish.prefersReducedMotion()
    this._$spinner = $(`<div class="ni_spinner">${animate ? '<div class="spinner"></div>' : Craft.t('neo', 'Loading')}</div>`)

    switch (settings.newBlockMenuStyle) {
      case 'grid':
        this.ButtonClass = ButtonsGrid
        break
      case 'list':
        this.ButtonClass = ButtonsList
        break
      default:
        this.ButtonClass = Buttons
    }

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
        const blockType = new BlockType(Object.assign({ field: this }, btInfo))
        this._blockTypes.push(blockType)
        tempBlockTypes[blockType.getHandle()] = blockType
      }
    }

    this.$form = this.$container.closest('form')
    this._siteId = this.$form.find('input[name="siteId"]').val()

    const $neo = this.$container.find('[data-neo]')
    this.$blocksContainer = $neo.filter('[data-neo="container.blocks"]')
    this.$buttonsContainer = $neo.filter('[data-neo="container.buttons"]')

    this._buttons = new this.ButtonClass({
      $ownerContainer: this.$container,
      field: this,
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

      bInfo.blockType = blockType
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

    // Create any required top level blocks, if this field has only one top level block type
    if (this._minBlocks > 0) {
      const missingBlockCount = this._minBlocks - this._blocks.length
      const topLevelBlockTypes = this.getBlockTypes(true)

      if (topLevelBlockTypes.length === 1 && missingBlockCount > 0) {
        for (let i = this._blocks.length; i < this._minBlocks; i++) {
          this['@newBlock']({
            blockType: topLevelBlockTypes[0],
            createChildBlocks: false,
            index: i,
            level: 1
          })
        }
      }
    }

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

    this._registerDynamicBlockConditions()

    this.trigger('afterInit')
  },

  /**
   * @public
   * @returns the field's ID
   * @since 5.0.0
   */
  getId () {
    return this._id
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

  addBlock (block, index = -1, level = 1, animate = null, createChildBlocks = true) {
    this._newBlockCount++
    this.$form.data('elementEditor')?.pause()
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

    // Construct the block's visible layout elements, since they might not be the default visible
    // layout elements for the block type, e.g. if pasting a block
    const visibleLayoutElements = {}
    block.$contentContainer.children('[data-layout-tab]').each((_, layoutTab) => {
      const tabUid = layoutTab.getAttribute('data-layout-tab')
      visibleLayoutElements[tabUid] = []
      layoutTab.querySelectorAll(':scope > [data-layout-element]:not([data-layout-element-placeholder])')
        .forEach((layoutElement) => {
          visibleLayoutElements[tabUid].push(layoutElement.getAttribute('data-layout-element'))
        })
    })
    this._visibleLayoutElements[block.getId()] = visibleLayoutElements

    // Create any required child blocks, if this block has only one child block type
    const createChildBlocksIfAllowed = () => {
      if (createChildBlocks) {
        const blockType = block.getBlockType()
        const minChildBlocks = blockType.getMinChildBlocks()

        if (minChildBlocks > 0) {
          let childBlockTypes = blockType.getChildBlocks()

          if (childBlockTypes === '*') {
            childBlockTypes = this.getBlockTypes()
          }

          if (childBlockTypes.length === 1) {
            const childBlockType = this.getBlockTypeByHandle(childBlockTypes[0])

            for (let i = 0; i < minChildBlocks; i++) {
              this['@newBlock']({
                blockType: childBlockType,
                createChildBlocks: false,
                index: index + i + 1,
                level: level + 1
              })
            }
          }
        }
      }

      this.$form.data('elementEditor')?.resume()
    }

    if (animate) {
      block.$container
        .css({
          opacity: 0,
          marginBottom: -(block.$container.outerHeight())
        })
        .velocity({
          opacity: 1,
          marginBottom: 10
        }, 'fast', _ => Garnish.requestAnimationFrame(() => {
          Garnish.scrollContainerToElement(block.$container)
          createChildBlocksIfAllowed()
        }))
    } else {
      createChildBlocksIfAllowed()
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

  getCopiedBlocks () {
    const copyData = window.localStorage.getItem(`neo:copy:${this._name}`)

    if (!copyData) {
      return []
    }

    const { blocks } = JSON.parse(copyData)
    return blocks
  },

  setVisibleElements (blockId, visibleLayoutElements) {
    // visibleLayoutElements might (will probably) be a JSON-encoded string
    if (typeof visibleLayoutElements === 'string') {
      visibleLayoutElements = JSON.parse(visibleLayoutElements)
    }

    const block = this._blocks.find((block) => block.getId() === blockId)

    if (block === null) {
      return
    }

    this._visibleLayoutElements[blockId] = visibleLayoutElements
  },

  /**
   * @since 3.9.2
   */
  getNamespace () {
    return Array.from(this._templateNs)
  },

  /**
   * @since 3.9.3
   */
  getOwnerId () {
    return this._ownerId
  },

  /**
   * @since 3.9.5
   */
  getSiteId () {
    return this._siteId
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
    // If undefined, then there's no previous block and the 'next' block will be the first block
    if (typeof index === 'undefined') {
      return 0
    }

    if (index instanceof Block) {
      index = this._blocks.indexOf(index)
    }

    const descendants = this._findChildBlocks(index, true)
    const lastDescendant = descendants[descendants.length - 1]

    return (lastDescendant ? this._blocks.indexOf(lastDescendant) : index) + 1
  },

  /**
   * TODO: hopefully remove this in the Craft 5 version
   * @private
   */
  _registerDynamicBlockConditions () {
    // A small timeout to let the element editor initialise
    setTimeout(() => {
      const elementEditor = this.$form.data('elementEditor')
      elementEditor?.on('update', () => {
        // If the draft's being resaved, wait until we get the next event
        if (elementEditor.submittingForm) {
          return
        }

        // Don't update visible elements if the draft save was the result of creating a new block
        if (this._newBlockCount > 0) {
          this._newBlockCount--
          return
        }

        elementEditor.pause()
        const siteId = elementEditor.settings.siteId
        NS.enter(this.getNamespace())
        const data = {
          namespace: NS.toFieldName(),
          blocks: {},
          sortOrder: [],
          fieldId: this._id,
          ownerCanonicalId: this._ownerId,
          ownerDraftId: elementEditor.settings.draftId,
          isProvisionalDraft: elementEditor.settings.isProvisionalDraft,
          siteId
        }
        NS.leave()
        const originalBlockIds = {}
        this._blocks.forEach((block) => {
          const selectedTabId = block.$contentContainer
            .children('[data-layout-tab]:not(.hidden)')
            .data('layout-tab')
          data.blocks[block.getDuplicatedBlockId()] = {
            selectedTab: selectedTabId ?? null,
            visibleLayoutElements: this._visibleLayoutElements[block.getId()] ?? {}
          }
          data.sortOrder.push(block.getDuplicatedBlockId())
          originalBlockIds[block.getDuplicatedBlockId()] = block.getId()
        })

        Craft.queue.push(() => new Promise((resolve, reject) => {
          Craft.sendActionRequest('POST', 'neo/input/update-visible-elements', { data })
            .then((response) => {
              for (const blockId in response.data.blocks) {
                const block = this._blocks.find((block) => block.getId() === originalBlockIds[blockId])
                this._updateVisibleElements(
                  block,
                  response.data.blocks[blockId],
                  data.blocks[block.getDuplicatedBlockId()].selectedTabId
                )
              }
              resolve()
            })
            .catch(reject)
            .finally(() => elementEditor.resume())
        }))
      })
    }, 200)
  },

  /**
   * TODO: hopefully remove this in the Craft 5 version
   * @private
   */
  _updateVisibleElements (block, blockData, selectedTabId) {
    let $allTabContainers = $()
    const visibleLayoutElements = {}
    let changedElements = false

    for (let i = 0; i < blockData.missingElements.length; i++) {
      const tabInfo = blockData.missingElements[i]
      let $tabContainer = block.$contentContainer.children(
        `[data-layout-tab="${tabInfo.uid}"]`
      )

      if (!$tabContainer.length) {
        $tabContainer = $('<div/>', {
          id: block.namespaceId(tabInfo.id),
          class: 'flex-fields',
          'data-id': tabInfo.id,
          'data-layout-tab': tabInfo.uid
        })
        if (tabInfo.id !== selectedTabId) {
          $tabContainer.addClass('hidden')
        }
        $tabContainer.appendTo(block.$contentContainer)
      }

      $allTabContainers = $allTabContainers.add($tabContainer)

      for (let j = 0; j < tabInfo.elements.length; j++) {
        const elementInfo = tabInfo.elements[j]

        if (elementInfo.html !== false) {
          if (!visibleLayoutElements[tabInfo.uid]) {
            visibleLayoutElements[tabInfo.uid] = []
          }
          visibleLayoutElements[tabInfo.uid].push(elementInfo.uid)

          if (typeof elementInfo.html === 'string') {
            const html = elementInfo.html.replaceAll('__NEOBLOCK__', block.getId())
            const $oldElement = $tabContainer.children(
              `[data-layout-element="${elementInfo.uid}"]`
            )
            const $newElement = $(html)
            if ($oldElement.length) {
              $oldElement.replaceWith($newElement)
            } else {
              $newElement.appendTo($tabContainer)
            }
            Craft.initUiElements($newElement)
            if ($newElement.hasClass('ni_child-blocks-ui-element')) {
              block.resetButtons()
            }
            changedElements = true
          }
        } else {
          const $oldElement = $tabContainer.children(
            `[data-layout-element="${elementInfo.uid}"]`
          )
          if (
            !$oldElement.length ||
            !Garnish.hasAttr(
              $oldElement,
              'data-layout-element-placeholder'
            )
          ) {
            const $placeholder = $('<div/>', {
              class: 'hidden',
              'data-layout-element': elementInfo.uid,
              'data-layout-element-placeholder': ''
            })

            if ($oldElement.length) {
              $oldElement.replaceWith($placeholder)
            } else {
              $placeholder.appendTo($tabContainer)
            }

            changedElements = true
          }
        }
      }

      if (changedElements) {
        this._updateButtons()
      }
    }

    // Remove any unused tab content containers
    // (`[data-layout-tab=""]` == unconditional containers, so ignore those)
    const $unusedTabContainers = block.$contentContainer
      .children('[data-layout-tab]')
      .not($allTabContainers)
      .not('[data-layout-tab=""]')
    if ($unusedTabContainers.length) {
      $unusedTabContainers.remove()
      changedElements = true
    }

    // Make the first tab visible if no others are
    if (!$allTabContainers.filter(':not(.hidden)').length) {
      $allTabContainers.first().removeClass('hidden')
    }

    this._visibleLayoutElements[block.getId()] = visibleLayoutElements

    // Update the tabs
    // Unfortunately can't use `block.getDuplicatedBlockId()` because it doesn't work here for new blocks
    const idToReplace = blockData.tabs?.match(/data-neo-b="([0-9]+).container.tabs"/)?.pop() ?? null
    const tabsHtml = idToReplace
      ? blockData.tabs.replaceAll(idToReplace, block.getId())
      : blockData.tabs
    const $tabsHtml = $(tabsHtml)
    const $tabsOuterContainer = block.$topbarRightContainer.find('.tabs')
    $tabsOuterContainer.empty().append($tabsHtml)
    block.initTabs()
    block.updateResponsiveness()

    Craft.appendHeadHtml(blockData.headHtml.replaceAll('__NEOBLOCK__', block.getId()))
    Craft.appendBodyHtml(blockData.bodyHtml.replaceAll('__NEOBLOCK__', block.getId()))

    // Did any layout elements get added or removed?
    if (changedElements && blockData.initialDeltaValues) {
      Object.assign(
        this.$form.data('initial-delta-values'),
        blockData.initialDeltaValues
      )
    }
  },

  _addSpinnerAfter (block) {
    if (typeof block !== 'undefined') {
      block.$container.after(this._$spinner)
    } else {
      this.$blocksContainer.prepend(this._$spinner)
    }

    this._animateSpinner()
  },

  _addSpinnerBefore (block) {
    if (typeof block !== 'undefined') {
      block.$container.before(this._$spinner)
    } else {
      this.$blocksContainer.append(this._$spinner)
    }

    this._animateSpinner()
  },

  _animateSpinner () {
    if (Garnish.prefersReducedMotion()) {
      return Promise.resolve()
    }

    return new Promise((resolve, reject) => {
      try {
        this._$spinner
          .css({
            opacity: 0,
            marginBottom: -(this._$spinner.outerHeight())
          })
          .velocity({
            opacity: 1,
            marginBottom: 10
          }, 'fast', () => resolve())
      } catch (err) {
        reject(err)
      }
    })
  },

  _removeSpinner () {
    this._$spinner.remove()
  },

  async _duplicate (data, block) {
    try {
      this.$form.data('elementEditor')?.pause()
      await this._addSpinnerAfter(block)
      const response = await Craft.sendActionRequest('POST', 'neo/input/render-blocks', { data })
      const newBlocks = []

      for (const renderedBlock of response.data.blocks) {
        const newBlockId = renderedBlock.id
        const newBlock = new Block({
          namespace: [...this._templateNs, newBlockId],
          field: this,
          blockType: this.getBlockTypeById(renderedBlock.type),
          blockHtml: renderedBlock.blockHtml,
          bodyHtml: renderedBlock.bodyHtml,
          headHtml: renderedBlock.headHtml,
          id: newBlockId,
          uuid: renderedBlock.uuid,
          level: renderedBlock.level | 0,
          enabled: !!renderedBlock.enabled,
          collapsed: !!renderedBlock.collapsed,
          showButtons: !this.atMaxLevels(renderedBlock.level | 0),
          showBlockTypeHandle: this._showBlockTypeHandles
        }, true)

        newBlocks.push(newBlock)
      }

      let newIndex = this._getNextBlockIndex(block)

      for (const newBlock of newBlocks) {
        this.addBlock(newBlock, newIndex++, newBlock.getLevel(), false)
      }

      if (!Garnish.prefersReducedMotion() && newBlocks.length > 0) {
        const firstBlock = newBlocks[0]

        firstBlock.$container
          .css({
            opacity: 0,
            marginBottom: this._$spinner.outerHeight() - firstBlock.$container.outerHeight() + 10
          })
          .velocity({
            opacity: 1,
            marginBottom: 10
          }, 'fast', _ => Garnish.requestAnimationFrame(() => Garnish.scrollContainerToElement(firstBlock.$container)))
      }
    } catch (err) {
      Craft.cp.displayError(err.message)
    } finally {
      this._removeSpinner()
      this.$form.data('elementEditor')?.resume()
    }
  },

  async '@newBlock' (e) {
    const elementEditor = this.$form.data('elementEditor')

    try {
      elementEditor?.pause()
      const level = e.level ?? 1
      const loopStartingPoint = typeof e.index !== 'undefined'
        ? Math.min(e.index - 1, this._blocks.length - 1)
        : this._blocks.length - 1
      let siblingBlock
      let addAfter = true

      for (let i = loopStartingPoint; i >= 0; i--) {
        // Look for the previous block at the same level as the new block, to add the spinner after
        if (this._blocks[i].getLevel() === level) {
          siblingBlock = this._blocks[i]
          break
        }

        // If we've gone to a lower level, any future block we find at the same level won't be a
        // sibling of the new block, so we need to add the spinner before the last block we checked
        if (this._blocks[i].getLevel() < level) {
          siblingBlock = this._blocks[i + 1]
          addAfter = false
          break
        }
      }

      if (addAfter) {
        await this._addSpinnerAfter(siblingBlock)
      } else {
        await this._addSpinnerBefore(siblingBlock)
      }

      const newBlock = await e.blockType.newBlock()
      const blockId = newBlock.id
      const block = new Block({
        namespace: [...this._templateNs, blockId],
        field: this,
        blockType: e.blockType,
        id: blockId,
        uuid: newBlock.uuid,
        blockHtml: newBlock.blockHtml,
        bodyHtml: newBlock.bodyHtml,
        headHtml: newBlock.headHtml,
        showButtons: !this.atMaxLevels(e.level),
        showBlockTypeHandle: this._showBlockTypeHandles
      }, true)

      this._removeSpinner()
      this.addBlock(block, e.index, e.level, e.createChildBlocks, e.createChildBlocks)
    } catch (error) {
      this._removeSpinner()
      Craft.cp.displayError(error)
    } finally {
      elementEditor?.resume()
    }
  },

  '@addBlockAbove' (e) {
    this._destroyTempButtons()

    const animate = !Garnish.prefersReducedMotion() && e.animate !== false
    const block = e.block
    const index = this._blocks.indexOf(block)
    const parent = this._findParentBlock(index)
    const blocks = this.getBlocks()
    const buttons = new this.ButtonClass({
      $ownerContainer: block.isTopLevel() ? this.$container : block.getParent().$container,
      field: this,
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
          content: block.getContent()
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

    window.localStorage.setItem(`neo:copy:${this._name}`, JSON.stringify(data))

    this._updateButtons()

    const notice = blockCount === 1 ? '1 block copied' : '{n} blocks copied'
    Craft.cp.displayNotice(Craft.t('neo', notice, { n: blockCount }))
  },

  '@pasteBlock' (e) {
    const block = e.block
    const baseLevel = (block?.getLevel() ?? 1) - 1
    const blocks = this.getCopiedBlocks()

    if (blocks.length > 0) {
      for (const pasteBlock of blocks) {
        pasteBlock.level += baseLevel
        pasteBlock.ownerId = this._ownerId
      }

      NS.enter(this._templateNs)

      const data = {
        namespace: NS.toFieldName(),
        fieldId: this._id,
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
      fieldId: this._id,
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
