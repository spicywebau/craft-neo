import $ from 'jquery'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

const _defaults = {
  namespace: [],
  html: '',
  layout: [],
  id: -1,
  blockId: null,
  blockName: ''
}

export default Garnish.Base.extend({

  _templateNs: [],
  _blockName: '',

  init (settings = {}) {
    settings = Object.assign({}, _defaults, settings)

    this._templateNs = NS.parse(settings.namespace)
    this._id = settings.id | 0
    this._blockTypeId = settings.blockTypeId

    this.setBlockName(settings.blockName)

    this.$container = $(settings.html).find('.layoutdesigner')
    this.$container.removeAttr('id')

    const nameInput = this.$container.find('input[name="fieldLayout"]')

    if (nameInput.length > 0) {
      nameInput[0].name = `neoBlockType${this._blockTypeId}[fieldLayout]`
    }

    NS.enter(this._templateNs)

    this._fld = new Craft.FieldLayoutDesigner(this.$container, {
      customizableTabs: true,
      customizableUi: true
    })

    NS.leave()

    this._tabObserver = new window.MutationObserver(() => {
      const selector = '[data-type=benf\\\\neo\\\\fieldlayoutelements\\\\ChildBlocksUiElement]'
      const $uiLibraryElement = this._fld.$uiLibraryElements.filter(selector)
      const $tabUiElement = this._fld.$tabContainer.find(selector)
      $uiLibraryElement.toggleClass(
        'hidden',
        $tabUiElement.length > 0 || $('body.dragging .draghelper' + selector).length > 0
      )
      if ($tabUiElement.hasClass('velocity-animating')) {
        $tabUiElement.removeClass('hidden')
      }
    })

    // this._tabObserver.observe($tab.children('.fld-tabcontent')[0], { childList: true, subtree: true })
  },

  getId () {
    return this._id
  },

  getBlockTypeId () {
    return this._blockTypeId
  },

  getBlockName () { return this._blockName },
  setBlockName (name) {
    this._blockName = name
  },

  getLayoutStructure () {
    const tabs = []
    const elementProperties = ['config', 'id', 'type']

    this._fld.$tabContainer.children('.fld-tab').each(function () {
      const $tab = $(this)
      const tabName = $tab.find('.tab span').text()
      const tabElements = []

      $tab.find('.fld-element').each(function () {
        const $element = $(this)
        const elementData = {}

        elementProperties
          .filter(prop => typeof $element.data(prop) !== 'undefined')
          .forEach(prop => { elementData[prop] = $element.data(prop) })

        // Do settings-html separately so we can replace the IDs
        if ($element.data('settings-html')) {
          elementData['settings-html'] = $element.data('settings-html').replace(
            /(id|for)="element-([0-9a-z]+)-([a-z-]+)/g,
            `$1="element-$2-${Date.now()}-$3`
          )
        }

        tabElements.push(elementData)
      })

      tabs.push({ name: tabName, elements: tabElements })
    })

    return tabs
  }
})
