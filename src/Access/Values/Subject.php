<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Access\Values;

use Kurt\Modules\MediaLibrary\Access\Enums\SubjectType;

final readonly class Subject
{
    public function __construct(
        public SubjectType $type,
        public ?string $value,
    ) {}

    public function matches(string $rowType, ?string $rowValue): bool
    {
        if ($this->type->value !== $rowType) {
            return false;
        }

        if ($this->type === SubjectType::Everyone) {
            return true;
        }

        return $this->value === $rowValue;
    }
}
