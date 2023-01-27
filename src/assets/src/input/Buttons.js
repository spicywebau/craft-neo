import $ from 'jquery'
import { NewBlockMenu, GarnishNewBlockMenu } from './NewBlockMenu'

class Buttons extends NewBlockMenu {
  initUi () {
    $('.menubtn', this.$container).menubtn()
    this.updateResponsiveness()

    // If no buttons were rendered (e.g. if all valid block types are disabled for the user), hide the button container
    if (this.$buttonsContainer.children().length === 0) {
      const parent = this.$container.parent()
      const grandParent = parent.parent()
      const childrenContainer = grandParent.children('.ni_blocks')

      if (childrenContainer.length === 0 || childrenContainer.children().length === 0) {
        grandParent.addClass('hidden')
      } else {
        parent.addClass('hidden')
      }
    }
  }

  updateResponsiveness () {
    this._buttonsContainerWidth ||= this.$buttonsContainer.width()
    const isMobile = this.$container.width() < this._buttonsContainerWidth

    this.$buttonsContainer.toggleClass('hidden', isMobile)
    this.$menuContainer.toggleClass('hidden', !isMobile)
  }
}

export default GarnishNewBlockMenu.extend({
  init (settings = {}) {
    this.base(new Buttons(settings))
  }
})
