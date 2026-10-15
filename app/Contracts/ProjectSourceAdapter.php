<?php
namespace App\Contracts;
interface ProjectSourceAdapter { public function sourceIdentifier(): string; public function fetchProjects(): iterable; public function normalizeProject(array $payload): array; }
