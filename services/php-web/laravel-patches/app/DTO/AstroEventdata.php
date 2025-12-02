<?php

namespace App\DTO;

readonly class AstroEventData
{
    public function __construct(
        public string $name,
        public string $date,
        public ?string $description = null,
        public array $raw = []
    ) {}
}