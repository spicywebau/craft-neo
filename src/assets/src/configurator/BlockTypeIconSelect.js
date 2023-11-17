import Craft from 'craft'

/**
 * Class for managing the selected icon for a block type.
 * @since 4.0.0
 */
export default class BlockTypeIconSelect {
  /**
   * Container for the display of the set icon.
   * @public
   */
  imageContainer = null

  /**
   * Image for the display of the set icon.
   * @public
   */
  image = null

  /**
   * Text (filename) for the display of the set icon.
   * @public
   */
  imageText = null

  /**
   * Icons that can be selected from the menu.
   * @public
   */
  menuItems = []

  /**
   * The button for setting the icon.
   * @public
   */
  btnSet = null

  /**
   * The button for unsetting the icon.
   * @public
   */
  btnRemove = null

  /**
   * The hidden input for the element editor form.
   * @public
   */
  input = null

  /**
   * The constructor.
   * @param container - The icon field container.
   * @public
   */
  constructor (container) {
    this.imageContainer = container.querySelector('[data-icon-select-show]')
    this.image = this.imageContainer?.querySelector('img') ?? null
    this.imageText = this.imageContainer?.querySelector('p') ?? null
    this.menuItems = container.querySelectorAll('[data-icon-select-item]')
    this.btnSet = container.querySelector('[data-icon-select-set]')
    this.btnRemove = container.querySelector('[data-icon-select-remove]')
    this.input = container.querySelector('input[name$="[iconFilename]"]')

    this.btnRemove?.addEventListener('click', (_) => this.remove())
    this.menuItems.forEach((item) => {
      const filename = item.querySelector('span')?.textContent
      const url = item.querySelector('img')?.getAttribute('src')
      item.addEventListener('click', (_) => this.set({ filename, url }))
    })
  }

  /**
   * Sets the selected icon.
   * @param item - An object representing the selected icon
   * @public
   */
  set (item) {
    this.image?.setAttribute('src', item.url)
    this.input?.setAttribute('value', item.filename)
    this.btnRemove?.classList.remove('hidden')

    if (this.imageText !== null) {
      this.imageText.textContent = item.filename
    }

    if (this.btnSet !== null) {
      this.btnSet.textContent = Craft.t('neo', 'Replace')
    }
  }

  /**
   * Unsets the icon.
   * @public
   */
  remove () {
    this.image?.setAttribute('src', '')
    this.input?.setAttribute('value', '')
    this.btnRemove?.classList.add('hidden')

    if (this.imageText !== null) {
      this.imageText.textContent = Craft.t('neo', 'None set')
    }

    if (this.btnSet !== null) {
      this.btnSet.textContent = Craft.t('neo', 'Add')
    }
  }
}
