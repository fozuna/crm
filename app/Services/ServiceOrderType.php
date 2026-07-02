<?php
declare(strict_types=1);

namespace App\Services;

final class ServiceOrderType
{
    public const CORRECAO = 'correcao';
    public const MELHORIA = 'melhoria';
    public const SUPORTE = 'suporte';
    public const CONSULTORIA = 'consultoria';
    public const IMPLANTACAO = 'implantacao';
    public const TREINAMENTO = 'treinamento';
    public const OUTRO = 'outro';

    public static function all(): array
    {
        return [
            self::CORRECAO => 'Correção',
            self::MELHORIA => 'Melhoria',
            self::SUPORTE => 'Suporte',
            self::CONSULTORIA => 'Consultoria',
            self::IMPLANTACAO => 'Implantação',
            self::TREINAMENTO => 'Treinamento',
            self::OUTRO => 'Outro',
        ];
    }

    public static function label(string $type): string
    {
        return self::all()[$type] ?? 'Não informado';
    }

    public static function isValid(string $type): bool
    {
        return array_key_exists($type, self::all());
    }
}
