const { createCanvas, registerFont } = require('canvas')
const fs = require('fs')

const text     = process.argv[2] || 'អរគុណសម្រាប់ការទិញ!'
const output   = process.argv[3] || '/tmp/khmer.png'
const fontPath = process.argv[4]

if (fontPath && fs.existsSync(fontPath)) {
  registerFont(fontPath, { family: 'Khmer' })
}

const fontSize   = 28
const lineHeight = 45
const padding    = 20
const canvasWidth = 576  // 80mm at 203dpi

// Split text into lines
const lines = text.split('\n').filter(l => l.trim() !== '')

const canvasHeight = (lines.length * lineHeight) + (padding * 2)

const canvas = createCanvas(canvasWidth, canvasHeight)
const ctx    = canvas.getContext('2d')

// White background
ctx.fillStyle = 'white'
ctx.fillRect(0, 0, canvasWidth, canvasHeight)

// Draw each line centered
ctx.fillStyle    = 'black'
ctx.font         = `${fontSize}px Khmer`
ctx.textAlign    = 'center'
ctx.textBaseline = 'middle'

lines.forEach((line, i) => {
  const y = padding + (i * lineHeight) + (lineHeight / 2)
  ctx.fillText(line, canvasWidth / 2, y)
})

fs.writeFileSync(output, canvas.toBuffer('image/png'))
