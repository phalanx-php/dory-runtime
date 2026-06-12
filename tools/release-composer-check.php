<?php

declare(strict_types=1);

require_once __DIR__ . '/ReleaseComposer.php';

class BiaRuntimeReleaseComposerCheck
{
    /** @var list<string> */
    private array $errors = [];

    private BiaRuntimeReleaseComposer $release;

    public function __construct(
        string $root,
    ) {
        $this->release = new BiaRuntimeReleaseComposer($root);
    }

    public function __invoke(): int
    {
        $composer = $this->release->localComposer();

        $this->assertLocalPathRepository($composer);
        $this->assertPublishMetadata($this->release->publishComposer());

        if ($this->errors === []) {
            fwrite(STDOUT, "Bia runtime Composer release checks passed.\n");

            return 0;
        }

        fwrite(STDERR, "Bia runtime Composer release checks failed:\n");
        foreach ($this->errors as $error) {
            fwrite(STDERR, "  - {$error}\n");
        }

        return 1;
    }

    /**
     * @param array<string, mixed> $composer
     */
    private function assertLocalPathRepository(array $composer): void
    {
        $branchAlias = $this->release->branchAlias($composer);
        if ($branchAlias === null) {
            $this->errors[] = 'Branch alias must be defined as MAJOR.MINOR.x-dev.';

            return;
        }

        $repositories = $composer['repositories'] ?? [];
        $repository = is_array($repositories) ? ($repositories[0] ?? null) : null;

        if (!is_array($repository)) {
            $this->errors[] = 'composer.json must keep the local Phalanx path repository.';

            return;
        }

        if (($repository['type'] ?? null) !== 'path') {
            $this->errors[] = 'Local Phalanx repository must be type path.';
        }

        if (($repository['url'] ?? null) !== '../../phalanx') {
            $this->errors[] = 'Local Phalanx repository must point at ../../phalanx.';
        }

        if (($repository['options']['symlink'] ?? null) !== true) {
            $this->errors[] = 'Local Phalanx repository must symlink source packages.';
        }

        $required = $this->release->phalanxRequires($composer);
        $versions = $repository['options']['versions'] ?? [];
        if (!is_array($versions)) {
            $this->errors[] = 'Local Phalanx repository must define package versions.';

            return;
        }

        if ($required !== ['phalanx-php/phalanx' => '^2.0@dev']) {
            $this->errors[] = 'Bia runtime must require only phalanx-php/phalanx locally.';
        }

        if ($versions !== ['phalanx-php/phalanx' => $branchAlias]) {
            $this->errors[] = "Local path version for phalanx-php/phalanx must be {$branchAlias}.";
        }
    }

    /**
     * @param array<string, mixed> $composer
     */
    private function assertPublishMetadata(array $composer): void
    {
        $publishConstraint = $this->release->publishConstraint($composer);
        if ($publishConstraint === null) {
            $this->errors[] = 'Publish constraint could not be derived from branch alias.';

            return;
        }

        if (array_key_exists('repositories', $composer)) {
            $this->errors[] = 'Publish metadata must not include local repositories.';
        }

        foreach ($this->release->phalanxRequires($composer) as $package => $constraint) {
            if ($constraint !== $publishConstraint) {
                $this->errors[] = "Publish constraint for {$package} must be {$publishConstraint}.";
            }
        }
    }
}

exit((new BiaRuntimeReleaseComposerCheck(dirname(__DIR__)))());
