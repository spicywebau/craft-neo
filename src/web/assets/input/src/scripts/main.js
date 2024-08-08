import Input from './Input'

const context = window ?? this
const inputs = []

context.Neo = {
  Input,
  inputs,

  createInput (settings = {}) {
    const input = new Input(settings)
    inputs.push(input)
    input.on('destroy', () => {
      for (const i in inputs) {
        if (inputs[i] === input) {
          inputs.splice(i, 1)
        }
      }
    })

    return input
  }
}
