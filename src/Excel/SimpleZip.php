<?php

declare(strict_types=1);

namespace LiteExport\Excel;

/**
 * Pure PHP 8.2 zero-dependency streaming Zip archive generator.
 * Implements PKWARE .ZIP specification using PHP's built-in zlib.
 * Ensures LiteExport runs on any PHP environment without requiring the ext-zip extension.
 */
class SimpleZip
{
    /** @var resource */
    private $stream;
    private int $offset = 0;

    /** @var array<int, array{name: string, crc32: int, compSize: int, uncompSize: int, offset: int, method: int, time: int, date: int}> */
    private array $entries = [];

    public function __construct(string $filePath)
    {
        $handle = fopen($filePath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open {$filePath} for writing zip archive.");
        }
        $this->stream = $handle;
    }

    public function addFromString(string $name, string $data): void
    {
        $time = time();
        $dtime = self::dosTime($time);
        $crc = crc32($data);
        $uncompSize = strlen($data);

        $compData = gzdeflate($data, 6);
        $method = 8; // DEFLATE
        $compSize = $compData !== false ? strlen($compData) : $uncompSize;

        if ($compData === false || $compSize >= $uncompSize) {
            $compData = $data;
            $method = 0; // STORE
            $compSize = $uncompSize;
        }

        $localHeaderOffset = $this->offset;

        // Local file header: PK\x03\x04
        $header = pack(
            'VvvvvvVVVvv',
            0x04034b50, // signature
            20,         // version needed (2.0)
            0x0800,     // general purpose bit flag (UTF-8 filename)
            $method,    // compression method
            $dtime['time'],
            $dtime['date'],
            $crc,
            $compSize,
            $uncompSize,
            strlen($name),
            0           // extra field length
        ) . $name;

        fwrite($this->stream, $header);
        fwrite($this->stream, $compData);

        $this->offset += strlen($header) + $compSize;

        $this->entries[] = [
            'name' => $name,
            'crc32' => $crc,
            'compSize' => $compSize,
            'uncompSize' => $uncompSize,
            'offset' => $localHeaderOffset,
            'method' => $method,
            'time' => $dtime['time'],
            'date' => $dtime['date'],
        ];
    }

    public function close(): void
    {
        $centralDirStart = $this->offset;

        // Central directory file headers
        foreach ($this->entries as $entry) {
            $cd = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50, // signature PK\x01\x02
                20,         // version made by
                20,         // version needed
                0x0800,     // UTF-8 flag
                $entry['method'],
                $entry['time'],
                $entry['date'],
                $entry['crc32'],
                $entry['compSize'],
                $entry['uncompSize'],
                strlen($entry['name']),
                0, // extra field len
                0, // comment len
                0, // disk number start
                0, // internal attr
                0, // external attr
                $entry['offset']
            ) . $entry['name'];

            fwrite($this->stream, $cd);
            $this->offset += strlen($cd);
        }

        $centralDirSize = $this->offset - $centralDirStart;

        // End of central directory record (EOCD): PK\x05\x06
        $eocd = pack(
            'VvvvvVVv',
            0x06054b50,
            0, // disk number
            0, // disk with central dir
            count($this->entries), // entries on this disk
            count($this->entries), // total entries
            $centralDirSize,
            $centralDirStart,
            0 // comment length
        );

        fwrite($this->stream, $eocd);
        fclose($this->stream);
    }

    private static function dosTime(int $timestamp): array
    {
        $date = getdate($timestamp);
        return [
            'time' => ($date['hours'] << 11) | ($date['minutes'] << 5) | ($date['seconds'] >> 1),
            'date' => (($date['year'] - 1980) << 9) | ($date['mon'] << 5) | $date['mday'],
        ];
    }
}
