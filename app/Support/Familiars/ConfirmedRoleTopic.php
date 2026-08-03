<?php

namespace App\Support\Familiars;

use App\Enums\FamiliarTopicType;
use App\Enums\Priority;

readonly class ConfirmedRoleTopic
{
    public function __construct(
        public string $id,
        public string $label,
        public FamiliarTopicType $type,
        public Priority $priority,
    ) {}

    /** @return array{id: string, label: string, type: string, priority: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'type' => $this->type->value,
            'priority' => $this->priority->value,
        ];
    }

    /** @return array{label: string, type: string, priority: string} */
    public function promptContext(): array
    {
        return [
            'label' => $this->label,
            'type' => $this->type->value,
            'priority' => $this->priority->value,
        ];
    }
}
