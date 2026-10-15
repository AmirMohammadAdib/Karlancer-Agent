<?php
namespace App\Services\ProjectSources;
class ManualProjectSourceAdapter extends MockProjectSourceAdapter {
    public function __construct(private array $records) {}
    public function sourceIdentifier(): string { return 'karlancer'; }
    public function fetchProjects(): iterable { return $this->records; }
}
