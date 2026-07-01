<?php
declare(strict_types=1);

namespace App\Services;

final class LeadStages
{
    public const CADASTRO_REALIZADO = 'cadastro_realizado';
    public const EM_CONTATO = 'em_contato';
    public const PROPOSTA_ENVIADA = 'proposta_enviada';
    public const NEGOCIACAO = 'negociacao_em_andamento';
    public const PRONTO_APROVACAO = 'pronto_para_aprovacao';
    public const APROVADO = 'aprovado';

    public static function all(): array
    {
        return [
            self::CADASTRO_REALIZADO,
            self::EM_CONTATO,
            self::PROPOSTA_ENVIADA,
            self::NEGOCIACAO,
            self::PRONTO_APROVACAO,
            self::APROVADO,
        ];
    }

    public static function kanban(): array
    {
        return [
            self::CADASTRO_REALIZADO => 'Cadastro Realizado',
            self::EM_CONTATO => 'Em Contato',
            self::PROPOSTA_ENVIADA => 'Proposta Enviada',
            self::NEGOCIACAO => 'Negociação em Andamento',
            self::PRONTO_APROVACAO => 'Pronto para Aprovação',
            self::APROVADO => 'Aprovado',
        ];
    }

    public static function label(string $stage): string
    {
        return self::kanban()[$stage] ?? 'Desconhecido';
    }

    public static function isValid(string $stage): bool
    {
        return in_array($stage, self::all(), true);
    }

    public static function color(string $stage): string
    {
        return match ($stage) {
            self::CADASTRO_REALIZADO => 'bg-slate-100 text-slate-700',
            self::EM_CONTATO => 'bg-sky-100 text-sky-700',
            self::PROPOSTA_ENVIADA => 'bg-violet-100 text-violet-700',
            self::NEGOCIACAO => 'bg-amber-100 text-amber-700',
            self::PRONTO_APROVACAO => 'bg-emerald-100 text-emerald-700',
            self::APROVADO => 'bg-green-100 text-green-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }
}
