<?php

namespace DefStudio\Telegraph\Keyboard;

class Button
{
    private string $url;
    private bool $requestContact;
    private bool $requestLocation;

    /** @var string[] */
    private array $callbackData = [];

    private int $width = 0;

    private function __construct(
        private string $label,
    ) {
    }

    public static function make(string $label): Button
    {
        return new self($label);
    }

    public function width(float $percentage): Button
    {
        $width = (int)($percentage * 100);

        if ($width > 100) {
            $width = 100;
        }

        $this->width = $width;

        return $this;
    }

    public function action(string $name): static
    {
        return $this->param('action', $name);
    }

    public function param(string $key, int|string $value): static
    {
        $key = trim($key);
        $value = trim((string) $value);

        $this->callbackData[] = "$key:$value";

        return $this;
    }

    public function url(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function requestContact(bool $requestContact): static
    {
        $this->requestContact = $requestContact;

        return $this;
    }

    public function requestLocation(bool $requestLocation): static
    {
        $this->requestLocation = $requestLocation;

        return $this;
    }

    /**
     * @return string[]
     */
    public function toArray(): array
    {
        if (count($this->callbackData) > 0) {
            return [
                'text' => $this->label,
                'callback_data' => implode(';', $this->callbackData),
            ];
        }

        if (isset($this->url)) {
            return [
                'text' => $this->label,
                'url' => $this->url,
            ];
        }

        if (isset($this->requestContact)) {
            return [
                'text' => $this->label,
                'request_contact' => $this->requestContact,
            ];
        }

        if (isset($this->requestLocation)) {
            return [
                'text' => $this->label,
                'request_location' => $this->requestLocation,
            ];
        }

        return [
            'text' => $this->label,
        ];
    }

    public function label(): string
    {
        return $this->label;
    }

    public function get_width(): float
    {
        if ($this->width === 0) {
            return 1;
        }

        return round($this->width / 100, 1);
    }

    public function has_width(): bool
    {
        return $this->width > 0;
    }
}
