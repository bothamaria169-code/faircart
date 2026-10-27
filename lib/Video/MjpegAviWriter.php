<?php
declare(strict_types=1);

/**
 * Writes Motion-JPEG frames into an AVI file (every frame a JPEG). YouTube takes it as it is;
 * ImageKit turns it into MP4 for Reels. Frames go to a temporary file as they come, so the
 * whole video never sits in memory; finish() wraps them in the AVI headers and index.
 * Ported from EasyAuctions (no ffmpeg on shared hosting).
 */
final class MjpegAviWriter
{
    /** @var resource */
    private $movi;

    private string $moviPath;

    /** @var list<array{0: int, 1: int}> offset and size of each frame inside the movi list */
    private array $index = [];

    private int $offset = 4;   // offsets count from the 'movi' fourcc

    private int $largest = 0;

    private ?GdImage $scaled = null;

    public function __construct(private int $width, private int $height, private int $fps, private int $quality = 72)
    {
        $this->moviPath = tempnam(sys_get_temp_dir(), 'fc-movi');
        $this->movi = fopen($this->moviPath, 'wb');
    }

    /** Adds the frame as it is now, $repeat times (a still held on screen). */
    public function addFrame(GdImage $frame, int $repeat = 1): void
    {
        if (imagesx($frame) !== $this->width || imagesy($frame) !== $this->height) {
            $this->scaled ??= imagecreatetruecolor($this->width, $this->height);
            imagecopyresampled($this->scaled, $frame, 0, 0, 0, 0, $this->width, $this->height, imagesx($frame), imagesy($frame));
            $frame = $this->scaled;
        }
        ob_start();
        imagejpeg($frame, null, $this->quality);
        $jpeg = (string) ob_get_clean();
        $size = strlen($jpeg);
        $chunk = '00dc'.pack('V', $size).$jpeg.($size % 2 ? "\0" : '');
        $this->largest = max($this->largest, $size);
        for ($i = 0; $i < max(1, $repeat); $i++) {
            fwrite($this->movi, $chunk);
            $this->index[] = [$this->offset, $size];
            $this->offset += strlen($chunk);
        }
    }

    public function frames(): int
    {
        return count($this->index);
    }

    public function rate(): int
    {
        return $this->fps;
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function discard(): void
    {
        fclose($this->movi);
        @unlink($this->moviPath);
    }

    /** Writes the finished .avi and returns its path. */
    public function finish(): string
    {
        fclose($this->movi);
        $frames = count($this->index);
        $moviSize = filesize($this->moviPath);
        $buffer = $this->largest + 8;
        $rate = $this->fps;

        $avih = pack('V14', (int) round(1000000 / $rate), $buffer * $rate, 0, 0x10, $frames, 0, 1, $buffer,
            $this->width, $this->height, 0, 0, 0, 0);
        $strh = 'vids'.'MJPG'.pack('VvvVVVVVVVV', 0, 0, 0, 0, 1, $rate, 0, $frames, $buffer, 0xFFFFFFFF, 0)
            .pack('v4', 0, 0, $this->width, $this->height);
        $strf = pack('VVVvv', 40, $this->width, $this->height, 1, 24).'MJPG'.pack('VVVVV', $this->width * $this->height * 3, 0, 0, 0, 0);
        $strl = 'strl'.'strh'.pack('V', strlen($strh)).$strh.'strf'.pack('V', strlen($strf)).$strf;
        $hdrl = 'hdrl'.'avih'.pack('V', strlen($avih)).$avih.'LIST'.pack('V', strlen($strl)).$strl;

        $idx = '';
        foreach ($this->index as [$offset, $size]) {
            $idx .= '00dc'.pack('VVV', 0x10, $offset, $size);
        }
        $riffSize = 4 + (8 + strlen($hdrl)) + (8 + 4 + $moviSize) + (8 + strlen($idx));

        $path = $this->moviPath.'.avi';
        $out = fopen($path, 'wb');
        fwrite($out, 'RIFF'.pack('V', $riffSize).'AVI ');
        fwrite($out, 'LIST'.pack('V', strlen($hdrl)).$hdrl);
        fwrite($out, 'LIST'.pack('V', 4 + $moviSize).'movi');
        $in = fopen($this->moviPath, 'rb');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fwrite($out, 'idx1'.pack('V', strlen($idx)).$idx);
        fclose($out);
        @unlink($this->moviPath);

        return $path;
    }
}
