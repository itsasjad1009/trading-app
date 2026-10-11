<?php

namespace App\MarketData;

final class PipelineDiagnostics
{
    private $file = null;
    public function open(?string $path): void
    {
        if (!$path) return;
        $parent = realpath(dirname($path));
        if (!$parent || !str_starts_with($parent.DIRECTORY_SEPARATOR, base_path().DIRECTORY_SEPARATOR)) throw new \RuntimeException('Diagnostic path outside workspace');
        $this->file = fopen($path, 'x');
        if (!$this->file) throw new \RuntimeException('Diagnostic file already exists');
    }
    public function record(array $row): void
    {
        if ($this->file) fwrite($this->file, json_encode($row, JSON_THROW_ON_ERROR)."\n");
    }
    public function close(): void {if ($this->file) fclose($this->file);$this->file=null;}
}
