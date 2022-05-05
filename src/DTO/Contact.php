<?php

namespace DefStudio\Telegraph\DTO;

use Illuminate\Contracts\Support\Arrayable;

class Contact implements Arrayable
{
    private string $phoneNumber;

    private function __construct()
    {
    }

    /**
     * @param array{phone_number:string} $data
     */
    public static function fromArray(array $data): Contact
    {
        $contact = new self();

        $contact->phoneNumber = $data['phone_number'];

        return $contact;
    }

    public function phoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function toArray(): array
    {
        return array_filter(
            [
                'phone_number' => $this->phoneNumber,
            ]
        );
    }
}