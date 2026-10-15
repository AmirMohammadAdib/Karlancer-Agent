<?php

namespace App\Services;

use App\Models\MatchingProfile;
use App\Models\Project;
use App\Models\ProjectMatch;

class ProjectMatchingService
{
    public function score(Project $project, MatchingProfile $profile): array
    {
        $skills = $this->list($profile->preferred_skills);
        $positive = $this->list($profile->positive_keywords, true);
        $negative = $this->list($profile->negative_keywords, true);
        $projectSkills = $this->list($project->required_skills);
        $text = $this->normalize(implode(' ', [$project->title, $project->description]));

        $matchedSkills = array_values(array_intersect($skills, $projectSkills));
        $missingSkills = array_values(array_diff($skills, $projectSkills));
        $matchedPositive = array_values(array_filter($positive, fn ($word) => str_contains($text, $word)));
        $matchedNegative = array_values(array_filter($negative, fn ($word) => str_contains($text, $word)));
        $skillPoints = $skills ? (int) round(count($matchedSkills) / count($skills) * 45) : 0;
        $keywordPoints = $positive ? (int) round(count($matchedPositive) / count($positive) * 25) : 0;
        $budget = $this->budgetResult($project, $profile);
        $negativePenalty = $negative ? min(30, (int) round(count($matchedNegative) / count($negative) * 30)) : 0;
        $positiveConfigured = $skills || $positive || $profile->min_budget !== null || $profile->max_budget !== null;
        $allPositiveMatched = (! $skills || count($matchedSkills) === count($skills)) && (! $positive || count($matchedPositive) === count($positive)) && ($budget['configured'] === false || $budget['compatible'] === true);
        $bonus = $positiveConfigured && $allPositiveMatched && ! $matchedNegative ? 10 : 0;
        $score = max(0, min(100, $skillPoints + $keywordPoints + $budget['points'] + $bonus - $negativePenalty));

        return ['score' => $score, 'source_hash' => $this->sourceHash($project, $profile), 'breakdown' => ['skill_points' => $skillPoints, 'keyword_points' => $keywordPoints, 'budget_points' => $budget['points'], 'profile_bonus' => $bonus, 'negative_penalty' => $negativePenalty, 'matched_skills' => $matchedSkills, 'missing_skills' => $missingSkills, 'matched_positive_keywords' => $matchedPositive, 'matched_negative_keywords' => $matchedNegative, 'budget' => $budget, 'explanation' => $this->explanation($score, $matchedSkills, $matchedPositive, $matchedNegative, $budget)]];
    }

    public function persist(Project $project, MatchingProfile $profile): ProjectMatch
    {
        $result = $this->score($project, $profile);

        return ProjectMatch::updateOrCreate(['matching_profile_id' => $profile->id, 'project_id' => $project->id], ['score' => $result['score'], 'breakdown' => $result['breakdown'], 'source_hash' => $result['source_hash'], 'scored_at' => now()]);
    }

    public function ensureCurrent(Project $project, MatchingProfile $profile): ProjectMatch
    {
        $match = ProjectMatch::where('matching_profile_id', $profile->id)->where('project_id', $project->id)->first();

        return $match && $match->source_hash === $this->sourceHash($project, $profile) ? $match : $this->persist($project, $profile);
    }

    public function recalculate(MatchingProfile $profile): int
    {
        $count = 0;
        Project::where('platform', 'mock')->each(function (Project $project) use ($profile, &$count) {
            $this->persist($project, $profile);
            $count++;
        });

        return $count;
    }

    public function sourceHash(Project $project, MatchingProfile $profile): string
    {
        return hash('sha256', json_encode(['project' => [$project->id, $project->title, $project->description, $project->budget, $project->currency, $project->required_skills], 'profile' => [$profile->id, $profile->preferred_skills, $profile->positive_keywords, $profile->negative_keywords, $profile->min_budget, $profile->max_budget, $profile->preferred_currency, $profile->min_score, $profile->enabled]], JSON_UNESCAPED_UNICODE));
    }

    private function budgetResult(Project $project, MatchingProfile $profile): array
    {
        $configured = $profile->min_budget !== null || $profile->max_budget !== null;
        if (! $configured) {
            return ['configured' => false, 'compatible' => null, 'currency_compatible' => null, 'points' => 0, 'label' => 'بودجه در ترجیحات تنظیم نشده است.'];
        }
        if ($project->budget === null || ! is_numeric($project->budget)) {
            return ['configured' => true, 'compatible' => false, 'currency_compatible' => null, 'points' => 0, 'label' => 'بودجه پروژه نامشخص است.'];
        }
        if (! $profile->preferred_currency || ! $project->currency || $profile->preferred_currency !== $project->currency) {
            return ['configured' => true, 'compatible' => false, 'currency_compatible' => false, 'points' => 0, 'label' => 'واحد پول با ترجیح شما سازگار نیست.'];
        }
        $amount = (float) $project->budget;
        $compatible = ($profile->min_budget === null || $amount >= (float) $profile->min_budget) && ($profile->max_budget === null || $amount <= (float) $profile->max_budget);

        return ['configured' => true, 'compatible' => $compatible, 'currency_compatible' => true, 'points' => $compatible ? 20 : 0, 'label' => $compatible ? 'بودجه و واحد پول سازگار است.' : 'بودجه خارج از بازه ترجیحی است.'];
    }

    private function list(mixed $items, bool $short = false): array
    {
        if (is_string($items)) {
            $items = json_decode($items, true);
        }
        if (! is_array($items)) {
            $items = [];
        }

        return collect($items ?? [])->map(fn ($item) => $this->normalize((string) $item))->filter(fn ($item) => ! $short || mb_strlen($item) >= 2)->filter()->unique()->values()->all();
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'ۀ' => 'ه']);
        $value = preg_replace('/[\x{064B}-\x{065F}]/u', '', $value) ?? $value;

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private function explanation(int $score, array $skills, array $positive, array $negative, array $budget): string
    {
        $parts = ["امتیاز نهایی {$score} از ۱۰۰"];
        if ($skills) {
            $parts[] = count($skills).' مهارت ترجیحی منطبق شد';
        } if ($positive) {
            $parts[] = count($positive).' کلیدواژه مثبت یافت شد';
        } if ($negative) {
            $parts[] = count($negative).' کلیدواژه منفی باعث کسر امتیاز شد';
        } $parts[] = $budget['label'];

        return implode('؛ ', $parts).'.';
    }
}
