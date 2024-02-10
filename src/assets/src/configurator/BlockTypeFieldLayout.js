import $ from 'jquery'
import Garnish from 'garnish'
import Craft from 'craft'
import NS from '../namespace'

const _defaults = {
  namespace: [],
  html: '',
  layout: null,
  id: -1,
  uid: null,
  blockId: null,
  blockName: ''
}

export default Garnish.Base.extend({

  _templateNs: [],

  init (settings = {}) {
    settings = Object.assign({}, _defaults, settings)

    this._templateNs = NS.parse(settings.namespace)
    this._id = settings.id | 0
    this._uid = settings.uid
    this._blockTypeId = settings.blockTypeId

    this.$container = $(settings.html).find('.layoutdesigner')
    this.$container.removeAttr('id')

    const nameInput = this.$container.find('input[name="fieldLayout"]')

    if (nameInput.length > 0) {
      nameInput[0].name = `neoBlockType${this._blockTypeId}[fieldLayout]`

      if (settings.layout) {
        nameInput[0].value = JSON.stringify(settings.layout)
      }
    }

    NS.enter(this._templateNs)

    this._fld = new Craft.FieldLayoutDesigner(this.$container, {
      customizableTabs: true,
      customizableUi: true
    })

    NS.leave()

    const updateChildBlocksUiElement = () => {
      const selector = '[data-type=benf-neo-fieldlayoutelements-ChildBlocksUiElement]'
      const $uiLibraryElement = this._fld.$uiLibraryElements.filter(selector)
      const $tabUiElement = this._fld.$tabContainer.find(selector)
      $uiLibraryElement.toggleClass(
        'hidden',
        $tabUiElement.length > 0 || $('body.dragging .draghelper' + selector).length > 0
      )
      if ($tabUiElement.hasClass('velocity-animating')) {
        $tabUiElement.removeClass('hidden')
      }
    }

    updateChildBlocksUiElement()
    this._tabObserver = new window.MutationObserver(updateChildBlocksUiElement)
    this._tabObserver.observe(this._fld.$tabContainer[0], { childList: true, subtree: true })
  },

  getId () {
    return this._id
  },

  /**
   * @since 4.0.5
   */
  getUid () {
    return this._uid
  },

  getBlockTypeId () {
    return this._blockTypeId
  },

  getConfig () {
    const newConfig = {
      tabs: [],
      uid: this._uid
    }

    for (const tab of this._fld.config.tabs) {
      const newElements = []

      for (const element of tab.elements) {
        const newElement = {}

        for (const key in element) {
          newElement[key] = key === 'required' && !element[key] ? '' : element[key]
        }

        newElements.push(newElement)
      }

      newConfig.tabs.push({
        elements: newElements,
        name: tab.name.slice()
      })
    }

    return newConfig
  }
})
