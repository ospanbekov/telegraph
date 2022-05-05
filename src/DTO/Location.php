<?php

namespace DefStudio\Telegraph\DTO;

use Illuminate\Contracts\Support\Arrayable;

class Location implements Arrayable
{
    private float $latitude;
    private float $longitude;

    private function __construct()
    {
    }

    /**
     * @param array{latitude:float, longitude:float,} $data
     */
    public static function fromArray(array $data): Location
    {
        $location = new self();

        $location->latitude  = $data['latitude'];
        $location->longitude = $data['longitude'];

        return $location;
    }

    public function latitude(): float
    {
        return $this->latitude;
    }

    public function longitude(): float
    {
        return $this->longitude;
    }

    public function toArray(): array
    {
        return array_filter(
            [
                'latitude'  => $this->latitude,
                'longitude' => $this->longitude,
            ]
        );
    }
}