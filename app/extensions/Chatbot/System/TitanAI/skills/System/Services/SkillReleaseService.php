<?php

declare(strict_types=1);

namespace App\Extensions\SystemAIChatSkills\System\Services;

use App\Contracts\LoggerContract;
use App\Extensions\SystemAIChatSkills\System\Models\Skill;
use App\Extensions\SystemAIChatSkills\System\Models\SkillVersion;
use App\Extensions\SystemAIChatSkills\System\Packages\SkillPackageFingerprint;
use App\Extensions\SystemAIChatSkills\System\Packages\SkillPackageHasher;
use App\Extensions\SystemAIChatSkills\System\Packages\SkillPackageVerificationResult;
use App\Extensions\SystemAIChatSkills\System\Packages\SkillPackageVerificationStatus;
use App\Extensions\SystemAIChatSkills\System\Packages\SkillPackageVerifier;
use App\Extensions\SystemAIChatSkills\System\Packages\SkillResourceFingerprint;
use App\Extensions\SystemAIChatSkills\System\Packages\SkillResourceScanner;
use App\Extensions\SystemAIChatSkills\System\Support\SkillUpdatePolicy;
use App\Extensions\SystemAIChatSkills\System\Support\SkillVersionStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

final class SkillReleaseService
{
    public function __construct(
        private readonly SkillPackageHasher $packageHasher,
        private readonly SkillPackageVerifier $packageVerifier,
        private readonly SkillResourceScanner $resourceScanner,
        private readonly LoggerContract $logger,
    ) {
    }

    public function createInitialPublishedRelease(Skill $skill): SkillVersion
    {
        try {
            if ($skill->versions()->exists()) {
                $existingVersion = $skill->versions()->oldest('id')->firstOrFail();
                $this->logger->debug('Skill version already exists', [
                    'skill_id' => $skill->getAttribute('id'),
                    'skill_name' => $skill->getAttribute('name'),
                    'version_id' => $existingVersion->getAttribute('id'),
                ]);
                return $existingVersion;
            }

            $this->logger->info('Creating initial skill release', [
                'skill_id' => $skill->getAttribute('id'),
                'skill_name' => $skill->getAttribute('name'),
                'version' => $skill->version ?: '1.0.0',
            ]);

            $resources = $this->scanSkillResources($skill);
            $fingerprint = $this->fingerprintPayload([
                'version' => $skill->version ?: '1.0.0',
                'name' => $skill->name,
                'description' => $skill->description,
                'instructions' => $skill->instructions,
                'bundled_resources' => $skill->bundled_resources,
                'metadata' => $skill->metadata,
            ], $resources);
            $provenance = $this->provenanceFromMetadata($skill->metadata, $skill);
            $integrity = $this->initialIntegrityDecision($skill, $fingerprint);

            return DB::transaction(function () use ($skill, $resources, $fingerprint, $provenance, $integrity): SkillVersion {
                $version = $skill->versions()->create([
                    'version' => $skill->version ?: '1.0.0',
                    'lifecycle_status' => $integrity['status'] === SkillPackageVerificationStatus::VERIFIED
                        ? SkillVersionStatus::PUBLISHED
                        : SkillVersionStatus::QUARANTINED,
                    'name' => $skill->name,
                    'description' => $skill->description,
                    'instructions' => $skill->instructions,
                    'bundled_resources' => $skill->bundled_resources,
                    'metadata' => $skill->metadata,
                    'content_hash' => $fingerprint->packageHash,
                    'package_hash' => $fingerprint->packageHash,
                    'manifest_hash' => $fingerprint->manifestHash,
                    'instructions_hash' => $fingerprint->instructionsHash,
                    'source' => $skill->source,
                    'source_url' => $skill->source_url,
                    'source_repository' => $provenance['source_repository'],
                    'source_branch' => $provenance['source_branch'],
                    'source_commit_sha' => $provenance['source_commit_sha'],
                    'imported_at' => $provenance['imported_at'],
                    'imported_by' => $provenance['imported_by'],
                    'verification_status' => $integrity['status'],
                    'verification_details' => $integrity['details'],
                    'verified_at' => now(),
                    'release_notes' => 'Initial release.',
                    'created_by' => $skill->created_by ?? $skill->user_id,
                    'published_by' => $skill->managed_by ?? $skill->created_by ?? $skill->user_id,
                    'published_at' => now(),
                ]);

                $this->persistResources($version, $resources);
                $this->activateVersion(
                    $skill,
                    $version,
                    $integrity['status'] === SkillPackageVerificationStatus::VERIFIED,
                );

                if ($integrity['status'] !== SkillPackageVerificationStatus::VERIFIED) {
                    Skill::withoutEvents(function () use ($skill, $integrity): void {
                        $skill->forceFill([
                            'status' => 'inactive',
                            'verification_status' => $integrity['status'],
                            'latest_published_version_id' => null,
                        ])->save();
                    });

                    $this->logger->warning('Skill release quarantined due to verification failure', [
                        'skill_id' => $skill->getAttribute('id'),
                        'version_id' => $version->getAttribute('id'),
                        'verification_status' => $integrity['status'],
                    ]);
                } else {
                    $this->logger->info('Skill release published successfully', [
                        'skill_id' => $skill->getAttribute('id'),
                        'version_id' => $version->getAttribute('id'),
                        'version' => $version->getAttribute('version'),
                    ]);
                }

                return $version->fresh(['resources']);
            });
        } catch (Throwable $e) {
            $this->logger->error('Failed to create initial skill release', [
                'skill_id' => $skill->getAttribute('id'),
                'skill_name' => $skill->getAttribute('name'),
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);
            throw $e;
        }
    }

    public function createDraft(Skill $skill, ?int $actorId, ?SkillVersion $baseVersion = null, ?string $version = null): SkillVersion
    {
        $baseVersion ??= $skill->currentVersion ?: $skill->latestPublishedVersion;
        $version ??= $this->nextPatchVersion($skill);

        if (! preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version)) {
            throw new InvalidArgumentException('Skill versions must use semantic version syntax.');
        }

        if ($skill->versions()->where('version', $version)->exists()) {
            throw new LogicException("Skill version {$version} already exists.");
        }

        $payload = [
            'version' => $version,
            'name' => $baseVersion?->name ?? $skill->name,
            'description' => $baseVersion?->description ?? $skill->description,
            'instructions' => $baseVersion?->instructions ?? $skill->instructions,
            'bundled_resources' => $baseVersion?->bundled_resources ?? $skill->bundled_resources,
            'metadata' => $baseVersion?->metadata ?? $skill->metadata,
        ];
        $resources = $baseVersion !== null
            ? $this->storedResourceFingerprints($baseVersion)
            : $this->scanSkillResources($skill);
        $fingerprint = $this->fingerprintPayload($payload, $resources);

        $draft = $skill->versions()->create([
            ...$payload,
            'lifecycle_status' => SkillVersionStatus::DRAFT,
            'content_hash' => $fingerprint->packageHash,
            'package_hash' => $fingerprint->packageHash,
            'manifest_hash' => $fingerprint->manifestHash,
            'instructions_hash' => $fingerprint->instructionsHash,
            'source' => $baseVersion?->source ?? $skill->source,
            'source_url' => $baseVersion?->source_url ?? $skill->source_url,
            'source_repository' => $baseVersion?->source_repository,
            'source_branch' => $baseVersion?->source_branch,
            'source_commit_sha' => $baseVersion?->source_commit_sha,
            'imported_at' => $baseVersion?->imported_at,
            'imported_by' => $baseVersion?->imported_by,
            'verification_status' => SkillPackageVerificationStatus::UNVERIFIED,
            'based_on_version_id' => $baseVersion?->id,
            'created_by' => $actorId,
        ]);

        $this->persistResources($draft, $resources);

        return $draft->fresh(['resources']);
    }

    /** @param array<string, mixed> $attributes */
    public function updateDraft(SkillVersion $version, array $attributes): SkillVersion
    {
        if (! $version->isContentMutable()) {
            throw new LogicException('Only draft skill versions may be edited.');
        }

        $allowed = array_intersect_key($attributes, array_flip([
            'version',
            'name',
            'description',
            'instructions',
            'bundled_resources',
            'metadata',
            'source',
            'source_url',
            'source_repository',
            'source_branch',
            'source_commit_sha',
            'release_notes',
        ]));

        if (isset($allowed['version']) && $allowed['version'] !== $version->version) {
            if (! preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', (string) $allowed['version'])) {
                throw new InvalidArgumentException('Skill versions must use semantic version syntax.');
            }

            if ($version->skill->versions()->where('version', $allowed['version'])->where('id', '!=', $version->id)->exists()) {
                throw new LogicException("Skill version {$allowed['version']} already exists.");
            }
        }

        $prospective = array_merge($version->only([
            'version', 'name', 'description', 'instructions', 'bundled_resources', 'metadata',
        ]), $allowed);
        $fingerprint = $this->fingerprintPayload($prospective, $this->storedResourceFingerprints($version));
        $allowed['content_hash'] = $fingerprint->packageHash;
        $allowed['package_hash'] = $fingerprint->packageHash;
        $allowed['manifest_hash'] = $fingerprint->manifestHash;
        $allowed['instructions_hash'] = $fingerprint->instructionsHash;
        $allowed['verification_status'] = SkillPackageVerificationStatus::UNVERIFIED;
        $allowed['verification_details'] = null;
        $allowed['verified_at'] = null;
        $version->fill($allowed)->save();

        return $version->fresh(['resources']);
    }

    public function publish(SkillVersion $version, ?int $actorId, ?string $releaseNotes = null): SkillVersion
    {
        if (! SkillVersionStatus::canPublish($version->lifecycle_status)) {
            throw new LogicException('Only draft or approved skill versions may be published.');
        }

        return DB::transaction(function () use ($version, $actorId, $releaseNotes): SkillVersion {
            $resources = $this->scanVersionResources($version);
            $calculated = $this->fingerprintPayload($version->only([
                'version', 'name', 'description', 'instructions', 'bundled_resources', 'metadata',
            ]), $resources);

            if ($version->lifecycle_status === SkillVersionStatus::APPROVED
                && ! hash_equals((string) ($version->package_hash ?: $version->content_hash), $calculated->packageHash)) {
                throw new LogicException('Approved skill package changed after review. Return it to draft before publishing.');
            }

            $updates = [
                'lifecycle_status' => SkillVersionStatus::PUBLISHED,
                'release_notes' => $releaseNotes ?? $version->release_notes,
                'published_by' => $actorId,
                'published_at' => now(),
                'verification_status' => SkillPackageVerificationStatus::VERIFIED,
                'verification_details' => ['verified_during' => 'publish'],
                'verified_at' => now(),
            ];

            if ($version->lifecycle_status === SkillVersionStatus::DRAFT) {
                $updates += [
                    'content_hash' => $calculated->packageHash,
                    'package_hash' => $calculated->packageHash,
                    'manifest_hash' => $calculated->manifestHash,
                    'instructions_hash' => $calculated->instructionsHash,
                ];
                $this->persistResources($version, $resources, true);
            }

            $version->forceFill($updates)->save();

            $skill = $version->skill()->lockForUpdate()->firstOrFail();
            $this->activateVersion($skill, $version, true);
            $this->advanceInstallations($skill, $version);

            return $version->fresh(['resources']);
        });
    }

    public function verifyPackage(SkillVersion $version): SkillPackageVerificationResult
    {
        $resources = [];

        try {
            $expected = $this->expectedFingerprint($version);
            $resources = $this->scanVersionResources($version);
            $result = $this->packageVerifier->verify(
                $expected,
                $this->manifestPayload($version->only([
                    'version', 'name', 'description', 'bundled_resources', 'metadata',
                ])),
                (string) $version->instructions,
                $resources,
            );
        } catch (RuntimeException|InvalidArgumentException $exception) {
            $actual = $this->fingerprintPayload($version->only([
                'version', 'name', 'description', 'instructions', 'bundled_resources', 'metadata',
            ]), []);
            $result = new SkillPackageVerificationResult(
                SkillPackageVerificationStatus::CORRUPTED,
                $actual,
                ['resources'],
                $exception->getMessage(),
            );
        }

        if ($result->isVerified()
            && $version->lifecycle_status === SkillVersionStatus::QUARANTINED
            && in_array($version->verification_status, [
                SkillPackageVerificationStatus::CORRUPTED,
                SkillPackageVerificationStatus::SOURCE_CHANGED,
            ], true)) {
            $result = new SkillPackageVerificationResult(
                $version->verification_status,
                $result->actual,
                [],
                is_array($version->verification_details)
                    ? (string) ($version->verification_details['reason'] ?? 'This package remains quarantined pending review.')
                    : 'This package remains quarantined pending review.',
            );
        }

        $canonicalUpgrade = $version->manifest_hash === null || $version->instructions_hash === null;
        $versionUpdates = [
            'verification_status' => $result->status,
            'verification_details' => $result->toArray(),
            'verified_at' => now(),
        ];

        if ($canonicalUpgrade && $result->isVerified()) {
            $versionUpdates += [
                'content_hash' => $result->actual->packageHash,
                'package_hash' => $result->actual->packageHash,
                'manifest_hash' => $result->actual->manifestHash,
                'instructions_hash' => $result->actual->instructionsHash,
            ];
            $this->persistResources($version, $resources, true);
        }

        SkillVersion::withoutEvents(function () use ($version, $versionUpdates): void {
            $version->forceFill($versionUpdates)->save();
        });

        if ((int) $version->skill->current_version_id === (int) $version->id) {
            Skill::withoutEvents(function () use ($version, $result): void {
                $version->skill->forceFill(['verification_status' => $result->status])->save();
            });
        }

        return $result;
    }

    public function rollback(Skill $skill, SkillVersion $version, ?int $actorId = null): SkillVersion
    {
        if ((int) $version->skill_id !== (int) $skill->id || ! $version->isInstallable()) {
            throw new LogicException('Rollback requires an installable version belonging to the skill.');
        }

        return DB::transaction(function () use ($skill, $version): SkillVersion {
            $lockedSkill = $skill->newQuery()->lockForUpdate()->findOrFail($skill->id);
            $this->activateVersion($lockedSkill, $version, false);
            $this->advanceInstallations($lockedSkill, $version);

            return $version->fresh(['resources']);
        });
    }

    /** @return array<string, array{from: mixed, to: mixed}> */
    public function compare(SkillVersion $from, SkillVersion $to): array
    {
        if ((int) $from->skill_id !== (int) $to->skill_id) {
            throw new LogicException('Only versions of the same skill may be compared.');
        }

        $differences = [];
        foreach ([
            'version', 'name', 'description', 'instructions', 'bundled_resources', 'metadata',
            'source_url', 'source_repository', 'source_branch', 'source_commit_sha', 'package_hash',
        ] as $field) {
            if ($from->{$field} !== $to->{$field}) {
                $differences[$field] = ['from' => $from->{$field}, 'to' => $to->{$field}];
            }
        }

        return $differences;
    }

    public function transition(SkillVersion $version, string $status, ?int $actorId = null): SkillVersion
    {
        $transitions = [
            SkillVersionStatus::DRAFT => [
                SkillVersionStatus::VALIDATING,
                SkillVersionStatus::REVIEW_REQUIRED,
                SkillVersionStatus::APPROVED,
                SkillVersionStatus::QUARANTINED,
            ],
            SkillVersionStatus::VALIDATING => [
                SkillVersionStatus::DRAFT,
                SkillVersionStatus::REVIEW_REQUIRED,
                SkillVersionStatus::APPROVED,
                SkillVersionStatus::QUARANTINED,
            ],
            SkillVersionStatus::REVIEW_REQUIRED => [
                SkillVersionStatus::DRAFT,
                SkillVersionStatus::APPROVED,
                SkillVersionStatus::QUARANTINED,
            ],
            SkillVersionStatus::APPROVED => [
                SkillVersionStatus::DRAFT,
                SkillVersionStatus::QUARANTINED,
            ],
            SkillVersionStatus::PUBLISHED => [
                SkillVersionStatus::DEPRECATED,
                SkillVersionStatus::SUSPENDED,
                SkillVersionStatus::ARCHIVED,
                SkillVersionStatus::QUARANTINED,
            ],
            SkillVersionStatus::DEPRECATED => [
                SkillVersionStatus::SUSPENDED,
                SkillVersionStatus::ARCHIVED,
                SkillVersionStatus::QUARANTINED,
            ],
            SkillVersionStatus::SUSPENDED => [
                SkillVersionStatus::ARCHIVED,
                SkillVersionStatus::QUARANTINED,
            ],
            SkillVersionStatus::QUARANTINED => [
                SkillVersionStatus::DRAFT,
                SkillVersionStatus::ARCHIVED,
            ],
            SkillVersionStatus::ARCHIVED => [],
        ];

        if (! in_array($status, $transitions[$version->lifecycle_status] ?? [], true)) {
            throw new LogicException("Cannot transition skill version from {$version->lifecycle_status} to {$status}.");
        }

        $timestamps = match ($status) {
            SkillVersionStatus::DEPRECATED => ['deprecated_at' => now()],
            SkillVersionStatus::SUSPENDED => ['suspended_at' => now()],
            SkillVersionStatus::ARCHIVED => ['archived_at' => now()],
            default => [],
        };

        $version->forceFill(array_merge(['lifecycle_status' => $status], $timestamps))->save();

        if (in_array($status, [SkillVersionStatus::SUSPENDED, SkillVersionStatus::ARCHIVED, SkillVersionStatus::QUARANTINED], true)
            && (int) $version->skill->current_version_id === (int) $version->id) {
            $fallback = $version->skill->versions()
                ->where('id', '!=', $version->id)
                ->whereIn('lifecycle_status', SkillVersionStatus::INSTALLABLE)
                ->orderByDesc('published_at')
                ->first();

            if ($fallback !== null) {
                $this->activateVersion($version->skill, $fallback, false);
                $this->advanceInstallations($version->skill, $fallback);
            } else {
                $version->skill->update(['status' => 'inactive']);
            }
        }

        return $version->fresh(['resources']);
    }

    public function nextPatchVersion(Skill $skill): string
    {
        $highest = '0.0.0';

        foreach ($skill->versions()->pluck('version') as $version) {
            $normalised = preg_replace('/-.+$/', '', (string) $version);
            if (is_string($normalised) && preg_match('/^\d+\.\d+\.\d+$/', $normalised)
                && version_compare($normalised, $highest, '>')) {
                $highest = $normalised;
            }
        }

        [$major, $minor, $patch] = array_map('intval', explode('.', $highest));

        return $major . '.' . $minor . '.' . ($patch + 1);
    }

    /** @param array<string, mixed> $payload */
    public function hashContent(array $payload): string
    {
        return $this->fingerprintPayload($payload, [])->packageHash;
    }

    /** @param array<string, mixed> $payload @param list<SkillResourceFingerprint> $resources */
    private function fingerprintPayload(array $payload, array $resources): SkillPackageFingerprint
    {
        return $this->packageHasher->fingerprint(
            $this->manifestPayload($payload),
            (string) ($payload['instructions'] ?? ''),
            $resources,
        );
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function manifestPayload(array $payload): array
    {
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        unset($metadata['provenance'], $metadata['package_manifest'], $metadata['verification']);

        return [
            'name' => (string) ($payload['name'] ?? ''),
            'description' => (string) ($payload['description'] ?? ''),
            'version' => (string) ($payload['version'] ?? '1.0.0'),
            'metadata' => $metadata,
        ];
    }

    /** @return list<SkillResourceFingerprint> */
    private function scanSkillResources(Skill $skill): array
    {
        return $this->resourceScanner->scan($skill->absoluteStoragePath(), $skill->bundled_resources);
    }

    /** @return list<SkillResourceFingerprint> */
    private function scanVersionResources(SkillVersion $version): array
    {
        return $this->resourceScanner->scan($version->skill->absoluteStoragePath(), $version->bundled_resources);
    }

    /** @return list<SkillResourceFingerprint> */
    private function storedResourceFingerprints(SkillVersion $version): array
    {
        if (Schema::hasTable('skill_version_resources')) {
            $resources = $version->resources()->orderBy('path')->get();
            if ($resources->isNotEmpty()) {
                return $resources->map(static fn ($resource): SkillResourceFingerprint => $resource->fingerprint())->all();
            }
        }

        return $this->scanVersionResources($version);
    }

    /** @param list<SkillResourceFingerprint> $resources */
    private function persistResources(SkillVersion $version, array $resources, bool $replace = false): void
    {
        if (! Schema::hasTable('skill_version_resources')) {
            return;
        }

        if ($replace) {
            $version->resources()->delete();
        }

        foreach ($resources as $resource) {
            $version->resources()->updateOrCreate(
                ['path' => $resource->path],
                [
                    'sha256' => $resource->sha256,
                    'size' => $resource->size,
                    'mime_type' => $resource->mimeType,
                ],
            );
        }
    }

    private function expectedFingerprint(SkillVersion $version): SkillPackageFingerprint
    {
        $resources = [];
        foreach ($this->storedResourceFingerprints($version) as $resource) {
            $resources[$resource->path] = $resource->sha256;
        }
        ksort($resources, SORT_STRING);

        $fallback = $this->fingerprintPayload($version->only([
            'version', 'name', 'description', 'instructions', 'bundled_resources', 'metadata',
        ]), $this->storedResourceFingerprints($version));

        $hasCanonicalHashes = is_string($version->manifest_hash) && strlen($version->manifest_hash) === 64
            && is_string($version->instructions_hash) && strlen($version->instructions_hash) === 64;

        return new SkillPackageFingerprint(
            packageHash: $hasCanonicalHashes
                ? (string) ($version->package_hash ?: $version->content_hash ?: $fallback->packageHash)
                : $fallback->packageHash,
            manifestHash: $hasCanonicalHashes ? (string) $version->manifest_hash : $fallback->manifestHash,
            instructionsHash: $hasCanonicalHashes ? (string) $version->instructions_hash : $fallback->instructionsHash,
            resourceHashes: $resources,
        );
    }

    /** @return array{status: string, details: array<string, mixed>} */
    private function initialIntegrityDecision(Skill $skill, SkillPackageFingerprint $fingerprint): array
    {
        $metadata = is_array($skill->metadata) ? $skill->metadata : [];
        $declared = is_array($metadata['package_manifest'] ?? null) ? $metadata['package_manifest'] : [];
        $declaredHash = $declared['identity']['package_hash'] ?? $declared['package_hash'] ?? null;
        $details = ['created_from' => $skill->source ?: 'created'];
        $status = SkillPackageVerificationStatus::VERIFIED;

        if (is_string($declaredHash) && preg_match('/^[a-f0-9]{64}$/', $declaredHash)) {
            $details['declared_package_hash'] = $declaredHash;
            if (! hash_equals($declaredHash, $fingerprint->packageHash)) {
                $status = SkillPackageVerificationStatus::CORRUPTED;
                $details['reason'] = 'The declared package hash does not match the imported content.';
            }
        }

        if (Schema::hasColumn('skill_versions', 'package_hash')) {
            $duplicateCount = SkillVersion::query()
                ->where('package_hash', $fingerprint->packageHash)
                ->count();
            if ($duplicateCount > 0) {
                $details['duplicate_count'] = $duplicateCount;
            }

            if ($status === SkillPackageVerificationStatus::VERIFIED && is_string($skill->source_url) && $skill->source_url !== '') {
                $sourceConflictCount = SkillVersion::query()
                    ->where('source_url', $skill->source_url)
                    ->where('version', $skill->version ?: '1.0.0')
                    ->whereNotNull('package_hash')
                    ->where('package_hash', '!=', $fingerprint->packageHash)
                    ->count();
                if ($sourceConflictCount > 0) {
                    $status = SkillPackageVerificationStatus::SOURCE_CHANGED;
                    $details['source_conflict_count'] = $sourceConflictCount;
                    $details['reason'] = 'The same source and semantic version were previously imported with different content.';
                }
            }
        }

        $details['actual_package_hash'] = $fingerprint->packageHash;

        return ['status' => $status, 'details' => $details];
    }

    /** @param array<string, mixed>|null $metadata @return array<string, mixed> */
    private function provenanceFromMetadata(?array $metadata, Skill $skill): array
    {
        $provenance = is_array($metadata['provenance'] ?? null) ? $metadata['provenance'] : [];

        return [
            'source_repository' => $provenance['repository'] ?? null,
            'source_branch' => $provenance['branch'] ?? null,
            'source_commit_sha' => $provenance['commit_sha'] ?? null,
            'imported_at' => in_array($skill->source, ['uploaded', 'github'], true) ? now() : null,
            'imported_by' => in_array($skill->source, ['uploaded', 'github'], true)
                ? ($skill->created_by ?? $skill->user_id)
                : null,
        ];
    }

    private function activateVersion(Skill $skill, SkillVersion $version, bool $latestPublished): void
    {
        $updates = [
            'current_version_id' => $version->id,
            'name' => $version->name,
            'description' => $version->description,
            'instructions' => $version->instructions,
            'bundled_resources' => $version->bundled_resources,
            'metadata' => $version->metadata,
            'version' => $version->version,
            'content_hash' => $version->package_hash ?: $version->content_hash,
            'package_hash' => $version->package_hash ?: $version->content_hash,
            'verification_status' => $version->verification_status ?: SkillPackageVerificationStatus::UNVERIFIED,
            'source' => $version->source,
            'source_url' => $version->source_url,
            'status' => 'active',
        ];

        if ($latestPublished) {
            $updates['latest_published_version_id'] = $version->id;
        }

        Skill::withoutEvents(function () use ($skill, $updates): void {
            $skill->forceFill($updates)->save();
        });
    }

    private function advanceInstallations(Skill $skill, SkillVersion $version): void
    {
        $major = $this->major($version->version);

        DB::table('user_skills')
            ->where('skill_id', $skill->id)
            ->where('update_policy', SkillUpdatePolicy::FOLLOW_LATEST)
            ->update(['skill_version_id' => $version->id, 'updated_at' => now()]);

        DB::table('company_skills')
            ->where('skill_id', $skill->id)
            ->where('update_policy', SkillUpdatePolicy::FOLLOW_LATEST)
            ->update(['skill_version_id' => $version->id, 'updated_at' => now()]);

        $this->advanceCompatibleTable('user_skills', $skill, $version, $major);
        $this->advanceCompatibleTable('company_skills', $skill, $version, $major);
    }

    private function advanceCompatibleTable(string $table, Skill $skill, SkillVersion $version, int $major): void
    {
        $rows = DB::table($table)
            ->where('skill_id', $skill->id)
            ->where('update_policy', SkillUpdatePolicy::FOLLOW_COMPATIBLE)
            ->get(['id', 'skill_version_id']);

        foreach ($rows as $row) {
            $installedVersion = $row->skill_version_id !== null
                ? SkillVersion::query()->find($row->skill_version_id)
                : null;

            if ($installedVersion === null || $this->major($installedVersion->version) === $major) {
                DB::table($table)->where('id', $row->id)->update([
                    'skill_version_id' => $version->id,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function major(string $version): int
    {
        return preg_match('/^(\d+)/', $version, $matches) ? (int) $matches[1] : 0;
    }
}
