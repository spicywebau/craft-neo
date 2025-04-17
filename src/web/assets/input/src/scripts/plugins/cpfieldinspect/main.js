// import Craft from 'craft'

export function addFieldLinks ($element) {
  if (window.Craft.CpFieldInspectPlugin) {
    window.Craft.CpFieldInspectPlugin.addFieldLinks()
  }
}
