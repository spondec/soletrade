<?php

namespace App\Trade\Stub;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;

abstract class Creator
{
    public string $content;
    protected array $params = [];
    protected readonly string $stubPath;

    public function __construct(protected Filesystem $files)
    {
        $this->stubPath = $this->getStubDir() . $this->getStubFileName();
    }

    public function getStubDir(): string
    {
        return base_path('app/stubs/');
    }

    abstract public function getStubFileName(): string;

    public function setParams(array $params): static
    {
        $this->params = $params;

        return $this;
    }

    public function save(): void
    {
        $fileName = $this->getFileName();
        $destination = $this->getDestinationDir();

        if (!$destination || !$fileName)
        {
            throw new \LogicException('Destination directory or filename was not set.');
        }

        if (!$this->files->exists($this->stubPath))
        {
            throw new FileNotFoundException("Stub file not found at $this->stubPath.");
        }

        if ($this->isFileExists())
        {
            throw new \RuntimeException("File already exists at $destination$fileName.");
        }

        $this->files->put($destination . $fileName, $this->content);
    }

    abstract public function getFileName(): ?string;

    abstract public function getDestinationDir(): ?string;

    public function isFileExists(): bool
    {
        return $this->files->exists($this->getDestinationDir() . $this->getFileName());
    }

    public function apply(): static
    {
        $placeHolders = $this->getPlaceholders();
        $replacements = $this->modifyParams($this->params);

        $content = $this->files->get($this->stubPath);
        $content = $this->replacePlaceholders($placeHolders, $replacements, $content);

        $this->assertNoPlaceholderExists($content);
        $this->content = $this->modifyContent($content);
        return $this;
    }

    abstract public function getPlaceholders(): array;

    protected function modifyParams(array $params): array
    {
        return $params;
    }

    protected function replacePlaceholders(array $placeholders, array $replacements, string $content): string
    {
        foreach ($placeholders as $placeHolder)
        {
            $content = str_replace($this->wrapPlaceholder($placeHolder), $replacements[$placeHolder], $content);
        }
        return $content;
    }

    protected function wrapPlaceholder(mixed $placeHolder): string
    {
        return "{{ $placeHolder }}";
    }

    protected function assertNoPlaceholderExists(string $content): void
    {
        $p = [];
        foreach ($this->getPlaceholders() as $placeHolder)
        {
            if (str_contains($content, $this->wrapPlaceholder($placeHolder)))
            {
                $p[] = $placeHolder;
            }
        }

        if ($p)
        {
            throw new \UnexpectedValueException('Failed to replace placeholders: ' . implode(', ', $p));
        }
    }

    protected function modifyContent(string $content): string
    {
        return $content;
    }
}