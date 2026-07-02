<?php
declare(strict_types=1);

namespace App\Services;

final class ServiceOrderStatus
{
    public const ABERTO = 'aberto';
    public const EM_ANDAMENTO = 'em_andamento';
    public const AGUARDANDO_CLIENTE = 'aguardando_cliente';
    public const AGUARDANDO_TERCEIROS = 'aguardando_terceiros';
    public const CONCLUIDO = 'concluido';
    public const CANCELADO = 'cancelado';
    public const FATURADO = 'faturado';

    public static function all(): array
    {
        return [
            self::ABERTO => 'Aberto',
            self::EM_ANDAMENTO => 'Em andamento',
            self::AGUARDANDO_CLIENTE => 'Aguardando cliente',
            self::AGUARDANDO_TERCEIROS => 'Aguardando terceiros',
            self::CONCLUIDO => 'Concluído',
            self::CANCELADO => 'Cancelado',
            self::FATURADO => 'Faturado',
        ];
    }

    public static function label(string $status): string
    {
        return self::all()[$status] ?? 'Não informado';
    }

    public static function isValid(string $status): bool
    {
        return array_key_exists($status, self::all());
    }

    public static function isClosed(string $status): bool
    {
        return in_array($status, [self::CONCLUIDO, self::CANCELADO, self::FATURADO], true);
    }

    public static function badgeClass(string $status): string
    {
        return match ($status) {
            self::ABERTO => 'bg-sky-50 border-sky-200 text-sky-800',
            self::EM_ANDAMENTO => 'bg-indigo-50 border-indigo-200 text-indigo-800',
            self::AGUARDANDO_CLIENTE => 'bg-amber-50 border-amber-200 text-amber-800',
            self::AGUARDANDO_TERCEIROS => 'bg-orange-50 border-orange-200 text-orange-800',
            self::CONCLUIDO => 'bg-emerald-50 border-emerald-200 text-emerald-800',
            self::CANCELADO => 'bg-rose-50 border-rose-200 text-rose-800',
            self::FATURADO => 'bg-violet-50 border-violet-200 text-violet-800',
            default => 'bg-slate-50 border-slate-200 text-slate-700',
        };
    }
}
