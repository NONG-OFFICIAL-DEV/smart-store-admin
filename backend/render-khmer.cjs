const { createCanvas, registerFont } = require('canvas')
const fs = require('fs')

const text     = process.argv[2] || 'អរគុណសម្រាប់ការទិញ!'
const output   = process.argv[3] || '/tmp/khmer.png'
const fontPath = process.argv[4]

if (fontPath && fs.existsSync(fontPath)) {
  registerFont(fontPath, { family: 'Khmer' })
}

const canvas = createCanvas(400, 80)
const ctx    = canvas.getContext('2d')

ctx.fillStyle = 'white'
ctx.fillRect(0, 0, 400, 80)

ctx.fillStyle = 'black'
ctx.font      = '28px Khmer'
ctx.textAlign = 'center'
ctx.textBaseline = 'middle'
ctx.fillText(text, 200, 40)

fs.writeFileSync(output, canvas.toBuffer('image/png'))
