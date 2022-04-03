import Input from './input/Input'

const context = typeof window !== 'undefined' ? window : this
const inputs = []

context.Neo = {
  Input,
  inputs,

  createInput (settings = {}) {
    const input = new Input(settings)
    inputs.push(input)

    return input
  }
}
