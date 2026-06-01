<?php

declare(strict_types=1);

namespace Phalanx\Dory\Tests\Unit\Command;

use Phalanx\Archon\Command\CommandConfig;
use Phalanx\Dory\Command\CodeCheckCommand;
use Phalanx\Dory\Command\CodeDeclarationsCommand;
use Phalanx\Dory\Command\CodeTokensCommand;
use Phalanx\Dory\Command\DoctorCommand;
use Phalanx\Dory\Command\DoryCommandGroup;
use Phalanx\Dory\Command\InitCommand;
use Phalanx\Dory\Command\RunCommand;
use Phalanx\Dory\Command\ServeCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DoryCommandGroupTest extends TestCase
{
    #[Test]
    public function registers_run_command(): void
    {
        $group = DoryCommandGroup::commands();
        $commands = $group->commands();

        self::assertArrayHasKey('run', $commands);
        self::assertSame(RunCommand::class, $commands['run']->task);
    }

    #[Test]
    public function registers_init_command(): void
    {
        $group = DoryCommandGroup::commands();
        $commands = $group->commands();

        self::assertArrayHasKey('init', $commands);
        self::assertSame(InitCommand::class, $commands['init']->task);
    }

    #[Test]
    public function registers_doctor_command(): void
    {
        $group = DoryCommandGroup::commands();
        $commands = $group->commands();

        self::assertArrayHasKey('doctor', $commands);
        self::assertSame(DoctorCommand::class, $commands['doctor']->task);
    }

    #[Test]
    public function registers_serve_when_skopos_available(): void
    {
        $group = DoryCommandGroup::commands();
        $commands = $group->commands();

        self::assertArrayHasKey('serve', $commands);
        self::assertSame(ServeCommand::class, $commands['serve']->task);
    }

    #[Test]
    public function registers_code_command_group(): void
    {
        $group = DoryCommandGroup::commands();
        $code = $group->group('code');

        self::assertNotNull($code);

        $commands = $code->commands();
        self::assertArrayHasKey('check', $commands);
        self::assertArrayHasKey('declarations', $commands);
        self::assertArrayHasKey('tokens', $commands);
        self::assertSame(CodeCheckCommand::class, $commands['check']->task);
        self::assertSame(CodeDeclarationsCommand::class, $commands['declarations']->task);
        self::assertSame(CodeTokensCommand::class, $commands['tokens']->task);
    }

    #[Test]
    public function does_not_register_stele_on_alpha_surface(): void
    {
        $group = DoryCommandGroup::commands();

        self::assertNotContains('stele', $group->keys());
    }

    #[Test]
    public function run_has_required_script_argument(): void
    {
        $group = DoryCommandGroup::commands();
        $commands = $group->commands();

        $config = $commands['run']->config;
        self::assertInstanceOf(CommandConfig::class, $config);

        $arguments = $config->arguments;
        self::assertCount(1, $arguments);
        self::assertSame('script', $arguments[0]->name);
        self::assertTrue($arguments[0]->required);
    }

    #[Test]
    public function init_has_optional_directory_argument(): void
    {
        $group = DoryCommandGroup::commands();
        $commands = $group->commands();

        $config = $commands['init']->config;
        self::assertInstanceOf(CommandConfig::class, $config);

        $arguments = $config->arguments;
        self::assertCount(1, $arguments);
        self::assertSame('directory', $arguments[0]->name);
        self::assertFalse($arguments[0]->required);
        self::assertSame('.', $arguments[0]->default);
    }

    #[Test]
    public function doctor_has_no_arguments(): void
    {
        $group = DoryCommandGroup::commands();
        $commands = $group->commands();

        $config = $commands['doctor']->config;
        self::assertInstanceOf(CommandConfig::class, $config);
        self::assertCount(0, $config->arguments);
    }

    #[Test]
    public function top_level_check_remains_doctor_alias(): void
    {
        $group = DoryCommandGroup::commands();
        $commands = $group->commands();

        self::assertArrayHasKey('check', $commands);
        self::assertSame(DoctorCommand::class, $commands['check']->task);
    }

    #[Test]
    public function all_core_commands_present_in_keys(): void
    {
        $group = DoryCommandGroup::commands();
        $keys = $group->keys();

        self::assertContains('run', $keys);
        self::assertContains('r', $keys);
        self::assertContains('init', $keys);
        self::assertContains('new', $keys);
        self::assertContains('doctor', $keys);
        self::assertContains('check', $keys);
        self::assertContains('code', $keys);
        self::assertContains('serve', $keys);
        self::assertNotContains('stele', $keys);
    }
}
