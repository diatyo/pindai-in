const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const projectRoot = path.resolve(__dirname, '..', '..');
const jsQR = require(path.join(projectRoot, 'public', 'assets', 'vendor', 'jsQR.js'));
const scannerSource = fs.readFileSync(path.join(projectRoot, 'public', 'assets', 'js', 'scanner.js'), 'utf8');
const qrDir = path.join(projectRoot, 'public', 'qr');
const qrFile = fs.readdirSync(qrDir).find((file) => /^qr_.+\.png$/i.test(file));

assert.strictEqual(typeof jsQR, 'function', 'jsQR bundle tidak mengekspor fungsi decoder');
assert.match(scannerSource, /requestAnimationFrame\(startCamera\)/, 'Scanner tidak otomatis mengaktifkan kamera');
assert.match(scannerSource, /cameraStarting/, 'Scanner tidak memiliki guard request kamera ganda');
assert.ok(qrFile, 'Fixture QR lokal tidak ditemukan');

const php = `$image=imagecreatefrompng($argv[1]);if(!$image){fwrite(STDERR,'invalid png');exit(1);}$width=imagesx($image);$height=imagesy($image);fwrite(STDOUT,pack('N2',$width,$height));for($y=0;$y<$height;$y++){for($x=0;$x<$width;$x++){$color=imagecolorsforindex($image,imagecolorat($image,$x,$y));fwrite(STDOUT,chr($color['red']).chr($color['green']).chr($color['blue']).chr(255));}}imagedestroy($image);`;
const pixels = spawnSync('php', ['-r', php, path.join(qrDir, qrFile)], { encoding: null, maxBuffer: 32 * 1024 * 1024 });

assert.strictEqual(pixels.status, 0, String(pixels.stderr || 'PHP gagal membaca fixture QR'));
const width = pixels.stdout.readUInt32BE(0);
const height = pixels.stdout.readUInt32BE(4);
const decoded = jsQR(new Uint8ClampedArray(pixels.stdout.subarray(8)), width, height);
const itemCode = path.basename(qrFile, '.png').replace(/^qr_/i, '').toUpperCase();

assert.ok(decoded && decoded.data, 'jsQR gagal mendekode fixture QR');
assert.strictEqual(decoded.data, `PINDAI.IN|${itemCode}`);
console.log(`jsQR decoded ${decoded.data}`);
