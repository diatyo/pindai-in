$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing

$projectRoot = Split-Path $PSScriptRoot
$sourcePath = Join-Path $projectRoot 'public/assets/logo.png'
$frameSizes = @(16, 20, 24, 32, 40, 48, 64, 96, 128, 256)
$frames = [Collections.Generic.List[object]]::new()
$source = [Drawing.Image]::FromFile($sourcePath)

try {
    foreach ($size in $frameSizes) {
        $bitmap = [Drawing.Bitmap]::new($size, $size, [Drawing.Imaging.PixelFormat]::Format32bppArgb)
        $graphics = [Drawing.Graphics]::FromImage($bitmap)
        $buffer = [IO.MemoryStream]::new()
        try {
            $graphics.CompositingMode = [Drawing.Drawing2D.CompositingMode]::SourceCopy
            $graphics.CompositingQuality = [Drawing.Drawing2D.CompositingQuality]::HighQuality
            $graphics.InterpolationMode = [Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
            $graphics.PixelOffsetMode = [Drawing.Drawing2D.PixelOffsetMode]::HighQuality
            $graphics.Clear([Drawing.Color]::Transparent)
            $graphics.DrawImage($source, [Drawing.Rectangle]::new(0, 0, $size, $size))
            $bitmap.Save($buffer, [Drawing.Imaging.ImageFormat]::Png)
            $frames.Add($buffer.ToArray())
        } finally {
            $buffer.Dispose()
            $graphics.Dispose()
            $bitmap.Dispose()
        }
    }
} finally {
    $source.Dispose()
}

$iconBuffer = [IO.MemoryStream]::new()
$writer = [IO.BinaryWriter]::new($iconBuffer)
try {
    $writer.Write([uint16]0)
    $writer.Write([uint16]1)
    $writer.Write([uint16]$frames.Count)
    $offset = 6 + (16 * $frames.Count)
    for ($index = 0; $index -lt $frames.Count; $index++) {
        $dimension = [byte]($frameSizes[$index] % 256)
        $writer.Write($dimension)
        $writer.Write($dimension)
        $writer.Write([byte]0)
        $writer.Write([byte]0)
        $writer.Write([uint16]1)
        $writer.Write([uint16]32)
        $writer.Write([uint32]$frames[$index].Length)
        $writer.Write([uint32]$offset)
        $offset += $frames[$index].Length
    }
    foreach ($frame in $frames) { $writer.Write([byte[]]$frame) }
    $writer.Flush()
    $iconBytes = $iconBuffer.ToArray()
} finally {
    $writer.Dispose()
    $iconBuffer.Dispose()
}

foreach ($name in @('Pindaiin.ico', 'favicon.ico')) {
    $destination = Join-Path $projectRoot ('public/' + $name)
    $temporary = $destination + '.' + [Guid]::NewGuid().ToString('N') + '.tmp'
    try {
        [IO.File]::WriteAllBytes($temporary, $iconBytes)
        Move-Item -LiteralPath $temporary -Destination $destination -Force
    } finally {
        if (Test-Path -LiteralPath $temporary) { Remove-Item -LiteralPath $temporary -Force }
    }
}
Write-Output ('Windows icons generated from logo.png in ' + $frames.Count + ' sizes.')
