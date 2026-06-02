<?php

declare(strict_types=1);

namespace Phalanx\Dory\Command;

use Phalanx\Archon\Console\Output\StreamOutput;
use Phalanx\Dory\Code\CodeProjectIndex;
use Phalanx\Dory\Code\DeclarationQueryResult;
use Phalanx\Dory\Code\DeclarationRecord;
use Phalanx\Dory\Code\ParseError;
use Phalanx\Dory\Code\SourceFileRecord;
use Phalanx\Dory\Code\SpanRecord;
use Phalanx\Dory\Code\TokenQueryResult;
use Phalanx\Dory\Code\TokenRecord;

final class CodeCommandOutput
{
    /** @return array<string, mixed> */
    public static function projectIndex(CodeProjectIndex $index): array
    {
        return [
            'root' => $index->root,
            'file_count' => $index->fileCount,
            'declaration_count' => $index->declarationCount,
            'token_count' => $index->tokenCount,
            'files' => array_map(self::sourceFile(...), $index->files),
            'errors' => array_map(self::parseError(...), $index->errors),
        ];
    }

    /** @return array<string, mixed> */
    public static function declarationResult(DeclarationQueryResult $result): array
    {
        return [
            'root' => $result->root,
            'declarations' => array_map(self::declaration(...), $result->declarations),
            'errors' => array_map(self::parseError(...), $result->errors),
        ];
    }

    /** @return array<string, mixed> */
    public static function tokenResult(TokenQueryResult $result): array
    {
        return [
            'root' => $result->root,
            'tokens' => array_map(self::token(...), $result->tokens),
            'errors' => array_map(self::parseError(...), $result->errors),
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function json(StreamOutput $output, array $payload): void
    {
        $output->persist(json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    public static function jsonError(StreamOutput $output, string $message): void
    {
        self::json($output, [
            'ok' => false,
            'message' => $message,
        ]);
    }

    /** @return array<string, mixed> */
    private static function sourceFile(SourceFileRecord $file): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'wrapped' => $file->wrapped,
        ];
    }

    /** @return array<string, mixed> */
    private static function declaration(DeclarationRecord $declaration): array
    {
        return [
            'kind' => $declaration->kind,
            'name' => $declaration->name,
            'namespace' => $declaration->namespace,
            'declaring_type' => $declaration->declaringType,
            'fqn' => $declaration->fqn,
            'span' => self::span($declaration->span),
            'name_span' => self::span($declaration->nameSpan),
            'file' => $declaration->file,
        ];
    }

    /** @return array<string, mixed> */
    private static function token(TokenRecord $token): array
    {
        return [
            'kind' => $token->kind,
            'text' => $token->text,
            'span' => self::span($token->span),
            'file' => $token->file,
        ];
    }

    /** @return array<string, mixed> */
    private static function parseError(ParseError $error): array
    {
        return [
            'message' => $error->message,
            'span' => self::span($error->span),
        ];
    }

    /** @return array<string, int> */
    private static function span(SpanRecord $span): array
    {
        return [
            'start_offset' => $span->startOffset,
            'end_offset' => $span->endOffset,
            'start_line' => $span->startLine,
            'start_column' => $span->startColumn,
            'end_line' => $span->endLine,
            'end_column' => $span->endColumn,
        ];
    }
}
