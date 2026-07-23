<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO;

final readonly class InspectionData
{
    /**
     * @param  list<InspectionSection>  $sections
     */
    public function __construct(
        public string $subjectId,
        public string $subjectType,
        public string $title,
        public ?string $subtitle,
        public array $sections,
    ) {}

    /**
     * @return array{subject_id: string, subject_type: string, title: string, subtitle: string|null, sections: list<array{key: string, title: string, entries: array<string, scalar|list<scalar>|null>}>}
     */
    public function toArray(): array
    {
        return [
            'subject_id' => $this->subjectId,
            'subject_type' => $this->subjectType,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'sections' => array_map(
                static fn (InspectionSection $section): array => $section->toArray(),
                $this->sections,
            ),
        ];
    }
}
